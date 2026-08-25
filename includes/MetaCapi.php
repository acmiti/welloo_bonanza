<?php
// includes/MetaCapi.php — minimal client for the Meta (Facebook) Conversions API
require_once __DIR__ . '/config.php';

class MetaCapi
{
    /**
     * Sends one event to Meta CAPI. Never throws — a failed/misconfigured
     * send should not break the caller's request flow, so errors are logged
     * and false is returned instead.
     */
    public static function sendEvent(
        string $eventName,
        array $userData = [],
        array $customData = [],
        ?string $eventSourceUrl = null,
        ?string $eventId = null
    ): bool {
        if (empty(META_PIXEL_ID) || empty(META_ACCESS_TOKEN)) {
            return false;
        }

        $hashedUserData = [];
        foreach (['em', 'ph', 'external_id'] as $field) {
            if (!empty($userData[$field])) {
                $hashedUserData[$field] = self::hash($userData[$field]);
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $fbp = $userData['fbp'] ?? ($_COOKIE['_fbp'] ?? null);
        $fbc = $userData['fbc'] ?? ($_COOKIE['_fbc'] ?? null);

        if ($ip) $hashedUserData['client_ip_address'] = $ip;
        if ($ua) $hashedUserData['client_user_agent'] = $ua;
        if ($fbp) $hashedUserData['fbp'] = $fbp;
        if ($fbc) $hashedUserData['fbc'] = $fbc;

        $event = [
            'event_name'       => $eventName,
            'event_time'       => time(),
            'action_source'    => 'website',
            'event_source_url' => $eventSourceUrl ?? self::currentUrl(),
            'user_data'        => $hashedUserData,
        ];

        if (!empty($customData)) {
            $event['custom_data'] = $customData;
        }
        if ($eventId !== null) {
            $event['event_id'] = $eventId;
        }

        $payload = ['data' => [$event]];
        $url = 'https://graph.facebook.com/' . META_API_VERSION . '/' . META_PIXEL_ID
             . '/events?access_token=' . urlencode(META_ACCESS_TOKEN);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $ok = $response !== false && $httpCode < 400;
        if (!$ok) {
            error_log("MetaCapi: failed to send '{$eventName}' event (HTTP {$httpCode}): {$curlError} {$response}");
        }

        return $ok;
    }

    private static function hash(string $value): string
    {
        return hash('sha256', trim(strtolower($value)));
    }

    private static function currentUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return "{$scheme}://{$host}{$uri}";
    }
}
