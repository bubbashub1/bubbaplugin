<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');session_start();
function out(int $status,array $body):never{http_response_code($status);echo json_encode($body);exit;}
if(empty($_SESSION['bh_admin_authenticated']))out(401,['ok'=>false,'error'=>'admin_login_required']);
if($_SERVER['REQUEST_METHOD']!=='POST')out(405,['ok'=>false,'error'=>'method_not_allowed']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
$host=(string)($_SERVER['HTTP_HOST']??'');
$source=(string)($_SERVER['HTTP_SEC_FETCH_SITE']??'');
if($source!==''&&!in_array($source,['same-origin','none'],true))out(403,['ok'=>false,'error'=>'cross_origin_forbidden']);
if($origin==='')out(403,['ok'=>false,'error'=>'origin_required']);
$parts=parse_url($origin);
if(!is_array($parts)||strtolower((string)($parts['host']??''))!==strtolower(explode(':',$host)[0])||!in_array(strtolower((string)($parts['scheme']??'')),['https'],true)||isset($parts['port']))out(403,['ok'=>false,'error'=>'origin_forbidden']);
if(!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json'))out(415,['ok'=>false,'error'=>'json_required']);

$body=json_decode(file_get_contents('php://input'),true);if(!is_array($body))out(422,['ok'=>false,'error'=>'invalid_body']);
$id=(int)($body['id']??0);$action=(string)($body['action']??'');
if($id<1||!in_array($action,['edit','deactivate','restore','promote','revoke_promotion','permanent_delete'],true))out(422,['ok'=>false,'error'=>'invalid_action']);
try{
 require __DIR__.'/db.php';$db=bh_mysql();
 $db->exec("CREATE TABLE IF NOT EXISTS bh_admin_leader_actions (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, leader_user_id BIGINT UNSIGNED NOT NULL, action VARCHAR(40) NOT NULL, details TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (leader_user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $db->exec("CREATE TABLE IF NOT EXISTS bh_admin_leader_promotions (leader_user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, active TINYINT(1) NOT NULL DEFAULT 1, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $db->beginTransaction();
 $s=$db->prepare("SELECT id,email,status FROM bh_users WHERE id=? AND role='leader' FOR UPDATE");$s->execute([$id]);$leader=$s->fetch(PDO::FETCH_ASSOC);
 if(!$leader){$db->rollBack();out(404,['ok'=>false,'error'=>'leader_not_found']);}
 $details=[];
 if($action==='permanent_delete'){
  if(($leader['status']??'')!=='inactive'){$db->rollBack();out(409,['ok'=>false,'error'=>'deactivate_first']);}
  if(($body['confirmation']??'')!==$leader['email']){$db->rollBack();out(422,['ok'=>false,'error'=>'email_confirmation_required']);}
  // Fail closed: never delete a leader with linked data or unknown schema references.
  $tables=$db->query("SELECT TABLE_NAME,COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN ('user_id','leader_user_id','created_by_user_id','owner_user_id','organiser_user_id')")->fetchAll(PDO::FETCH_ASSOC);
  foreach($tables as $ref){
   $table=(string)$ref['TABLE_NAME'];$col=(string)$ref['COLUMN_NAME'];
   if(!preg_match('/^[a-zA-Z0-9_]+$/',$table)||!preg_match('/^[a-zA-Z0-9_]+$/',$col))throw new RuntimeException('Unsafe reference metadata');
   if(in_array($table,['bh_admin_leader_actions','bh_admin_leader_promotions','bh_users'],true))continue;
   $check=$db->prepare("SELECT 1 FROM `$table` WHERE `$col`=? LIMIT 1");$check->execute([$id]);
   if($check->fetchColumn()){$db->rollBack();out(409,['ok'=>false,'error'=>'linked_records_prevent_delete','table'=>$table]);}
  }
  $check=$db->prepare("SELECT 1 FROM bh_organisers WHERE LOWER(email)=LOWER(?) LIMIT 1");$check->execute([$leader['email']]);
  if($check->fetchColumn()){$db->rollBack();out(409,['ok'=>false,'error'=>'linked_organiser_prevents_delete']);}
  $fk=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='bh_users' AND REFERENCED_COLUMN_NAME='id' AND TABLE_NAME NOT IN ('bh_admin_leader_actions','bh_admin_leader_promotions')");
  $fk->execute();if((int)$fk->fetchColumn()>0){$db->rollBack();out(409,['ok'=>false,'error'=>'foreign_key_review_required']);}
  $db->prepare("DELETE FROM bh_admin_leader_promotions WHERE leader_user_id=?")->execute([$id]);
  $db->prepare("DELETE FROM bh_users WHERE id=? AND role='leader' AND status='inactive'")->execute([$id]);
  $details=['deleted_email'=>$leader['email']];
 }elseif($action==='edit'){
  $status=(string)($body['status']??'');if(!in_array($status,['active','inactive','suspended'],true)){$db->rollBack();out(422,['ok'=>false,'error'=>'invalid_status']);}
  $db->prepare("UPDATE bh_users SET status=? WHERE id=? AND role='leader'")->execute([$status,$id]);$details=['old_status'=>$leader['status'],'new_status'=>$status];
 }elseif($action==='deactivate'||$action==='restore'){
  $new=$action==='deactivate'?'inactive':'active';
  $db->prepare("UPDATE bh_users SET status=? WHERE id=? AND role='leader'")->execute([$new,$id]);$details=['old_status'=>$leader['status'],'new_status'=>$new];
 }else{
  $active=$action==='promote'?1:0;
  $db->prepare("INSERT INTO bh_admin_leader_promotions (leader_user_id,active) VALUES (?,?) ON DUPLICATE KEY UPDATE active=VALUES(active)")->execute([$id,$active]);
  $details=['complimentary_pro'=>$active];
 }
 $db->prepare("INSERT INTO bh_admin_leader_actions (leader_user_id,action,details) VALUES (?,?,?)")->execute([$id,$action,json_encode(['admin_session'=>hash('sha256',session_id()),'change'=>$details])]);
 $db->commit();out(200,['ok'=>true,'action'=>$action,'leader_id'=>$id,'details'=>$details]);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('Admin leader action: '.$e->getMessage());out(500,['ok'=>false,'error'=>'leader_action_failed']);}
