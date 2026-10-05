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
require __DIR__.'/onesignal-email.php';
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
function bh_activation_token(PDO $db, int $userId): string {
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $db->prepare("UPDATE bh_account_activation_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL")->execute([$userId]);
    $db->prepare("INSERT INTO bh_account_activation_tokens (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 7 DAY))")->execute([$userId,$hash]);
    return $raw;
}
function bh_send_welcome(string $email, string $organisation, string $listing, string $token): bool {
    $safeOrg = htmlspecialchars($organisation, ENT_QUOTES, 'UTF-8');
    $safeListing = htmlspecialchars($listing, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars('https://bubbahub.co.uk/activate.html?token='.rawurlencode($token), ENT_QUOTES, 'UTF-8');
    $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#26352b;max-width:640px;margin:0 auto">'
        .'<h1 style="color:#617261">Welcome to Bubba Hub</h1>'
        .'<p>Hi'.($safeOrg!==''?' '.$safeOrg:'').',</p>'
        .'<p>Welcome to Bubba Hub. Your listing <strong>'.$safeListing.'</strong> is now on the new website.</p>'
        .'<p>We have created your organiser account so you can manage your listing and keep your details up to date.</p>'
        .'<p><a href="'.$safeUrl.'" style="display:inline-block;background:#416651;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px">Activate your account</a></p>'
        .'<p>This secure activation link can only be used once and expires in 7 days.</p>'
        .'<p>The Bubba Hub team</p></div>';
    $plain = "Welcome to Bubba Hub\n\nYour listing "{$listing}" is now on the new website.\n\nActivate your organiser account:\nhttps://bubbahub.co.uk/activate.html?token=".rawurlencode($token)."\n\nThis secure activation link can only be used once and expires in 7 days.\n\nThe Bubba Hub team";
    return bh_send_onesignal_email($email, 'Welcome to Bubba Hub – your listing is now live', $html, $plain);
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

        $schedule = bh_first($row,['schedule','sessions','session_times','opening_times']);
        foreach (preg_split('/\s*;\s*/',$schedule) as $part) {
            if (!preg_match('/^([A-Za-z]+)\s+([0-9]{1,2}:[0-9]{2})(?:\s*[-–]\s*([0-9]{1,2}:[0-9]{2}))?/',$part,$m)) continue;
            $days=['mon'=>1,'monday'=>1,'tue'=>2,'tues'=>2,'tuesday'=>2,'wed'=>3,'wednesday'=>3,'thu'=>4,'thur'=>4,'thurs'=>4,'thursday'=>4,'fri'=>5,'friday'=>5,'sat'=>6,'saturday'=>6,'sun'=>7,'sunday'=>7];
            $day=$days[strtolower($m[1])]??0; if($day<1) continue;
            $start=$m[2].':00'; $end=isset($m[3])&&$m[3]!==''?$m[3].':00':null;
            $q=$db->prepare("INSERT INTO bh_sessions (venue_id,day_of_week,start_time,end_time,price,term_time_only,frequency,start_date,end_date) VALUES (?,?,?,?,?,?,?,?,?)");
            $q->execute([(int)$db->lastInsertId(),$day,$start,$end,$price,0,'weekly',null,null]);
        }

        $summary['created_listings']++;
        if ($newUser) {
            $token = bh_activation_token($db,$userId);
            if (bh_send_welcome($email, $org, $title, $token)) $summary['emails_queued']++;
            else $summary['email_failures']++;
        }
    }
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    bh_bulk_response(500, ['ok'=>false,'error'=>'bulk_import_failed','message'=>$e->getMessage(),'summary'=>$summary]);
}

bh_bulk_response(200, ['ok'=>true,'mode'=>'import','summary'=>$summary,'message'=>'Bulk organiser import completed.']);
?>