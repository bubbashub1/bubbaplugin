<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');if(session_status()!==PHP_SESSION_ACTIVE){session_name('BUBBAHUBSESSID');session_start();}
function ldm(int $c,array $d):never{http_response_code($c);echo json_encode($d,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function ldm_table(PDO $db,string $name):bool{$q=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$q->execute([$name]);return (int)$q->fetchColumn()>0;}
$map=['event'=>'bh_directory_events','business'=>'bh_directory_businesses','childcare'=>'bh_directory_childcare','place'=>'bh_directory_places','school_club'=>'bh_directory_school_clubs'];
require_once __DIR__.'/db.php';$db=bh_mysql();
if(empty($_SESSION['bh_user_id']))ldm(401,['ok'=>false,'error'=>'login_required','message'=>'Sign in with your Leader account to continue.']);
$q=$db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1");$q->execute([(int)$_SESSION['bh_user_id']]);$u=$q->fetch(PDO::FETCH_ASSOC);
if(!$u||$u['role']!=='leader'||$u['status']!=='active')ldm(403,['ok'=>false,'error'=>'leader_required']);
$uid=(int)$u['id'];$org=null;
try{$q=$db->prepare("SELECT id FROM bh_organisers WHERE user_id=? LIMIT 1");$q->execute([$uid]);$org=$q->fetchColumn();}catch(Throwable $ignored){}
if(!$org){try{$q=$db->prepare("SELECT COUNT(*) FROM bh_organisers WHERE LOWER(email)=LOWER(?)");$q->execute([$u['email']]);if((int)$q->fetchColumn()===1){$q=$db->prepare("SELECT id FROM bh_organisers WHERE LOWER(email)=LOWER(?) LIMIT 1");$q->execute([$u['email']]);$org=$q->fetchColumn();}}catch(Throwable $ignored){}}
if(!$org)ldm(403,['ok'=>false,'error'=>'organiser_required','message'=>'Your Leader account needs an organisation profile first.']);
if($_SERVER['REQUEST_METHOD']==='GET'){
 $_SESSION['bh_directory_manage_csrf']??=bin2hex(random_bytes(32));$all=[];$missing=[];
 foreach($map as $section=>$table){if(!ldm_table($db,$table)){$missing[]=$section;continue;}$q=$db->prepare("SELECT id,title,category,town,status,created_at,updated_at,review_note FROM ".$table." WHERE organiser_id=? ORDER BY updated_at DESC LIMIT 250");$q->execute([(int)$org]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$row['section']=$section;$all[]=$row;}}
 usort($all,static fn($a,$b)=>strcmp((string)$b['updated_at'],(string)$a['updated_at']));
 ldm(200,['ok'=>true,'csrf'=>$_SESSION['bh_directory_manage_csrf'],'records'=>$all,'schema_ready'=>!$missing,'missing_sections'=>$missing]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')ldm(405,['ok'=>false,'error'=>'method_not_allowed']);
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if(!$origin||parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)ldm(403,['ok'=>false,'error'=>'invalid_origin']);
$b=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($b)||empty($_SESSION['bh_directory_manage_csrf'])||!is_string($b['csrf']??null)||!hash_equals($_SESSION['bh_directory_manage_csrf'],$b['csrf']))ldm(403,['ok'=>false,'error'=>'invalid_csrf']);
$section=(string)($b['section']??'');$id=filter_var($b['record_id']??null,FILTER_VALIDATE_INT);
if(!isset($map[$section])||!$id||$id<1)ldm(422,['ok'=>false,'error'=>'invalid_record']);
$table=$map[$section];
if(!ldm_table($db,$table))ldm(503,['ok'=>false,'error'=>'directory_schema_not_installed','message'=>'This Directory section is not enabled on staging yet.']);
try{$db->beginTransaction();$q=$db->prepare("SELECT id,status FROM ".$table." WHERE id=? AND organiser_id=? FOR UPDATE");$q->execute([$id,(int)$org]);$row=$q->fetch(PDO::FETCH_ASSOC);
if(!$row){$db->rollBack();ldm(404,['ok'=>false,'error'=>'record_not_found']);}
if($row['status']==='published'){ $db->rollBack();ldm(409,['ok'=>false,'error'=>'published_change_requires_review','message'=>'Published entries cannot be changed or removed directly. Please contact Bubba Hub to request an update.']);}
if($row['status']==='archived'){$db->rollBack();ldm(409,['ok'=>false,'error'=>'already_archived']);}
$q=$db->prepare("UPDATE ".$table." SET status='archived',updated_at=NOW() WHERE id=? AND organiser_id=?");$q->execute([$id,(int)$org]);$db->commit();ldm(200,['ok'=>true,'message'=>'Entry archived.']);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Leader Directory manage: '.$e->getMessage());ldm(500,['ok'=>false,'error'=>'manage_failed']);}
