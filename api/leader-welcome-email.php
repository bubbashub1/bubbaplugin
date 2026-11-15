<?php
declare(strict_types=1);
function bh_leader_activation_credentials(PDO $db,int $userId):array{
 $token=bin2hex(random_bytes(32));$code=(string)random_int(100000,999999);
 $db->prepare("UPDATE bh_account_activation_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL")->execute([$userId]);
 $db->prepare("INSERT INTO bh_account_activation_tokens (user_id,token_hash,code_hash,expires_at) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 7 DAY))")->execute([$userId,hash('sha256',$token),hash('sha256',$code)]);
 return ['token'=>$token,'code'=>$code];
}
function bh_send_leader_welcome(string $email,string $firstName,string $organisation,string $listing,string $token,string $code):bool{
 $wp=dirname(__DIR__).'/wp-config.php';if(!is_file($wp))return false;require_once $wp;$dir=dirname(__DIR__).'/wp-includes/PHPMailer';
 foreach(['Exception.php','PHPMailer.php','SMTP.php'] as $f){$p=$dir.'/'.$f;if(!is_file($p))return false;require_once $p;}
 $password=defined('BH_SMTP_PASSWORD')?(string)BH_SMTP_PASSWORD:'';if($password===''||$password==='YOUR-NOREPLY-MAILBOX-PASSWORD')return false;
 $host=defined('BH_SMTP_HOST')?(string)BH_SMTP_HOST:'smtp.bubbahub.co.uk';$port=defined('BH_SMTP_PORT')?(int)BH_SMTP_PORT:465;$user=defined('BH_SMTP_USERNAME')?(string)BH_SMTP_USERNAME:'noreply@bubbahub.co.uk';$secure=defined('BH_SMTP_SECURE')?strtolower((string)BH_SMTP_SECURE):'ssl';$from=defined('BH_SMTP_FROM')?(string)BH_SMTP_FROM:$user;$fromName=defined('BH_SMTP_FROM_NAME')?(string)BH_SMTP_FROM_NAME:'Bubba Hub';
 $sf=htmlspecialchars($firstName,ENT_QUOTES,'UTF-8');$so=htmlspecialchars($organisation,ENT_QUOTES,'UTF-8');$sl=htmlspecialchars($listing,ENT_QUOTES,'UTF-8');$url=htmlspecialchars('https://bubbahub.co.uk/activate.html?token='.rawurlencode($token),ENT_QUOTES,'UTF-8');$hello=$sf!==''?'Hi '.$sf.',':'Hello,';
 try{$m=new PHPMailer\PHPMailer\PHPMailer(true);$m->isSMTP();$m->Host=$host;$m->Port=$port;$m->SMTPAuth=true;$m->Username=$user;$m->Password=$password;$m->SMTPSecure=($secure==='tls'||$secure==='starttls')?PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS:PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;$m->CharSet='UTF-8';$m->setFrom($from,$fromName);$m->addAddress($email);$m->isHTML(true);$m->Subject='Welcome to Bubba Hub — your leader account is ready 💚';
 $m->Body='<div style="background:#f4f7f3;padding:32px 12px;font-family:Arial,Helvetica,sans-serif;color:#33483a"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:auto;background:#fff;border-radius:18px;overflow:hidden"><tr><td style="background:#d8e6db;padding:24px;text-align:center"><img src="https://bubbahub.co.uk/wp-content/uploads/logo/logoheader.png" alt="Bubba Hub" width="190" style="display:block;margin:auto;width:190px;max-width:100%;height:auto"><div style="margin-top:10px;font-size:12px;font-weight:bold;color:#416651">YOUR FAMILY HUB FOR FINDING, PLANNING &amp; BOOKING FAMILY ACTIVITIES</div></td></tr><tr><td style="padding:34px"><div style="font-size:12px;font-weight:bold;letter-spacing:1.5px;color:#3c8e96">WELCOME TO BUBBA HUB</div><h1 style="color:#416651;font-size:30px;line-height:1.2;margin:8px 0 16px">Your leader account is ready 💚</h1><p style="font-size:16px;line-height:1.7">'.$hello.'</p><p style="font-size:16px;line-height:1.7">Your Bubba Hub account has been created for <strong>'.$so.'</strong>'.($sl!==''?' and <strong>'.$sl.'</strong> is ready for you to manage.':'').'</p><div style="background:#f0f5f0;border:1px solid #d8e6db;border-radius:14px;padding:22px;text-align:center;margin:24px 0"><div style="font-size:12px;font-weight:bold;color:#617261;text-transform:uppercase;letter-spacing:1px">Your one-time sign-in code</div><div style="font-size:34px;font-weight:bold;letter-spacing:8px;color:#144400;margin:12px 0">'.$code.'</div><div style="font-size:13px;color:#617261">Use this code once. It expires in 7 days.</div></div><div style="text-align:center"><a href="'.$url.'" style="display:inline-block;background:#416651;color:#fff;text-decoration:none;font-weight:bold;padding:15px 26px;border-radius:10px">LOG IN &amp; TAKE CHARGE OF YOUR LISTING</a></div><h2 style="color:#416651;font-size:20px;margin-top:30px">What is waiting for you?</h2><p style="line-height:1.7">📍 Manage your activities and listings<br>👨‍👩‍👧 Help local families discover what you offer<br>📅 Keep your information up to date<br>⭐ Build your presence on Bubba Hub</p></td></tr><tr><td style="background:#416651;padding:22px 34px;color:#fff;font-size:13px;line-height:1.6">Your code is for you only, can be used once and expires after 7 days. If you were not expecting this email, please contact the Bubba Hub team.</td></tr><tr><td style="padding:20px;text-align:center;font-size:12px;color:#617261">Bubba Hub<br>Your Family Hub for Finding, Planning &amp; Booking Family Activities</td></tr></table></div>';
 $m->AltBody="Welcome to Bubba Hub — your leader account is ready 💚\n\n{$hello}\n\nYour one-time sign-in code: {$code}\n\nLog in, set your password and take charge of your listing: https://bubbahub.co.uk/activate.html?token=".rawurlencode($token)."\n\nThe code expires in 7 days.\n\nThe Bubba Hub Team";$m->send();return true;
 }catch(Throwable $e){error_log('Bubba Hub leader welcome failed: '.$e->getMessage());return false;}
}
function bh_ensure_leader_activation_table(PDO $db):void{
 $db->exec("CREATE TABLE IF NOT EXISTS bh_account_activation_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  code_hash CHAR(64) NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activation_user (user_id),
  INDEX idx_activation_expiry (expires_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function bh_provision_listing_leader_account(PDO $db,int $organiserId,string $email,string $firstName,string $organisation,string $listing):?array{
 $email=strtolower(trim($email));
 if($organiserId<1||!filter_var($email,FILTER_VALIDATE_EMAIL))return null;
 $orgCols=$db->query("SHOW COLUMNS FROM bh_organisers")->fetchAll(PDO::FETCH_COLUMN);
 $ownerId=0;
 if(in_array('user_id',$orgCols,true)){
  $owner=$db->prepare("SELECT user_id FROM bh_organisers WHERE id=? LIMIT 1");
  $owner->execute([$organiserId]);
  $ownerId=(int)$owner->fetchColumn();
  if($ownerId>0)return null;
 }
 $check=$db->prepare("SELECT id,role,status FROM bh_users WHERE LOWER(email)=? LIMIT 1");
 $check->execute([$email]);
 $existing=$check->fetch(PDO::FETCH_ASSOC);
 if($existing){
  if(in_array(($existing['role']??''),['leader','organiser'],true) && in_array(($existing['status']??''),['active','pending'],true) && in_array('user_id',$orgCols,true)){
   $db->prepare("UPDATE bh_organisers SET user_id=? WHERE id=? AND (user_id IS NULL OR user_id=0)")->execute([(int)$existing['id'],$organiserId]);
  }
  return null;
 }
 $temporaryHash=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
 $insert=$db->prepare("INSERT INTO bh_users (email,password_hash,role,status) VALUES (?,?,'leader','pending')");
 $insert->execute([$email,$temporaryHash]);
 $userId=(int)$db->lastInsertId();
 if($userId<1)throw new RuntimeException('Leader account could not be created.');
 if(in_array('user_id',$orgCols,true)){
  $db->prepare("UPDATE bh_organisers SET user_id=? WHERE id=? AND (user_id IS NULL OR user_id=0)")->execute([$userId,$organiserId]);
 }
 if(in_array('email',$orgCols,true))$db->prepare("UPDATE bh_organisers SET email=? WHERE id=?")->execute([$email,$organiserId]);
 $credentials=bh_leader_activation_credentials($db,$userId);
 return ['email'=>$email,'first_name'=>$firstName,'organisation'=>$organisation,'listing'=>$listing,'token'=>$credentials['token'],'code'=>$credentials['code']];
}
