<?php
declare(strict_types=1);
/**
 * Scheduled claim invitation worker. Fail-closed until server configuration is set.
 * Invoke by server-side cron (not a public web URL): php api/claim-invitations-cron.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/db.php';
require_once __DIR__.'/mailer.php';
$enabled = getenv('BH_CLAIM_AUTO_ENABLED') === '1';
$paused = getenv('BH_CLAIM_AUTO_PAUSED') === '1';
if (!$enabled || $paused) { echo "Claim auto invitations disabled or paused\n"; exit; }
$limit = max(1, min(5, (int)(getenv('BH_CLAIM_AUTO_DAILY_LIMIT') ?: 2)));
$db = bh_mysql();
$lock = $db->query("SELECT GET_LOCK('bh_claim_auto_invites',0)")->fetchColumn();
if ((int)$lock !== 1) { echo "Worker already running\n"; exit; }
try {
  // Only contact new, unclaimed listings; never email an organiser already attached to an account.
  // An existing invitation of any status permanently excludes automated re-sends.
  $sql = "SELECT a.id,a.title,a.contact_email
    FROM bh_activities a
    LEFT JOIN bh_organisers o ON o.id=a.organiser_id
    WHERE a.contact_email IS NOT NULL AND a.contact_email <> ''
      AND a.organiser_id IS NOT NULL
      AND (o.user_id IS NULL OR o.user_id=0)
      AND NOT EXISTS (SELECT 1 FROM bh_claim_invitations i WHERE i.activity_id=a.id)
      AND NOT EXISTS (SELECT 1 FROM bh_listing_claims c WHERE c.activity_id=a.id)
    ORDER BY a.id ASC LIMIT 20";
  $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  $today = $db->query("SELECT COUNT(*) FROM bh_claim_invitations WHERE sent_at >= CURRENT_DATE()")->fetchColumn();
  $remaining = max(0, $limit-(int)$today);
  $sent=0; $failed=0;
  foreach ($rows as $row) {
    if ($remaining<=0) break;
    $id=(int)$row['id']; $email=strtolower(trim((string)$row['contact_email']));
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) continue;
    $token=bin2hex(random_bytes(32)); $hash=hash('sha256',$token);
    $insert=$db->prepare("INSERT IGNORE INTO bh_claim_invitations (activity_id,recipient,token_hash,status,sent_at,expires_at) VALUES (?,?,?,'sending',NULL,DATE_ADD(NOW(),INTERVAL 14 DAY))");
    $insert->execute([$id,$email,$hash]);
    if ($insert->rowCount()!==1) continue;
    $url='https://bubbahub.co.uk/auth.html?context=leader&next='.rawurlencode('/claim-invitation.html?activity_id='.$id.'&invite='.$token);
    $title=(string)$row['title'];
    $subject='Your activity is now on Bubba Hub – Claim your free listing!';
    $plain="Hello,\n\nWe've added ".$title." to Bubba Hub South West. Claim your free listing and manage your details by creating or signing into a free Class Leader account:\n".$url."\n\nThanks,\nBubba Hub South West";
    $html='<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;color:#21452d"><img alt="Bubba Hub South West" width="210" src="https://bubbahub.co.uk/wp-content/uploads/logo/logoheader.png"><h2>Your activity is on Bubba Hub!</h2><p>We have added <strong>'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'</strong> to our family activities directory.</p><p>Claiming your listing and creating a Class Leader account are free.</p><p><a style="background:#205b36;color:#fff;padding:14px 20px;border-radius:8px;display:inline-block;text-decoration:none" href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">Claim my free listing</a></p><p>Once approved, you can update your activity details, photos and timetable.</p><p>The Bubba Hub South West Team</p></div>';
    $ok=bh_send_smtp_mail($email,$subject,$html,$plain,'');
    $q=$db->prepare("UPDATE bh_claim_invitations SET status=?,sent_at=IF(?='sent',NOW(),NULL) WHERE activity_id=? AND recipient=? AND token_hash=? AND status='sending'");
    $status=$ok?'sent':'failed';$q->execute([$status,$status,$id,$email,$hash]);
    if($ok){$sent++;$remaining--;}else{$failed++;}
  }
  echo json_encode(['sent'=>$sent,'failed'=>$failed,'daily_cap'=>$limit])."\n";
} catch(Throwable $e) {
  error_log('Claim auto worker failed: '.$e->getMessage());
  fwrite(STDERR,"Claim worker error; see server log\n"); exit(1);
} finally { $db->query("SELECT RELEASE_LOCK('bh_claim_auto_invites')"); }
