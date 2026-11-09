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
function duplicate_review_reply(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))duplicate_review_reply(401,['ok'=>false,'error'=>'admin_login_required']);
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')duplicate_review_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');$host=strtolower((string)($_SERVER['HTTP_HOST']??''));
if($origin===''||strtolower((string)parse_url($origin,PHP_URL_HOST))!==explode(':',$host)[0]||parse_url($origin,PHP_URL_SCHEME)!=='https')duplicate_review_reply(403,['ok'=>false,'error'=>'invalid_origin']);
if(!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json'))duplicate_review_reply(415,['ok'=>false,'error'=>'json_required']);
$input=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($input))duplicate_review_reply(422,['ok'=>false,'error'=>'invalid_json']);
if(!isset($_SESSION['bh_approval_csrf'])||!is_string($input['csrf']??null)||!hash_equals($_SESSION['bh_approval_csrf'],$input['csrf']))duplicate_review_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
$id=filter_var($input['id']??null,FILTER_VALIDATE_INT);$decision=(string)($input['decision']??'');$reason=trim((string)($input['reason']??''));
if(!$id||$id<1||!in_array($decision,['approve','reject'],true)||mb_strlen($reason)>2000||($decision==='reject'&&$reason===''))duplicate_review_reply(422,['ok'=>false,'error'=>'invalid_decision','message'=>'A rejection reason is required.']);
try{
 require_once __DIR__.'/db.php';require_once __DIR__.'/mailer.php';$db=bh_mysql();
 $db->beginTransaction();
 $q=$db->prepare("SELECT r.id,r.activity_id,r.status,a.title,a.organiser_id FROM bh_duplicate_review_requests r JOIN bh_activities a ON a.id=r.activity_id WHERE r.id=? FOR UPDATE");
 $q->execute([$id]);$item=$q->fetch(PDO::FETCH_ASSOC);
 if(!$item||$item['status']!=='pending'){$db->rollBack();duplicate_review_reply(409,['ok'=>false,'error'=>'review_no_longer_pending']);}
 $activityStatus=$decision==='approve'?'published':'rejected';
 $activityUpdate=$db->prepare("UPDATE bh_activities SET status=? WHERE id=? AND status='pending'");
 $activityUpdate->execute([$activityStatus,(int)$item['activity_id']]);
 if($activityUpdate->rowCount()!==1){$db->rollBack();duplicate_review_reply(409,['ok'=>false,'error'=>'activity_no_longer_pending']);}
 $db->prepare("UPDATE bh_duplicate_review_requests SET status=?,review_reason=?,reviewed_by_session_hash=?,reviewed_at=UTC_TIMESTAMP() WHERE id=? AND status='pending'")->execute([$decision==='approve'?'approved':'rejected',$reason!==''?$reason:null,hash('sha256',session_id()),$id]);
 $db->commit();
 $notificationSent=false;
 if($decision==='reject'){
  $hasUserLink=false;
  $checkLink=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='user_id'");
  $checkLink->execute();$hasUserLink=(int)$checkLink->fetchColumn()>0;
  if($hasUserLink){$mail=$db->prepare("SELECT COALESCE(NULLIF(u.email,''),NULLIF(o.email,'')) FROM bh_activities a JOIN bh_organisers o ON o.id=a.organiser_id LEFT JOIN bh_users u ON u.id=o.user_id WHERE a.id=? LIMIT 1");}
  else{$mail=$db->prepare("SELECT o.email FROM bh_activities a JOIN bh_organisers o ON o.id=a.organiser_id WHERE a.id=? LIMIT 1");}
  $mail->execute([(int)$item['activity_id']]);$email=(string)$mail->fetchColumn();
  $title=htmlspecialchars((string)$item['title'],ENT_QUOTES,'UTF-8');$safeReason=nl2br(htmlspecialchars($reason,ENT_QUOTES,'UTF-8'));
  $html='<p>Thank you for submitting <strong>'.$title.'</strong> to Bubba Hub.</p><p>After reviewing your request, we have not approved this listing because it appears to duplicate an existing activity.</p><p><strong>Team feedback:</strong><br>'.$safeReason.'</p><p>If you think we have misunderstood, please contact the Bubba Hub team.</p>';
  $plain="Thank you for submitting ".$item['title']." to Bubba Hub.\n\nWe have not approved this listing because it appears to duplicate an existing activity.\n\nTeam feedback: ".$reason."\n\nIf you think we have misunderstood, please contact the Bubba Hub team.";
  $notificationSent=$email!==''&&bh_send_smtp_mail($email,'Update on your Bubba Hub activity submission',$html,$plain);
 }
 duplicate_review_reply(200,['ok'=>true,'decision'=>$decision,'activity_status'=>$activityStatus,'notification_sent'=>$notificationSent,'message'=>$decision==='approve'?'Listing approved and published.':'Listing rejected.'.($notificationSent?' The leader has been emailed.':' Email notification could not be sent; please contact the leader directly.')]);
}catch(Throwable $e){if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();error_log('Duplicate review action: '.$e->getMessage());duplicate_review_reply(500,['ok'=>false,'error'=>'duplicate_review_failed','message'=>'The review could not be completed.']);}
