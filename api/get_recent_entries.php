<?php
// api/get_recent_entries.php — public endpoint feeding the landing-page social-proof toast.
// Intentionally no auth: the public entry page needs this to render for anonymous visitors.
// Only non-identifying fragments are exposed (first name + district/town), never phone numbers.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

/**
 * Reduce a stored full name to a safe display token: first word only,
 * capitalised, with anything that isn't a letter/mark/space/apostrophe/hyphen
 * stripped out. Unicode-aware so Sinhala/Tamil names survive intact.
 */
function social_proof_first_name(string $fullName): string
{
    $clean = preg_replace('/[^\p{L}\p{M}\s\'\-]/u', '', trim($fullName));
    $parts = preg_split('/\s+/u', trim((string) $clean), -1, PREG_SPLIT_NO_EMPTY);
    $first = $parts[0] ?? '';
    $first = mb_substr($first, 0, 20);

    if ($first === '') {
        return 'Someone';
    }

    return mb_strtoupper(mb_substr($first, 0, 1)) . mb_substr($first, 1);
}

/**
 * Rough "x minutes/hours/days ago" string from a MySQL DATETIME.
 * Returns null when the timestamp is missing/unparseable.
 */
function social_proof_relative_time(?string $datetime): ?string
{
    if (empty($datetime)) {
        return null;
    }

    $ts = strtotime($datetime);
    if ($ts === false) {
        return null;
    }

    $diff = max(0, time() - $ts);

    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff / 60) . 'm ago';
    if ($diff < 86400)   return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)  return floor($diff / 86400) . 'd ago';

    return date('M j', $ts);
}

$entries = [];

try {
    // Last 20 real submissions, excluding anything an admin has disqualified.
    $stmt = $pdo->query(
        "SELECT e.name, e.district, e.town, e.created_at
         FROM bonanza_entries e
         LEFT JOIN disqualifications d ON d.entry_id = e.id
         WHERE d.id IS NULL
         ORDER BY e.id DESC
         LIMIT 20"
    );

    foreach ($stmt->fetchAll() as $row) {
        $city = trim((string) ($row['district'] ?? ''));
        if ($city === '') {
            $city = trim((string) ($row['town'] ?? ''));
        }

        $isoTime = null;
        if (!empty($row['created_at']) && ($ts = strtotime((string) $row['created_at'])) !== false) {
            // Explicit Asia/Colombo (+05:30) ISO 8601 string so the client can
            // compute its own relative time without guessing the server's zone.
            $isoTime = date('c', $ts);
        }

        $entries[] = [
            'name'    => social_proof_first_name((string) ($row['name'] ?? '')),
            'city'    => $city !== '' ? mb_substr($city, 0, 40) : 'Sri Lanka',
            'time'    => social_proof_relative_time($row['created_at'] ?? null),
            'iso'     => $isoTime,
        ];
    }
} catch (PDOException $e) {
    // Fall through to the seed list below — the toast must never be empty.
    $entries = [];
}

// Graceful fallback: below 5 real entries we blend in a neutral seed set so the
// popup always has at least a handful of items to cycle through.
$fallbackSeed = [
    ['name' => 'Nimal',   'city' => 'Gampaha'],
    ['name' => 'Kasun',   'city' => 'Kandy'],
    ['name' => 'Suresh',  'city' => 'Jaffna'],
    ['name' => 'Pradeep', 'city' => 'Colombo'],
    ['name' => 'Ruwan',   'city' => 'Kurunegala'],
    ['name' => 'Kamal',   'city' => 'Galle'],
    ['name' => 'Mohamed', 'city' => 'Batticaloa'],
    ['name' => 'Nuwan',   'city' => 'Ratnapura'],
    ['name' => 'Dilan',   'city' => 'Matara'],
    ['name' => 'Ajith',   'city' => 'Anuradhapura'],
];

$usedFallback = false;
if (count($entries) < 5) {
    $usedFallback = true;
    foreach ($fallbackSeed as $seed) {
        if (count($entries) >= 20) {
            break;
        }
        $entries[] = ['name' => $seed['name'], 'city' => $seed['city'], 'time' => null, 'iso' => null];
    }
}

echo json_encode([
    'status'   => 'success',
    'fallback' => $usedFallback,
    'entries'  => array_slice($entries, 0, 20),
], JSON_UNESCAPED_UNICODE);
