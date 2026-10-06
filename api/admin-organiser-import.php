<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_start();

function bh_bulk_response(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['bh_admin_authenticated'])) {
    bh_bulk_response(401, ['ok'=>false,'error'=>'Admin login required.']);
}

require __DIR__.'/db.php';
$db = bh_mysql();

$db->exec("CREATE TABLE IF NOT EXISTS bh_account_activation_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activation_user (user_id),
    INDEX idx_activation_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
try{$db->exec("ALTER TABLE bh_account_activation_tokens ADD COLUMN organisation_name VARCHAR(190) NULL");}catch(Throwable $ignored){} try{$db->exec("ALTER TABLE bh_account_activation_tokens ADD COLUMN listing_title VARCHAR(190) NULL");}catch(Throwable $ignored){} try{$db->exec("ALTER TABLE bh_account_activation_tokens ADD COLUMN first_name VARCHAR(120) NULL");}catch(Throwable $ignored){} try{$db->exec("ALTER TABLE bh_account_activation_tokens ADD COLUMN code_hash CHAR(64) NULL");}catch(Throwable $ignored){} try{$db->exec("ALTER TABLE bh_account_activation_tokens ADD COLUMN email_sent_at DATETIME NULL");}catch(Throwable $ignored){} try{$db->exec("ALTER TABLE bh_account_activation_tokens ADD COLUMN listing_title VARCHAR(190) NULL");}catch(Throwable $ignored){} try{$db->exec("ALTER TABLE bh_account_activation_tokens ADD COLUMN email_sent_at DATETIME NULL");}catch(Throwable $ignored){}

function bh_clean_header(string $s): string {
    $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
    return preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($s)));
}
function bh_first(array $row, array $keys, string $default=''): string {
    foreach ($keys as $key) {
        $key = bh_clean_header($key);
        if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') return trim((string)$row[$key]);
    }
    return $default;
}
function bh_bool(string $v): int {
    return in_array(strtolower(trim($v)), ['1','yes','y','true','on'], true) ? 1 : 0;
}
function bh_slug(string $value): string {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
    return $slug !== '' ? $slug : 'activity';
}
function bh_has_column(PDO $db, string $table, string $column): bool {
    $q = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $q->execute([$table,$column]);
    return (int)$q->fetchColumn() > 0;
}
function bh_activation_credentials(PDO $db, int $userId): array {
    $token=bin2hex(random_bytes(32)); $code=(string)random_int(100000,999999);
    $db->prepare("UPDATE bh_account_activation_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL")->execute([$userId]);
    $db->prepare("INSERT INTO bh_account_activation_tokens (user_id,token_hash,code_hash,expires_at) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 7 DAY))")->execute([$userId,hash('sha256',$token),hash('sha256',$code)]);
    return ['token'=>$token,'code'=>$code];
}
function bh_send_welcome(string $email,string $firstName,string $organisation,string $listing,string $token,string $code): bool {
    $wpConfig=dirname(__DIR__).'/wp-config.php'; if(!is_file($wpConfig)){error_log('Bubba Hub leader welcome: wp-config.php not found.');return false;} require_once $wpConfig;
    $dir=dirname(__DIR__).'/wp-includes/PHPMailer'; foreach(['Exception.php','PHPMailer.php','SMTP.php'] as $f){$p=$dir.'/'.$f;if(!is_file($p)){error_log('Bubba Hub leader welcome: PHPMailer file missing: '.$p);return false;}require_once $p;}
    $host=defined('BH_SMTP_HOST')?(string)BH_SMTP_HOST:'smtp.bubbahub.co.uk';$port=defined('BH_SMTP_PORT')?(int)BH_SMTP_PORT:465;$username=defined('BH_SMTP_USERNAME')?(string)BH_SMTP_USERNAME:'noreply@bubbahub.co.uk';$password=defined('BH_SMTP_PASSWORD')?(string)BH_SMTP_PASSWORD:'';$secure=defined('BH_SMTP_SECURE')?strtolower((string)BH_SMTP_SECURE):'ssl';$from=defined('BH_SMTP_FROM')?(string)BH_SMTP_FROM:$username;$fromName=defined('BH_SMTP_FROM_NAME')?(string)BH_SMTP_FROM_NAME:'Bubba Hub';
    if($password===''||$password==='YOUR-NOREPLY-MAILBOX-PASSWORD'){error_log('Bubba Hub leader welcome: SMTP password not configured.');return false;}
    $sf=htmlspecialchars($firstName,ENT_QUOTES,'UTF-8');$so=htmlspecialchars($organisation,ENT_QUOTES,'UTF-8');$sl=htmlspecialchars($listing,ENT_QUOTES,'UTF-8');$url=htmlspecialchars('https://bubbahub.co.uk/activate.html?token='.rawurlencode($token),ENT_QUOTES,'UTF-8');$hello=$sf!==''?'Hi '.$sf.',':'Hello,';
    try{$mail=new PHPMailer\PHPMailer\PHPMailer(true);$mail->isSMTP();$mail->Host=$host;$mail->Port=$port;$mail->SMTPAuth=true;$mail->Username=$username;$mail->Password=$password;$mail->SMTPSecure=($secure==='tls'||$secure==='starttls')?PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS:PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;$mail->CharSet='UTF-8';$mail->setFrom($from,$fromName);$mail->addAddress($email);$mail->isHTML(true);$mail->Subject='Welcome to Bubba Hub — your leader account is ready 💚';
    $mail->Body='<div style="margin:0;background:#f4f7f3;padding:32px 12px;font-family:Arial,Helvetica,sans-serif;color:#33483a"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#fff;border-radius:18px;overflow:hidden"><tr><td style="background:#d8e6db;padding:24px;text-align:center"><img src="https://bubbahub.co.uk/wp-content/uploads/logo/logoheader.png" alt="Bubba Hub" width="190" style="display:block;width:190px;max-width:100%;height:auto;margin:0 auto 10px"><div style="font-size:12px;color:#416651;font-weight:bold">YOUR FAMILY HUB FOR FINDING, PLANNING &amp; BOOKING FAMILY ACTIVITIES</div></td></tr><tr><td style="padding:34px 34px 12px"><div style="font-size:12px;letter-spacing:1.5px;font-weight:bold;color:#3c8e96">WELCOME TO BUBBA HUB</div><h1 style="margin:8px 0 14px;font-size:30px;line-height:1.2;color:#416651">Your leader account is ready 💚</h1><p style="font-size:16px;line-height:1.7;margin:0 0 14px">'.$hello.'</p><p style="font-size:16px;line-height:1.7;margin:0 0 18px">The Bubba Hub team has created a leader account for <strong>'.$so.'</strong>'.($sl!==''?' and added <strong>'.$sl.'</strong> to your account.':'').'</p></td></tr><tr><td style="padding:0 34px 28px"><div style="background:#f0f5f0;border:1px solid #d8e6db;border-radius:14px;padding:22px;text-align:center"><div style="font-size:13px;color:#617261;font-weight:bold;text-transform:uppercase;letter-spacing:1px">Your one-time sign-in code</div><div style="font-size:34px;line-height:1.2;letter-spacing:8px;font-weight:bold;color:#144400;margin:12px 0">'.$code.'</div><div style="font-size:13px;color:#617261">Use this code once when activating your account. It expires in 7 days.</div></div></td></tr><tr><td style="padding:0 34px 28px;text-align:center"><a href="'.$url.'" style="display:inline-block;background:#416651;color:#fff;text-decoration:none;font-weight:bold;font-size:16px;padding:15px 26px;border-radius:10px">SIGN IN &amp; CREATE YOUR PASSWORD</a><p style="font-size:13px;line-height:1.6;color:#617261;margin:14px 0 0">You will create your own password during your first sign-in.</p></td></tr><tr><td style="padding:0 34px 30px"><h2 style="font-size:20px;color:#416651;margin:0 0 14px">What is waiting for you?</h2><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td width="50%" valign="top" style="padding:0 8px 12px 0"><strong style="color:#144400">📍 Add activities</strong><div style="font-size:14px;line-height:1.6;margin-top:4px">Manage your listings, locations, ages, prices and booking information.</div></td><td width="50%" valign="top" style="padding:0 0 12px 8px"><strong style="color:#144400">👨‍👩‍👧 Reach families</strong><div style="font-size:14px;line-height:1.6;margin-top:4px">Help local families discover what you offer.</div></td></tr><tr><td width="50%" valign="top" style="padding:0 8px 0 0"><strong style="color:#144400">📅 Keep it up to date</strong><div style="font-size:14px;line-height:1.6;margin-top:4px">Update your activities whenever details change.</div></td><td width="50%" valign="top" style="padding:0 0 0 8px"><strong style="color:#144400">⭐ Build your presence</strong><div style="font-size:14px;line-height:1.6;margin-top:4px">Give families a clear place to discover your organisation.</div></td></tr></table></td></tr><tr><td style="background:#416651;padding:24px 34px;color:#fff"><div style="font-size:16px;font-weight:bold;margin-bottom:6px">A little security note</div><div style="font-size:13px;line-height:1.6">Your code is for you only. It can be used once and expires after 7 days. If you were not expecting this email, please contact the Bubba Hub team.</div></td></tr><tr><td style="padding:22px 34px;text-align:center;font-size:12px;line-height:1.6;color:#617261">Bubba Hub<br>Your Family Hub for Finding, Planning &amp; Booking Family Activities<br><span style="color:#9fb7a5">You are receiving this because a Bubba Hub leader account was created for you.</span></td></tr></table></div>';
    $mail->AltBody="Welcome to Bubba Hub — your leader account is ready 💚\n\n{$hello}\n\nYour one-time sign-in code is: {$code}\n\nUse it once at: https://bubbahub.co.uk/activate.html?token=".rawurlencode($token)."\n\nYou will create your own password during first sign-in. The code expires in 7 days.\n\nThe Bubba Hub Team";$mail->send();return true;}catch(Throwable $e){error_log('Bubba Hub leader welcome failed: '.$e->getMessage());return false;}
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bh_bulk_response(405, ['ok'=>false,'error'=>'POST required.']);
}

