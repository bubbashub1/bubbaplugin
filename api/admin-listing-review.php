<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');
session_start();
function bh_lcr_reply(int $status,array $data): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if(empty($_SESSION['bh_admin_authenticated'])) bh_lcr_reply(401,['ok'=>false,'error'=>'Admin login required.']);
try {
 require_once __DIR__.'/db.php'; $db=bh_mysql();
 foreach(['bh_listing_check_findings','bh_activities'] as $table){$q=$db->prepare('SHOW TABLES LIKE ?');$q->execute([$table]);if(!$q->fetchColumn())bh_lcr_reply(503,['ok'=>false,'error'=>'Listing Checker database migration is required.']);}
 $_SESSION['bh_listing_review_csrf']??=bin2hex(random_bytes(32));
 $method=$_SERVER['REQUEST_METHOD']??'GET';
 if($method==='GET'){
  $status=(string)($_GET['status']??'new');$allowed=['all','new','follow_up','approved','rejected','correct','incorrect'];
  if(!in_array($status,$allowed,true))$status='new';
  $limit=max(1,min(100,(int)($_GET['limit']??50)));$offset=max(0,min(100000,(int)($_GET['offset']??0)));
  $where=$status==='all'?'':' WHERE f.review_status=?';
  $cols=$db->query('SHOW COLUMNS FROM bh_activities')->fetchAll(PDO::FETCH_COLUMN);$title=in_array('title',$cols,true)?'a.title':'NULL';$town=in_array('town',$cols,true)?'a.town':'NULL';
  $sql="SELECT f.id,f.check_type,f.activity_id,f.term_id,f.candidate_json,f.finding_type,f.confidence,f.source_url,f.checked_at,f.review_status,".$title." AS activity_title,".$town." AS activity_town FROM bh_listing_check_findings f LEFT JOIN bh_activities a ON a.id=f.activity_id".$where." ORDER BY f.checked_at DESC,f.id DESC LIMIT ".$limit." OFFSET ".$offset;
  $stmt=$db->prepare($sql);$stmt->execute($status==='all'?[]:[$status]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach($rows as &$row){$row['candidate']=json_decode((string)$row['candidate_json'],true)?:[];unset($row['candidate_json']);}unset($row);
  $counts=[];foreach($db->query('SELECT review_status,COUNT(*) AS total FROM bh_listing_check_findings GROUP BY review_status')->fetchAll(PDO::FETCH_ASSOC) as $r)$counts[$r['review_status']]=(int)$r['total'];
  bh_lcr_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_listing_review_csrf'],'items'=>$rows,'counts'=>$counts,'limit'=>$limit,'offset'=>$offset]);
 }
 if($method!=='POST')bh_lcr_reply(405,['ok'=>false,'error'=>'Method not allowed']);
  $origin=(string)($_SERVER['HTTP_ORIGIN']??'');$host=(string)($_SERVER['HTTP_HOST']??'');
  if($origin!==''&&(!filter_var($origin,FILTER_VALIDATE_URL)||strcasecmp((string)parse_url($origin,PHP_URL_HOST),preg_replace('/:\\d+$/','',$host))!==0)) bh_lcr_reply(403,['ok'=>false,'error'=>'Cross-origin request blocked']);
  if(in_array((string)($_SERVER['HTTP_SEC_FETCH_SITE']??''),['cross-site'],true)) bh_lcr_reply(403,['ok'=>false,'error'=>'Cross-site request blocked']);
  if((int)($_SERVER['CONTENT_LENGTH']??0)>65536) bh_lcr_reply(413,['ok'=>false,'error'=>'Request too large']);
 $input=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($input)||!hash_equals((string)$_SESSION['bh_listing_review_csrf'],(string)($input['csrf']??'')))bh_lcr_reply(403,['ok'=>false,'error'=>'Security check failed. Refresh and try again.']);
 $id=(int)($input['id']??0);$status=(string)($input['review_status']??'');
 if($id<1||!in_array($status,['new','correct','incorrect','follow_up','approved','rejected'],true))bh_lcr_reply(422,['ok'=>false,'error'=>'Invalid review update.']);
 $stmt=$db->prepare('UPDATE bh_listing_check_findings SET review_status=?,reviewed_at=NOW() WHERE id=?');$stmt->execute([$status,$id]);
 if(!$stmt->rowCount()){$check=$db->prepare('SELECT id FROM bh_listing_check_findings WHERE id=?');$check->execute([$id]);if(!$check->fetchColumn())bh_lcr_reply(404,['ok'=>false,'error'=>'Finding not found.']);}
 bh_lcr_reply(200,['ok'=>true,'message'=>'Review status saved.']);
} catch(Throwable $e){error_log('Listing review queue: '.$e->getMessage());bh_lcr_reply(500,['ok'=>false,'error'=>'Unable to load or update the review queue.']);}
