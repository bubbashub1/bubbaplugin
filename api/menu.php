<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/menu-validation.php';

function bh_menu_api_out(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function bh_menu_table_exists(PDO $db): bool {
    $query = $db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $query->execute(['bh_site_menus']);
    return $query->fetchColumn() !== false;
}

function bh_menu_read_data(PDO $db): array {
    $data = bh_menu_default_data();
    if (!bh_menu_table_exists($db)) return $data;
    $query = $db->query("SELECT menu_key, menu_json FROM bh_site_menus");
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (string)($row['menu_key'] ?? '');
        if (!array_key_exists($key, $data)) continue;
        $decoded = json_decode((string)($row['menu_json'] ?? ''), true);
        if (is_array($decoded)) $data[$key] = bh_menu_clean_items($decoded);
    }
    return $data;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string)($_GET['action'] ?? 'public');

if ($method === 'GET' && $action === 'public') {
    // Public header/footer reads must remain available without an admin session.
    // Missing tables and temporary database errors fall back to the safe defaults.
    try {
        require __DIR__ . '/db.php';
        $data = bh_menu_read_data(bh_mysql());
        bh_menu_api_out(200, ['ok'=>true, 'data'=>$data]);
    } catch (Throwable $error) {
        error_log('Public menu read failed: ' . $error->getMessage());
        bh_menu_api_out(200, ['ok'=>true, 'data'=>bh_menu_default_data(), 'fallback'=>true]);
    }
}

if ($method === 'GET' && $action === 'admin') {
    require_once __DIR__ . '/admin-session-guard.php';
    if (empty($_SESSION['bh_menu_manager_csrf'])) {
        $_SESSION['bh_menu_manager_csrf'] = bin2hex(random_bytes(32));
    }
    $csrf = (string)$_SESSION['bh_menu_manager_csrf'];
    try {
        require __DIR__ . '/db.php';
        $db = bh_mysql();
        $exists = bh_menu_table_exists($db);
        $data = $exists ? bh_menu_read_data($db) : bh_menu_default_data();
        bh_menu_api_out(200, ['ok'=>true, 'csrf'=>$csrf, 'setup_required'=>!$exists, 'data'=>$data]);
    } catch (Throwable $error) {
        error_log('Admin menu read failed: ' . $error->getMessage());
        bh_menu_api_out(503, ['ok'=>false, 'error'=>'Menu settings are temporarily unavailable.']);
    }
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    bh_menu_api_out(405, ['ok'=>false, 'error'=>'Use the public/admin GET route or an authenticated POST action.']);
}

require_once __DIR__ . '/admin-session-guard.php';
if (empty($_SESSION['bh_menu_manager_csrf'])) {
    $_SESSION['bh_menu_manager_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['bh_menu_manager_csrf'];

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    $requestHost = parse_url('https://' . (string)($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if (!is_string($originHost) || !is_string($requestHost) || strcasecmp($originHost, $requestHost) !== 0) {
        bh_menu_api_out(403, ['ok'=>false, 'error'=>'This menu request must come from the same website.']);
    }
}
if (strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')) === 'cross-site') {
    bh_menu_api_out(403, ['ok'=>false, 'error'=>'Cross-site menu requests are not allowed.']);
}

$raw = (string)file_get_contents('php://input');
if (strlen($raw) > 100000) bh_menu_api_out(413, ['ok'=>false, 'error'=>'The menu update is too large.']);
$body = json_decode($raw, true);
if (!is_array($body)) bh_menu_api_out(400, ['ok'=>false, 'error'=>'Invalid menu request.']);
if (!hash_equals($csrf, (string)($body['csrf'] ?? ''))) {
    bh_menu_api_out(403, ['ok'=>false, 'error'=>'Security check failed. Refresh the menu manager and try again.']);
}

$postAction = (string)($body['action'] ?? '');
if (!in_array($postAction, ['setup','save'], true)) {
    bh_menu_api_out(400, ['ok'=>false, 'error'=>'Choose a supported menu action.']);
}
if ($postAction === 'setup' && array_diff(array_keys($body), ['action','csrf'])) {
    bh_menu_api_out(400, ['ok'=>false, 'error'=>'The setup request contains unsupported fields.']);
}
if ($postAction === 'save' && array_diff(array_keys($body), ['action','csrf','menus'])) {
    bh_menu_api_out(400, ['ok'=>false, 'error'=>'The save request contains unsupported fields.']);
}

try {
    require __DIR__ . '/db.php';
    $db = bh_mysql();

    if ($postAction === 'setup') {
        $db->exec("CREATE TABLE IF NOT EXISTS bh_site_menus (
            menu_key VARCHAR(40) PRIMARY KEY,
            menu_json LONGTEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        bh_menu_api_out(200, ['ok'=>true, 'csrf'=>$csrf, 'message'=>'Menu settings storage is ready. Existing page content has not been changed.']);
    }

    $validated = bh_menu_validate_payload($body['menus'] ?? null);
    if (!$validated['ok']) bh_menu_api_out(422, ['ok'=>false, 'error'=>$validated['error']]);
    if (!bh_menu_table_exists($db)) {
        bh_menu_api_out(409, ['ok'=>false, 'error'=>'Set up menu storage before saving menu changes.', 'setup_required'=>true]);
    }

    $db->beginTransaction();
    $statement = $db->prepare("INSERT INTO bh_site_menus(menu_key, menu_json) VALUES(?, ?) ON DUPLICATE KEY UPDATE menu_json = VALUES(menu_json)");
    foreach ($validated['menus'] as $key => $items) {
        $json = json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $statement->execute([$key, $json]);
    }
    $db->commit();
    bh_menu_api_out(200, ['ok'=>true, 'csrf'=>$csrf, 'message'=>'Navigation menus saved for this deployment.']);
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log('Admin menu save failed: ' . $error->getMessage());
    bh_menu_api_out(500, ['ok'=>false, 'error'=>'The menu changes could not be saved.']);
}
