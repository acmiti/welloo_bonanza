<?php
header('Content-Type: text/html; charset=utf-8');

/**
 * Renders a minimal branded HTML shell and stops execution.
 * Used for both the success ("redirecting to WhatsApp") screen and
 * the error screens, so the visitor never sees a raw JSON blob.
 */
function render_page(string $title, string $bodyHtml, array $headTags = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    $head = implode("\n    ", $headTags);
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    {$head}
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, sans-serif; }
        body { min-height: 100vh; background: #0A0A0A; color: #FFF; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { max-width: 420px; width: 100%; background: linear-gradient(180deg, #1A1A1A 0%, #0A0A0A 100%); border: 1px solid #333; border-radius: 16px; padding: 32px 24px; text-align: center; box-shadow: 0 10px 30px rgba(255, 102, 0, 0.15); }
        h1 { font-size: 20px; font-weight: 800; margin-bottom: 12px; }
        p { font-size: 14px; color: #BBB; line-height: 1.5; margin-bottom: 20px; }
        .spinner { width: 44px; height: 44px; margin: 0 auto 20px; border: 4px solid #333; border-top-color: #FF6600; border-radius: 50%; animation: spin 0.9s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .btn { display: inline-block; background: linear-gradient(90deg, #FF6600, #FF9900); color: #000; font-weight: 800; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-size: 14px; }
        .btn.secondary { background: #222; color: #FF9900; border: 1px solid #FF6600; }
    </style>
</head>
<body>
    <div class="card">
        {$bodyHtml}
    </div>
</body>
</html>
HTML;
    exit;
}

/**
 * Builds the cross-platform WhatsApp deep link for the campaign inbox,
 * pre-filling the registration message in the visitor's chosen language.
 * Mirrors the waTemplates map that used to live in index.html.
 */
function build_whatsapp_url(string $language, array $fields): string
{
    $targetPhone = '94777886966';

    $name     = $fields['name'];
    $phone    = $fields['phone'];
    $district = $fields['district'];
    $town     = $fields['town'];
    $dealer   = $fields['dealer'];

    switch (strtoupper($language)) {
        case 'TA':
            $message = "வணக்கம் Welloo Sri Lanka,\n\nநான் 'Always Dinum Bonanza' போட்டியில் பதிவு செய்ய விரும்புகிறேன்.\n"
                . "*பெயர்:* {$name}\n"
                . "*தொலைபேசி எண்:* {$phone}\n"
                . "*மாவட்டம்:* {$district}\n"
                . "*நகரம்/ஊர்:* {$town}\n"
                . "*வாங்கும் கடை:* {$dealer}\n"
                . "*மொழி:* Tamil";
            break;

        case 'EN':
            $message = "Hi Welloo Sri Lanka,\n\nI would like to register for the 'Always Dinum Bonanza'!\n"
                . "*Name:* {$name}\n"
                . "*Phone:* {$phone}\n"
                . "*District:* {$district}\n"
                . "*City/Town:* {$town}\n"
                . "*Power Tools Shop:* {$dealer}\n"
                . "*Language:* English";
            break;

        case 'SI':
        default:
            $message = "ආයුබෝවන් Welloo Sri Lanka,\n\nමම 'Always Dinum Bonanza' සඳහා ලියාපදිංචි වීමට කැමැත්තෙමි.\n"
                . "*නම:* {$name}\n"
                . "*දුරකථන අංකය:* {$phone}\n"
                . "*දිස්ත්‍රික්කය:* {$district}\n"
                . "*නගරය/ගම:* {$town}\n"
                . "*මිලදී ගන්නා වෙළඳසැල:* {$dealer}\n"
                . "*භාෂාව:* Sinhala";
            break;
    }

    return 'https://api.whatsapp.com/send/?phone=' . $targetPhone . '&text=' . rawurlencode($message);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    render_page(
        'Method Not Allowed',
        '<h1>Method Not Allowed</h1><p>Please submit the registration form.</p><a class="btn" href="index.html">Back to the form</a>',
        [],
        405
    );
}

// Connect via central database configuration file
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/includes/MetaCapi.php';

$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true) ?: $_POST;

$name     = trim($data['name'] ?? '');
$phone    = trim($data['phone'] ?? '');
$district = trim($data['district'] ?? '');
$town     = trim($data['town'] ?? '');
$dealer   = trim($data['dealer'] ?? '');
$language = strtoupper(trim($data['lang'] ?? 'SI'));

if (empty($name) || empty($phone) || empty($district) || empty($town) || empty($dealer)) {
    render_page(
        'Missing details',
        '<h1>Some details are missing</h1><p>Please fill in every field and try again.</p><a class="btn" href="index.html">Back to the form</a>',
        [],
        400
    );
}

/**
 * Returns the draw batch this entry should be filed under.
 * Rolls over to the next draft batch — or creates a fresh weekly batch —
 * whenever the currently active batch's deadline has passed.
 * Must be called inside an open transaction; the row locks it takes
 * (FOR UPDATE) are what keep concurrent submissions from racing each
 * other into creating duplicate batches.
 */
function resolve_active_batch(PDO $pdo): array
{
    $active = $pdo->query(
        "SELECT * FROM draw_batches WHERE status = 'active' ORDER BY id DESC LIMIT 1 FOR UPDATE"
    )->fetch();

    if ($active && strtotime($active['entry_deadline']) >= time()) {
        return $active;
    }

    // Deadline passed (or no active batch exists) — lock the stale batch.
    if ($active) {
        $pdo->prepare("UPDATE draw_batches SET status = 'locked' WHERE id = :id")
            ->execute([':id' => $active['id']]);
    }

    // Promote the next upcoming draft batch, if one exists and hasn't itself expired.
    $next = $pdo->prepare(
        "SELECT * FROM draw_batches
         WHERE status = 'draft' AND entry_deadline >= NOW()
         ORDER BY entry_deadline ASC, id ASC
         LIMIT 1 FOR UPDATE"
    );
    $next->execute();
    $next = $next->fetch();

    if ($next) {
        $pdo->prepare("UPDATE draw_batches SET status = 'active' WHERE id = :id")
            ->execute([':id' => $next['id']]);
        $next['status'] = 'active';
        return $next;
    }

    // No usable batch left — dynamically create the next one.
    $weekNumber = ((int) $pdo->query("SELECT COUNT(*) FROM draw_batches")->fetchColumn()) + 1;
    $batchName  = "Week {$weekNumber} Draw";

    $insert = $pdo->prepare(
        "INSERT INTO draw_batches (batch_name, entry_start_time, entry_deadline, draw_datetime, status)
         VALUES (:name, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 7 DAY), 'active')"
    );
    $insert->execute([':name' => $batchName]);
    $newId = (int) $pdo->lastInsertId();

    $fetch = $pdo->prepare("SELECT * FROM draw_batches WHERE id = :id");
    $fetch->execute([':id' => $newId]);
    return $fetch->fetch();
}

try {
    $pdo->beginTransaction();

    $batch = resolve_active_batch($pdo);

    $stmt = $pdo->prepare(
        "INSERT INTO bonanza_entries (name, phone, district, town, dealer, language, batch_id)
         VALUES (:name, :phone, :district, :town, :dealer, :language, :batch_id)"
    );
    $stmt->execute([
        ':name'     => $name,
        ':phone'    => $phone,
        ':district' => $district,
        ':town'     => $town,
        ':dealer'   => $dealer,
        ':language' => $language,
        ':batch_id' => $batch['id'],
    ]);

    $pdo->commit();

    $capiUserData = ['ph' => $phone, 'external_id' => $phone];
    $capiCustomData = ['content_name' => 'Always Dinum Bonanza Registration', 'district' => $district];
    MetaCapi::sendEvent('Lead', $capiUserData, $capiCustomData);
    MetaCapi::sendEvent('CompleteRegistration', $capiUserData, $capiCustomData);

    // The confirmation screen below always redirects the visitor to a WhatsApp
    // API link, so fire a Contact event to match.
    MetaCapi::sendEvent('Contact', $capiUserData, ['action' => 'whatsapp_redirect', 'destination' => 'whatsapp']);

    $waUrl    = build_whatsapp_url($language, compact('name', 'phone', 'district', 'town', 'dealer'));
    $waUrlAttr = htmlspecialchars($waUrl, ENT_QUOTES, 'UTF-8');
    $waUrlJs   = json_encode($waUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    render_page(
        'Registering your entry…',
        <<<BODY
        <div class="spinner"></div>
        <h1>Thank you! Registering your entry…</h1>
        <p>Redirecting you to WhatsApp in 2 seconds…</p>
        <a class="btn" href="{$waUrlAttr}">Click here if you are not redirected automatically</a>
        <script>
            setTimeout(function () { window.location.href = {$waUrlJs}; }, 2000);
        </script>
BODY,
        ["<meta http-equiv=\"refresh\" content=\"2;url={$waUrlAttr}\">"]
    );

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    render_page(
        'Something went wrong',
        '<h1>Something went wrong</h1><p>We could not record your entry. Please try again in a moment.</p><a class="btn" href="index.html">Back to the form</a>',
        [],
        500
    );
}
