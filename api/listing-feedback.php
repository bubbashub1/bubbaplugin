<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function reply(int $code,array $body): never {http_response_code($code);echo json_encode($body);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')reply(405,['ok'=>false,'error'=>'Method not allowed']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>12000)reply(413,['ok'=>false,'error'=>'Submission too large']);
$origin=$_SERVER['HTTP_ORIGIN']??'';
if($origin && parse_url($origin,PHP_URL_HOST)!==($_SERVER['HTTP_HOST']??''))reply(403,['ok'=>false,'error'=>'Invalid origin']);
$data=json_decode(file_get_contents('php://input'),true);
if(!is_array($data))reply(400,['ok'=>false,'error'=>'Invalid request']);
if(!empty($data['website']))reply(200,['ok'=>true]);
$type=$data['type']??'';
$id=filter_var($data['activity_id']??null,FILTER_VALIDATE_INT);
$reason=trim((string)($data['reason']??''));
$details=trim((string)($data['details']??''));
$name=trim((string)($data['name']??''));
$email=trim((string)($data['email']??''));
$share=!empty($data['share_contact']);
if(!in_array($type,['report','suggestion'],true)||!$id||$id<1||mb_strlen($reason)>100||mb_strlen($details)<10||mb_strlen($details)>3000||mb_strlen($name)>120||($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)))reply(422,['ok'=>false,'error'=>'Please check your submission']);
if($type==='report'&&!in_array($reason,['Incorrect information','Closed or cancelled','Duplicate listing','Inappropriate content','Other'],true))reply(422,['ok'=>false,'error'=>'Choose a reason']);
if($type==='suggestion'&&!in_array($reason,['Schedule','Price','Venue','Age range','Contact details','Other'],true))reply(422,['ok'=>false,'error'=>'Choose a field']);
try {
 require __DIR__.'/db.php';
 $db=bh_mysql();
 $check=$db->prepare("SELECT id,title,organiser_id FROM bh_activities WHERE id=? AND LOWER(TRIM(status)) IN ('published','publish') LIMIT 1");
 $check->execute([$id]);$activity=$check->fetch();
 if(!$activity)reply(404,['ok'=>false,'error'=>'Listing not found']);
 $db->exec("CREATE TABLE IF NOT EXISTS bh_listing_feedback (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 activity_id BIGINT UNSIGNED NOT NULL,
 type VARCHAR(20) NOT NULL,
 reason VARCHAR(100) NOT NULL,
 details TEXT NOT NULL,
 submitter_name VARCHAR(120) NOT NULL DEFAULT '',
 submitter_email VARCHAR(190) NOT NULL DEFAULT '',
 share_contact TINYINT(1) NOT NULL DEFAULT 0,
 status VARCHAR(30) NOT NULL DEFAULT 'new',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX activity_idx(activity_id), INDEX status_idx(status)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $fingerprint=hash('sha256',($_SERVER['REMOTE_ADDR']??'').':'.($id).':'.$type);
 if(session_status()!==PHP_SESSION_ACTIVE)session_start();
 $last=$_SESSION['bh_feedback_'.$fingerprint]??0;
 if(time()-(int)$last<60)reply(429,['ok'=>false,'error'=>'Please wait before submitting again']);
 $stmt=$db->prepare('INSERT INTO bh_listing_feedback (activity_id,type,reason,details,submitter_name,submitter_email,share_contact) VALUES (?,?,?,?,?,?,?)');
 $stmt->execute([$id,$type,$reason,$details,$name,$email,$share?1:0]);
 $_SESSION['bh_feedback_'.$fingerprint]=time();
 $feedbackId=$db->lastInsertId();
 $subject='[Bubba Hub] New listing '.($type==='report'?'report':'edit suggestion').' #'.$feedbackId;
 $message="Listing: ".$activity['title']."\nActivity ID: $id\nReason: $reason\nDetails:\n$details\n\nReview in admin dashboard. Feedback ID: $feedbackId";
 $headers=['Content-Type: text/plain; charset=UTF-8'];
 $send=static function(string $to,string $subject,string $message,array $headers):void {
   if(function_exists('wp_mail')){wp_mail($to,$subject,$message,$headers);}
   else {mail($to,$subject,$message,implode("\r\n",$headers));}
 };
 // Admin notification. Leader routing is handled separately after identity verification.
 $send('contact@bubbahub.co.uk',$subject,$message,$headers);
 reply(200,['ok'=>true,'id'=>(int)$feedbackId]);
} catch(Throwable $e){error_log('Listing feedback: '.$e->getMessage());reply(500,['ok'=>false,'error'=>'Unable to save feedback right now']);}
