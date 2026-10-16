<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');
session_start();
function review_reply(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))review_reply(401,['ok'=>false,'error'=>'admin_login_required']);
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')review_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
$host=strtolower((string)($_SERVER['HTTP_HOST']??''));
if($origin===''||strtolower((string)parse_url($origin,PHP_URL_HOST))!==explode(':',$host)[0]||parse_url($origin,PHP_URL_SCHEME)!=='https')review_reply(403,['ok'=>false,'error'=>'invalid_origin']);
if(!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json'))review_reply(415,['ok'=>false,'error'=>'json_required']);
$input=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($input))review_reply(422,['ok'=>false,'error'=>'invalid_json']);
if(!isset($_SESSION['bh_approval_csrf'])||!is_string($input['csrf']??null)||!hash_equals($_SESSION['bh_approval_csrf'],$input['csrf']))review_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
$type=(string)($input['type']??'');$id=filter_var($input['id']??null,FILTER_VALIDATE_INT);$decision=(string)($input['decision']??'');$note=trim((string)($input['note']??''));
if(!in_array($type,['leaders','listings'],true)||!$id||$id<1||!in_array($decision,['reviewed','request_changes','reject_recommendation'],true)||mb_strlen($note)>2000||($decision!=='reviewed'&&$note===''))review_reply(422,['ok'=>false,'error'=>'invalid_review']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $db->exec("CREATE TABLE IF NOT EXISTS bh_admin_approval_reviews (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, item_type VARCHAR(20) NOT NULL, item_id BIGINT UNSIGNED NOT NULL, decision VARCHAR(40) NOT NULL, note TEXT NOT NULL, admin_session_hash CHAR(64) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX item_history (item_type,item_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $sql=$type==='leaders'?"SELECT 1 FROM bh_users WHERE id=? AND role='leader' AND status='pending' LIMIT 1":"SELECT 1 FROM bh_activities WHERE id=? AND status='draft' LIMIT 1";
 $stmt=$db->prepare($sql);$stmt->execute([$id]);if(!$stmt->fetchColumn())review_reply(409,['ok'=>false,'error'=>'item_no_longer_pending']);
 $stmt=$db->prepare("INSERT INTO bh_admin_approval_reviews (item_type,item_id,decision,note,admin_session_hash) VALUES (?,?,?,?,?)");
 $stmt->execute([$type,$id,$decision,$note,hash('sha256',session_id())]);
 review_reply(200,['ok'=>true,'review_id'=>(int)$db->lastInsertId(),'publication_changed'=>false,'notification_sent'=>false]);
}catch(Throwable $e){error_log('Approval review: '.$e->getMessage());review_reply(500,['ok'=>false,'error'=>'review_save_failed']);}
