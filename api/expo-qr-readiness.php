<?php
declare(strict_types=1);
/**
 * Staging-only Expo QR dependency check. No tickets, tokens, database or mail.
 * Requires a valid Bubba Hub admin session; returns only boolean readiness.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bh_expo_qr_check_reply(int $code,array $body): never {
    http_response_code($code);
    echo json_encode($body,JSON_UNESCAPED_SLASHES);
    exit;
}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){
    header('Allow: GET');
    bh_expo_qr_check_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk'){
    bh_expo_qr_check_reply(404,['ok'=>false,'error'=>'not_found']);
}
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
$last=(int)($_SESSION['bh_admin_last_activity']??0);
if(empty($_SESSION['bh_admin_authenticated']) || $last<1 || time()-$last>1800){
    bh_expo_qr_check_reply(401,['ok'=>false,'error'=>'admin_login_required']);
}
$_SESSION['bh_admin_last_activity']=time();
session_write_close();
$autoload=dirname(__DIR__).'/vendor/autoload.php';
$composerReady=is_file($autoload);
$gdReady=extension_loaded('gd');
$qrReady=false;
$renderReady=false;
if($composerReady){
    try{
        require_once $autoload;
        $qrReady=class_exists('Endroid\\QrCode\\QrCode') && class_exists('Endroid\\QrCode\\Writer\\PngWriter');
        if($qrReady && $gdReady){
            require_once __DIR__.'/expo-qr-image.php';
            $png=bh_expo_adult_ticket_qr_png('BH-EXPO-2027:'.str_repeat('a',64));
            $renderReady=substr($png,0,8)==="\x89PNG\r\n\x1a\n";
        }
    }catch(Throwable $e){
        error_log('Expo QR readiness check failed: '.get_class($e));
    }
}
bh_expo_qr_check_reply(200,[
    'ok'=>true,
    'php_compatible'=>PHP_VERSION_ID>=80100,
    'gd_enabled'=>$gdReady,
    'composer_autoload_present'=>$composerReady,
    'qr_library_available'=>$qrReady,
    'qr_png_render_test'=>$renderReady,
    'ready'=>PHP_VERSION_ID>=80100 && $gdReady && $qrReady && $renderReady
]);
