<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require_once __DIR__.'/db.php';
function out(int $code,array $data): never {http_response_code($code);echo json_encode($data);exit;}
function adminOnly(): void {
 if(session_status()!==PHP_SESSION_ACTIVE){session_name('BUBBAHUBSESSID');session_start();}
 if(empty($_SESSION['bh_admin_authenticated'])) out(403,['ok'=>false,'error'=>'Admin required']);
}
try {
 $db=bh_mysql();$action=(string)($_GET['action']??'');$method=$_SERVER['REQUEST_METHOD']??'GET';
 if($action==='apply'){
  if($method!=='POST')out(405,['ok'=>false,'error'=>'Method not allowed']);
  if((int)($_SERVER['CONTENT_LENGTH']??0)>12000)out(413,['ok'=>false,'error'=>'Too large']);
  $body=json_decode((string)file_get_contents('php://input'),true);
  if(!is_array($body))out(400,['ok'=>false,'error'=>'Invalid JSON']);
  if(!empty($body['website']))out(200,['ok'=>true,'message'=>'Received']);
  $get=static fn(string $key,int $max=190): string=>mb_substr(trim((string)($body[$key]??'')),0,$max);
  $event=filter_var($body['event_id']??null,FILTER_VALIDATE_INT);
  $name=$get('business_name');$contact=$get('contact_name');$email=$get('contact_email');$description=$get('business_description',4000);
  if(!$event||!$name||!$contact||!filter_var($email,FILTER_VALIDATE_EMAIL)||!$description)out(422,['ok'=>false,'error'=>'Complete required fields']);
  $stmt=$db->prepare("SELECT a.event_id FROM bh_event_early_access a JOIN bh_events e ON e.id=a.event_id WHERE a.event_id=? AND a.enabled=1 AND e.status='published' AND NOW()>=a.public_at LIMIT 1");
  $stmt->execute([$event]);if(!$stmt->fetchColumn())out(403,['ok'=>false,'error'=>'Public exhibitor applications are not open']);
  $stmt=$db->prepare("SELECT COUNT(*) FROM bh_event_exhibitor_applications WHERE event_id=? AND contact_email=?");
  $stmt->execute([$event,$email]);if((int)$stmt->fetchColumn()>0)out(409,['ok'=>false,'error'=>'An application already exists for this email']);
  $url=$get('website',1000);
  if($url && (!filter_var($url,FILTER_VALIDATE_URL)||!in_array(parse_url($url,PHP_URL_SCHEME),['https','http'],true)))out(422,['ok'=>false,'error'=>'Invalid website']);
  $stmt=$db->prepare("INSERT INTO bh_event_exhibitor_applications (event_id,business_name,contact_name,contact_email,contact_phone,website,business_description,stall_requirements,participation_notes) VALUES (?,?,?,?,?,?,?,?,?)");
  $stmt->execute([$event,$name,$contact,$email,$get('contact_phone',60),$url,$description,$get('stall_requirements',2000),$get('participation_notes',2000)]);
  out(201,['ok'=>true,'message'=>'Application received for review']);
 }
 if($action==='settings' && $method==='GET'){
  adminOnly();$rows=$db->query("SELECT * FROM bh_event_early_access ORDER BY event_id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
  out(200,['ok'=>true,'settings'=>$rows]);
 }
 if($action==='settings' && $method==='POST'){
  adminOnly();$body=json_decode((string)file_get_contents('php://input'),true);
  if(!is_array($body))out(400,['ok'=>false,'error'=>'Invalid JSON']);
  $id=filter_var($body['event_id']??null,FILTER_VALIDATE_INT);$enabled=empty($body['enabled'])?0:1;
  $opens=(string)($body['opens_at']??'');$public=(string)($body['public_at']??'');
  foreach([$opens,$public] as $date)if(!DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$date))out(422,['ok'=>false,'error'=>'Use YYYY-MM-DD HH:MM:SS']);
  if(!$id||$opens>=$public)out(422,['ok'=>false,'error'=>'Invalid event or access window']);
  $stmt=$db->prepare("INSERT INTO bh_event_early_access (event_id,enabled,opens_at,public_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),opens_at=VALUES(opens_at),public_at=VALUES(public_at)");
  $stmt->execute([$id,$enabled,$opens,$public]);out(200,['ok'=>true]);
 }
 if($action==='applications' && $method==='GET'){
  adminOnly();$rows=$db->query("SELECT id,event_id,business_name,contact_name,contact_email,status,created_at FROM bh_event_exhibitor_applications ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);out(200,['ok'=>true,'applications'=>$rows]);
 }
 out(404,['ok'=>false,'error'=>'Unknown action']);
} catch(Throwable $e){error_log('[BH event exhibitors] '.$e->getMessage());out(500,['ok'=>false,'error'=>'Service unavailable']);}