$action = (string)($_POST['action'] ?? 'preview');
if (!in_array($action, ['preview','import'], true)) {
    bh_bulk_response(400, ['ok'=>false,'error'=>'Unknown action.']);
}
if (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    bh_bulk_response(422, ['ok'=>false,'error'=>'Choose a CSV file.']);
}
if ((int)$_FILES['csv']['size'] > 20 * 1024 * 1024) {
    bh_bulk_response(422, ['ok'=>false,'error'=>'CSV files must be 20 MB or smaller.']);
}

$fh = fopen($_FILES['csv']['tmp_name'], 'rb');
if (!$fh) bh_bulk_response(422, ['ok'=>false,'error'=>'Could not read the CSV.']);

$headers = fgetcsv($fh);
if (!$headers) {
    fclose($fh);
    bh_bulk_response(422, ['ok'=>false,'error'=>'The CSV has no header row.']);
}
$headers = array_map('bh_clean_header', $headers);
$rows = [];
while (($r = fgetcsv($fh)) !== false) {
    if (count(array_filter($r, fn($v) => trim((string)$v) !== '')) === 0) continue;
    $r = array_pad($r, count($headers), '');
    $row = [];
    foreach ($headers as $i => $h) $row[$h] = trim((string)($r[$i] ?? ''));
    $rows[] = $row;
}
fclose($fh);

