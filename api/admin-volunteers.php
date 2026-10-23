<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function out(int $status,array $body): never {http_response_code($status);echo json_encode($body);exit;}
if(session_status()!==PHP_SESSION_ACTIVE){session_name('BUBBAHUBSESSID');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);session_start();}
if(empty($_SESSION['bh_admin_authenticated']))out(403,['ok'=>false,'error'=>'admin_required']);
try{
require __DIR__.'/db.php';$db=bh_mysql();
if($_SERVER['REQUEST_METHOD']==='GET'){
 $stmt=$db->query("SELECT id,name,email,town,region,preferred_role,availability,motivation,status,created_at FROM bh_volunteer_applications ORDER BY created_at DESC LIMIT 200");
 out(200,['ok'=>true,'applications'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')out(405,['ok'=>false,'error'=>'method_not_allowed']);
$input=json_decode(file_get_contents('php://input'),true);
if(!is_array($input))out(400,['ok'=>false,'error'=>'invalid_json']);
$token=(string)($input['csrf']??'');
if(empty($_SESSION['bh_csrf'])||!hash_equals((string)$_SESSION['bh_csrf'],$token))out(403,['ok'=>false,'error'=>'csrf_invalid']);
$id=(int)($input['id']??0);$status=(string)($input['status']??'');
if($id<1||!in_array($status,['pending','approved','declined'],true))out(422,['ok'=>false,'error'=>'invalid_fields']);
$stmt=$db->prepare("UPDATE bh_volunteer_applications SET status=? WHERE id=?");
$stmt->execute([$status,$id]);
if(!$stmt->rowCount()){
$exists=$db->prepare("SELECT status FROM bh_volunteer_applications WHERE id=? LIMIT 1");
$exists->execute([$id]);
$current=$exists->fetchColumn();
if($current===false)out(404,['ok'=>false,'error'=>'application_not_found']);
if($current===$status)out(200,['ok'=>true,'message'=>'Application already has this status.']);
out(409,['ok'=>false,'error'=>'status_not_updated']);
}
out(200,['ok'=>true,'message'=>'Application status updated. Account access is not granted automatically.']);
}catch(Throwable $e){error_log('Volunteer admin failed: '.$e->getMessage());out(500,['ok'=>false,'error'=>'temporarily_unavailable']);}
