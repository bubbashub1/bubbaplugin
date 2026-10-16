<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');session_start();
function approval_publish_reply(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))approval_publish_reply(401,['ok'=>false,'error'=>'admin_login_required']);
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')approval_publish_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
if($origin===''||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host||parse_url($origin,PHP_URL_SCHEME)!=='https')approval_publish_reply(403,['ok'=>false,'error'=>'invalid_origin']);
if(!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json'))approval_publish_reply(415,['ok'=>false,'error'=>'json_required']);
$input=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($input)||!is_string($input['csrf']??null)||!isset($_SESSION['bh_approval_csrf'])||!hash_equals($_SESSION['bh_approval_csrf'],$input['csrf']))approval_publish_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
$id=filter_var($input['id']??null,FILTER_VALIDATE_INT);
if(!$id||$id<1||($input['type']??'')!=='listings'||($input['decision']??'')!=='approve')approval_publish_reply(422,['ok'=>false,'error'=>'invalid_decision']);
$confirmation=(string)($input['confirmation']??'');
$note=trim((string)($input['note']??''));
if(mb_strlen($note)>2000)approval_publish_reply(422,['ok'=>false,'error'=>'note_too_long']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $db->beginTransaction();
 $stmt=$db->prepare("SELECT id,title,status,organiser_id FROM bh_activities WHERE id=? FOR UPDATE");
 $stmt->execute([$id]);$item=$stmt->fetch(PDO::FETCH_ASSOC);
 if(!$item||$item['status']!=='draft'){$db->rollBack();approval_publish_reply(409,['ok'=>false,'error'=>'listing_not_draft']);}
 if($confirmation!==$item['title']){$db->rollBack();approval_publish_reply(422,['ok'=>false,'error'=>'title_confirmation_required']);}
 if(trim((string)$item['title'])===''||(int)$item['organiser_id']<1){$db->rollBack();approval_publish_reply(409,['ok'=>false,'error'=>'listing_missing_organiser_or_title']);}
 $stmt=$db->prepare("SELECT 1 FROM bh_organisers WHERE id=? LIMIT 1");$stmt->execute([(int)$item['organiser_id']]);
 if(!$stmt->fetchColumn()){$db->rollBack();approval_publish_reply(409,['ok'=>false,'error'=>'organiser_not_found']);}
 $db->exec("CREATE TABLE IF NOT EXISTS bh_admin_approval_reviews (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,item_type VARCHAR(20) NOT NULL,item_id BIGINT UNSIGNED NOT NULL,decision VARCHAR(40) NOT NULL,note TEXT NOT NULL,admin_session_hash CHAR(64) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX item_history (item_type,item_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $stmt=$db->prepare("UPDATE bh_activities SET status='published' WHERE id=? AND status='draft'");$stmt->execute([$id]);
 if($stmt->rowCount()!==1)throw new RuntimeException('Unexpected listing update count');
 $stmt=$db->prepare("INSERT INTO bh_admin_approval_reviews (item_type,item_id,decision,note,admin_session_hash) VALUES ('listings',?,'approved',?,?)");
 $stmt->execute([$id,$note,hash('sha256',session_id())]);
 $db->commit();
 approval_publish_reply(200,['ok'=>true,'listing_id'=>$id,'status'=>'published','notification_sent'=>false]);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('Approval publish: '.$e->getMessage());approval_publish_reply(500,['ok'=>false,'error'=>'approval_publish_failed']);}
