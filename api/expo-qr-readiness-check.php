<?php
declare(strict_types=1);
/** Read-only, admin-only Expo QR email readiness check. No database writes or emails. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhExpoReadinessReply(int $status,array $payload): never {
 http_response_code($status);
 echo json_encode($payload,JSON_UNESCAPED_SLASHES);
 exit;
}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){header('Allow: GET');bhExpoReadinessReply(405,['ok'=>false,'error'=>'method_not_allowed']);}
$host=strtolower((string)($_SERVER['HTTP_HOST']??''));
if(!in_array($host,['bubbahub.co.uk','staging.bubbahub.co.uk'],true))bhExpoReadinessReply(404,['ok'=>false,'error'=>'not_found']);
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['bh_admin_authenticated']))bhExpoReadinessReply(401,['ok'=>false,'error'=>'admin_login_required']);
$autoload=dirname(__DIR__).'/vendor/autoload.php';
$autoloadPresent=is_file($autoload);
$autoloadLoaded=false;
if($autoloadPresent){
 try {require_once $autoload;$autoloadLoaded=true;}catch(Throwable $e){error_log('Expo QR dependency autoload failed: '.get_class($e));}
}
$gd=extension_loaded('gd')&&function_exists('imagepng');
$qrClass=$autoloadLoaded&&class_exists('Endroid\\QrCode\\QrCode');
$pngWriter=$autoloadLoaded&&class_exists('Endroid\\QrCode\\Writer\\PngWriter');
$ready=$gd&&$qrClass&&$pngWriter;
bhExpoReadinessReply(200,[
 'ok'=>true,
 'qr_ready'=>$ready,
 'checks'=>[
  'vendor_autoload_present'=>$autoloadPresent,
  'vendor_autoload_loaded'=>$autoloadLoaded,
  'gd_available'=>$gd,
  'qr_code_class_available'=>$qrClass,
  'png_writer_available'=>$pngWriter
 ],
 'writes_performed'=>false,
 'emails_sent'=>false
]);
