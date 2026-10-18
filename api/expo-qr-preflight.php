<?php
declare(strict_types=1);
/**
 * Staging-only, admin-only, read-only Expo QR renderer preflight.
 * Never accepts a ticket payload, touches the database, or sends email.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function expoQrPreflightReply(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    expoQrPreflightReply(405, ['ok'=>false, 'error'=>'method_not_allowed']);
}
if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'staging.bubbahub.co.uk') {
    expoQrPreflightReply(404, ['ok'=>false, 'error'=>'not_found']);
}
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if (empty($_SESSION['bh_admin_authenticated'])) {
    expoQrPreflightReply(401, ['ok'=>false, 'error'=>'admin_login_required']);
}
$last=(int)($_SESSION['bh_admin_last_activity'] ?? 0);
if ($last<1 || time()-$last>1800) {
    unset($_SESSION['bh_admin_authenticated'], $_SESSION['bh_admin_csrf']);
    expoQrPreflightReply(401, ['ok'=>false, 'error'=>'admin_session_expired']);
}
$_SESSION['bh_admin_last_activity']=time();

$autoload=dirname(__DIR__).'/vendor/autoload.php';
$checks=[
    'gd_enabled'=>extension_loaded('gd'),
    'composer_autoload_present'=>is_file($autoload),
    'qr_class_available'=>false,
    'png_writer_available'=>false,
    'qr_png_render_test'=>false
];
$stage='autoload';
$errorClass=null;
try {
    if ($checks['composer_autoload_present']) {
        require_once $autoload;
        $checks['qr_class_available']=class_exists(\\Endroid\\QrCode\\QrCode::class);
        $checks['png_writer_available']=class_exists(\\Endroid\\QrCode\\Writer\\PngWriter::class);
    }
    if ($checks['gd_enabled'] && $checks['qr_class_available'] && $checks['png_writer_available']) {
        $stage='render';
        // Non-ticket fixed test content; no real booking or bearer credential.
        $qr=new \\Endroid\\QrCode\\QrCode(
            data:'BUBBA-HUB-QR-RENDER-PREFLIGHT',
            errorCorrectionLevel:\\Endroid\\QrCode\\ErrorCorrectionLevel::High,
            size:320,
            margin:16
        );
        $writer=new \\Endroid\\QrCode\\Writer\\PngWriter();
        $png=$writer->write($qr)->getString();
        $checks['qr_png_render_test']=substr($png,0,8)==="\\x89PNG\\r\\n\\x1a\\n";
    }
} catch (Throwable $e) {
    $errorClass=get_class($e);
    error_log('Expo QR preflight failed: stage='.$stage.' class='.$errorClass);
}
$ready=!in_array(false,$checks,true);
expoQrPreflightReply(200, [
    'ok'=>true,
    'ready'=>$ready,
    'checks'=>$checks,
    'failure_stage'=>$ready ? null : $stage,
    'failure_class'=>$errorClass,
    'writes_performed'=>false,
    'email_sent'=>false
]);
