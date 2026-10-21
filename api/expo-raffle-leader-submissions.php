<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function reply(int $code,array $data): never {http_response_code($code);echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(session_status()!==PHP_SESSION_ACTIVE){session_name('BUBBAHUBSESSID');session_start();}
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST');reply(405,['ok'=>false,'error'=>'method_not_allowed']);}
if(empty($_SESSION['bh_user_id'])||empty($_SESSION['bh_leader_authenticated']))reply(401,['ok'=>false,'error'=>'leader_login_required']);
try{
 require_once __DIR__.'/db.php';
 $db=bh_mysql();$uid=(int)$_SESSION['bh_user_id'];
 $q=$db->prepare("SELECT id FROM bh_users WHERE id=? AND role='leader' AND status='active' LIMIT 1");$q->execute([$uid]);
 if(!$q->fetchColumn())reply(403,['ok'=>false,'error'=>'active_leader_required']);
 $q=$db->prepare('SELECT id,name FROM bh_organisers WHERE user_id=? LIMIT 1');$q->execute([$uid]);$org=$q->fetch(PDO::FETCH_ASSOC);
 if(!$org)reply(403,['ok'=>false,'error'=>'linked_organiser_required']);
 if($method==='GET'){
  if(empty($_SESSION['bh_expo_donation_csrf']))$_SESSION['bh_expo_donation_csrf']=bin2hex(random_bytes(24));
  $q=$db->prepare('SELECT id,title,description,image_url,prize_value,redemption_terms,status,review_note,created_at FROM bh_expo_raffle_submissions WHERE event_key=? AND user_id=? AND organiser_id=? ORDER BY created_at DESC LIMIT 50');
  $q->execute(['bubba-me-2027',$uid,(int)$org['id']]);
  reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_expo_donation_csrf'],'organiser_name'=>$org['name']??'','submissions'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
 }
 $host=strtolower((string)parse_url('https://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST));
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');$ref=(string)($_SERVER['HTTP_REFERER']??'');$source=$origin!==''?$origin:$ref;
 if(parse_url($source,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($source,PHP_URL_HOST))!==$host)reply(403,['ok'=>false,'error'=>'invalid_origin']);
 if(empty($_SESSION['bh_expo_donation_csrf'])||!hash_equals($_SESSION['bh_expo_donation_csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))reply(403,['ok'=>false,'error'=>'invalid_csrf']);
 $raw=file_get_contents('php://input');if(strlen($raw)>15000)reply(413,['ok'=>false,'error'=>'request_too_large']);
 $v=json_decode($raw,true);if(!is_array($v))reply(400,['ok'=>false,'error'=>'invalid_json']);
 $title=trim((string)($v['title']??''));$desc=trim((string)($v['description']??''));$terms=trim((string)($v['redemption_terms']??''));
 $value=trim((string)($v['prize_value']??''));$contact=trim((string)($v['fulfilment_contact']??''));$image=trim((string)($v['image_url']??''));
 if($title===''||mb_strlen($title)>160||mb_strlen($desc)>3000||mb_strlen($terms)>2000||mb_strlen($value)>60||mb_strlen($contact)>190)reply(422,['ok'=>false,'error'=>'invalid_fields']);
 if($image!==''&&(strlen($image)>500||!filter_var($image,FILTER_VALIDATE_URL)||parse_url($image,PHP_URL_SCHEME)!=='https'))reply(422,['ok'=>false,'error'=>'invalid_image_url']);
 if(empty($v['public_donor_consent']))reply(422,['ok'=>false,'error'=>'donor_consent_required']);
 $q=$db->prepare("INSERT INTO bh_expo_raffle_submissions (event_key,user_id,organiser_id,title,description,image_url,prize_value,redemption_terms,fulfilment_contact,public_donor_consent,status) VALUES (?,?,?,?,?,?,?,?,?,1,'pending')");
 $q->execute(['bubba-me-2027',$uid,(int)$org['id'],$title,$desc,$image?:null,$value,$terms,$contact]);
 reply(201,['ok'=>true,'id'=>(int)$db->lastInsertId(),'status'=>'pending']);
}catch(Throwable $e){error_log('Expo leader donation: '.$e->getMessage());reply(503,['ok'=>false,'error'=>'submissions_unavailable']);}
