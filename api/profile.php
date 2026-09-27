<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function bh_profile_response(int $status,array $data): never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES);exit;}
try{
 require __DIR__.'/db.php';
 $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
 if(session_status()!==PHP_SESSION_ACTIVE){session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();}
 if(empty($_SESSION['bh_user_id']))bh_profile_response(401,['ok'=>false,'message'=>'Please sign in to manage your profile.']);
 $db=bh_mysql();
 foreach(['ALTER TABLE bh_users ADD COLUMN first_name VARCHAR(80) NULL','ALTER TABLE bh_users ADD COLUMN last_name VARCHAR(80) NULL','ALTER TABLE bh_users ADD COLUMN phone VARCHAR(40) NULL'] as $sql){try{$db->exec($sql);}catch(Throwable $ignored){}}
 if(!isset($_SESSION['bh_csrf']))$_SESSION['bh_csrf']=bin2hex(random_bytes(24));
 $id=(int)$_SESSION['bh_user_id'];
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $stmt=$db->prepare('SELECT id,email,role,status,first_name,last_name,phone FROM bh_users WHERE id=? LIMIT 1');$stmt->execute([$id]);$u=$stmt->fetch();
  if(!$u||$u['status']!=='active')bh_profile_response(401,['ok'=>false,'message'=>'Your account could not be found.']);
  bh_profile_response(200,['ok'=>true,'user'=>['id'=>(int)$u['id'],'email'=>$u['email'],'role'=>$u['role'],'status'=>$u['status'],'first_name'=>$u['first_name']??'','last_name'=>$u['last_name']??'','phone'=>$u['phone']??''],'csrf'=>$_SESSION['bh_csrf']]);
 }
 if($_SERVER['REQUEST_METHOD']!=='POST')bh_profile_response(405,['ok'=>false,'message'=>'Method not allowed.']);
 $body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))bh_profile_response(400,['ok'=>false,'message'=>'Invalid request.']);
 $csrf=(string)($body['csrf']??'');if(!$csrf||!hash_equals((string)$_SESSION['bh_csrf'],$csrf))bh_profile_response(403,['ok'=>false,'message'=>'Security check failed. Please refresh and try again.']);
 $first=trim((string)($body['first_name']??''));$last=trim((string)($body['last_name']??''));$phone=trim((string)($body['phone']??''));
 if(strlen($first)>80||strlen($last)>80||strlen($phone)>40)bh_profile_response(422,['ok'=>false,'message'=>'One of the details is too long.']);
 $stmt=$db->prepare('UPDATE bh_users SET first_name=?,last_name=?,phone=? WHERE id=?');$stmt->execute([$first?:null,$last?:null,$phone?:null,$id]);
 bh_profile_response(200,['ok'=>true,'message'=>'Your profile has been saved.']);
}catch(Throwable $e){bh_profile_response(500,['ok'=>false,'message'=>'Unable to save your profile right now.']);}
?>