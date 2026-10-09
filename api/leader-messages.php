<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/db.php';
if(session_status()!==PHP_SESSION_ACTIVE){session_name('BUBBAHUBSESSID');session_start();}
function inboxReply(int $code,array $body):never{http_response_code($code);echo json_encode($body);exit;}
try{
$db=bh_mysql();$uid=(int)($_SESSION['bh_user_id']??0);
if(!$uid)inboxReply(401,['ok'=>false,'message'=>'Please sign in.']);
$q=$db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1");$q->execute([$uid]);$user=$q->fetch(PDO::FETCH_ASSOC);
if(!$user||$user['status']!=='active')inboxReply(403,['ok'=>false,'message'=>'Account unavailable.']);
$db->exec("CREATE TABLE IF NOT EXISTS bh_leader_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,organiser_id BIGINT UNSIGNED NOT NULL,sender_user_id BIGINT UNSIGNED NOT NULL,subject VARCHAR(180) NOT NULL,body TEXT NOT NULL,category VARCHAR(30) NOT NULL DEFAULT 'help',reply TEXT NULL,replied_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_leader_messages_organiser(organiser_id,created_at),INDEX idx_leader_messages_sender(sender_user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))$body=[];
$action=(string)($body['action']??'');
if($_SERVER['REQUEST_METHOD']==='POST'&&$action==='send'){
 $oid=(int)($body['organiser_id']??0);$slug=trim((string)($body['organiser_slug']??''));if(!$oid&&$slug!==''){$lookup=$db->prepare("SELECT id FROM bh_organisers WHERE slug=? LIMIT 1");$lookup->execute([$slug]);$oid=(int)$lookup->fetchColumn();}$subject=trim((string)($body['subject']??''));$message=trim((string)($body['message']??''));$category=(string)($body['category']??'help');
 if($oid<1||$subject===''||$message===''||mb_strlen($subject)>180||mb_strlen($message)>5000||!in_array($category,['help','booking'],true))inboxReply(422,['ok'=>false,'message'=>'Enter a valid recipient, subject and message.']);
 $q=$db->prepare("SELECT id,email FROM bh_organisers WHERE id=? LIMIT 1");$q->execute([$oid]);$org=$q->fetch(PDO::FETCH_ASSOC);if(!$org)inboxReply(404,['ok'=>false,'message'=>'Organiser not found.']);
 $q=$db->prepare("INSERT INTO bh_leader_messages(organiser_id,sender_user_id,subject,body,category) VALUES(?,?,?,?,?)");$q->execute([$oid,$uid,$subject,$message,$category]);
 inboxMail((string)$org['email'],'New Bubba Hub message: '.$subject,"A family has sent you a new ".($category==='booking'?'booking enquiry':'help enquiry').". Sign in to Bubba Hub > Leader > My Messages to view and reply.\n\n".$message);
 inboxReply(201,['ok'=>true,'message'=>'Your message has been sent.']);
}
if($user['role']!=='leader')inboxReply(403,['ok'=>false,'message'=>'Leader access required.']);
$q=$db->prepare("SELECT id,email FROM bh_organisers WHERE user_id=? LIMIT 1");$q->execute([$uid]);$org=$q->fetch(PDO::FETCH_ASSOC);
if(!$org){$q=$db->prepare("SELECT id,email FROM bh_organisers WHERE email=? LIMIT 1");$q->execute([$user['email']]);$org=$q->fetch(PDO::FETCH_ASSOC);}
if(!$org)inboxReply(403,['ok'=>false,'message'=>'Organiser profile required.']);$oid=(int)$org['id'];
if($_SERVER['REQUEST_METHOD']==='GET'){
 $count=$db->prepare("SELECT COUNT(*) FROM bh_leader_messages WHERE organiser_id=? AND replied_at IS NULL");$count->execute([$oid]);$unread=(int)$count->fetchColumn();
 $q=$db->prepare("SELECT m.id,m.subject,m.body,m.category,m.reply,m.replied_at,m.created_at,u.email AS sender_email FROM bh_leader_messages m JOIN bh_users u ON u.id=m.sender_user_id WHERE m.organiser_id=? ORDER BY m.created_at DESC LIMIT 100");$q->execute([$oid]);inboxReply(200,['ok'=>true,'messages'=>$q->fetchAll(PDO::FETCH_ASSOC),'unanswered_count'=>$unread]);
}
if($_SERVER['REQUEST_METHOD']==='POST'&&$action==='reply'){
 $id=(int)($body['id']??0);$reply=trim((string)($body['reply']??''));if(!$id||$reply===''||mb_strlen($reply)>5000)inboxReply(422,['ok'=>false,'message'=>'Write a reply of up to 5000 characters.']);
 $q=$db->prepare("SELECT m.id,m.subject,u.email FROM bh_leader_messages m JOIN bh_users u ON u.id=m.sender_user_id WHERE m.id=? AND m.organiser_id=?");$q->execute([$id,$oid]);$msg=$q->fetch(PDO::FETCH_ASSOC);if(!$msg)inboxReply(404,['ok'=>false,'message'=>'Message not found.']);
 $q=$db->prepare("UPDATE bh_leader_messages SET reply=?,replied_at=NOW() WHERE id=? AND organiser_id=?");$q->execute([$reply,$id,$oid]);
 $sent=inboxMail((string)$msg['email'],'Re: '.$msg['subject'],"A class leader has replied to your Bubba Hub enquiry:\n\n".$reply."\n\nYou can contact the organiser through Bubba Hub.");
 inboxReply(200,['ok'=>true,'email_sent'=>$sent,'message'=>$sent?'Reply saved and emailed to the family.':'Reply saved, but email delivery could not be confirmed.']);
}
inboxReply(405,['ok'=>false,'message'=>'Unsupported action.']);
}catch(Throwable $e){error_log('Leader inbox: '.$e->getMessage());inboxReply(500,['ok'=>false,'message'=>'Messaging is temporarily unavailable.']);}
function inboxMail(string $to,string $subject,string $body):bool{
 if(!filter_var($to,FILTER_VALIDATE_EMAIL))return false;
 $wp=dirname(__DIR__).'/wp-load.php';
 if(is_file($wp)){ob_start();require_once $wp;ob_end_clean();if(function_exists('wp_mail'))return (bool)wp_mail($to,$subject,$body);}
 return false;
}
