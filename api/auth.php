<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

// A syntactically valid bcrypt hash that matches nothing. Used so that login attempts
// for unknown emails cost the same time as real ones (prevents user enumeration by timing).
const BH_DUMMY_HASH = '$2y$10$abcdefghijklmnopqrstuuabcdefghijklmnopqrstuvwxyzABCDE';

/* -------------------------------------------------------------------------
 * Generic helpers
 * ---------------------------------------------------------------------- */

function bh_auth_response(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function bh_client_ip(array $config): string {
    // Only trust a forwarded header if you explicitly configure one, e.g.
    // 'app' => ['trusted_ip_header' => 'HTTP_CF_CONNECTING_IP'] when behind Cloudflare.
    $header = (string)($config['app']['trusted_ip_header'] ?? '');
    $ip = $header !== '' ? (string)($_SERVER[$header] ?? '') : '';
    if ($ip === '') $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function bh_clean_email($value): string {
    $email = strtolower(trim((string)$value));
    return (strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : '';
}

function bh_validate_new_password(string $password, string $confirm): void {
    if (strlen($password) < 8) {
        bh_auth_response(422, ['ok'=>false,'error'=>'password_too_short','message'=>'Please choose a password with at least 8 characters.']);
    }
    // bcrypt only uses the first 72 bytes; reject rather than silently truncate.
    if (strlen($password) > 72) {
        bh_auth_response(422, ['ok'=>false,'error'=>'password_too_long','message'=>'Please choose a password of 72 characters or fewer.']);
    }
    if (!hash_equals($password, $confirm)) {
        bh_auth_response(422, ['ok'=>false,'error'=>'password_mismatch','message'=>'The passwords do not match.']);
    }
}

function bh_http_get_json(string $url, int $timeout = 8): ?array {
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method'=>'GET','timeout'=>$timeout,'ignore_errors'=>true,'header'=>"Accept: application/json\r\n"]]);
        $raw = @file_get_contents($url, false, $ctx);
    }
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) ? $data : null;
}

/**
 * Read a define('NAME','value'); from wp-config.php without executing WordPress.
 * Ignores indented/commented lines that don't start with define().
 */
function bh_read_wp_constant(string $name): string {
    $candidates = array_filter([
        !empty($_SERVER['DOCUMENT_ROOT']) ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/wp-config.php' : '',
        '/public_html/wp-config.php',
        dirname(__DIR__) . '/wp-config.php',
        dirname(__DIR__, 2) . '/wp-config.php',
    ]);
    $pattern = '/^[ \t]*define\s*\(\s*([\'"])' . preg_quote($name, '/') . '\1\s*,\s*([\'"])(.*?)\2\s*\)\s*;/m';
    foreach ($candidates as $file) {
        if (!is_file($file)) continue;
        $contents = @file_get_contents($file);
        if ($contents === false) continue;
        if (preg_match($pattern, $contents, $m)) return stripcslashes($m[3]);
    }
    return '';
}

/* -------------------------------------------------------------------------
 * Rate limiting (DB backed, fails open if the table cannot be created)
 * ---------------------------------------------------------------------- */

function bh_rate_limit(PDO $db, string $bucket, int $max, int $windowSeconds): void {
    static $ready = false;
    try {
        if (!$ready) {
            $db->exec("CREATE TABLE IF NOT EXISTS bh_rate_limits (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,bucket CHAR(64) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY idx_bucket_time(bucket,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $ready = true;
        }
        $key = hash('sha256', $bucket);
        $q = $db->prepare("SELECT COUNT(*) FROM bh_rate_limits WHERE bucket=? AND created_at > (NOW() - INTERVAL " . (int)$windowSeconds . " SECOND)");
        $q->execute([$key]);
        if ((int)$q->fetchColumn() >= $max) {
            header('Retry-After: ' . $windowSeconds);
            bh_auth_response(429, ['ok'=>false,'error'=>'rate_limited','message'=>'Too many attempts. Please wait a little while and try again.']);
        }
        $db->prepare("INSERT INTO bh_rate_limits (bucket) VALUES (?)")->execute([$key]);
        if (random_int(1, 100) === 1) {
            $db->exec("DELETE FROM bh_rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)");
        }
    } catch (PDOException $e) {
        error_log('Bubba Hub rate limiter unavailable: ' . $e->getMessage());
    }
}

function bh_rate_clear(PDO $db, string $bucket): void {
    try {
        $db->prepare("DELETE FROM bh_rate_limits WHERE bucket=?")->execute([hash('sha256', $bucket)]);
    } catch (PDOException $e) {}
}

/* -------------------------------------------------------------------------
 * Email
 * ---------------------------------------------------------------------- */

function bh_send_family_welcome(string $email, string $firstName=''): bool {
    try {
        require_once __DIR__.'/mailer.php';
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        $safeName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $greeting = $safeName !== '' ? 'Hi ' . $safeName . '!' : 'Welcome!';
        $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#26352b;max-width:640px;margin:0 auto">'
            . '<h1 style="color:#416651">Welcome to Bubba Hub 💚</h1>'
            . '<p>' . $greeting . '</p>'
            . '<p>Your family account is ready. Bubba Hub helps you find, save, plan and book family activities across Devon &amp; Cornwall.</p>'
            . '<p><strong>Next steps:</strong></p>'
            . '<ul><li>Add your child or children to your family profiles.</li><li>Save activities you love.</li><li>Build your family planner.</li><li>Explore upcoming events and bookings.</li></ul>'
            . '<p><a href="https://bubbahub.co.uk/my-hub.html" style="display:inline-block;padding:12px 20px;background:#416651;color:#fff;text-decoration:none;border-radius:8px">Open My Hub</a></p>'
            . '<p>You can update your account and notification preferences at any time.</p>'
            . '<p>The Bubba Hub team</p></div>';

        $plain = "Welcome to Bubba Hub 💚\n\n"
            . ($firstName !== '' ? "Hi {$firstName}!\n\n" : '')
            . "Your family account is ready. Bubba Hub helps you find, save, plan and book family activities across Devon & Cornwall.\n\n"
            . "Open My Hub: https://bubbahub.co.uk/my-hub.html\n\n"
            . "The Bubba Hub team";

        return (bool)bh_send_smtp_mail($email, 'Welcome to Bubba Hub 💚', $html, $plain);
    } catch (Throwable $e) {
        // A mail problem must never break sign-up after the account has been created.
        error_log('Bubba Hub welcome email failed: ' . $e->getMessage());
        return false;
    }
}

/* -------------------------------------------------------------------------
 * Accounts / sessions
 * ---------------------------------------------------------------------- */

function bh_create_leader_organiser(PDO $db, int $userId, string $email, string $displayName='New organiser', string $phone='', string $website=''): int {
    $displayName = trim($displayName) !== '' ? mb_substr(trim($displayName), 0, 190) : 'New organiser';
    $columns = [];
    try {
        $q = $db->query("SHOW COLUMNS FROM bh_organisers");
        foreach ($q->fetchAll() as $row) {
            $field=(string)($row['Field']??'');
            if($field!=='') $columns[$field]=[
                'Null'=>(string)($row['Null']??'YES'),
                'Default'=>$row['Default']??null,
                'Extra'=>(string)($row['Extra']??'')
            ];
        }
    } catch(Throwable $e) {
        throw new RuntimeException('Leader organiser setup is not available yet.');
    }

    $nameField=isset($columns['organisation_name'])?'organisation_name':(isset($columns['name'])?'name':(isset($columns['organisation'])?'organisation':(isset($columns['business_name'])?'business_name':'')));
    if($nameField==='') {
        throw new RuntimeException('Leader organiser setup is missing its organisation name column.');
    }

    // Older live bh_organisers schemas have no slug/status columns.
    // Only populate optional fields when the actual table supports them.
    $fields=[$nameField]; $values=[$displayName];
    if(isset($columns['slug'])) {
        $base=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-', $displayName),'-'));
        $base=$base!==''?$base:'leader';
        $slug=$base; $n=2;
        for ($i=0; $i<50; $i++) {
            $q=$db->prepare("SELECT id FROM bh_organisers WHERE slug=? LIMIT 1");
            $q->execute([$slug]);
            if(!$q->fetch()) break;
            $slug=$base.'-'.$n++;
        }
        if($i>=50) $slug=$base.'-'.bin2hex(random_bytes(4));
        $fields[]='slug'; $values[]=$slug;
    }
    if(isset($columns['status'])) { $fields[]='status'; $values[]='pending'; }
    if(isset($columns['user_id'])){$fields[]='user_id';$values[]=$userId;}
    if(isset($columns['email'])){$fields[]='email';$values[]=$email;}
    if(isset($columns['phone'])){$fields[]='phone';$values[]=$phone!==''?$phone:null;}
    if(isset($columns['website'])){$fields[]='website';$values[]=$website!==''?$website:null;}
    if(isset($columns['description'])){$fields[]='description';$values[]='';}

    /* Fill common legacy NOT NULL organiser fields automatically. */
    foreach($columns as $field=>$meta){
        $required=((string)($meta['Null']??'YES'))==='NO' && ($meta['Default']??null)===null && stripos((string)($meta['Extra']??''),'auto_increment')===false;
        if(!$required || in_array($field,$fields,true)) continue;
        $f=strtolower($field); $value='';
        if(in_array($f,['contact_name','contact_person','leader_name','owner_name','contact_full_name'],true)) $value=$displayName;
        elseif(in_array($f,['created_by','owner_user_id','leader_user_id','account_user_id'],true)) $value=$userId;
        elseif(in_array($f,['terms_accepted','terms_agreed','organiser_terms_accepted','active','is_active','enabled'],true)) $value=1;
        elseif(in_array($f,['terms_accepted_at','terms_agreed_at'],true)) $value=date('Y-m-d H:i:s');
        elseif(str_contains($f,'email')) $value=$email;
        elseif(str_contains($f,'phone')) $value=$phone;
        elseif(str_contains($f,'website')) $value=$website;
        elseif(str_contains($f,'description') || str_contains($f,'about') || str_contains($f,'notes')) $value='';
        elseif(str_contains($f,'name') || str_contains($f,'title') || str_contains($f,'address') || str_contains($f,'town') || str_contains($f,'city') || str_contains($f,'region') || str_contains($f,'county')) $value='';
        else throw new RuntimeException('Leader organiser setup is missing a required field: '.$field.'.');
        $fields[]=$field; $values[]=$value;
    }

    $q=$db->prepare("INSERT INTO bh_organisers (".implode(',',$fields).") VALUES (".implode(',',array_fill(0,count($fields),'?')).")");
    $q->execute($values);
    return (int)$db->lastInsertId();
}

/** Status + role/context gate shared by password and social sign-in. */
function bh_check_account_context(array $user, string $ctx): void {
    if (($user['status'] ?? '') !== 'active') {
        bh_auth_response(403, ['ok'=>false,'error'=>'account_not_active','message'=>'This account is not currently active.']);
    }
    $role = (string)($user['role'] ?? '');
    if ($ctx === 'leader' && $role !== 'leader') {
        bh_auth_response(403, ['ok'=>false,'error'=>'leader_account_required','message'=>'That email is not a class leader account. Please use the family sign in or create a leader account with a separate email.']);
    }
    if ($ctx === 'family' && !in_array($role, ['family','organiser','leader'], true)) {
        bh_auth_response(403, ['ok'=>false,'error'=>'family_account_required','message'=>'Please use the Class Leader sign in for this account.']);
    }
}

function bh_login_session(int $userId, string $ctx): void {
    session_regenerate_id(true);
    $_SESSION['bh_user_id'] = $userId;
    unset($_SESSION['bh_family_authenticated'], $_SESSION['bh_leader_authenticated']);
    if ($ctx === 'leader') $_SESSION['bh_leader_authenticated'] = true;
    else $_SESSION['bh_family_authenticated'] = true;

    $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
}

function bh_ensure_social_table(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS bh_social_accounts (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,provider VARCHAR(32) NOT NULL,provider_user_id VARCHAR(191) NOT NULL,email VARCHAR(255) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_provider_user(provider,provider_user_id),UNIQUE KEY uq_user_provider(user_id,provider),KEY idx_social_user(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function bh_ensure_email_verification_schema(PDO $db): void {
    // Keep this self-healing for existing Bubba Hub databases so deployment does not
    // depend on a manual SQL migration.
    $db->exec("CREATE TABLE IF NOT EXISTS bh_email_verifications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE KEY uq_email_verification_token(token_hash),
        KEY idx_email_verification_user(user_id),
        KEY idx_email_verification_expiry(expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = [];
    try {
        $q = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_users'");
        $columns = array_map('strval', $q->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $ignored) {}

    if (!in_array('email_verified_at', $columns, true)) {
        $db->exec("ALTER TABLE bh_users ADD COLUMN email_verified_at DATETIME NULL");
    }
}

function bh_ensure_reset_table(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS bh_password_resets (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE KEY uq_password_reset_token(token_hash),
        KEY idx_password_reset_user(user_id),
        KEY idx_password_reset_expiry(expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * One code path for Google and Facebook.
 * Returns [userRow, isNewAccount]. Responds and exits on any policy failure.
 *
 * $emailTrusted: true only when the provider has verified ownership of the email
 * (Google's email_verified). Only then may we attach the social identity to an
 * existing password account with the same email.
 */
function bh_social_login(PDO $db, string $provider, string $providerId, string $email, string $displayName, string $ctx, bool $emailTrusted): array {
    bh_ensure_social_table($db); // DDL first: it would implicitly commit a transaction.

    $stmt = $db->prepare("SELECT u.id,u.email,u.role,u.status FROM bh_social_accounts s JOIN bh_users u ON u.id=s.user_id WHERE s.provider=? AND s.provider_user_id=? LIMIT 1");
    $stmt->execute([$provider, $providerId]);
    $user = $stmt->fetch();
    if ($user) {
        bh_check_account_context($user, $ctx);
        return [$user, false];
    }

    $stmt = $db->prepare("SELECT id,email,role,status FROM bh_users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        bh_check_account_context($user, $ctx);
        if (!$emailTrusted) {
            bh_auth_response(409, ['ok'=>false,'error'=>'email_exists','message'=>'An account already exists for this email. Please sign in with your email and password.']);
        }
        $db->prepare("INSERT INTO bh_social_accounts (user_id,provider,provider_user_id,email) VALUES (?,?,?,?)")
           ->execute([(int)$user['id'], $provider, $providerId, $email]);
        return [$user, false];
    }

    $role = $ctx === 'leader' ? 'leader' : 'family';
    try {
        $db->beginTransaction();
        $db->prepare("INSERT INTO bh_users (email,password_hash,role,status) VALUES (?,?,?,'active')")
           ->execute([$email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $role]);
        $userId = (int)$db->lastInsertId();
        if ($ctx === 'leader') {
            bh_create_leader_organiser($db, $userId, $email, $displayName !== '' ? $displayName : 'New organiser');
        }
        $db->prepare("INSERT INTO bh_social_accounts (user_id,provider,provider_user_id,email) VALUES (?,?,?,?)")
           ->execute([$userId, $provider, $providerId, $email]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    return [['id'=>$userId,'email'=>$email,'role'=>$role,'status'=>'active'], true];
}

function bh_social_response(array $user, bool $isNew, string $ctx, string $provider, string $firstName): void {
    $welcome = ($isNew && $ctx === 'family') ? bh_send_family_welcome((string)$user['email'], $firstName) : false;
    bh_auth_response(200, [
        'ok' => true,
        'authenticated' => true,
        'user' => ['id'=>(int)$user['id'],'email'=>$user['email'],'role'=>$user['role'],'status'=>$user['status']],
        'csrf' => $_SESSION['bh_csrf'],
        'provider' => $provider,
        'leader_authenticated' => ($ctx === 'leader'),
        'family_authenticated' => ($ctx === 'family'),
        'created_leader' => ($ctx === 'leader' && $isNew),
        'pending_review' => ($ctx === 'leader' && $isNew),
        'welcome_email_sent' => $welcome,
    ]);
}

/* -------------------------------------------------------------------------
 * Request handling
 * ---------------------------------------------------------------------- */

try {
    require __DIR__ . '/db.php';

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Keep Bubba Hub's family session separate from WordPress or other PHP apps
        // sharing the same domain, so another application cannot overwrite PHPSESSID.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('BUBBAHUBSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    // Database is lazy so anonymous ?action=me checks do not depend on MySQL being available.
    $db = null;
    $configFile = __DIR__ . '/config.php';
    $config = is_file($configFile) ? require $configFile : [];
    if (!is_array($config)) $config = [];

    // Social-auth settings: wp-config.php constants override config.php.
    if (!isset($config['google']) || !is_array($config['google'])) $config['google'] = [];
    $googleClientId = bh_read_wp_constant('BH_GOOGLE_CLIENT_ID');
    if ($googleClientId !== '') $config['google']['client_id'] = $googleClientId;

    if (!isset($config['facebook']) || !is_array($config['facebook'])) $config['facebook'] = [];
    $facebookAppId = bh_read_wp_constant('BH_FACEBOOK_APP_ID');
    $facebookAppSecret = bh_read_wp_constant('BH_FACEBOOK_APP_SECRET');
    if ($facebookAppId !== '') $config['facebook']['app_id'] = $facebookAppId;
    if ($facebookAppSecret !== '') $config['facebook']['app_secret'] = $facebookAppSecret;

    $action = trim((string)($_GET['action'] ?? 'me'));
    $ip = bh_client_ip($config);

    if (!isset($_SESSION['bh_csrf'])) {
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
    }

    /* ---------------- me (GET) ---------------- */
    if ($action === 'me') {
        $adminAuthenticated = !empty($_SESSION['bh_admin_authenticated']);
        $familyAuthenticated = !empty($_SESSION['bh_family_authenticated']);
        $leaderAuthenticated = !empty($_SESSION['bh_leader_authenticated']);
        $adminUser = ['id' => 0, 'email' => 'Admin access', 'role' => 'admin', 'status' => 'active'];

        if (empty($_SESSION['bh_user_id'])) {
            if ($adminAuthenticated) {
                bh_auth_response(200, [
                    'ok' => true, 'authenticated' => true, 'family_authenticated' => false,
                    'leader_authenticated' => false, 'is_admin' => true,
                    'user' => $adminUser, 'csrf' => $_SESSION['bh_csrf'],
                ]);
            }
            bh_auth_response(200, ['ok'=>true,'authenticated'=>false,'family_authenticated'=>false,'leader_authenticated'=>false,'is_admin'=>false,'csrf'=>$_SESSION['bh_csrf']]);
        }

        try {
            $db = bh_mysql();
        } catch (Throwable $e) {
            error_log('Bubba Hub auth session lookup failed: '.$e->getMessage());
            bh_auth_response(503, ['ok'=>false,'error'=>'auth_service_unavailable','message'=>'Your session could not be checked right now. Please try again shortly.']);
        }

        try {
            $stmt = $db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1");
            $stmt->execute([(int)$_SESSION['bh_user_id']]);
            $user = $stmt->fetch();
        } catch (Throwable $e) {
            error_log('Bubba Hub auth user lookup failed: '.$e->getMessage());
            bh_auth_response(503, ['ok'=>false,'error'=>'auth_user_lookup_failed','message'=>'Your account could not be checked right now. Please try again shortly.']);
        }

        if (!$user || $user['status'] !== 'active') {
            if ($adminAuthenticated) {
                unset($_SESSION['bh_user_id'], $_SESSION['bh_family_authenticated'], $_SESSION['bh_leader_authenticated']);
                $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
                bh_auth_response(200, ['ok'=>true,'authenticated'=>true,'is_admin'=>true,'user'=>$adminUser,'csrf'=>$_SESSION['bh_csrf']]);
            }
            $_SESSION = [];
            $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
            bh_auth_response(200, ['ok'=>true,'authenticated'=>false,'family_authenticated'=>false,'leader_authenticated'=>false,'is_admin'=>false,'csrf'=>$_SESSION['bh_csrf']]);
        }

        bh_auth_response(200, [
            'ok' => true,
            'authenticated' => $familyAuthenticated,
            'family_authenticated' => $familyAuthenticated,
            'leader_authenticated' => $leaderAuthenticated,
            'is_admin' => $adminAuthenticated,
            'user' => [
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'status' => $user['status'],
            ],
            'csrf' => $_SESSION['bh_csrf'],
        ]);
    }

    // Render a friendly mobile-first page for verification links opened in a browser.
    // Browser verification links render branded HTML, never raw JSON.
    if ($action === 'verify_email' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $verificationResponse = static function (int $status, bool $success, string $message): void {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store, private');
            header('X-Robots-Tag: noindex, nofollow');
            $heading = $success ? 'Welcome to Bubba Hub!' : 'This verification link is no longer valid';
            $description = $success
                ? 'Thank you for confirming your email address. You can now sign in to your Bubba Hub account and start exploring.'
                : $message;
            $heading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
            $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
            $icon = $success ? '&#10003;' : '&#9993;';
            $cardClass = $success ? 'success' : 'failure';
            $extra = $success
                ? '<div class="bh-v-features"><span>&#128197; <strong>Find activities</strong></span><span>&#9825; <strong>Plan your week</strong></span><span>&#127912; <strong>Class leaders</strong></span></div>'
                : '<p class="bh-v-note">If you have already verified your email, you can sign in. Otherwise, please contact our support team for help with a new link.</p>';
            echo '<!doctype html><html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Email verification | Bubba Hub</title><link rel="stylesheet" href="/assets/css/styles.css"><style>
            body{margin:0;background:#f8faf7;color:#194b36;font-family:Quicksand,Calibri,Arial,sans-serif}
            .bh-v-header{padding:24px 18px 12px;text-align:center;background:white}
            .bh-v-header img{width:min(310px,78vw);height:auto;max-height:120px;object-fit:contain}
            .bh-v-card{box-sizing:border-box;max-width:650px;margin:24px auto 32px;padding:36px 30px 24px;border-radius:32px;text-align:center;background:linear-gradient(155deg,#eef7ed 0%,#fff 68%);border:1px solid #dce9dc}
            .bh-v-card.failure{background:linear-gradient(155deg,#fff2eb 0%,#fff 70%);border-color:#f2e4dc}
            .bh-v-icon{display:grid;place-items:center;width:100px;height:100px;margin:0 auto 22px;border-radius:50%;background:#c8e7c0;color:#144400;font-size:60px;font-weight:800}
            .failure .bh-v-icon{background:#f9d5d4;color:#a83f40;font-size:55px}
            .bh-v-card h1{font-size:clamp(31px,6vw,46px);line-height:1.12;letter-spacing:-.035em;margin:0 0 18px;color:#144400;font-weight:800}
            .bh-v-card p{font-size:18px;line-height:1.6;margin:0 auto 24px;max-width:510px}
            .bh-v-btn{display:block;padding:17px 20px;border-radius:999px;background:#144400;color:white!important;text-decoration:none!important;font-size:17px;font-weight:800;margin:12px 0}
            .bh-v-btn:hover,.bh-v-btn:focus{background:#df863e;color:#144400!important}
            .bh-v-btn.secondary{background:#dcebd9;color:#144400!important}
            .bh-v-features{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;background:#fff;border-radius:22px;padding:20px 8px;margin:24px 0}
            .bh-v-features span{font-size:14px;line-height:1.5;color:#144400}
            .bh-v-features strong{display:block}
            .bh-v-note{font-size:15px!important}
            .bh-v-help{margin:22px auto 0;padding:20px;border-radius:20px;background:#def0f1;text-align:left}
            .bh-v-help p{font-size:15px;margin:8px 0 0}.bh-v-help a{color:#206d77;font-weight:700}
            .bh-v-footer{padding:0 16px 28px;text-align:center}
            @media(max-width:700px){.bh-v-card{margin:14px 12px 24px;padding:30px 20px 20px;border-radius:28px}.bh-v-header img{max-height:100px}.bh-v-features{padding:16px 4px}.bh-v-features span{font-size:12px}}
            </style></head><body><header class="bh-v-header"><a href="/"><img src="/wp-content/uploads/logo/logoheader.png" alt="Bubba Hub"></a></header><main class="bh-v-card '.$cardClass.'"><div class="bh-v-icon" aria-hidden="true">'.$icon.'</div><h1>'.$heading.'</h1><p>'.$description.'</p><a class="bh-v-btn" href="/auth.html?context=leader">Sign in to Bubba Hub &rarr;</a>'.$extra.'<a class="bh-v-btn secondary" href="/">Go to Bubba Hub homepage &rarr;</a><div class="bh-v-help"><strong>Need help?</strong><p>Visit <a href="/help-support">Support &amp; Guidance &rarr;</a> if you have trouble accessing your account.</p></div></main><footer class="bh-v-footer">Bubba Hub South West</footer></body></html>';
            exit;
        };

    // Verification links are intentionally usable as a normal GET request.
        try {
            $db = bh_mysql();
            bh_ensure_email_verification_schema($db);
            $token = trim((string)($_GET['token'] ?? ''));
            if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
                $verificationResponse(400, false, 'This verification link is not valid.');
            }
            $stmt = $db->prepare("SELECT id,user_id,expires_at,used_at FROM bh_email_verifications WHERE token_hash=? LIMIT 1");
            $stmt->execute([hash('sha256',$token)]);
            $v=$stmt->fetch();
            if (!$v || $v['used_at'] !== null || strtotime((string)$v['expires_at']) < time()) {
                $verificationResponse(400, false, 'This verification link has expired or has already been used. Please request a new one.');
            }
            $db->beginTransaction();
            try {
                $db->prepare("UPDATE bh_users SET email_verified_at=NOW() WHERE id=?")->execute([(int)$v['user_id']]);
                $db->prepare("UPDATE bh_email_verifications SET used_at=NOW() WHERE id=?")->execute([(int)$v['id']]);
                $db->commit();
            } catch(Throwable $e) {
                if($db->inTransaction()) $db->rollBack();
                throw $e;
            }
            $verificationResponse(200, true, 'Your email has been verified. You can now sign in.');
        } catch (Throwable $e) {
            error_log('Bubba Hub email verification failed: '.$e->getMessage());
            $verificationResponse(500, false, 'We could not verify this email right now. Please try again shortly.');
        }
    }

    /* ---------------- Everything below is POST-only ---------------- */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        bh_auth_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
    }

    // Reject cross-site browser requests outright.
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $originHost = (string)parse_url($origin, PHP_URL_HOST);
        $requestHost = (string)strtok((string)($_SERVER['HTTP_HOST'] ?? ''), ':');
        if ($originHost === '' || strcasecmp($originHost, $requestHost) !== 0) {
            bh_auth_response(403, ['ok'=>false,'error'=>'origin_not_allowed']);
        }
    }

    $body = json_decode((string)file_get_contents('php://input', false, null, 0, 65536), true);
    if (!is_array($body)) {
        bh_auth_response(400, ['ok' => false, 'error' => 'invalid_json']);
    }

    if (!in_array($action, ['login','register','leader_register','leader_upgrade','resend_verification','google','facebook','forgot_password','reset_password','logout'], true)) {
        bh_auth_response(400, ['ok' => false, 'error' => 'unknown_action']);
    }

    // CSRF: every state-changing action must present the token from ?action=me,
    // either as "csrf" in the JSON body or an X-CSRF-Token header.
    // reset_password is exempt because it is authorised by the single-use emailed token.
    $csrfEnforced = (bool)($config['app']['enforce_csrf'] ?? true);
    if ($csrfEnforced && $action !== 'reset_password') {
        $sentCsrf = (string)($body['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($sentCsrf === '' || empty($_SESSION['bh_csrf']) || !hash_equals((string)$_SESSION['bh_csrf'], $sentCsrf)) {
            bh_auth_response(403, ['ok'=>false,'error'=>'csrf_invalid','message'=>'Please refresh the page and try again.']);
        }
    }

    $db = bh_mysql();

    $authContext = trim((string)($body['context'] ?? 'family'));
    if (!in_array($authContext, ['family','leader'], true)) $authContext = 'family';

    /* Resend verification only to the signed-in account's registered email. */
    if ($action === 'resend_verification') {
        $userId=(int)($_SESSION['bh_user_id'] ?? 0);
        if ($userId<1 || (empty($_SESSION['bh_family_authenticated']) && empty($_SESSION['bh_leader_authenticated']))) {
            bh_auth_response(401,['ok'=>false,'message'=>'Please sign in before requesting a verification email.']);
        }
        bh_rate_limit($db,'verify-resend-user:'.$userId,3,3600);
        $stmt=$db->prepare("SELECT email,email_verified_at FROM bh_users WHERE id=? AND status='active' LIMIT 1");
        $stmt->execute([$userId]);$user=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) bh_auth_response(403,['ok'=>false,'message'=>'Your account is not active.']);
        if (!empty($user['email_verified_at'])) bh_auth_response(200,['ok'=>true,'already_verified'=>true,'message'=>'Your email is already verified. Refresh and try again.']);
        try {
            bh_ensure_email_verification_schema($db);
            $token=bin2hex(random_bytes(32));
            $db->prepare("INSERT INTO bh_email_verifications (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR))")->execute([$userId,hash('sha256',$token)]);
            $base=rtrim((string)(($config['app']['base_url'] ?? '') ?: (getenv('BUBBAHUB_BASE_URL') ?: 'https://bubbahub.co.uk')),'/');
            $url=$base.'/api/auth.php?action=verify_email&token='.rawurlencode($token);
            $html='<div style="font-family:Arial,sans-serif;color:#144400;max-width:560px;margin:auto"><h2>Verify your Bubba Hub email</h2><p>Confirm your email address to continue setting up your class leader profile.</p><p><a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'" style="background:#144400;color:white;padding:12px 18px;border-radius:9px;display:inline-block;text-decoration:none">Verify my email</a></p><p>This link expires in 24 hours.</p></div>';
            require_once __DIR__.'/mailer.php';
            $sent=bh_send_smtp_mail((string)$user['email'],'Verify your Bubba Hub email',$html,"Verify your Bubba Hub email: ".$url."\nThis link expires in 24 hours.");
            if (!$sent) bh_auth_response(503,['ok'=>false,'message'=>'The verification email could not be sent. Please contact Bubba Hub support.']);
            bh_auth_response(200,['ok'=>true,'message'=>'Verification email sent to your registered email address. Check your inbox and spam folder.']);
        } catch(Throwable $e) {
            error_log('Bubba Hub verification resend failed: '.$e->getMessage());
            bh_auth_response(500,['ok'=>false,'message'=>'Unable to send verification email right now. Please try again later.']);
        }
    }

    /* Upgrade an existing verified family account without creating a second user. */
    if ($action === 'leader_upgrade') {
        $userId = (int)($_SESSION['bh_user_id'] ?? 0);
        if ($userId < 1 || empty($_SESSION['bh_family_authenticated'])) {
            bh_auth_response(401, ['ok'=>false,'error'=>'login_required','message'=>'Sign in to your Bubba Hub account first.']);
        }
        $stmt=$db->prepare("SELECT id,email,role,status,email_verified_at FROM bh_users WHERE id=? LIMIT 1");
        $stmt->execute([$userId]);
        $user=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['status']!=='active') bh_auth_response(403,['ok'=>false,'message'=>'Your account is not active.']);
        if (empty($user['email_verified_at'])) bh_auth_response(403,['ok'=>false,'message'=>'Verify your email before becoming a class leader.']);
        if ($user['role']==='leader') bh_auth_response(409,['ok'=>false,'message'=>'You already have a class leader account.']);
        if ($user['role']!=='family') bh_auth_response(403,['ok'=>false,'message'=>'This account cannot be upgraded here.']);
        $name=trim((string)($body['organisation_name']??''));
        if ($name==='' || mb_strlen($name)>190) bh_auth_response(422,['ok'=>false,'message'=>'Enter your class or business name (up to 190 characters).']);
        if (empty($body['terms'])) bh_auth_response(422,['ok'=>false,'message'=>'Please agree to the organiser terms.']);
        try {
            $db->beginTransaction();
            $organiserId=bh_create_leader_organiser($db,$userId,(string)$user['email'],$name);
            $update=$db->prepare("UPDATE bh_users SET role='leader' WHERE id=? AND role='family'");
            $update->execute([$userId]);
            if ($update->rowCount()!==1) throw new RuntimeException('Account was changed during upgrade.');
            $db->commit();
            $_SESSION['bh_family_authenticated']=true;
            $_SESSION['bh_leader_authenticated']=true;
            bh_auth_response(200,['ok'=>true,'organiser_id'=>$organiserId,'message'=>'Your leader profile has been created and is pending review.']);
        } catch(Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Bubba Hub leader upgrade failed: '.$e->getMessage());
            bh_auth_response(500,['ok'=>false,'error'=>'leader_upgrade_failed','message'=>'We could not set up your leader profile. Please contact support.']);
        }
    }

    /* ---------------- leader_register ---------------- */
    if ($action === 'leader_register') {
        bh_rate_limit($db, "register-ip:$ip", 10, 3600);

        $email = bh_clean_email($body['email'] ?? '');
        $password = (string)($body['password'] ?? '');
        $confirm = (string)($body['confirm_password'] ?? '');
        // Class/business details are completed later in the leader portal.
        $organisation = trim((string)($body['organisation_name'] ?? ''));
        $phone = trim((string)($body['phone'] ?? ''));
        $website = trim((string)($body['website'] ?? ''));
        if ($organisation === '') $organisation = 'New Bubba Hub Leader';
        $terms = !empty($body['terms']);

        if (mb_strlen($organisation) > 190) bh_auth_response(422, ['ok'=>false,'error'=>'organisation_invalid','message'=>'Please check your class or business name.']);
        if ($email === '') bh_auth_response(422, ['ok'=>false,'error'=>'invalid_email','message'=>'Please enter a valid email address.']);
        if ($phone !== '' && mb_strlen($phone) > 80) bh_auth_response(422, ['ok'=>false,'error'=>'invalid_phone','message'=>'Please check your phone number.']);
        if ($website !== '' && (mb_strlen($website) > 255 || !filter_var($website, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $website))) {
            bh_auth_response(422, ['ok'=>false,'error'=>'invalid_website','message'=>'Please enter a full website address starting with http:// or https://.']);
        }
        bh_validate_new_password($password, $confirm);
        if (!$terms) bh_auth_response(422, ['ok'=>false,'error'=>'terms_required','message'=>'Please confirm that you agree to the Bubba Hub organiser terms.']);

        $check = $db->prepare("SELECT id FROM bh_users WHERE email=? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) {
            bh_auth_response(409, ['ok'=>false,'error'=>'email_exists','message'=>'An account already exists for this email. Please sign in instead.']);
        }

        try {
            bh_ensure_email_verification_schema($db);
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO bh_users (email,password_hash,role,status,email_verified_at) VALUES (?,?, 'leader','active',NULL)");
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int)$db->lastInsertId();
            $organiserId = bh_create_leader_organiser($db, $userId, $email, $organisation, $phone, $website);
            $rawToken=bin2hex(random_bytes(32));
            $db->prepare("INSERT INTO bh_email_verifications (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR))")->execute([$userId,hash('sha256',$rawToken)]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof PDOException && (string)$e->getCode() === '23000') {
                bh_auth_response(409, ['ok'=>false,'error'=>'email_exists','message'=>'An account already exists for this email. Please sign in instead.']);
            }
            error_log('Bubba Hub leader registration failed: '.$e->getMessage());
            bh_auth_response(500, ['ok'=>false,'error'=>'leader_registration_error','message'=>'We could not create your leader account just yet. Please try again.']);
        }

        /* Do not authenticate a leader until their email is verified. */
        $base = rtrim((string)(($config['app']['base_url'] ?? '') ?: (getenv('BUBBAHUB_BASE_URL') ?: 'https://bubbahub.co.uk')), '/');
        $verificationUrl = $base . '/api/auth.php?action=verify_email&token=' . rawurlencode($rawToken);
        $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#144400"><h1 style="color:#416651">Verify your Bubba Hub leader account</h1><p>Thanks for registering as a Bubba Hub leader.</p><p>Please verify your email address before signing in.</p><p><a href="' . htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 18px;background:#416651;color:#fff;text-decoration:none;border-radius:10px">Verify my email</a></p><p>This link expires in 24 hours and can only be used once.</p><p>If you did not create this account, you can ignore this email.</p></div>';
        $plain = "Verify your Bubba Hub leader account\n\nOpen this link within 24 hours:\n{$verificationUrl}\n\nIf you did not create this account, ignore this email.";
        require_once __DIR__.'/mailer.php';
        $verificationEmailSent = bh_send_smtp_mail($email, 'Verify your Bubba Hub leader account', $html, $plain);

        bh_auth_response(201, [
            'ok'=>true,
            'authenticated'=>false,
            'leader_authenticated'=>false,
            'pending_review'=>true,
            'organiser_id'=>$organiserId,
            'user'=>['id'=>$userId,'email'=>$email,'role'=>'leader','status'=>'active'],
            'csrf'=>$_SESSION['bh_csrf'],
            'message'=>'Account created. Please check your email and click the verification link before signing in.','verification_email_sent'=>$verificationEmailSent
        ]);
    }

    /* ---------------- email verification (legacy POST compatibility) ---------------- */
    if ($action === 'verify_email') {
        $token = trim((string)($body['token'] ?? ($_GET['token'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) bh_auth_response(400, ['ok'=>false,'error'=>'invalid_verification_token','message'=>'This verification link is not valid.']);
        $hash = hash('sha256', $token);
        $stmt = $db->prepare("SELECT id,user_id,expires_at,used_at FROM bh_email_verifications WHERE token_hash=? LIMIT 1");
        $stmt->execute([$hash]); $v=$stmt->fetch();
        if (!$v || $v['used_at'] !== null || strtotime((string)$v['expires_at']) < time()) bh_auth_response(400, ['ok'=>false,'error'=>'verification_expired','message'=>'This verification link has expired or has already been used. Please request a new one.']);
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE bh_users SET email_verified_at=NOW() WHERE id=?")->execute([(int)$v['user_id']]);
            $db->prepare("UPDATE bh_email_verifications SET used_at=NOW() WHERE id=?")->execute([(int)$v['id']]);
            $db->commit();
        } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
        bh_auth_response(200,['ok'=>true,'verified'=>true,'message'=>'Your email has been verified. You can now sign in.']);
    }

    /* ---------------- login ---------------- */
    if ($action === 'login') {
        bh_rate_limit($db, "login-ip:$ip", 40, 900);

        $email = bh_clean_email($body['email'] ?? '');
        $password = (string)($body['password'] ?? '');

        if ($email === '' || $password === '' || strlen($password) > 1024) {
            bh_auth_response(422, ['ok' => false, 'error' => 'email_password_required']);
        }

        $acctBucket = "login-acct:$ip:$email";
        bh_rate_limit($db, $acctBucket, 8, 900);

        $stmt = $db->prepare("SELECT id,email,password_hash,role,status,email_verified_at FROM bh_users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always run one password_verify so response time doesn't reveal whether the email exists.
        $passwordOk = password_verify($password, $user ? (string)$user['password_hash'] : BH_DUMMY_HASH);
        if (!$user || !$passwordOk) {
            bh_auth_response(401, ['ok' => false, 'error' => 'invalid_login', 'message' => 'That email or password is not correct.']);
        }

        if ($authContext === 'leader' && (($user['role'] ?? '') !== 'leader' || empty($user['email_verified_at']))) {
            bh_auth_response(403, ['ok'=>false,'error'=>'email_not_verified','message'=>'Please verify your email address using the link we sent before signing in as a leader.']);
        }

        bh_check_account_context($user, $authContext);
        bh_rate_clear($db, $acctBucket);

        if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
            try {
                $db->prepare("UPDATE bh_users SET password_hash=? WHERE id=?")->execute([password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]);
            } catch (Throwable $ignored) {}
        }

        bh_login_session((int)$user['id'], $authContext);

        bh_auth_response(200, [
            'ok' => true,
            'authenticated' => true,
            'user' => ['id' => (int)$user['id'], 'email' => $user['email'], 'role' => $user['role'], 'status' => $user['status']],
            'csrf' => $_SESSION['bh_csrf'],
        ]);
    }

    /* ---------------- facebook ---------------- */
    if ($action === 'facebook') {
        if ($authContext === 'leader') bh_auth_response(403,['ok'=>false,'error'=>'leader_social_disabled','message'=>'Leader accounts must register and sign in with email and password.']);
        bh_rate_limit($db, "social-ip:$ip", 30, 900);

        $accessToken = trim((string)($body['access_token'] ?? ''));
        if ($accessToken === '' || strlen($accessToken) > 4096) {
            bh_auth_response(422, ['ok'=>false,'error'=>'facebook_access_token_required','message'=>'Facebook sign-in could not be started.']);
        }
        $appId = trim((string)($config['facebook']['app_id'] ?? ''));
        $appSecret = trim((string)($config['facebook']['app_secret'] ?? ''));
        if ($appId === '' || $appSecret === '') {
            bh_auth_response(503, ['ok'=>false,'error'=>'facebook_not_configured','message'=>'Facebook sign-in is not configured yet.']);
        }

        // Network verification only inside this try; DB errors are handled separately
        // so internal error text can never reach the client.
        $facebookId = ''; $email = ''; $fbName = '';
        try {
            $debug = bh_http_get_json('https://graph.facebook.com/debug_token?input_token=' . rawurlencode($accessToken) . '&access_token=' . rawurlencode($appId . '|' . $appSecret));
            $data = is_array($debug['data'] ?? null) ? $debug['data'] : [];
            if (empty($data['is_valid']) || !hash_equals($appId, (string)($data['app_id'] ?? '')) || empty($data['user_id'])) {
                throw new RuntimeException('Facebook token invalid.');
            }
            $me = bh_http_get_json('https://graph.facebook.com/me?fields=id,name,email&access_token=' . rawurlencode($accessToken));
            if (!is_array($me) || !empty($me['error'])) throw new RuntimeException('Facebook profile unavailable.');
            $facebookId = trim((string)($me['id'] ?? ''));
            // The verified token's user must be the profile we read.
            if ($facebookId === '' || !hash_equals((string)$data['user_id'], $facebookId)) throw new RuntimeException('Facebook identity mismatch.');
            $email = bh_clean_email($me['email'] ?? '');
            $fbName = (string)($me['name'] ?? '');
        } catch (Throwable $e) {
            error_log('Bubba Hub Facebook verification failed: ' . $e->getMessage());
            bh_auth_response(401, ['ok'=>false,'error'=>'facebook_invalid_token','message'=>'Facebook could not verify this sign-in. Please try again.']);
        }
        if ($email === '') {
            bh_auth_response(422, ['ok'=>false,'error'=>'facebook_email_missing','message'=>'Facebook did not provide an email address. Please allow the email permission and try again.']);
        }

        // Facebook does not guarantee a verified email, so never auto-link to an existing account by email.
        [$user, $isNew] = bh_social_login($db, 'facebook', $facebookId, $email, $fbName, $authContext, false);
        bh_login_session((int)$user['id'], $authContext);
        bh_social_response($user, $isNew, $authContext, 'facebook', (string)strtok($fbName, ' '));
    }

    /* ---------------- google ---------------- */
    if ($action === 'google') {
        if ($authContext === 'leader') bh_auth_response(403,['ok'=>false,'error'=>'leader_social_disabled','message'=>'Leader accounts must register and sign in with email and password.']);
        bh_rate_limit($db, "social-ip:$ip", 30, 900);

        // Expects the Google Identity Services JS callback: POST {credential: response.credential, context, csrf}.
        $credential = trim((string)($body['credential'] ?? ''));
        if ($credential === '' || strlen($credential) > 4096) {
            bh_auth_response(422, ['ok'=>false,'error'=>'google_credential_required','message'=>'Google sign-in could not be started.']);
        }
        $clientId = trim((string)($config['google']['client_id'] ?? ''));
        if ($clientId === '') {
            bh_auth_response(503, ['ok'=>false,'error'=>'google_not_configured','message'=>'Google sign-in is not configured yet.']);
        }

        $google = bh_http_get_json('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential));
        $issuerOk = is_array($google) && in_array((string)($google['iss'] ?? ''), ['https://accounts.google.com','accounts.google.com'], true);
        if (!is_array($google) || !empty($google['error']) || !empty($google['error_description'])
            || !hash_equals($clientId, (string)($google['aud'] ?? ''))
            || !$issuerOk
            || (int)($google['exp'] ?? 0) < time()) {
            bh_auth_response(401, ['ok'=>false,'error'=>'google_invalid_token','message'=>'Google could not verify this sign-in. Please try again.']);
        }

        $email = bh_clean_email($google['email'] ?? '');
        $googleId = trim((string)($google['sub'] ?? ''));
        $emailVerified = filter_var($google['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($email === '' || $googleId === '' || !$emailVerified) {
            bh_auth_response(401, ['ok'=>false,'error'=>'google_unverified_email','message'=>'Google did not provide a verified email address.']);
        }

        [$user, $isNew] = bh_social_login($db, 'google', $googleId, $email, (string)($google['name'] ?? ''), $authContext, true);
        bh_login_session((int)$user['id'], $authContext);
        bh_social_response($user, $isNew, $authContext, 'google', (string)($google['given_name'] ?? ''));
    }

    /* ---------------- forgot_password ---------------- */
    if ($action === 'forgot_password') {
        $generic = 'If an account exists for that email, a password reset link has been sent.';
        bh_rate_limit($db, "forgot-ip:$ip", 6, 3600);

        $email = bh_clean_email($body['email'] ?? '');
        if ($email === '') bh_auth_response(200, ['ok' => true, 'message' => $generic]);
        bh_rate_limit($db, "forgot-email:$email", 3, 3600);

        bh_ensure_reset_table($db);

        $stmt = $db->prepare("SELECT id,status FROM bh_users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && ($user['status'] ?? '') === 'active') {
            $rawToken = bin2hex(random_bytes(32));
            $hash = hash('sha256', $rawToken);

            $db->prepare("UPDATE bh_password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL")->execute([(int)$user['id']]);
            // Expiry is computed by MySQL so it always agrees with the NOW() used when the token is redeemed.
            $db->prepare("INSERT INTO bh_password_resets (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR))")->execute([(int)$user['id'],$hash]);

            $base = rtrim((string)(($config['app']['base_url'] ?? '') ?: (getenv('BUBBAHUB_BASE_URL') ?: 'https://bubbahub.co.uk')), '/');
            $resetUrl = $base . '/reset-password.html?token=' . rawurlencode($rawToken);
            $apiKey = (string)(($config['resend']['api_key'] ?? '') ?: (getenv('RESEND_API_KEY') ?: ''));
            $from = (string)(($config['resend']['from'] ?? '') ?: (getenv('RESEND_FROM') ?: 'Bubba Hub <noreply@bubbahub.co.uk>'));
            $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#144400"><h1 style="color:#416651">Reset your Bubba Hub password</h1><p>We received a request to reset your Bubba Hub password.</p><p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 18px;background:#416651;color:#fff;text-decoration:none;border-radius:10px">Reset my password</a></p><p>This link expires in 1 hour and can only be used once.</p><p>If you did not request this, you can safely ignore this email.</p></div>';
            $plain = "Reset your Bubba Hub password\n\nOpen this link within 1 hour:\n{$resetUrl}\n\nIf you did not request this, ignore this email.";

            // Failures are logged but the response stays generic, so the endpoint
            // cannot be used to discover which emails have accounts.
            try {
                if ($apiKey !== '') {
                    $ch = curl_init('https://api.resend.com/emails');
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
                        CURLOPT_POSTFIELDS => json_encode(['from'=>$from,'to'=>[$email],'subject'=>'Reset your Bubba Hub password','html'=>$html,'text'=>$plain], JSON_UNESCAPED_SLASHES),
                    ]);
                    curl_exec($ch);
                    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($http < 200 || $http >= 300) error_log('Bubba Hub reset email failed via Resend, HTTP ' . $http);
                } else {
                    require_once __DIR__.'/mailer.php';
                    if (!bh_send_smtp_mail($email, 'Reset your Bubba Hub password', $html, $plain)) {
                        error_log('Bubba Hub reset email failed via SMTP');
                    }
                }
            } catch (Throwable $e) {
                error_log('Bubba Hub reset email error: ' . $e->getMessage());
            }
        }

        bh_auth_response(200, ['ok' => true, 'message' => $generic]);
    }

    /* ---------------- reset_password ---------------- */
    if ($action === 'reset_password') {
        bh_rate_limit($db, "reset-ip:$ip", 10, 3600);

        $token = trim((string)($body['token'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $confirm = (string)($body['confirm_password'] ?? '');

        if ($token === '' || strlen($token) > 128) {
            bh_auth_response(422, ['ok'=>false,'error'=>'invalid_reset_request','message'=>'This password reset link is not valid.']);
        }
        bh_validate_new_password($password, $confirm);
        bh_ensure_reset_table($db);

        $hash = hash('sha256', $token);
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("SELECT id,user_id FROM bh_password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at > NOW() LIMIT 1 FOR UPDATE");
            $stmt->execute([$hash]);
            $reset = $stmt->fetch();
            if (!$reset) {
                $db->rollBack();
                bh_auth_response(400, ['ok'=>false,'error'=>'reset_token_invalid','message'=>'This password reset link has expired or has already been used.']);
            }
            // Burn the token and every other outstanding token for the user atomically.
            $db->prepare("UPDATE bh_password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL")->execute([(int)$reset['user_id']]);
            $db->prepare("UPDATE bh_users SET password_hash=? WHERE id=? AND status='active'")->execute([password_hash($password, PASSWORD_DEFAULT), (int)$reset['user_id']]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        bh_auth_response(200, ['ok' => true, 'message' => 'Your password has been updated.']);
    }

    /* ---------------- register (family) ---------------- */
    if ($action === 'register') {
        bh_rate_limit($db, "register-ip:$ip", 10, 3600);

        $email = bh_clean_email($body['email'] ?? '');
        $password = (string)($body['password'] ?? '');
        $confirm = (string)($body['confirm_password'] ?? '');
        $firstName = mb_substr(trim((string)($body['first_name'] ?? '')), 0, 100);
        $lastName = mb_substr(trim((string)($body['last_name'] ?? '')), 0, 100);
        $eventCode = strtoupper(trim((string)($body['event_code'] ?? '')));

        if ($email === '') {
            bh_auth_response(422, ['ok'=>false,'error'=>'invalid_email','message'=>'Please enter a valid email address.']);
        }
        bh_validate_new_password($password, $confirm);

        $check = $db->prepare("SELECT id FROM bh_users WHERE email=? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) {
            bh_auth_response(409, ['ok'=>false,'error'=>'email_exists','message'=>'An account already exists for this email.']);
        }

        // Name columns were added during the account upgrade. Check the live schema so
        // registration still works on older databases where the web user cannot ALTER TABLE.
        $userColumns = [];
        try {
            $cq = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_users'");
            $userColumns = array_map('strval', $cq->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $ignored) {}
        $columns = ['email','password_hash','role','status'];
        $values = [$email, password_hash($password, PASSWORD_DEFAULT), 'family', 'active'];
        if (in_array('first_name', $userColumns, true)) { $columns[]='first_name'; $values[]=$firstName ?: null; }
        if (in_array('last_name', $userColumns, true)) { $columns[]='last_name'; $values[]=$lastName ?: null; }

        try {
            $stmt = $db->prepare("INSERT INTO bh_users (".implode(',', $columns).") VALUES (".implode(',', array_fill(0, count($columns), '?')).")");
            $stmt->execute($values);
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                bh_auth_response(409, ['ok'=>false,'error'=>'email_exists','message'=>'An account already exists for this email.']);
            }
            throw $e;
        }
        $newUserId = (int)$db->lastInsertId();

        // Event sign-ups can receive Pro access. The code must be set in config.php
        // ('app' => ['event_signup_code' => '...']) or BUBBAHUB_EVENT_CODE; there is no hard-coded default.
        $eventPro = false;
        $configuredEventCode = strtoupper(trim((string)(($config['app']['event_signup_code'] ?? '') ?: (getenv('BUBBAHUB_EVENT_CODE') ?: ''))));
        if ($eventCode !== '' && $configuredEventCode !== '' && hash_equals($configuredEventCode, $eventCode)) {
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS bh_user_subscriptions (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,plan VARCHAR(40) NOT NULL DEFAULT 'family_pro',status VARCHAR(30) NOT NULL DEFAULT 'active',source VARCHAR(40) NOT NULL DEFAULT 'event',started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,expires_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_bh_user_subscription(user_id),KEY idx_bh_subscription_status(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $q = $db->prepare("INSERT INTO bh_user_subscriptions(user_id,plan,status,source,expires_at) VALUES(?,?,?,?,NULL) ON DUPLICATE KEY UPDATE plan=VALUES(plan),status='active',source=VALUES(source),expires_at=NULL");
                $q->execute([$newUserId,'family_pro','active','event']);
                $eventPro = true;
            } catch (Throwable $e) {
                error_log('Bubba Hub event Pro grant failed: ' . $e->getMessage());
            }
        }

        bh_login_session($newUserId, 'family');
        $welcomeEmailSent = bh_send_family_welcome($email, $firstName);

        bh_auth_response(201, [
            'ok' => true,
            'authenticated' => true,
            'family_authenticated' => true,
            'leader_authenticated' => false,
            'user' => ['id' => $newUserId, 'email' => $email, 'role' => 'family', 'status' => 'active', 'first_name' => $firstName, 'last_name' => $lastName, 'pro' => $eventPro],
            'pro' => $eventPro,
            'welcome_email_sent' => $welcomeEmailSent,
            'csrf' => $_SESSION['bh_csrf'],
        ]);
    }

    /* ---------------- logout ---------------- */
    if ($action === 'logout') {
        if (!empty($_SESSION['bh_admin_authenticated'])) {
            unset($_SESSION['bh_user_id'], $_SESSION['bh_family_authenticated'], $_SESSION['bh_leader_authenticated']);
            $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
            bh_auth_response(200, ['ok' => true, 'authenticated' => true, 'is_admin' => true]);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'] ?? '',
                'secure' => (bool)$params['secure'],
                'httponly' => (bool)$params['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
        bh_auth_response(200, ['ok' => true, 'authenticated' => false]);
    }

    bh_auth_response(400, ['ok' => false, 'error' => 'unknown_action']);
} catch (Throwable $e) {
    error_log('Bubba Hub auth API error: '.$e->getMessage());
    bh_auth_response(500, ['ok' => false, 'error' => 'auth_error', 'message' => 'Authentication is temporarily unavailable. Please try again.']);
}
