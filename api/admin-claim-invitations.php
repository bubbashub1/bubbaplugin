<?php
declare(strict_types=1);
/** Claim invitation management. Sending remains disabled unless explicitly enabled. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/admin-session-guard.php';
require_once __DIR__.'/db.php';
function respond(int $code,array $data):never {http_response_code($code);echo json_encode($data);exit;}

try {
$db=bh_mysql();
// Schema is installed by the migration, never during a user request.
$_SESSION['bh_claim_invite_csrf']??=bin2hex(random_bytes(32));
if($_SERVER['REQUEST_METHOD']==='GET') {
 foreach (['bh_claim_invitations','bh_listing_claims'] as $table) {
  if(!$db->query('SHOW TABLES LIKE '.$db->quote($table))->fetchColumn())respond(503,['ok'=>false,'error'=>'Claim database table missing: '.$table]);
 }

 $rows=$db->query("SELECT a.id,a.title,a.contact_email AS recipient,i.status AS invitation_status,i.sent_at
 FROM bh_activities a
 LEFT JOIN bh_claim_invitations i ON i.activity_id=a.id AND i.recipient COLLATE utf8mb4_unicode_ci = a.contact_email COLLATE utf8mb4_unicode_ci
 WHERE a.contact_email IS NOT NULL AND a.contact_email<>'' AND NOT EXISTS (
 SELECT 1 FROM bh_listing_claims c WHERE c.activity_id=a.id AND c.status IN ('approved','pending')
 ) ORDER BY CASE WHEN a.id=36 THEN 0 ELSE 1 END,a.id DESC LIMIT 250")->fetchAll(PDO::FETCH_ASSOC);
 $claims=$db->query("SELECT c.id,c.activity_id,c.user_id,c.organiser_id,c.status,c.created_at,a.title,u.email FROM bh_listing_claims c JOIN bh_activities a ON a.id=c.activity_id JOIN bh_users u ON u.id=c.user_id WHERE c.status='pending' ORDER BY c.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
 respond(200,['ok'=>true,'data'=>$rows,'claims'=>$claims,'csrf'=>$_SESSION['bh_claim_invite_csrf']]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')respond(405,['ok'=>false,'error'=>'Method not allowed']);
$input=json_decode((string)file_get_contents('php://input'),true)?:[];
if(!hash_equals((string)$_SESSION['bh_claim_invite_csrf'],(string)($input['csrf']??'')))respond(403,['ok'=>false,'error'=>'CSRF validation failed']);
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
$origin=rtrim((string)(getenv('BH_PUBLIC_BASE_URL')?:'https://bubbahub.co.uk'),'/');
if(!in_array($origin,['https://bubbahub.co.uk','https://staging.bubbahub.co.uk'],true))respond(500,['ok'=>false,'error'=>'Invalid public site URL']);
$next='/claim-invitation.html?activity_id='.rawurlencode((string)$id).'&invite='.rawurlencode($token);
$url=$origin.'/auth.html?context=leader&next='.rawurlencode($next);
$subject='Your activity is now on Bubba Hub – Claim your free listing!';
$body="Hello,\n\nWe've added ". $a['title'] ." to Bubba Hub South West. To claim your free listing, sign in or create a free Bubba Hub Class Leader account using the link below, then submit your claim for admin approval.\n\nView listing: ".$origin."/activity.html?id=".$id."\nClaim listing: ".$url."\n\nThanks,\nBubba Hub South West";
$pilot=(($input['pilot_test']??false)===true);
$pilotAllowed=$pilot && (string)$a['title']==='Bubba and Me Cranbrook' && strtolower(trim((string)$a['email']))==='contact@bubbahub.co.uk' && $origin==='https://bubbahub.co.uk';
$normalAllowed=!$pilot && getenv('BH_CLAIM_INVITES_ENABLED')==='1' && (getenv('BH_CLAIM_INVITES_ALL_ENABLED')==='1' || ((string)$a['title']==='Bubba and Me Cranbrook' && strtolower(trim((string)$a['email']))==='contact@bubbahub.co.uk'));
if(!$pilotAllowed && !$normalAllowed)respond(409,['ok'=>false,'error'=>'Sending is disabled. Only the confirmed Cranbrook pilot to contact@bubbahub.co.uk is available.']);
require_once __DIR__.'/mailer.php';
$safeTitle=htmlspecialchars((string)$a['title'],ENT_QUOTES,'UTF-8');
$safeClaimUrl=htmlspecialchars($url,ENT_QUOTES,'UTF-8');
$safeListingUrl=htmlspecialchars($origin.'/activity.html?id='.$id,ENT_QUOTES,'UTF-8');
$safeLogoUrl=htmlspecialchars($origin.'/wp-content/uploads/logo/logoheader.png',ENT_QUOTES,'UTF-8');
$html=<<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Claim your Bubba Hub listing</title></head>
<body style="margin:0;padding:0;background:#f2f7f0;color:#173e30;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f2f7f0;"><tr><td align="center" style="padding:32px 16px;">
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:560px;background:#ffffff;border:1px solid #dce9df;border-radius:20px;overflow:hidden;">
<tr><td align="center" style="padding:28px 28px 20px;background:#e5f4ed;"><img src="$safeLogoUrl" width="180" alt="Bubba Hub South West" style="max-width:100%;height:auto;border:0;display:block;"></td></tr>
<tr><td style="padding:32px 32px 36px;">
<p style="font-size:14px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#247a67;margin:0 0 12px;">Your activity is on Bubba Hub</p>
<h1 style="font-size:27px;line-height:1.25;color:#124b3b;margin:0 0 18px;">Claim your free listing</h1>
<p style="font-size:16px;line-height:1.65;margin:0 0 16px;">Hello,</p>
<p style="font-size:16px;line-height:1.65;margin:0 0 16px;">We've added <strong>$safeTitle</strong> to Bubba Hub South West so local families can discover your activity.</p>
<p style="font-size:16px;line-height:1.65;margin:0 0 26px;">To manage your listing, you'll need a free Bubba Hub Class Leader account. Select the button below, then sign in or create your Class Leader account if you're new to Bubba Hub. Once signed in, submit your claim for our team to review before we give you access to manage the listing.</p>
<table role="presentation" cellpadding="0" cellspacing="0"><tr><td bgcolor="#07563f" style="border-radius:12px;"><a href="$safeClaimUrl" style="display:inline-block;padding:16px 25px;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:12px;">Claim your free listing</a></td></tr></table>
<p style="margin:22px 0 0;font-size:15px;"><a href="$safeListingUrl" style="color:#08735e;text-decoration:underline;">View your listing first</a></p>
<p style="margin:30px 0 0;font-size:15px;line-height:1.6;">Thanks,<br><strong>Bubba Hub South West</strong></p>
</td></tr></table>
<p style="max-width:560px;margin:18px 0 0;font-size:12px;line-height:1.6;color:#60796e;text-align:center;">You received this email because your contact address is listed for this activity on Bubba Hub South West. If you don't manage this activity, you can ignore this invitation.</p>
</td></tr></table></body></html>
HTML;
// Persist the token before sending, so an emailed link is never missing from storage.
$q=$db->prepare("INSERT INTO bh_claim_invitations(activity_id,recipient,token_hash,status,sent_at,expires_at) VALUES (?,?,?,'sending',NULL,DATE_ADD(NOW(),INTERVAL 14 DAY)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),status='sending',sent_at=NULL,expires_at=VALUES(expires_at)");
$q->execute([$id,$a['email'],$hash]);
if(!bh_send_smtp_mail($a['email'],$subject,$html,$body,'')){
 $failed=$db->prepare("UPDATE bh_claim_invitations SET status='failed' WHERE activity_id=? AND recipient=? AND token_hash=? AND status='sending'");
 $failed->execute([$id,$a['email'],$hash]);
 respond(502,['ok'=>false,'error'=>'Mail transport rejected message']);
}
$q=$db->prepare("UPDATE bh_claim_invitations SET status='sent',sent_at=NOW() WHERE activity_id=? AND recipient=? AND token_hash=? AND status='sending'");
$q->execute([$id,$a['email'],$hash]);
respond(200,['ok'=>true,'message'=>'Invitation sent']);
} catch(Throwable $e){error_log('Claim invitation error: '.$e->getMessage());$message='Invitation operation failed';if($e instanceof PDOException){if((string)$e->getCode()==='42S22')$message='Claim database column mismatch';elseif((string)$e->getCode()==='42S02')$message='Claim database table missing';}respond(500,['ok'=>false,'error'=>$message]);}
