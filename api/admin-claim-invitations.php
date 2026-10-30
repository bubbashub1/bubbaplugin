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
$pilotRetest=(($input['pilot_test']??false)===true && ($input['retest']??false)===true && (string)$a['title']==='Bubba and Me Cranbrook' && strtolower(trim((string)$a['email']))==='contact@bubbahub.co.uk');
if($prior && $prior['sent_at'] && strtotime($prior['sent_at'])>time()-($pilotRetest?600:7*86400))respond(409,['ok'=>false,'error'=>$pilotRetest?'Please wait 10 minutes between test emails.':'Invitation already sent within seven days']);
$token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
$origin=rtrim((string)(getenv('BH_PUBLIC_BASE_URL')?:'https://bubbahub.co.uk'),'/');
if(!in_array($origin,['https://bubbahub.co.uk','https://staging.bubbahub.co.uk'],true))respond(500,['ok'=>false,'error'=>'Invalid public site URL']);
$next='/claim-invitation.html?activity_id='.rawurlencode((string)$id).'&invite='.rawurlencode($token);
$url=$origin.'/auth.html?context=leader&next='.rawurlencode($next);
$subject='Your activity is now on Bubba Hub – Claim your free listing!';
$body="Hello,\n\nWe've added ". $a['title'] ." to Bubba Hub South West. Please review your listing and claim it for free.\n\nView listing: ".$origin."/activity.html?id=".$id."\nClaim listing: ".$url."\n\nIf you do not have an account yet, create a free Class Leader account today. Use the claim link above, select Create account, and return to your listing to submit your claim.\n\nThanks,\nBubba Hub South West";
$pilot=(($input['pilot_test']??false)===true);
$pilotAllowed=$pilot && (string)$a['title']==='Bubba and Me Cranbrook' && strtolower(trim((string)$a['email']))==='contact@bubbahub.co.uk' && $origin==='https://bubbahub.co.uk';
$normalAllowed=!$pilot && getenv('BH_CLAIM_INVITES_ENABLED')==='1' && (getenv('BH_CLAIM_INVITES_ALL_ENABLED')==='1' || ((string)$a['title']==='Bubba and Me Cranbrook' && strtolower(trim((string)$a['email']))==='contact@bubbahub.co.uk'));
if(!$pilotAllowed && !$normalAllowed)respond(409,['ok'=>false,'error'=>'Sending is disabled. Only the confirmed Cranbrook pilot to contact@bubbahub.co.uk is available.']);
require_once __DIR__.'/mailer.php';
$safeTitle=htmlspecialchars((string)$a['title'],ENT_QUOTES,'UTF-8');
$safeUrl=htmlspecialchars($url,ENT_QUOTES,'UTF-8');
$safeListing=htmlspecialchars($origin.'/activity.html?id='.$id,ENT_QUOTES,'UTF-8');
$html='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;background:#f6f9f3;font-family:Arial,Helvetica,sans-serif;color:#21452d;">'
.'<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f6f9f3;"><tr><td align="center" style="padding:32px 12px;">'
.'<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px;background:#ffffff;border-radius:20px;overflow:hidden;">'
 .'<tr><td style="background:#e4f1dc;padding:24px 28px;text-align:center;"><img src="'.$origin.'/images/logos/mainlogo-20260924-221429-7e305d.jpg" alt="Bubba Hub South West" width="270" style="display:block;width:100%;max-width:270px;height:auto;margin:0 auto 12px;border:0;"><p style="font-size:14px;color:#285c3a;margin:0;">Helping local families find, enjoy and make the most of family life in Devon &amp; Cornwall</p></td></tr>'
.'<tr><td style="padding:32px 34px;"><p style="margin:0 0 12px;font-size:14px;color:#64806c;font-weight:700;">YOUR FREE LISTING IS READY</p>'
.'<h1 style="font-size:29px;line-height:1.2;color:#174b2c;margin:0 0 18px;">Your activity is on Bubba Hub!</h1>'
.'<p style="font-size:16px;line-height:1.65;margin:0 0 18px;">Hello! We have added <strong>'.$safeTitle.'</strong> to Bubba Hub South West so local families can discover you.</p>'
.'<p style="font-size:16px;line-height:1.65;margin:0 0 24px;">Please take a moment to review your activity and <strong>claim your free listing</strong>.</p>'
.'<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="background:#205b36;border-radius:12px;"><a href="'.$safeUrl.'" style="display:inline-block;padding:15px 26px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;">Claim my free listing</a></td></tr></table>'
 .'<p style="font-size:14px;line-height:1.6;margin:16px 0 26px;"><a href="'.$safeListing.'" style="color:#205b36;text-decoration:underline;">View your listing first</a></p><p style="font-size:14px;line-height:1.6;margin:0 0 24px;color:#496b53;">Once claimed, you can keep your details, photos and timetable up to date for local families.</p>'
.'<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#eff7f0;border-radius:14px;"><tr><td style="padding:20px 22px;"><p style="font-size:17px;font-weight:700;margin:0 0 8px;color:#174b2c;">New to Bubba Hub?</p><p style="font-size:15px;line-height:1.6;margin:0;">If you have not got an account yet, <strong>create a free Class Leader account today!</strong> Follow the claim button above and choose <strong>Create account</strong> to get started. Once signed in, submit your claim for approval.</p></td></tr></table>'
.'<p style="font-size:15px;line-height:1.6;margin:26px 0 0;">We look forward to welcoming you!<br><strong>The Bubba Hub South West Team</strong></p></td></tr>'
.'<tr><td style="background:#f0f5ed;padding:19px 28px;text-align:center;font-size:12px;line-height:1.6;color:#637b69;">You are receiving this because your activity contact email is listed on Bubba Hub.<br>Claiming your listing is free.</td></tr>'
.'</table></td></tr></table></body></html>';
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
