<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_name('BUBBAHUB_ADMINSESSID');session_start();
function dar(int $c,array $d):never{http_response_code($c);echo json_encode($d,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
$map=['business'=>'bh_directory_businesses','childcare'=>'bh_directory_childcare','place'=>'bh_directory_places','school_club'=>'bh_directory_school_clubs'];
if(empty($_SESSION['bh_admin_authenticated']))dar(401,['ok'=>false,'error'=>'admin_login_required']);
require_once __DIR__.'/db.php';$db=bh_mysql();
$exists=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_directory_record_reviews'");if(!(int)$exists->fetchColumn())dar(503,['ok'=>false,'error'=>'schema_not_installed','message'=>'The consolidated Directory schema has not been installed.']);
if($_SERVER['REQUEST_METHOD']==='GET'){
 $_SESSION['bh_directory_review_csrf']??=bin2hex(random_bytes(32));$all=[];
 foreach($map as $key=>$table){$q=$db->query("SELECT id,title,category,summary,description,town,region,status,created_at FROM ".$table." WHERE status IN ('pending','draft') ORDER BY created_at ASC LIMIT 150");foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$row['section']=$key;$all[]=$row;}}
 usort($all,static fn($a,$b)=>strcmp((string)$a['created_at'],(string)$b['created_at']));dar(200,['ok'=>true,'csrf'=>$_SESSION['bh_directory_review_csrf'],'records'=>$all]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')dar(405,['ok'=>false,'error'=>'method_not_allowed']);
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);$origin=(string)($_SERVER['HTTP_ORIGIN']??'');if(!$origin||parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)dar(403,['ok'=>false,'error'=>'invalid_origin']);
$b=json_decode((string)file_get_contents('php://input'),true);if(!is_array($b)||empty($_SESSION['bh_directory_review_csrf'])||!is_string($b['csrf']??null)||!hash_equals($_SESSION['bh_directory_review_csrf'],$b['csrf']))dar(403,['ok'=>false,'error'=>'invalid_csrf']);
$section=(string)($b['section']??'');$id=filter_var($b['record_id']??null,FILTER_VALIDATE_INT);$decision=(string)($b['decision']??'');$note=trim((string)($b['note']??''));if(!isset($map[$section])||!$id||$id<1||!in_array($decision,['approved','rejected','returned_for_edits'],true)||strlen($note)>2000)dar(422,['ok'=>false,'error'=>'invalid_review']);
$status=$decision==='approved'?'published':($decision==='rejected'?'rejected':'draft');$table=$map[$section];
try{$db->beginTransaction();$q=$db->prepare("SELECT id,status FROM ".$table." WHERE id=? FOR UPDATE");$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row||!in_array($row['status'],['pending','draft'],true)){$db->rollBack();dar(409,['ok'=>false,'error'=>'record_not_reviewable']);}
$q=$db->prepare("UPDATE ".$table." SET status=?,reviewed_at=NOW(),reviewed_by_user_id=?,review_note=? WHERE id=?");$q->execute([$status,(int)($_SESSION['bh_user_id']??0)?:null,$note?:null,$id]);
$q=$db->prepare("INSERT INTO bh_directory_record_reviews (section_key,record_id,reviewer_user_id,decision,note) VALUES (?,?,?,?,?)");$q->execute([$section,$id,(int)($_SESSION['bh_user_id']??0)?:null,$decision,$note?:null]);$db->commit();dar(200,['ok'=>true,'status'=>$status,'message'=>'Directory review saved.']);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Directory review: '.$e->getMessage());dar(500,['ok'=>false,'error'=>'review_failed']);}
