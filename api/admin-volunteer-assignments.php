<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function respond(int $code,array $body): never {http_response_code($code);echo json_encode($body);exit;}
if(session_status()!==PHP_SESSION_ACTIVE){session_name('BUBBAHUBSESSID');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);session_start();}
if(empty($_SESSION['bh_admin_authenticated']))respond(403,['ok'=>false,'error'=>'admin_required']);
try{
require __DIR__.'/db.php';$db=bh_mysql();
$db->exec("CREATE TABLE IF NOT EXISTS bh_volunteer_assignments (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED NOT NULL,
region VARCHAR(120) NOT NULL,
is_active TINYINT(1) NOT NULL DEFAULT 1,
created_at DATETIME NOT NULL,
UNIQUE KEY uniq_user_region (user_id,region),
KEY idx_region_active(region,is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if($_SERVER['REQUEST_METHOD']==='GET'){
$q=$db->query("SELECT va.id,va.user_id,u.email,va.region,va.is_active,va.created_at FROM bh_volunteer_assignments va JOIN bh_users u ON u.id=va.user_id ORDER BY va.created_at DESC LIMIT 300");
respond(200,['ok'=>true,'assignments'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')respond(405,['ok'=>false,'error'=>'method_not_allowed']);
$b=json_decode(file_get_contents('php://input'),true);
if(!is_array($b))respond(400,['ok'=>false,'error'=>'invalid_json']);
if(empty($_SESSION['bh_csrf'])||!hash_equals((string)$_SESSION['bh_csrf'],(string)($b['csrf']??'')))respond(403,['ok'=>false,'error'=>'csrf_invalid']);
$userId=(int)($b['user_id']??0);$region=trim((string)($b['region']??''));$active=($b['active']??false)===true?1:0;
if($userId<1||mb_strlen($region)<2||mb_strlen($region)>120)respond(422,['ok'=>false,'error'=>'invalid_fields']);
$u=$db->prepare("SELECT id FROM bh_users WHERE id=? AND status='active' LIMIT 1");$u->execute([$userId]);if(!$u->fetchColumn())respond(422,['ok'=>false,'error'=>'active_user_required']);
$stmt=$db->prepare("INSERT INTO bh_volunteer_assignments(user_id,region,is_active,created_at) VALUES(?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE is_active=VALUES(is_active)");
$stmt->execute([$userId,$region,$active]);
respond(200,['ok'=>true,'message'=>'Region assignment saved. Editing permissions require separate server-side enforcement.']);
}catch(Throwable $e){error_log('Volunteer assignment failure: '.$e->getMessage());respond(500,['ok'=>false,'error'=>'temporarily_unavailable']);}
