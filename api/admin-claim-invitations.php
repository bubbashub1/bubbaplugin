<?php
declare(strict_types=1);
/** Staging claim invitation management. Enable only after SMTP and claim-flow QA. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
session_start();
function respond(int $code,array $data):never {http_response_code($code);echo json_encode($data);exit;}
if(empty($_SESSION['bh_admin_authenticated']))respond(401,['ok'=>false,'error'=>'Admin authentication required']);
require_once __DIR__.'/db.php';
try {
$db=bh_mysql();
$db->exec("CREATE TABLE IF NOT EXISTS bh_claim_invitations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, activity_id BIGINT UNSIGNED NOT NULL,
 recipient VARCHAR(190) NOT NULL, token_hash CHAR(64) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending', sent_at DATETIME NULL,
 expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY unique_activity_recipient (activity_id,recipient), KEY status_idx(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$_SESSION['bh_claim_invite_csrf']??=bin2hex(random_bytes(32));
if($_SERVER['REQUEST_METHOD']==='GET') {
 $rows=$db->query("SELECT a.id,a.title,a.contact_email AS recipient,i.status AS invitation_status,i.sent_at
 FROM bh_activities a JOIN bh_organisers o ON o.id=a.organiser_id
 LEFT JOIN bh_claim_invitations i ON i.activity_id=a.id AND i.recipient=a.contact_email
 WHERE a.contact_email IS NOT NULL AND a.contact_email<>'' AND NOT EXISTS (
 SELECT 1 FROM bh_listing_claims c WHERE c.activity_id=a.id AND c.status IN ('approved','pending')
 ) ORDER BY a.id DESC LIMIT 250")->fetchAll(PDO::FETCH_ASSOC);
 respond(200,['ok'=>true,'data'=>$rows,'csrf'=>$_SESSION['bh_claim_invite_csrf']]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')respond(405,['ok'=>false,'error'=>'Method not allowed']);
$input=json_decode((string)file_get_contents('php://input'),true)?:[];
if(!hash_equals($_SESSION['bh_claim_invite_csrf'],(string)($input['csrf']??'')))respond(403,['ok'=>false,'error'=>'CSRF validation failed']);
$id=(int)($input['activity_id']??0);
if($id<1)respond(422,['ok'=>false,'error'=>'Invalid listing']);
$q=$db->prepare("SELECT a.id,a.title,a.contact_email AS email FROM bh_activities a WHERE a.id=? LIMIT 1");
$q->execute([$id]);$a=$q->fetch(PDO::FETCH_ASSOC);
if(!$a||!filter_var($a['email'],FILTER_VALIDATE_EMAIL))respond(422,['ok'=>false,'error'=>'Missing valid organiser email']);
$q=$db->prepare("SELECT COUNT(*) FROM bh_listing_claims WHERE activity_id=? AND status IN ('approved','pending')");
$q->execute([$id]);if((int)$q->fetchColumn()>0)respond(409,['ok'=>false,'error'=>'Claim already pending or approved']);
$q=$db->prepare("SELECT status,sent_at FROM bh_claim_invitations WHERE activity_id=? AND recipient=?");
$q->execute([$id,$a['email']]);$prior=$q->fetch(PDO::FETCH_ASSOC);
if($prior && $prior['sent_at'] && strtotime($prior['sent_at'])>time()-7*86400)respond(409,['ok'=>false,'error'=>'Invitation already sent within seven days']);
$token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
$url='https://staging.bubbahub.co.uk/auth.html?context=leader&claim_activity='.rawurlencode((string)$id).'&invite='.rawurlencode($token);
$subject='Your activity is on Bubba Hub – review and claim it';
$body="Hello,\n\nWe've added ". $a['title'] ." to Bubba Hub South West. Please review your listing and claim it for free.\n\nView listing: https://staging.bubbahub.co.uk/activity.html?id=".$id."\nClaim listing: ".$url."\n\nThanks,\nBubba Hub South West";
if(getenv('BH_CLAIM_INVITES_ENABLED')!=='1')respond(409,['ok'=>false,'error'=>'Sending disabled. Set BH_CLAIM_INVITES_ENABLED=1 after staging verification.']);
if(!mail($a['email'],$subject,$body,"From: Bubba Hub South West <noreply@bubbahub.co.uk>\r\nContent-Type: text/plain; charset=UTF-8"))respond(502,['ok'=>false,'error'=>'Mail transport rejected message']);
$q=$db->prepare("INSERT INTO bh_claim_invitations(activity_id,recipient,token_hash,status,sent_at,expires_at) VALUES (?,?,?,'sent',NOW(),DATE_ADD(NOW(),INTERVAL 14 DAY)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),status='sent',sent_at=NOW(),expires_at=VALUES(expires_at)");
$q->execute([$id,$a['email'],$hash]);
respond(200,['ok'=>true,'message'=>'Invitation sent']);
} catch(Throwable $e){error_log('Claim invitation error: '.$e->getMessage());respond(500,['ok'=>false,'error'=>'Invitation operation failed']);}
