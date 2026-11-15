<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');session_start();
function bh_assignment_reply(int $status,array $data):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))bh_assignment_reply(401,['ok'=>false,'error'=>'Admin login required.']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $activityCols=$db->query("SHOW COLUMNS FROM bh_activities")->fetchAll(PDO::FETCH_COLUMN);
 $organiserCols=$db->query("SHOW COLUMNS FROM bh_organisers")->fetchAll(PDO::FETCH_COLUMN);
 $userCols=$db->query("SHOW COLUMNS FROM bh_users")->fetchAll(PDO::FETCH_COLUMN);
 $db->exec("CREATE TABLE IF NOT EXISTS bh_listing_assignment_email_log (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,activity_id BIGINT UNSIGNED NOT NULL,organiser_id BIGINT UNSIGNED NOT NULL,recipient_email VARCHAR(190) NOT NULL,status VARCHAR(20) NOT NULL,sent_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_assignment_activity(activity_id),INDEX idx_assignment_organiser(organiser_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 if(!in_array('organiser_id',$activityCols,true)||!in_array('contact_email',$activityCols,true))bh_assignment_reply(503,['ok'=>false,'error'=>'The listing database does not support contact emails and organiser assignment.']);
 $hasUserId=in_array('user_id',$organiserCols,true);
 $nameCol=in_array('organisation_name',$organiserCols,true)?'organisation_name':(in_array('name',$organiserCols,true)?'name':null);
 if(!$nameCol)bh_assignment_reply(503,['ok'=>false,'error'=>'Organiser names are unavailable.']);
 $_SESSION['bh_assignment_csrf']??=bin2hex(random_bytes(32));
 $leaderJoin=$hasUserId?'u.id=o.user_id':'LOWER(u.email)=LOWER(o.email)';
 $orgStatus=in_array('status',$organiserCols,true)?" AND (o.status IS NULL OR o.status NOT IN ('suspended','inactive'))":'';
 $leaders=$db->query("SELECT o.id AS organiser_id,o.`".$nameCol."` AS organisation_name,u.id AS user_id,u.email FROM bh_organisers o INNER JOIN bh_users u ON ".$leaderJoin." WHERE u.role IN ('leader','organiser') AND u.status='active'".$orgStatus." ORDER BY o.`".$nameCol."`,o.id")->fetchAll(PDO::FETCH_ASSOC);
 if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
  $q=trim((string)($_GET['q']??''));$where="TRIM(COALESCE(a.contact_email,''))<>'' AND (u.id IS NULL OR EXISTS (SELECT 1 FROM bh_listing_assignment_email_log pending WHERE pending.activity_id=a.id AND pending.id=(SELECT MAX(latest.id) FROM bh_listing_assignment_email_log latest WHERE latest.activity_id=a.id) AND pending.status<>'sent'))";
  $params=[];
  if($q!==''){$where.=" AND (a.title LIKE ? OR a.contact_email LIKE ? OR a.id=?)";$params[]='%'.$q.'%';$params[]='%'.$q.'%';$params[]=(int)$q;}
  $joinUser=$hasUserId?'(u.id=o.user_id OR LOWER(u.email)=LOWER(o.email))':'LOWER(u.email)=LOWER(o.email)';
  $sql="SELECT a.id,a.title,a.contact_email,a.organiser_id,COALESCE(o.`".$nameCol."`,'') AS current_organisation,COALESCE(v.town,'') AS town FROM bh_activities a LEFT JOIN bh_organisers o ON o.id=a.organiser_id LEFT JOIN bh_users u ON ".$joinUser." AND u.role IN ('leader','organiser') AND u.status='active' LEFT JOIN (SELECT activity_id,MIN(town) AS town FROM bh_venues GROUP BY activity_id) v ON v.activity_id=a.id WHERE ".$where." ORDER BY a.title,a.id LIMIT 1000";
  $stmt=$db->prepare($sql);$stmt->execute($params);$listings=$stmt->fetchAll(PDO::FETCH_ASSOC);
  $logs=$db->query("SELECT activity_id,status,sent_at FROM bh_listing_assignment_email_log l INNER JOIN (SELECT activity_id,MAX(id) AS latest_id FROM bh_listing_assignment_email_log GROUP BY activity_id) x ON x.latest_id=l.id")->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);foreach($listings as &$item){$log=$logs[(int)$item['id']]??null;$item['email_status']=$log['status']??'not_sent';$item['email_sent_at']=$log['sent_at']??null;}unset($item);
  bh_assignment_reply(200,['ok'=>true,'listings'=>$listings,'leaders'=>$leaders,'csrf'=>$_SESSION['bh_assignment_csrf']]);
 }
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')bh_assignment_reply(405,['ok'=>false,'error'=>'GET or POST required.']);
 $origin=parse_url((string)($_SERVER['HTTP_ORIGIN']??''));$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
 if(!is_array($origin)||strtolower((string)($origin['host']??''))!==$host||strtolower((string)($origin['scheme']??''))!=='https'||isset($origin['port']))bh_assignment_reply(403,['ok'=>false,'error'=>'Request origin could not be verified.']);
 $input=json_decode((string)file_get_contents('php://input'),true);if(!is_array($input))bh_assignment_reply(400,['ok'=>false,'error'=>'Invalid request.']);
 if(!hash_equals($_SESSION['bh_assignment_csrf'],(string)($input['csrf']??'')))bh_assignment_reply(403,['ok'=>false,'error'=>'Please refresh the page and try again.']);
 if(($input['action']??'')!=='assign')bh_assignment_reply(422,['ok'=>false,'error'=>'Unsupported action.']);
 $ids=array_values(array_unique(array_filter(array_map('intval',(array)($input['listing_ids']??[])),static fn($id)=>$id>0)));
 $organiserId=(int)($input['organiser_id']??0);$sendEmail=!empty($input['send_email']);
 if(!$ids||count($ids)>200||$organiserId<1)bh_assignment_reply(422,['ok'=>false,'error'=>'Select up to 200 listings and choose a class leader.']);
 $leader=null;foreach($leaders as $candidate)if((int)$candidate['organiser_id']===$organiserId){$leader=$candidate;break;}
 if(!$leader)bh_assignment_reply(422,['ok'=>false,'error'=>'Choose an active class leader account.']);
 $marks=implode(',',array_fill(0,count($ids),'?'));
 $db->beginTransaction();
 try{
  $stmt=$db->prepare("SELECT id,title FROM bh_activities WHERE id IN (".$marks.") AND TRIM(COALESCE(contact_email,''))<>'' FOR UPDATE");$stmt->execute($ids);$selected=$stmt->fetchAll(PDO::FETCH_ASSOC);
  if(count($selected)!==count($ids))throw new RuntimeException('One or more selected listings are no longer eligible. Refresh the list and try again.');
  $update=$db->prepare("UPDATE bh_activities SET organiser_id=? WHERE id=?");foreach($selected as $listing)$update->execute([$organiserId,(int)$listing['id']]);
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 $emailSent=false;$emailError=null;
 if($sendEmail){
  $titles=array_map(static fn($item)=>(string)$item['title'],$selected);
  $emailSent=bh_assignment_send_email((string)$leader['email'],(string)$leader['organisation_name'],$titles);
  if(!$emailSent)$emailError='The listings were assigned, but the update email could not be sent.';
  $log=$db->prepare("INSERT INTO bh_listing_assignment_email_log(activity_id,organiser_id,recipient_email,status,sent_at) VALUES(?,?,?,?,?)");
  foreach($selected as $listing)$log->execute([(int)$listing['id'],$organiserId,(string)$leader['email'],$emailSent?'sent':($sendEmail?'failed':'not_sent'),$emailSent?date('Y-m-d H:i:s'):null]);
 }
 bh_assignment_reply(200,['ok'=>true,'assigned'=>count($selected),'leader_email'=>$leader['email'],'email_requested'=>$sendEmail,'email_sent'=>$emailSent,'email_error'=>$emailError,'message'=>count($selected).' listing(s) assigned to '.$leader['organisation_name'].'.'.($sendEmail?($emailSent?' Update email sent.':' Email could not be sent; you can retry from this page.'):'')]);
}catch(Throwable $e){error_log('Admin listing assignment: '.$e->getMessage());bh_assignment_reply(500,['ok'=>false,'error'=>$e->getMessage()]);}

