<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function volunteerReply(int $code,array $data): never { http_response_code($code); echo json_encode($data); exit; }
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)) volunteerReply(405,['ok'=>false,'error'=>'method_not_allowed']);
if(session_status()!==PHP_SESSION_ACTIVE){
 session_name('BUBBAHUBSESSID');
 session_set_cookie_params(['path'=>'/','httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
 session_start();
}
$userId=(int)($_SESSION['bh_user_id']??0);
if($userId<1) volunteerReply(401,['ok'=>false,'error'=>'login_required']);
if(empty($_SESSION['bh_csrf'])) $_SESSION['bh_csrf']=bin2hex(random_bytes(24));
$body=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 $raw=file_get_contents('php://input');
 if($raw===false||strlen($raw)>12000) volunteerReply(413,['ok'=>false,'error'=>'invalid_body']);
 $body=json_decode($raw,true);
 if(!is_array($body)) volunteerReply(400,['ok'=>false,'error'=>'invalid_json']);
 if(!hash_equals((string)$_SESSION['bh_csrf'],(string)($body['csrf']??''))) volunteerReply(403,['ok'=>false,'error'=>'csrf_invalid']);
}
$id=(int)(($_SERVER['REQUEST_METHOD']==='GET'?($_GET['id']??0):($body['id']??0)));
if($id<1) volunteerReply(422,['ok'=>false,'error'=>'invalid_activity']);
try{
 require __DIR__.'/db.php';
 $db=bh_mysql();
 $user=$db->prepare("SELECT id FROM bh_users WHERE id=? AND status='active' LIMIT 1");
 $user->execute([$userId]);
 if(!$user->fetchColumn()) volunteerReply(403,['ok'=>false,'error'=>'inactive_account']);
 // An activity with venues outside the assigned regions is not editable by a regional volunteer.
 $check=$db->prepare("SELECT COUNT(*) AS total,
 SUM(CASE WHEN EXISTS (SELECT 1 FROM bh_volunteer_assignments va
 WHERE va.user_id=? AND va.is_active=1 AND LOWER(TRIM(va.region))=LOWER(TRIM(v.region)))
 THEN 1 ELSE 0 END) AS allowed
 FROM bh_venues v WHERE v.activity_id=?");
 $check->execute([$userId,$id]);$counts=$check->fetch(PDO::FETCH_ASSOC);
 if((int)$counts['total']===0||(int)$counts['allowed']!==(int)$counts['total'])
   volunteerReply(403,['ok'=>false,'error'=>'region_not_authorised']);
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $q=$db->prepare("SELECT id,title,description FROM bh_activities WHERE id=? LIMIT 1");
  $q->execute([$id]);$item=$q->fetch(PDO::FETCH_ASSOC);
  if(!$item) volunteerReply(404,['ok'=>false,'error'=>'listing_not_found']);
  volunteerReply(200,['ok'=>true,'activity'=>$item,'csrf'=>$_SESSION['bh_csrf']]);
 }
 $title=trim((string)($body['title']??''));
 $description=trim((string)($body['description']??''));
 if(mb_strlen($title)<3||mb_strlen($title)>200||mb_strlen($description)>8000)
   volunteerReply(422,['ok'=>false,'error'=>'invalid_fields']);
 $db->beginTransaction();
 $q=$db->prepare("SELECT id,title,description FROM bh_activities WHERE id=? FOR UPDATE");
 $q->execute([$id]);$before=$q->fetch(PDO::FETCH_ASSOC);
 if(!$before){$db->rollBack();volunteerReply(404,['ok'=>false,'error'=>'listing_not_found']);}
 // Revalidate permissions inside the transaction to protect against concurrent assignment changes.
 $check->execute([$userId,$id]);$counts=$check->fetch(PDO::FETCH_ASSOC);
 if((int)$counts['total']===0||(int)$counts['allowed']!==(int)$counts['total']){
  $db->rollBack();volunteerReply(403,['ok'=>false,'error'=>'region_not_authorised']);
 }
 $db->exec("CREATE TABLE IF NOT EXISTS bh_volunteer_edit_audit (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,activity_id BIGINT UNSIGNED NOT NULL,
 before_json LONGTEXT NOT NULL,after_json LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_activity_created(activity_id,created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $after=['id'=>$id,'title'=>$title,'description'=>$description];
 $audit=$db->prepare("INSERT INTO bh_volunteer_edit_audit(user_id,activity_id,before_json,after_json) VALUES(?,?,?,?)");
 $audit->execute([$userId,$id,json_encode($before,JSON_THROW_ON_ERROR),json_encode($after,JSON_THROW_ON_ERROR)]);
 $update=$db->prepare("UPDATE bh_activities SET title=?,description=? WHERE id=?");
 $update->execute([$title,$description,$id]);
 $db->commit();
 volunteerReply(200,['ok'=>true,'message'=>'Listing saved','activity'=>$after]);
}catch(Throwable $e){
 if(isset($db)&&$db instanceof PDO&&$db->inTransaction()) $db->rollBack();
 error_log('Volunteer listing edit failed: '.$e->getMessage());
 volunteerReply(500,['ok'=>false,'error'=>'temporarily_unavailable']);
}
