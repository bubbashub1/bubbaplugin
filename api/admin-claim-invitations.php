<?php
declare(strict_types=1);
/** Staging claim invitation management. Enable only after SMTP and claim-flow QA. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/admin-session-guard.php';
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
 FROM bh_activities a
 LEFT JOIN bh_claim_invitations i ON i.activity_id=a.id AND i.recipient=a.contact_email
 WHERE a.contact_email IS NOT NULL AND a.contact_email<>'' AND NOT EXISTS (
 SELECT 1 FROM bh_listing_claims c WHERE c.activity_id=a.id AND c.status IN ('approved','pending')
 ) ORDER BY a.id DESC LIMIT 250")->fetchAll(PDO::FETCH_ASSOC);
 $claims=$db->query("SELECT c.id,c.activity_id,c.user_id,c.organiser_id,c.status,c.created_at,a.title,u.email FROM bh_listing_claims c JOIN bh_activities a ON a.id=c.activity_id JOIN bh_users u ON u.id=c.user_id WHERE c.status='pending' ORDER BY c.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
 respond(200,['ok'=>true,'data'=>$rows,'claims'=>$claims,'csrf'=>$_SESSION['bh_claim_invite_csrf']]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')respond(405,['ok'=>false,'error'=>'Method not allowed']);
$input=json_decode((string)file_get_contents('php://input'),true)?:[];
if(!hash_equals($_SESSION['bh_claim_invite_csrf'],(string)($input['csrf']??'')))respond(403,['ok'=>false,'error'=>'CSRF validation failed']);
if(isset($input['claim_id'])) {
 $claimId=(int)$input['claim_id'];$decision=(string)($input['decision']??'');
 if($claimId<1||!in_array($decision,['approve','reject'],true))respond(422,['ok'=>false,'error'=>'Invalid claim decision']);
 $q=$db->prepare("SELECT c.id,c.activity_id,c.user_id,c.organiser_id,c.status,a.organiser_id AS current_organiser FROM bh_listing_claims c JOIN bh_activities a ON a.id=c.activity_id WHERE c.id=? LIMIT 1");
 $q->execute([$claimId]);$claim=$q->fetch(PDO::FETCH_ASSOC);
 if(!$claim||$claim['status']!=='pending')respond(409,['ok'=>false,'error'=>'Claim no longer pending']);
 if($decision==='approve'){
  if(empty($claim['organiser_id'])||(int)$claim['organiser_id']!==(int)$claim['current_organiser'])respond(409,['ok'=>false,'error'=>'Organiser link needs manual review before approval']);
  $q=$db->prepare("SELECT user_id FROM bh_organisers WHERE id=? LIMIT 1");$q->execute([(int)$claim['organiser_id']]);$owner=$q->fetchColumn();
  if($owner!==false && $owner!==null && (int)$owner>0 && (int)$owner!==(int)$claim['user_id'])respond(409,['ok'=>false,'error'=>'Organiser already belongs to another account']);
 }
 $db->beginTransaction();
 try{
  $q=$db->prepare("UPDATE bh_listing_claims SET status=?,reviewed_at=NOW() WHERE id=? AND status='pending'");
  $q->execute([$decision==='approve'?'approved':'rejected',$claimId]);
  if($q->rowCount()!==1)throw new RuntimeException('Claim changed while reviewing');
  if($decision==='approve'){
   $q=$db->prepare("UPDATE bh_organisers SET user_id=? WHERE id=? AND (user_id IS NULL OR user_id=0 OR user_id=?)");
   $q->execute([(int)$claim['user_id'],(int)$claim['organiser_id'],(int)$claim['user_id']]);
   if($q->rowCount()===0){$check=$db->prepare("SELECT user_id FROM bh_organisers WHERE id=?");$check->execute([(int)$claim['organiser_id']]);if((int)$check->fetchColumn()!==(int)$claim['user_id'])throw new RuntimeException('Unable to link organiser');}
   $q=$db->prepare("UPDATE bh_listing_claims SET status='rejected',reviewed_at=NOW() WHERE activity_id=? AND id<>? AND status='pending'");$q->execute([(int)$claim['activity_id'],$claimId]);
  }
  $q=$db->prepare("UPDATE bh_claim_invitations SET status=? WHERE activity_id=? AND status IN ('sent','pending')");$q->execute([$decision==='approve'?'approved':'rejected',(int)$claim['activity_id']]);
  $db->commit();
 }catch(Throwable $e){$db->rollBack();throw $e;}
 respond(200,['ok'=>true,'message'=>$decision==='approve'?'Claim approved and organiser linked':'Claim rejected']);
}
$id=(int)($input['activity_id']??0);
if($id<1)respond(422,['ok'=>false,'error'=>'Invalid listing']);
if(!empty($input['automatic']))respond(403,['ok'=>false,'error'=>'Automatic sends are not supported by the admin endpoint']);
$q=$db->prepare("SELECT a.id,a.title,a.contact_email AS email FROM bh_activities a WHERE a.id=? LIMIT 1");
$q->execute([$id]);$a=$q->fetch(PDO::FETCH_ASSOC);
if(!$a||!filter_var($a['email'],FILTER_VALIDATE_EMAIL))respond(422,['ok'=>false,'error'=>'Missing valid activity contact email']);
$q=$db->prepare("SELECT COUNT(*) FROM bh_listing_claims WHERE activity_id=? AND status IN ('approved','pending')");
$q->execute([$id]);if((int)$q->fetchColumn()>0)respond(409,['ok'=>false,'error'=>'Claim already pending or approved']);
$q=$db->prepare("SELECT status,sent_at FROM bh_claim_invitations WHERE activity_id=? AND recipient=?");
$q->execute([$id,$a['email']]);$prior=$q->fetch(PDO::FETCH_ASSOC);
if($prior && $prior['sent_at'] && strtotime($prior['sent_at'])>time()-7*86400)respond(409,['ok'=>false,'error'=>'Invitation already sent within seven days']);
$token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
$next='/claim-invitation.html?activity_id='.rawurlencode((string)$id).'&invite='.rawurlencode($token);
$url='https://staging.bubbahub.co.uk/auth.html?context=leader&next='.rawurlencode($next);
$subject='Your activity is now on Bubba Hub – Claim your free listing!';
$body="Hello,\n\nWe've added ". $a['title'] ." to Bubba Hub South West. Please review your listing and claim it for free.\n\nView listing: https://staging.bubbahub.co.uk/activity.html?id=".$id."\nClaim listing: ".$url."\n\nThanks,\nBubba Hub South West";
if(getenv('BH_CLAIM_INVITES_ENABLED')!=='1')respond(409,['ok'=>false,'error'=>'Sending disabled. Set BH_CLAIM_INVITES_ENABLED=1 after staging verification.']);
require_once __DIR__.'/mailer.php';
$html='<p>Hello,</p><p>We have added <strong>'.htmlspecialchars((string)$a['title'],ENT_QUOTES,'UTF-8').'</strong> to Bubba Hub South West.</p><p>Please review your listing and claim it for free.</p><p><a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">Claim your listing</a></p><p>Thanks,<br>Bubba Hub South West</p>';
if(!bh_send_smtp_mail($a['email'],$subject,$html,$body,''))respond(502,['ok'=>false,'error'=>'Mail transport rejected message']);
$q=$db->prepare("INSERT INTO bh_claim_invitations(activity_id,recipient,token_hash,status,sent_at,expires_at) VALUES (?,?,?,'sent',NOW(),DATE_ADD(NOW(),INTERVAL 14 DAY)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),status='sent',sent_at=NOW(),expires_at=VALUES(expires_at)");
$q->execute([$id,$a['email'],$hash]);
respond(200,['ok'=>true,'message'=>'Invitation sent']);
} catch(Throwable $e){error_log('Claim invitation error: '.$e->getMessage());respond(500,['ok'=>false,'error'=>'Invitation operation failed']);}