function bh_assignment_send_email(string $email,string $leaderName,array $titles):bool{
 if(!filter_var($email,FILTER_VALIDATE_EMAIL))return false;
 $wp=dirname(__DIR__).'/wp-config.php';if(!is_file($wp))return false;require_once $wp;
 $dir=dirname(__DIR__).'/wp-includes/PHPMailer';foreach(['Exception.php','PHPMailer.php','SMTP.php'] as $file){$path=$dir.'/'.$file;if(!is_file($path))return false;require_once $path;}
 $password=defined('BH_SMTP_PASSWORD')?(string)BH_SMTP_PASSWORD:'';if($password===''||$password==='YOUR-NOREPLY-MAILBOX-PASSWORD')return false;
 $host=defined('BH_SMTP_HOST')?(string)BH_SMTP_HOST:'smtp.bubbahub.co.uk';$port=defined('BH_SMTP_PORT')?(int)BH_SMTP_PORT:465;$user=defined('BH_SMTP_USERNAME')?(string)BH_SMTP_USERNAME:'noreply@bubbahub.co.uk';$secure=defined('BH_SMTP_SECURE')?strtolower((string)BH_SMTP_SECURE):'ssl';$from=defined('BH_SMTP_FROM')?(string)BH_SMTP_FROM:$user;$fromName=defined('BH_SMTP_FROM_NAME')?(string)BH_SMTP_FROM_NAME:'Bubba Hub';
 $safeName=htmlspecialchars($leaderName,ENT_QUOTES,'UTF-8');$items='';foreach($titles as $title)$items.='<li>'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'</li>';
 try{$mail=new PHPMailer\PHPMailer\PHPMailer(true);$mail->isSMTP();$mail->Host=$host;$mail->Port=$port;$mail->SMTPAuth=true;$mail->Username=$user;$mail->Password=$password;$mail->SMTPSecure=($secure==='tls'||$secure==='starttls')?PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS:PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;$mail->CharSet='UTF-8';$mail->setFrom($from,$fromName);$mail->addAddress($email);$mail->isHTML(true);$mail->Subject='Your Bubba Hub listings have been updated';$mail->Body='<div style="font-family:Arial,sans-serif;color:#33483a;max-width:620px;margin:auto;padding:24px"><h1 style="color:#416651">Your listings have been updated</h1><p>Hi '.$safeName.',</p><p>The following listings have now been attached to your Bubba Hub leader account:</p><ul>'.$items.'</ul><p>You can sign in to your leader account to manage your listings and keep your information up to date.</p><p><a href="https://bubbahub.co.uk/leader.html" style="display:inline-block;background:#416651;color:#fff;text-decoration:none;padding:13px 22px;border-radius:8px;font-weight:bold">OPEN MY LEADER ACCOUNT</a></p><p>Thank you,<br>The Bubba Hub Team</p></div>';$mail->AltBody="Hi ".$leaderName.",\n\nThese listings have been attached to your Bubba Hub leader account:\n- ".implode("\n- ",$titles)."\n\nSign in at https://bubbahub.co.uk/leader.html\n\nThe Bubba Hub Team";$mail->send();return true;}catch(Throwable $e){error_log('Bubba Hub listing assignment email failed: '.$e->getMessage());return false;}
}
