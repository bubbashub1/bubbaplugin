<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) < 3) {
    respond(['ok' => false, 'error' => 'Enter at least 3 characters.'], 400);
}
$q = mb_substr($q, 0, 180);

$headers = [
    'User-Agent: Bubba Hub Address Lookup/1.2 (+https://bubbahub.co.uk)',
    'Accept-Language: en-GB,en;q=0.9',
    'Accept: application/json'
];

function http_get_json(string $url, array $headers, int $timeout = 12): array {
    $body = false;
    $status = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => implode("\r\n", $headers)
            ]
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = $body !== false ? 200 : 0;
    }

    if ($body === false || $status < 200 || $status >= 300) {
        return [];
    }

    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

/*
 * UK postcodes are much more reliable through postcodes.io than free-text
 * geocoding. Use it first when the input looks like a postcode.
 */
if (preg_match('/\b[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}\b/i', $q)) {
    $postcode = strtoupper(trim($q));
    $postcodeUrl = 'https://api.postcodes.io/postcodes/' . rawurlencode(str_replace(' ', '', $postcode));
    $postcodeData = http_get_json($postcodeUrl, $headers, 10);

    if (($postcodeData['status'] ?? 0) === 200 && !empty($postcodeData['result'])) {
        $r = $postcodeData['result'];
        respond([
            'ok' => true,
            'results' => [[
                'lat' => $r['latitude'] ?? null,
                'lon' => $r['longitude'] ?? null,
                'display_name' => $r['postcode'] ?? $postcode,
                'type' => 'postcode',
                'address' => [
                    'postcode' => $r['postcode'] ?? $postcode,
                    'city' => $r['admin_district'] ?? ($r['parliamentary_constituency'] ?? ''),
                    'town' => $r['parish'] ?? ($r['admin_ward'] ?? '')
                ]
            ]]
        ]);
    }
}

/*
 * Free-text town/place lookup. Try the user's wording first, then a
 * UK-qualified version. Keeping the fallback server-side avoids exposing
 * third-party API details to the browser.
 */
$queries = [$q];
if (!preg_match('/,\s*(uk|united kingdom)$/i', $q)) {
    $queries[] = $q . ', UK';
}

$results = [];
foreach ($queries as $query) {
    $url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=5&countrycodes=gb&q=' . rawurlencode($query);
    $data = http_get_json($url, $headers, 12);
    if ($data) {
        $results = $data;
        break;
    }
}

if (!$results) {
    respond([
        'ok' => false,
        'error' => 'We could not locate that town or postcode. Try the town name with the county, or enter a full UK postcode.'
    ], 404);
}

respond(['ok' => true, 'results' => $results]);