if (count($rows) > 2000) bh_bulk_response(422, ['ok'=>false,'error'=>'CSV contains more than 2,000 rows.']);
foreach (['title','category','venue_name','town','email'] as $required) {
    if (!in_array($required, $headers, true)) bh_bulk_response(422, ['ok'=>false,'error'=>'Required CSV column missing: '.$required]);
}

$hasUserId = bh_has_column($db,'bh_organisers','user_id');
$hasOrgDescription = bh_has_column($db,'bh_organisers','description');
$hasOrgName = bh_has_column($db,'bh_organisers','name');
$hasAccessibility = bh_has_column($db,'bh_activities','accessibility');
$hasCounty = bh_has_column($db,'bh_activities','county');

$summary = ['rows'=>count($rows),'new_accounts'=>0,'existing_accounts'=>0,'family_conflicts'=>0,'missing_email'=>0,'created_listings'=>0,'skipped_listings'=>0,'emails_queued'=>0,'email_failures'=>0,'errors'=>[]];
$seenEmails = [];

foreach ($rows as $idx => $row) {
    $line = $idx + 2;
    $email = strtolower(trim(bh_first($row,['email','email_address','contact_email'])));
    $title = bh_first($row,['title','activity_title','activity_name','activity','class_name','class','name']);
    $org = bh_first($row,['organisation_name','organisation','organization','organizer','organiser','company','company_name','provider','provider_name','leader','leader_name']);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $summary['missing_email']++;
        if (count($summary['errors']) < 30) $summary['errors'][] = 'Row '.$line.': valid email is required.';
        continue;
    }
    if ($title === '' || bh_first($row,['category']) === '' || bh_first($row,['venue_name','venue']) === '' || bh_first($row,['town','city','town_or_city']) === '') {
        if (count($summary['errors']) < 30) $summary['errors'][] = 'Row '.$line.': title, category, venue_name and town are required.';
        continue;
    }
    if (isset($seenEmails[$email])) continue;
    $seenEmails[$email] = true;

    $q = $db->prepare("SELECT id,role,status FROM bh_users WHERE LOWER(email)=? LIMIT 1");
    $q->execute([$email]);
    $user = $q->fetch();
    if ($user) {
        if (($user['role'] ?? '') === 'leader' || ($user['role'] ?? '') === 'organiser') $summary['existing_accounts']++;
        else $summary['family_conflicts']++;
    } else {
        $summary['new_accounts']++;
    }
}

