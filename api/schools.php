<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_schools_json(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);

if (
    $lat === false || $lon === false ||
    $lat === null || $lon === null ||
    $lat < 49 || $lat > 59 || $lon < -9 || $lon > 3
) {
    bh_schools_json(422, ['ok' => false, 'error' => 'invalid_location']);
}

$query = '[out:json][timeout:20];'
    . '(nwr["amenity"="school"](around:30000,' . (float)$lat . ',' . (float)$lon . '););'
    . 'out center tags;';

$overpassEndpoints = [
    'https://overpass-api.de/api/interpreter',
    'https://overpass.kumi.systems/api/interpreter',
    'https://overpass.private.coffee/api/interpreter'
];

$raw = false;
$code = 0;
$curlError = '';

foreach ($overpassEndpoints as $endpoint) {
    $ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $query,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_HTTPHEADER => [
        'Content-Type: text/plain; charset=utf-8',
        'Accept: application/json',
        'User-Agent: BubbaHub/1.0 (+https://bubbahub.co.uk) local-schools'
    ],
]);

$attemptRaw = curl_exec($ch);
    $attemptCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $attemptError = curl_error($ch);
    curl_close($ch);

    if ($attemptRaw !== false && $attemptCode >= 200 && $attemptCode < 300) {
        $raw = $attemptRaw;
        $code = $attemptCode;
        $curlError = '';
        break;
    }

    $code = $attemptCode;
    $curlError = $attemptError;
}

if ($raw === false || $code < 200 || $code >= 300) {
    bh_schools_json(502, [
        'ok' => false,
        'error' => 'school_source_unavailable',
        'message' => 'The school directory is temporarily unavailable.',
        'debug' => $curlError !== '' ? $curlError : ('HTTP '.$code)
    ]);
}

$payload = json_decode($raw, true);

if (!is_array($payload) || !isset($payload['elements']) || !is_array($payload['elements'])) {
    bh_schools_json(502, ['ok' => false, 'error' => 'invalid_school_source']);
}

$distance = function (float $a, float $b) use ($lat, $lon): float {
    $radius = 3958.7613;
    $p1 = deg2rad((float)$lat);
    $p2 = deg2rad($a);
    $dp = deg2rad($a - (float)$lat);
    $dl = deg2rad($b - (float)$lon);

    $x = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return $radius * 2 * asin(min(1, sqrt($x)));
};

$phase = function (array $tags): string {
    $raw = strtolower(trim(implode(' ', array_filter([
        (string)($tags['school:type'] ?? ''),
        (string)($tags['isced:level'] ?? ''),
        (string)($tags['education'] ?? '')
    ]))));

    if (
        ($tags['special_school'] ?? '') === 'yes' ||
        str_contains($raw, 'special')
    ) {
        return 'special';
    }

    if (str_contains($raw, 'nursery') || str_contains($raw, 'early')) {
        return 'nursery';
    }

    if (
        str_contains($raw, 'secondary') ||
        str_contains($raw, 'isced:2') ||
        str_contains($raw, 'isced:3')
    ) {
        return 'secondary';
    }

    if (
        str_contains($raw, 'primary') ||
        str_contains($raw, 'isced:1')
    ) {
        return 'primary';
    }

    return '';
};

$out = [];

foreach ($payload['elements'] as $element) {
    if (!is_array($element)) {
        continue;
    }

    $tags = is_array($element['tags'] ?? null) ? $element['tags'] : [];
    $name = trim((string)($tags['name'] ?? ''));

    $position = is_array($element['center'] ?? null) ? $element['center'] : $element;
    $schoolLat = isset($position['lat']) ? (float)$position['lat'] : null;
    $schoolLon = isset($position['lon']) ? (float)$position['lon'] : null;

    if ($name === '' || $schoolLat === null || $schoolLon === null) {
        continue;
    }

    $address = trim(implode(', ', array_filter([
        $tags['addr:housenumber'] ?? '',
        $tags['addr:street'] ?? '',
        $tags['addr:place'] ?? '',
        $tags['addr:city'] ?? $tags['addr:town'] ?? $tags['addr:village'] ?? '',
        $tags['addr:postcode'] ?? ''
    ])));

    $website = trim((string)($tags['website'] ?? $tags['contact:website'] ?? ''));
    $phone = trim((string)($tags['phone'] ?? $tags['contact:phone'] ?? ''));
    $urn = trim((string)($tags['ref:GB:school'] ?? $tags['ref:URN'] ?? ''));

    $ofstedUrl = '';
    if ($urn !== '' && preg_match('/^\d{5,8}$/', $urn)) {
        $ofstedUrl = 'https://reports.ofsted.gov.uk/provider/21/' . rawurlencode($urn);
    }

    $rating = trim((string)($tags['ofsted:rating'] ?? $tags['ofsted_rating'] ?? ''));

    $out[] = [
        'name' => $name,
        'latitude' => $schoolLat,
        'longitude' => $schoolLon,
        'distance_miles' => round($distance($schoolLat, $schoolLon), 1),
        'address' => $address,
        'postcode' => (string)($tags['addr:postcode'] ?? ''),
        'city' => (string)($tags['addr:city'] ?? $tags['addr:town'] ?? $tags['addr:village'] ?? ''),
        'phone' => $phone,
        'website' => $website,
        'ofsted_url' => $ofstedUrl,
        'ofsted_rating' => $rating,
        'phase' => $phase($tags),
    ];
}

usort($out, static fn(array $a, array $b): int => $a['distance_miles'] <=> $b['distance_miles']);
$out = array_slice($out, 0, 10);

bh_schools_json(200, [
    'ok' => true,
    'data' => $out,
    'count' => count($out),
    'source' => 'OpenStreetMap',
    'note' => 'School locations and public contact details come from OpenStreetMap. Ofsted information is only shown when a valid school URN and/or rating is present in the source data.'
]);
?>