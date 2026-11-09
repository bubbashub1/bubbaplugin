<?php
declare(strict_types=1);
/** Fail-closed administrator session guard for account APIs. */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('BUBBAHUB_ADMINSESSID');
    $secure = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
if (empty($_SESSION['bh_admin_authenticated'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'admin_login_required']);
    exit;
}
$now = time();
$last = (int)($_SESSION['bh_admin_last_activity'] ?? 0);
$created = (int)($_SESSION['bh_admin_created_at'] ?? 0);
if ($last < 1 || $created < 1 || ($now - $last) > 1800 || ($now - $created) > 28800) {
    // Clear only admin authentication; preserve any separate family session data.
    unset($_SESSION['bh_admin_authenticated'], $_SESSION['bh_admin_username'],
        $_SESSION['bh_admin_last_activity'], $_SESSION['bh_admin_created_at'],
        $_SESSION['bh_admin_csrf'], $_SESSION['bh_live_baseline_csrf']);
    session_regenerate_id(true);
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'admin_session_expired']);
    exit;
}
$_SESSION['bh_admin_last_activity'] = $now;
