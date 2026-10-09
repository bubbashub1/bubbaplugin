<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
// This probe is only available to an already signed-in class leader or admin.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('BUBBAHUBSESSID');
    session_start();
}
if (empty($_SESSION['bh_user_id']) && empty($_SESSION['bh_admin_authenticated'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'not_authorised']);
    exit;
}
register_shutdown_function(static function (): void {
    $last = error_get_last();
    if (!$last || !in_array($last['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true)) return;
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(200);
    echo json_encode(['ok'=>false,'error'=>'fatal','file'=>basename($last['file']),'line'=>$last['line']]);
});
ob_start();
try {
    require __DIR__.'/leader-portal.php';
    $output=ob_get_clean();
    echo $output;
} catch (Throwable $e) {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(200);
    echo json_encode(['ok'=>false,'error'=>($e instanceof ParseError ? 'parse_error':'exception'),'file'=>basename($e->getFile()),'line'=>$e->getLine()]);
}
