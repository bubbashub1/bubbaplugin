<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');
session_start();
function bh_monitor_settings_reply(int $status,array $payload): never {
 http_response_code($status);
 echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
 exit;
}
if(empty($_SESSION['bh_admin_authenticated'])) bh_monitor_settings_reply(401,['ok'=>false,'error'=>'Admin login required.']);
require_once __DIR__.'/db.php';
$allowedFields=['Description','Website','Booking link','Email','Telephone','Facebook','Instagram','Age range','Category','Location','Postcode','Image'];
try {
 $db=bh_mysql();
 $tableExists=(bool)$db->query('SHOW TABLES LIKE '.$db->quote('bh_listing_checker_settings'))->fetchColumn();
 if(!$tableExists) bh_monitor_settings_reply(503,['ok'=>false,'error'=>'Listing Monitor settings storage is unavailable.']);
 $key='monitor_completeness_excluded_fields';
 if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
  $_SESSION['bh_listing_monitor_settings_csrf']??=bin2hex(random_bytes(32));
  $stmt=$db->prepare('SELECT setting_value FROM bh_listing_checker_settings WHERE setting_key=? LIMIT 1');
  $stmt->execute([$key]);
  $raw=$stmt->fetchColumn();
  $excluded=is_string($raw)?json_decode($raw,true):[];
  if(!is_array($excluded)) $excluded=[];
  $excluded=array_values(array_intersect($allowedFields,$excluded));
  bh_monitor_settings_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_listing_monitor_settings_csrf'],'excluded_fields'=>$excluded,'available_fields'=>$allowedFields]);
 }
 if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST') bh_monitor_settings_reply(405,['ok'=>false,'error'=>'Method not allowed.']);
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
 $host=(string)($_SERVER['HTTP_HOST']??'');
 if($origin!==''&&(!filter_var($origin,FILTER_VALIDATE_URL)||strcasecmp((string)parse_url($origin,PHP_URL_HOST),preg_replace('/:\\d+$/','',$host))!==0)) bh_monitor_settings_reply(403,['ok'=>false,'error'=>'Cross-origin request blocked.']);
 if((string)($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site') bh_monitor_settings_reply(403,['ok'=>false,'error'=>'Cross-site request blocked.']);
 if((int)($_SERVER['CONTENT_LENGTH']??0)>8192) bh_monitor_settings_reply(413,['ok'=>false,'error'=>'Request too large.']);
 $input=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($input)) bh_monitor_settings_reply(400,['ok'=>false,'error'=>'Invalid JSON.']);
 if(!hash_equals((string)($_SESSION['bh_listing_monitor_settings_csrf']??''),(string)($input['csrf']??''))) bh_monitor_settings_reply(403,['ok'=>false,'error'=>'Security check failed. Refresh the page and try again.']);
 if(($input['action']??'')!=='save_completeness') bh_monitor_settings_reply(422,['ok'=>false,'error'=>'Unknown settings action.']);
 $excluded=$input['excluded_fields']??null;
 if(!is_array($excluded)) bh_monitor_settings_reply(422,['ok'=>false,'error'=>'Choose which fields to exclude.']);
 $excluded=array_values(array_unique(array_intersect($allowedFields,array_map('strval',$excluded))));
 $stmt=$db->prepare('INSERT INTO bh_listing_checker_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
 $stmt->execute([$key,json_encode($excluded,JSON_UNESCAPED_UNICODE)]);
 bh_monitor_settings_reply(200,['ok'=>true,'excluded_fields'=>$excluded,'message'=>'Listing Monitor completeness settings saved.']);
} catch(Throwable $e) {
 error_log('Listing Monitor settings: '.$e->getMessage());
 bh_monitor_settings_reply(500,['ok'=>false,'error'=>'Unable to load or save Listing Monitor settings.']);
}
