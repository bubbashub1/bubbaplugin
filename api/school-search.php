<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_school_search_json(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim((string)($_GET['name'] ?? ''));
$town = trim((string)($_GET['town'] ?? ''));

if ($name === '') {
    bh_school_search_json(422, ['ok' => false, 'error' => 'school_name_required']);
}

$name = mb_substr($name, 0, 180);
$town = mb_substr($town, 0, 100);

$configFile = __DIR__ . '/config.php';
$config = is_file($configFile) ? require $configFile : [];
$google = is_array($config['google'] ?? null) ? $config['google'] : [];

$key = trim((string)($google['search_api_key'] ?? $google['custom_search_api_key'] ?? ''));
$cx  = trim((string)($google['search_engine_id'] ?? $google['cx'] ?? ''));

if ($key === '' || $cx === '') {
    bh_school_search_json(503, [
        'ok' => false,
        'error' => 'google_search_not_configured',
        'message' => 'Online school search is not configured.'
    ]);
}

$query = $name . ($town !== '' ? ' ' . $town : '') . ' school official website';

$url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query([
    'key' => $key,
    'cx' => $cx,
    'q' => $query,
    'num' => 5,
    'safe' => 'active',
], '', '&', PHP_QUERY_RFC3986);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 6,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_USERAGENT => 'BubbaHub/1.0 (+https://bubbahub.co.uk)',
]);
$raw = curl_exec($ch);
$curlError = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($raw === false || $code < 200 || $code >= 300) {
    bh_school_search_json(502, [
        'ok' => false,
        'error' => 'google_search_unavailable',
        'message' => 'The online school search is temporarily unavailable.'
    ]);
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    bh_school_search_json(502, [
        'ok' => false,
        'error' => 'invalid_google_response'
    ]);
}

$results = [];
foreach (($data['items'] ?? []) as $item) {
    if (!is_array($item) || empty($item['link'])) {
        continue;
    }

    $results[] = [
        'title' => (string)($item['title'] ?? ''),
        'url' => (string)$item['link'],
        'snippet' => (string)($item['snippet'] ?? ''),
        'display_link' => (string)($item['displayLink'] ?? ''),
    ];
}

bh_school_search_json(200, [
    'ok' => true,
    'query' => $query,
    'results' => $results,
]);
?>