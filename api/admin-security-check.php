<?php
declare(strict_types=1);

/**
 * Read-only security posture checks for authenticated Bubba Hub administrators.
 * This endpoint never returns credentials, paths, configuration values or user data.
 */
$secure = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
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
header('X-Content-Type-Options: nosniff');

function bh_security_reply(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    session_write_close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    bh_security_reply(405, ['ok' => false, 'error' => 'method_not_allowed']);
}
if (empty($_SESSION['bh_admin_authenticated'])) {
    bh_security_reply(401, ['ok' => false, 'error' => 'admin_login_required']);
}
$lastActivity = (int)($_SESSION['bh_admin_last_activity'] ?? 0);
if ($lastActivity < 1 || time() - $lastActivity > 1800) {
    unset($_SESSION['bh_admin_authenticated'], $_SESSION['bh_admin_username'], $_SESSION['bh_admin_last_activity']);
    session_regenerate_id(true);
    bh_security_reply(401, ['ok' => false, 'error' => 'admin_session_expired']);
}
$_SESSION['bh_admin_last_activity'] = time();

$checks = [];
$add = static function (string $id, string $title, string $status, string $detail, string $severity = 'normal') use (&$checks): void {
    $checks[] = ['id' => $id, 'title' => $title, 'status' => $status, 'detail' => $detail, 'severity' => $severity];
};

$add('https', 'HTTPS connection', $secure ? 'pass' : 'fail',
    $secure ? 'This request reached PHP over HTTPS.' : 'This request does not appear to be using HTTPS. Check the hosting TLS and proxy configuration.',
    $secure ? 'normal' : 'critical');

$phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
$add('php', 'PHP runtime baseline', $phpOk ? 'pass' : 'warning',
    $phpOk ? 'The PHP runtime meets the application baseline (8.1 or newer).' : 'The PHP runtime is below the application baseline of 8.1. Confirm compatibility before upgrading.',
    $phpOk ? 'normal' : 'high');

$strict = filter_var((string)ini_get('session.use_strict_mode'), FILTER_VALIDATE_BOOLEAN);
$cookieOnly = filter_var((string)ini_get('session.use_only_cookies'), FILTER_VALIDATE_BOOLEAN);
$cookie = session_get_cookie_params();
$httpOnly = !empty($cookie['httponly']);
$sameSite = strtolower((string)($cookie['samesite'] ?? ''));
$sessionSecure = !empty($cookie['secure']);
$sessionOk = $strict && $cookieOnly && $httpOnly && in_array($sameSite, ['lax', 'strict'], true) && $sessionSecure;
$sessionDetail = 'Strict mode: '.($strict ? 'on' : 'off').'; cookies only: '.($cookieOnly ? 'on' : 'off').'; HttpOnly: '.($httpOnly ? 'on' : 'off').'; SameSite: '.($sameSite !== '' ? $sameSite : 'not set').'; Secure: '.($sessionSecure ? 'on' : 'off').'.';
if (!$secure) $sessionDetail .= ' Secure-cookie assessment may be affected until HTTPS is correctly configured.';
$add('session', 'Admin session cookie settings', $sessionOk ? 'pass' : 'warning', $sessionDetail, $sessionOk ? 'normal' : 'high');

try {
    require_once __DIR__ . '/db.php';
    $pdo = bh_mysql();
    $pdo->query('SELECT 1')->fetchColumn();
    $add('database', 'Database connectivity', 'pass', 'The application connected to its database and completed a read-only test query.');
} catch (Throwable $e) {
    error_log('Bubba Hub security dashboard database check failed: '.$e->getMessage());
    $add('database', 'Database connectivity', 'fail', 'The application could not complete its database health check. See the private server error log.', 'critical');
}

$manual = [
    ['id'=>'rate-limits','title'=>'Login rate limiting','status'=>'review','detail'=>'Confirm failed-login throttling is enforced even when the rate-limit store is unavailable.'],
    ['id'=>'permissions','title'=>'Role and record ownership','status'=>'review','detail'=>'Verify family, child, organiser and leader ownership checks on every read and write endpoint.'],
    ['id'=>'csrf','title'=>'CSRF protection','status'=>'review','detail'=>'Review every state-changing request, including admin actions, for CSRF and origin validation.'],
    ['id'=>'uploads','title'=>'File uploads','status'=>'review','detail'=>'Check file type, size, generated filenames, storage location and executable-file blocking.'],
    ['id'=>'secrets','title'=>'Secrets and configuration','status'=>'review','detail'=>'Confirm credentials and private configuration are outside the public web root and never committed.'],
    ['id'=>'migrations','title'=>'Database migrations','status'=>'review','detail'=>'Confirm schema changes run through reviewed, versioned migrations rather than normal visitor requests.'],
    ['id'=>'backups','title'=>'Backups and restore','status'=>'review','detail'=>'Verify encrypted/off-site backups and periodically test a restore.'],
    ['id'=>'deployment','title'=>'Deployment SSH trust','status'=>'review','detail'=>'Pin the hosting SSH host key; the current workflow disables strict host-key verification.'],
    ['id'=>'logging','title'=>'Error handling and audit logs','status'=>'review','detail'=>'Confirm public responses hide internals while private logs capture security-relevant events without secrets.'],
    ['id'=>'dependencies','title'=>'Dependencies and patching','status'=>'review','detail'=>'Review PHP, Composer and front-end dependencies for known vulnerabilities and supported versions.'],
];

bh_security_reply(200, [
    'ok' => true,
    'generated_at' => gmdate('c'),
    'automated' => $checks,
    'manual' => $manual,
    'notice' => 'This is a read-only first-pass check, not a penetration test or a certification. Items marked Review require a human audit.'
]);
