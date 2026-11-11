<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_start();
function bh_activate_response(int $status,array $data): never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES);exit;}
require __DIR__.'/db.php';
$db=bh_mysql();
$activationTable=$db->query("SHOW TABLES LIKE 'bh_account_activation_tokens'")->fetchColumn();
if(!$activationTable)bh_activate_response(503,['ok'=>false,'error'=>'activation_storage_unavailable']);
$activationColumns=$db->query("SHOW COLUMNS FROM bh_account_activation_tokens")->fetchAll(PDO::FETCH_COLUMN);
foreach(['id','user_id','token_hash','code_hash','expires_at','used_at','created_at'] as $requiredActivationColumn){
 if(!in_array($requiredActivationColumn,$activationColumns,true))bh_activate_response(503,['ok'=>false,'error'=>'activation_storage_update_required']);
}


$method=$_SERVER['REQUEST_METHOD'];
if($method==='GET'){
  $token=trim((string)($_GET['token']??''));
  if($token==='') bh_activate_response(422,['ok'=>false,'error'=>'activation_token_required']);
  $hash=hash('sha256',$token);
  $q=$db->prepare("SELECT t.id,t.user_id,u.email,u.role,u.status FROM bh_account_activation_tokens t INNER JOIN bh_users u ON u.id=t.user_id WHERE t.token_hash=? AND t.used_at IS NULL AND t.expires_at>NOW() LIMIT 1");
  $q->execute([$hash]);$row=$q->fetch();
  if(!$row) bh_activate_response(410,['ok'=>false,'error'=>'activation_link_invalid','message'=>'This activation link is invalid, expired or has already been used.']);
  bh_activate_response(200,['ok'=>true,'email'=>$row['email'],'role'=>$row['role'],'status'=>$row['status']]);
}
if($method!=='POST') bh_activate_response(405,['ok'=>false,'error'=>'GET or POST required.']);

$body=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($body))$body=$_POST;
$token=trim((string)($body['token']??''));
$code=preg_replace('/\D+/','',(string)($body['code']??''));
$password=(string)($body['password']??'');
$confirm=(string)($body['confirm_password']??$body['password_confirm']??'');
if($token===''||!preg_match('/^\d{6}$/',$code)||strlen($password)<8||$password!==$confirm)bh_activate_response(422,['ok'=>false,'error'=>'activation_details_invalid','message'=>'Enter the 6-digit one-time code and choose a password of at least 8 characters.']);

$hash=hash('sha256',$token);
$db->beginTransaction();
try{
  $q=$db->prepare("SELECT t.id,t.user_id,t.code_hash,u.email,u.role,u.status FROM bh_account_activation_tokens t INNER JOIN bh_users u ON u.id=t.user_id WHERE t.token_hash=? AND t.used_at IS NULL AND t.expires_at>NOW() LIMIT 1 FOR UPDATE");
  $q->execute([$hash]);$row=$q->fetch();
  if(!$row){$db->rollBack();bh_activate_response(410,['ok'=>false,'error'=>'activation_link_invalid','message'=>'This activation link is invalid, expired or has already been used.']);}
  if(empty($row['code_hash'])||!hash_equals((string)$row['code_hash'],hash('sha256',$code))){$db->rollBack();bh_activate_response(422,['ok'=>false,'error'=>'activation_code_invalid','message'=>'That one-time sign-in code is incorrect. Check the email and try again.']);}
  $db->prepare("UPDATE bh_users SET password_hash=?,status='active',role=CASE WHEN role='family' THEN 'leader' ELSE role END WHERE id=?")->execute([password_hash($password,PASSWORD_DEFAULT),(int)$row['user_id']]);
  $db->prepare("UPDATE bh_account_activation_tokens SET used_at=NOW() WHERE id=? AND used_at IS NULL")->execute([(int)$row['id']]);
  $db->commit();
  session_regenerate_id(true);
  $_SESSION['bh_user_id']=(int)$row['user_id'];
  $_SESSION['bh_csrf']=bin2hex(random_bytes(24));
  bh_activate_response(200,['ok'=>true,'authenticated'=>true,'user'=>['id'=>(int)$row['user_id'],'email'=>$row['email'],'role'=>'leader','status'=>'active'],'csrf'=>$_SESSION['bh_csrf'],'redirect'=>'leader-portal.html']);
}catch(Throwable $e){
  if($db->inTransaction())$db->rollBack();
  bh_activate_response(500,['ok'=>false,'error'=>'activation_failed','message'=>'The account could not be activated. Please try again.']);
}
?>