if ($action === 'preview') {
    bh_bulk_response(200, ['ok'=>true,'mode'=>'preview','summary'=>$summary,'message'=>'Preview only. No accounts, listings or emails were changed.']);
}

$createdUserIds = [];
$db->beginTransaction();
try {
    foreach ($rows as $idx => $row) {
        $line = $idx + 2;
        $email = strtolower(trim(bh_first($row,['email','email_address','contact_email'])));
        $firstName = bh_first($row,['first_name','firstname','forename','contact_first_name']);
        $title = bh_first($row,['title','activity_title','activity_name','activity','class_name','class','name']);
        $org = bh_first($row,['organisation_name','organisation','organization','organizer','organiser','company','company_name','provider','provider_name','leader','leader_name']);
        $category = bh_first($row,['category','activity_category','type','class_type']);
        $venue = bh_first($row,['venue_name','venue','venue_name_location','location','location_name']);
        $town = bh_first($row,['town','village_town_or_city','city','town_city','town_or_city','village','location_town']);
        if ($email === '' || !filter_var($email,FILTER_VALIDATE_EMAIL) || $title==='' || $category==='' || $venue==='' || $town==='') continue;

        $q = $db->prepare("SELECT id,role,status FROM bh_users WHERE LOWER(email)=? LIMIT 1");
        $q->execute([$email]);
        $user = $q->fetch();
        $userId = 0;
        $newUser = false;

        if ($user) {
            if (!in_array(($user['role'] ?? ''), ['leader','organiser'], true)) {
                continue;
            }
            $userId = (int)$user['id'];
            $db->prepare("UPDATE bh_users SET status='active' WHERE id=?")->execute([$userId]);
        } else {
            $temporaryHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            $q = $db->prepare("INSERT INTO bh_users (email,password_hash,role,status) VALUES (?,?, 'leader','active')");
            $q->execute([$email,$temporaryHash]);
            $userId = (int)$db->lastInsertId();
            $newUser = true;
            $summary['new_accounts']++;
            $createdUserIds[$userId] = true;
        }

        $q = $db->prepare("SELECT id".($hasUserId?',user_id':'')." FROM bh_organisers WHERE LOWER(email)=? ORDER BY id LIMIT 1");
        $q->execute([$email]);
        $orgRow = $q->fetch();
        $organiserId = $orgRow ? (int)$orgRow['id'] : 0;

        if ($organiserId === 0) {
            $baseOrgSlug = bh_slug($org !== '' ? $org : $title);
            $orgSlug = $baseOrgSlug;
            $n = 2;
            while (true) {
                $q = $db->prepare("SELECT id FROM bh_organisers WHERE slug=? LIMIT 1");
                $q->execute([$orgSlug]);
                if (!$q->fetch()) break;
                $orgSlug = $baseOrgSlug.'-'.$n++;
            }
            $q = $db->prepare("INSERT INTO bh_organisers (".($hasUserId?'user_id,':'')."organisation_name,slug,email,status) VALUES (".($hasUserId?'?,':'')."?,?,?,'published')");
            $args = $hasUserId ? [$userId, ($org !== '' ? $org : $title), $orgSlug, $email] : [($org !== '' ? $org : $title), $orgSlug, $email];
            $q->execute($args);
            $organiserId = (int)$db->lastInsertId();
        } elseif ($hasUserId) {
            $db->prepare("UPDATE bh_organisers SET user_id=?,organisation_name=?,email=?,status='published' WHERE id=?")->execute([$userId,($org !== '' ? $org : $title),$email,$organiserId]);
        } else {
            $db->prepare("UPDATE bh_organisers SET organisation_name=?,email=?,status='published' WHERE id=?")->execute([($org !== '' ? $org : $title),$email,$organiserId]);
        }

        $description = bh_first($row,['description','activity_description','details']);
        $age = bh_first($row,['age_range','ages','age','age_group','age_groups']);
        $county = bh_first($row,['county','area','county_area']);
        if (!in_array($county,['Devon','Cornwall','Plymouth','Torbay'],true)) $county = null;
        $priceRaw = bh_first($row,['price_from','price','cost']);
        $price = $priceRaw === '' ? null : (float)preg_replace('/[^0-9.\-]/','',$priceRaw);
        $booking = bh_first($row,['booking_url','booking','booking_link']);
        $image = bh_first($row,['image_path','image','image_url']);
        $accessRaw = bh_first($row,['accessibility','accessibility_options']);
        $access = array_values(array_unique(array_filter(array_map('trim', preg_split('/\s*[;,|]\s*/',$accessRaw)))));
        $slug = bh_slug($title);
        $baseSlug = $slug;
        $n = 2;
        while (true) {
            $q = $db->prepare("SELECT id FROM bh_activities WHERE slug=? LIMIT 1");
            $q->execute([$slug]);
            if (!$q->fetch()) break;
            $slug = $baseSlug.'-'.$n++;
        }

        $columns = ['organiser_id','title','slug','description','category','age_range'];
        $values = [$organiserId,$title,$slug,$description,$category,$age];
        if ($hasCounty) { $columns[]='county'; $values[]=$county; }
        $columns = array_merge($columns,['price_from','booking_url','image_path','status']);
        $values = array_merge($values,[$price,$booking,$image,'published']);
        if ($hasAccessibility) { $columns[]='accessibility'; $values[]=json_encode($access,JSON_UNESCAPED_SLASHES); }
        $marks = implode(',',array_fill(0,count($columns),'?'));
        $q = $db->prepare("INSERT INTO bh_activities (".implode(',',$columns).") VALUES (".$marks.")");
        $q->execute($values);
        $activityId = (int)$db->lastInsertId();

        $address = bh_first($row,['address','venue_address','full_address','street_address']);
        $region = bh_first($row,['region','area_region','locality','district']);
        $postcode = bh_first($row,['postcode','post_code','postal_code','zip']);
        $latRaw = bh_first($row,['latitude','lat']); $lat = $latRaw===''?null:(float)$latRaw;
        $lngRaw = bh_first($row,['longitude','lng','lon']); $lng = $lngRaw===''?null:(float)$lngRaw;
        $q = $db->prepare("INSERT INTO bh_venues (activity_id,venue_name,address,town,region,postcode,latitude,longitude,notes) VALUES (?,?,?,?,?,?,?,?,?)");
        $q->execute([$activityId,$venue,$address,$town,$region,$postcode,$lat,$lng,'']);
        $venueId = (int)$db->lastInsertId();

        $schedule = bh_first($row,['schedule','sessions','session_times','opening_times']);
        foreach (preg_split('/\s*;\s*/',$schedule) as $part) {
            if (!preg_match('/^([A-Za-z]+)\s+([0-9]{1,2}:[0-9]{2})(?:\s*[-–]\s*([0-9]{1,2}:[0-9]{2}))?/',$part,$m)) continue;
            $days=['mon'=>1,'monday'=>1,'tue'=>2,'tues'=>2,'tuesday'=>2,'wed'=>3,'wednesday'=>3,'thu'=>4,'thur'=>4,'thurs'=>4,'thursday'=>4,'fri'=>5,'friday'=>5,'sat'=>6,'saturday'=>6,'sun'=>7,'sunday'=>7];
            $day=$days[strtolower($m[1])]??0; if($day<1) continue;
            $start=$m[2].':00'; $end=isset($m[3])&&$m[3]!==''?$m[3].':00':null;
            $q=$db->prepare("INSERT INTO bh_sessions (venue_id,day_of_week,start_time,end_time,price,term_time_only,frequency,start_date,end_date) VALUES (?,?,?,?,?,?,?,?,?)");
            $q->execute([$venueId,$day,$start,$end,$price,0,'weekly',null,null]);
        }

        $summary['created_listings']++;
        if ($newUser) {
            $credentials=bh_activation_credentials($db,$userId);$token=$credentials['token'];$code=$credentials['code'];
            $db->prepare("UPDATE bh_account_activation_tokens SET organisation_name=?,listing_title=?,first_name=? WHERE token_hash=?")->execute([$org,$title,$firstName,hash('sha256',$token)]);
            if (bh_send_welcome($email,$firstName,$org,$title,$token,$code)) { $summary['emails_queued']++; try{$db->prepare("UPDATE bh_account_activation_tokens SET email_sent_at=NOW() WHERE token_hash=?")->execute([hash('sha256',$token)]);}catch(Throwable $ignored){} } else $summary['email_failures']++;
        }
    }
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    bh_bulk_response(500, ['ok'=>false,'error'=>'bulk_import_failed','message'=>$e->getMessage(),'summary'=>$summary]);
}

bh_bulk_response(200, ['ok'=>true,'mode'=>'import','summary'=>$summary,'message'=>'Bulk organiser import completed.']);
?>