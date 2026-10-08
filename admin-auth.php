<?php
declare(strict_types=1);

// Keep admin authentication on the exact same PHP session configuration as
// /api/auth.php so the login survives navigation across browsers.
$secure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');

// Use a dedicated cookie name so WordPress or another PHP application on the
// same domain cannot overwrite the admin session cookie.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('BUBBAHUB_ADMINSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function respond(int $s, array $d): void {
    http_response_code($s);
    echo json_encode($d);
    session_write_close();
    exit;
}

// Root deployment: resolve the server-only configuration without exposing it to the browser.
$configCandidates = array_filter([
    dirname(__DIR__) . '/github-deploy-config.php',
    dirname($_SERVER['DOCUMENT_ROOT'] ?? '') . '/github-deploy-config.php',
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/github-deploy-config.php',
    '/github-deploy-config.php'
]);
$f = '';
foreach ($configCandidates as $candidate) {
    if (is_file($candidate)) {
        $f = $candidate;
        break;
    }
}
if (!is_file($f)) {
    respond(503, ['ok'=>false, 'error'=>'Admin authentication is not configured on the server.']);
}
$c = require $f;
if (!is_array($c)) {
    respond(503, ['ok'=>false, 'error'=>'Invalid server configuration.']);
}

$a = (string)($_GET['action'] ?? 'check');

if ($a === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, ['ok'=>false, 'error'=>'POST required']);
    }
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $originHost = parse_url($origin, PHP_URL_HOST);
        $requestHost = explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0];
        if (!$originHost || strcasecmp($originHost, $requestHost) !== 0) {
            respond(403, ['ok'=>false, 'error'=>'Invalid request origin']);
        }
    }

    $i = json_decode((string)file_get_contents('php://input'), true);
    $u = is_array($i) ? trim((string)($i['username'] ?? '')) : '';
    $p = is_array($i) ? (string)($i['password'] ?? '') : '';
    $eu = (string)($c['admin_username'] ?? '');
    $ep = (string)($c['admin_password'] ?? '');

    if ($eu === '' || $ep === '' || !hash_equals($eu, $u) || !hash_equals($ep, $p)) {
        respond(401, ['ok'=>false, 'error'=>'Invalid username or password.']);
    }

    session_regenerate_id(true);
    $_SESSION['bh_admin_authenticated'] = true;
    session_write_close();
    respond(200, ['ok'=>true]);
}

if ($a === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, ['ok'=>false, 'error'=>'POST required']);
    }
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $originHost = parse_url($origin, PHP_URL_HOST);
        $requestHost = explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0];
        if (!$originHost || strcasecmp($originHost, $requestHost) !== 0) {
            respond(403, ['ok'=>false, 'error'=>'Invalid request origin']);
        }
    }
    // Admin authentication is separate from the family account. Remove only
    // the admin flag so My Hub/family session remains signed in.
    unset($_SESSION['bh_admin_authenticated']);
    respond(200, ['ok'=>true]);
}

if (empty($_SESSION['bh_admin_authenticated'])) {
    respond(401, ['ok'=>false, 'error'=>'Admin login required.']);
}

respond(200, ['ok'=>true]);
?>