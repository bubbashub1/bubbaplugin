<?php
declare(strict_types=1);
/**
 * Read-only public Expo exhibitor directory.
 * Fail closed if approval schema cannot be verified.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
    exit;
}
if (($_GET['event'] ?? '') !== 'bubba-me-2027') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'invalid_event']);
    exit;
}
try {
    require_once __DIR__ . '/db.php';
    $pdo = bh_mysql();
    $columns = $pdo->query('SHOW COLUMNS FROM bh_expo_exhibitors')->fetchAll(PDO::FETCH_COLUMN);
    $columns = array_flip($columns);
    // Approval must be explicit; never treat an application as approved by default.
    $approval = isset($columns['application_status']) ? 'application_status' : (isset($columns['approval_status']) ? 'approval_status' : (isset($columns['status']) ? 'status' : null));
    $name = isset($columns['business_name']) ? 'business_name' : (isset($columns['name']) ? 'name' : null);
    if ($approval === null || $name === null || !isset($columns['public_visible']) || !isset($columns['event_key'])) {
        throw new RuntimeException('Approval schema not available');
    }
    $fields = ["`$name` AS name"];
    if (isset($columns['details_json'])) $fields[] = 'details_json';
    if (isset($columns['participation_json'])) $fields[] = 'participation_json';
    foreach (['website_url'=>['website_url','website'], 'image_url'=>['logo_url','image_url'], 'description'=>['public_description','description']] as $alias=>$options) {
        foreach ($options as $candidate) {
            if (isset($columns[$candidate])) { $fields[] = "`$candidate` AS `$alias`"; break; }
        }
    }
    $where = ["`$approval` = 'approved'"];
    $where[] = 'public_visible = 1';
    $where[] = "event_key = 'bubba-me-2027'";
    $sql = 'SELECT '.implode(', ', $fields).' FROM bh_expo_exhibitors WHERE '.implode(' AND ', $where).' ORDER BY `'.$name.'` ASC LIMIT 100';
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $safe = [];
    foreach ($rows as $row) {
        $details = json_decode((string)($row['details_json'] ?? '{}'), true);
        if (!is_array($details)) $details = [];
        $website = $row['website_url'] ?? $details['website'] ?? '';
        $logo = $row['image_url'] ?? $details['logo_url'] ?? '';
        $stored = json_decode((string)($row['participation_json']??''),true);
        $social = is_array($stored) && is_array($stored['social_links']??null) ? $stored['social_links'] : [];
        $links=[];foreach(['facebook','instagram','tiktok'] as $network){$url=(string)($social[$network]??'');$links[$network]=filter_var($url,FILTER_VALIDATE_URL)&&strtolower((string)parse_url($url,PHP_URL_SCHEME))==='https'?$url:null;}
        $safe[] = [
            'name'=>mb_substr((string)($row['name']??''),0,150),
            'website_url'=>filter_var($website, FILTER_VALIDATE_URL) && str_starts_with((string)$website, 'https://') ? $website : null,
            'image_url'=>filter_var($logo, FILTER_VALIDATE_URL) && str_starts_with((string)$logo, 'https://') ? $logo : null,
            'social_links'=>$links
        ];
    }
    echo json_encode(['ok'=>true,'exhibitors'=>$safe], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    // Never disclose SQL, schema, contacts or credentials publicly.
    http_response_code(503);
    echo json_encode(['ok'=>false,'error'=>'exhibitors_unavailable']);
}
