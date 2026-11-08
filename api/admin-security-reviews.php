<?php
declare(strict_types=1);

/** Admin-only security review register. Requires migration 006_security_review_register.sql. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

function bh_security_review_reply(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    session_write_close();
    exit;
}

$secure = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('BUBBAHUB_ADMINSESSID');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
if (empty($_SESSION['bh_admin_authenticated'])) {
    bh_security_review_reply(401, ['ok'=>false,'error'=>'admin_login_required']);
}
$last = (int)($_SESSION['bh_admin_last_activity'] ?? 0);
if ($last < 1 || time() - $last > 1800) {
    unset($_SESSION['bh_admin_authenticated'], $_SESSION['bh_admin_username'], $_SESSION['bh_admin_last_activity']);
    session_regenerate_id(true);
    bh_security_review_reply(401, ['ok'=>false,'error'=>'admin_session_expired']);
}
$_SESSION['bh_admin_last_activity'] = time();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST'], true)) {
    header('Allow: GET, POST');
    bh_security_review_reply(405, ['ok'=>false,'error'=>'method_not_allowed']);
}
if ($method === 'POST') {
    $host = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $source = $origin !== '' ? $origin : $referer;
    if (!$secure || !$source || parse_url($source, PHP_URL_SCHEME) !== 'https' || strtolower((string)parse_url($source, PHP_URL_HOST)) !== $host) {
        bh_security_review_reply(403, ['ok'=>false,'error'=>'invalid_origin']);
    }
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['bh_security_review_csrf']) || !hash_equals($_SESSION['bh_security_review_csrf'], $token)) {
        bh_security_review_reply(403, ['ok'=>false,'error'=>'invalid_csrf']);
    }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 12000) {
        bh_security_review_reply(413, ['ok'=>false,'error'=>'request_too_large']);
    }
}

try {
    require_once __DIR__ . '/db.php';
    $db = bh_mysql();
    if ($method === 'GET') {
        if (empty($_SESSION['bh_security_review_csrf'])) {
            $_SESSION['bh_security_review_csrf'] = bin2hex(random_bytes(32));
        }
        $stmt = $db->query('SELECT check_key,title,description,severity,status,notes,reviewed_by,reviewed_at,updated_at FROM bh_security_reviews ORDER BY FIELD(severity,\'critical\',\'high\',\'medium\',\'low\'), title');
        bh_security_review_reply(200, ['ok'=>true,'reviews'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'csrf_token'=>$_SESSION['bh_security_review_csrf']]);
    }

    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) bh_security_review_reply(400, ['ok'=>false,'error'=>'invalid_json']);
    $key = is_string($data['check_key'] ?? null) ? trim($data['check_key']) : '';
    $status = is_string($data['status'] ?? null) ? $data['status'] : '';
    $notes = is_string($data['notes'] ?? null) ? trim($data['notes']) : '';
    $allowedStatuses = ['open','in_progress','verified','accepted_risk'];
    if (!preg_match('/^[a-z0-9_-]{2,64}$/', $key) || !in_array($status, $allowedStatuses, true) || mb_strlen($notes) > 4000) {
        bh_security_review_reply(422, ['ok'=>false,'error'=>'invalid_review_data']);
    }
    $reviewedAt = in_array($status, ['verified','accepted_risk'], true) ? date('Y-m-d H:i:s') : null;
    $reviewedBy = $reviewedAt !== null ? substr((string)($_SESSION['bh_admin_username'] ?? 'admin'), 0, 120) : null;
    $stmt = $db->prepare('UPDATE bh_security_reviews SET status=?,notes=?,reviewed_by=?,reviewed_at=? WHERE check_key=?');
    $stmt->execute([$status, $notes !== '' ? $notes : null, $reviewedBy, $reviewedAt, $key]);
    if ($stmt->rowCount() === 0) {
        $exists = $db->prepare('SELECT 1 FROM bh_security_reviews WHERE check_key=?');
        $exists->execute([$key]);
        if (!$exists->fetchColumn()) bh_security_review_reply(404, ['ok'=>false,'error'=>'review_item_not_found']);
    }
    bh_security_review_reply(200, ['ok'=>true,'message'=>'Review saved']);
} catch (Throwable $e) {
    error_log('Bubba Hub security review API failed: '.$e->getMessage());
    bh_security_review_reply(503, ['ok'=>false,'error'=>'security_review_storage_unavailable','message'=>'The security review register is unavailable. Check that migration 006 has been applied.']);
}
