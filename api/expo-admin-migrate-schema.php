<?php
declare(strict_types=1);
/**
 * Staging-only, admin-authenticated Expo schema migration.
 * Explicit POST and CSRF required; no automatic execution on deployment.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhExpoMigrateReply(int $code,array $body): never {http_response_code($code);echo json_encode($body,JSON_UNESCAPED_SLASHES);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){header('Allow: POST');bhExpoMigrateReply(405,['ok'=>false,'error'=>'method_not_allowed']);}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk')bhExpoMigrateReply(404,['ok'=>false,'error'=>'not_found']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if($origin!==''&&$origin!=='https://staging.bubbahub.co.uk')bhExpoMigrateReply(403,['ok'=>false,'error'=>'invalid_origin']);
$referer=(string)($_SERVER['HTTP_REFERER']??'');
if($origin===''&&($referer===''||parse_url($referer,PHP_URL_SCHEME)!=='https'||parse_url($referer,PHP_URL_HOST)!=='staging.bubbahub.co.uk'))bhExpoMigrateReply(403,['ok'=>false,'error'=>'origin_required']);
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['bh_admin_authenticated']))bhExpoMigrateReply(401,['ok'=>false,'error'=>'admin_login_required']);
$last=(int)($_SESSION['bh_admin_last_activity']??0);
if($last<1||time()-$last>1800){unset($_SESSION['bh_admin_authenticated'],$_SESSION['bh_admin_csrf']);bhExpoMigrateReply(401,['ok'=>false,'error'=>'admin_session_expired']);}
$_SESSION['bh_admin_last_activity']=time();
$csrf=(string)($_SERVER['HTTP_X_BH_ADMIN_CSRF']??'');
$expected=(string)($_SESSION['bh_admin_csrf']??'');
if($csrf===''||$expected===''||!hash_equals($expected,$csrf))bhExpoMigrateReply(403,['ok'=>false,'error'=>'invalid_csrf']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>256)bhExpoMigrateReply(413,['ok'=>false,'error'=>'request_too_large']);
$body=json_decode((string)file_get_contents('php://input',false,null,0,257),true);
if(!is_array($body)||($body['confirm']??null)!=='CREATE_EXPO_STAGING_TABLES')bhExpoMigrateReply(422,['ok'=>false,'error'=>'explicit_confirmation_required']);
try{
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 // Hostname is not proof of DB isolation. Explicit expected database identifier
 // must be configured on staging, independent of any client-supplied value.
 if(!defined('BUBBAHUB_EXPO_STAGING_DB_NAME')||constant('BUBBAHUB_EXPO_STAGING_DB_NAME')!=='SCSTAGING-353034334ce5')
  bhExpoMigrateReply(503,['ok'=>false,'error'=>'staging_database_guard_not_configured']);
 $actual=(string)$db->query('SELECT DATABASE()')->fetchColumn();
 if(!hash_equals(constant('BUBBAHUB_EXPO_STAGING_DB_NAME'),$actual))
  bhExpoMigrateReply(503,['ok'=>false,'error'=>'staging_database_mismatch']);
 $ddl=[
 'bh_expo_booking_qrs'=>"CREATE TABLE IF NOT EXISTS bh_expo_booking_qrs (reservation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_expo_booking_qr_hash (token_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 'bh_expo_family_checkins'=>"CREATE TABLE IF NOT EXISTS bh_expo_family_checkins (reservation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,adults_checked_in INT UNSIGNED NOT NULL DEFAULT 0,children_checked_in INT UNSIGNED NOT NULL DEFAULT 0,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 'bh_expo_family_email_delivery'=>"CREATE TABLE IF NOT EXISTS bh_expo_family_email_delivery (reservation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,status ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',attempt_count INT UNSIGNED NOT NULL DEFAULT 0,lease_until DATETIME NULL,last_attempt_at DATETIME NULL,sent_at DATETIME NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,KEY idx_expo_family_delivery_retry (status,lease_until)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 'bh_expo_giveaway_claims'=>"CREATE TABLE IF NOT EXISTS bh_expo_giveaway_claims (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,email_key VARCHAR(254) NOT NULL,claimed_by_admin_id BIGINT UNSIGNED NOT NULL,claimed_at DATETIME NOT NULL,UNIQUE KEY uniq_event_email (event_id,email_key),KEY idx_claimed_at (claimed_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
 ];
 $lock=$db->prepare('SELECT GET_LOCK(?,5)');$lock->execute(['bh-expo-staging-schema-migrate']);
 if((int)$lock->fetchColumn()!==1)bhExpoMigrateReply(409,['ok'=>false,'error'=>'migration_busy']);
 try{
  $result=[];
  $exists=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
  foreach($ddl as $table=>$sql){
   $exists->execute([$table]);$wasPresent=(int)$exists->fetchColumn()===1;
   $db->exec($sql);
   $result[$table]=$wasPresent?'already_exists':'created';
  }
 }finally{$db->query("SELECT RELEASE_LOCK('bh-expo-staging-schema-migrate')");}
 bhExpoMigrateReply(200,['ok'=>true,'database'=>'staging','tables'=>$result]);
}catch(Throwable $e){error_log('Expo staging schema migration error: '.get_class($e));bhExpoMigrateReply(503,['ok'=>false,'error'=>'migration_failed_check_server_logs']);}
