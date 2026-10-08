<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_auth_response(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function bh_send_family_welcome(string $email, string $firstName=''): bool {
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

    return bh_send_smtp_mail($email, 'Welcome to Bubba Hub 💚', $html, $plain);
}
function bh_create_leader_organiser(PDO $db, int $userId, string $email, string $displayName='New organiser', string $phone='', string $website=''): int {
    $displayName = trim($displayName) !== '' ? trim($displayName) : 'New organiser';
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

    $nameField=isset($columns['organisation_name'])?'organisation_name':(isset($columns['name'])?'name':(isset($columns['organisation'])?'organisation':(isset($columns['business_name'])?'business_name':''));
    if($nameField==='' || !isset($columns['slug']) || !isset($columns['status'])) {
        throw new RuntimeException('Leader organiser setup is not available yet.');
    }

    $base=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-', $displayName),'-'));
    $base=$base!==''?$base:'leader';
    $slug=$base; $n=2;
    while(true){
        $q=$db->prepare("SELECT id FROM bh_organisers WHERE slug=? LIMIT 1"); $q->execute([$slug]);
        if(!$q->fetch()) break;
        $slug=$base.'-'.$n++;
    }

    $fields=[$nameField,'slug','status']; $values=[$displayName,$slug,'pending'];
    if(isset($columns['user_id'])){$fields[]='user_id';$values[]=$userId;}
    if(isset($columns['email'])){$fields[]='email';$values[]=$email;}
    if(isset($columns['phone'])){$fields[]='phone';$values[]=$phone!==''?$phone:null;}
    if(isset($columns['website'])){$fields[]='website';$values[]=$website!==''?$website:null;}
    if(isset($columns['description'])){$fields[]='description';$values[]='';}

    foreach($columns as $field=>$meta){
        $required=((string)($meta['Null']??'YES'))==='NO' && ($meta['Default']??null)===null && stripos((string)($meta['Extra']??''),'auto_increment')===false;
        if($required && !in_array($field,$fields,true)) {
            throw new RuntimeException('Leader organiser setup is missing a required field: '.$field.'.');
        }
    }

    $q=$db->prepare("INSERT INTO bh_organisers (".implode(',',$fields).") VALUES (".implode(',',array_fill(0,count($fields),'?')).")");
    $q->execute($values);
    return (int)$db->lastInsertId();
}

function bh_b64url_encode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}
function bh_b64url_decode(string $value): string|false {
    $value = strtr($value, '-_', '+/');
    $pad = strlen($value) % 4;
    if ($pad) $value .= str_repeat('=', 4 - $pad);
    return base64_decode($value, true);
}

try {
    require __DIR__ . '/db.php';

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (session_status() !== PHP_SESSION_ACTIVE) {
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

    // Read social-auth settings from wp-config.php without executing WordPress.
    // Loading wp-config.php directly can bootstrap environment-specific WordPress code
    // and turn otherwise simple API requests into HTTP 500 errors.
    function bh_read_wp_constant(string $name): string {
        $candidates = [
            '/public_html/wp-config.php',
            dirname(__DIR__) . '/wp-config.php',
            dirname(__DIR__, 2) . '/wp-config.php',
        ];

        foreach ($candidates as $file) {
            if (!is_file($file)) continue;
            $contents = @file_get_contents($file);
            if ($contents === false) continue;

            $pattern = '/define\\s*\\(\\s*[\\\'"]' . preg_quote($name, '/') . '[\\\'"]\\s*,\\s*[\\\'"](.*?)[\\\'"]\\s*\\)\\s*;/s';
            if (preg_match($pattern, $contents, $m)) {
                return stripcslashes($m[1]);
            }
        }

        return '';
    }

    if (!isset($config['google']) || !is_array($config['google'])) $config['google'] = [];
    $googleClientId = bh_read_wp_constant('BH_GOOGLE_CLIENT_ID');
    if ($googleClientId !== '') {
        $config['google']['client_id'] = $googleClientId;
    }

    if (!isset($config['facebook']) || !is_array($config['facebook'])) $config['facebook'] = [];
    $facebookAppId = bh_read_wp_constant('BH_FACEBOOK_APP_ID');
    $facebookAppSecret = bh_read_wp_constant('BH_FACEBOOK_APP_SECRET');
    if ($facebookAppId !== '') $config['facebook']['app_id'] = $facebookAppId;
    if ($facebookAppSecret !== '') $config['facebook']['app_secret'] = $facebookAppSecret;

    $action = trim((string)($_GET['action'] ?? 'me'));

    if (!isset($_SESSION['bh_csrf'])) {
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
    }

    if ($action === 'me') {
        $adminAuthenticated = !empty($_SESSION['bh_admin_authenticated']);
        $familyAuthenticated = !empty($_SESSION['bh_family_authenticated']);
        $leaderAuthenticated = !empty($_SESSION['bh_leader_authenticated']);
        if (empty($_SESSION['bh_user_id'])) {
            if ($adminAuthenticated) {
                bh_auth_response(200, [
                    'ok' => true,
                    'authenticated' => true,
                    'family_authenticated' => false,
                    'leader_authenticated' => false,
                    'is_admin' => true,
                    'user' => ['id' => 0, 'email' => 'Admin access', 'role' => 'admin', 'status' => 'active'],
                    'csrf' => $_SESSION['bh_csrf'],
                ]);
            }
            bh_auth_response(200, ['ok' => true, 'authenticated' => false, 'family_authenticated' => false, 'leader_authenticated' => false, 'is_admin' => false, 'csrf' => $_SESSION['bh_csrf']]);
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
                unset($_SESSION['bh_user_id']);
                $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
                bh_auth_response(200, [
                    'ok' => true,
                    'authenticated' => true,
                    'is_admin' => true,
                    'user' => ['id' => 0, 'email' => 'Admin access', 'role' => 'admin', 'status' => 'active'],
                    'csrf' => $_SESSION['bh_csrf'],
                ]);
            }
            $_SESSION = [];
            bh_auth_response(200, ['ok' => true, 'authenticated' => false, 'is_admin' => false, 'csrf' => null]);
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

    // All write actions require the database.
    if ($db === null) $db = bh_mysql();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        bh_auth_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
    }

    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) {
        bh_auth_response(400, ['ok' => false, 'error' => 'invalid_json']);
    }

    if (in_array($action, ['login','register','logout'], true) && $action !== 'login') {
        if ($action !== 'register' && empty($_SESSION['bh_csrf'])) {
            bh_auth_response(403, ['ok' => false, 'error' => 'csrf_required']);
        }
        if ($action === 'register' && !empty($_SESSION['bh_csrf']) && !empty($body['csrf']) && !hash_equals((string)$_SESSION['bh_csrf'], (string)$body['csrf'])) {
            bh_auth_response(403, ['ok' => false, 'error' => 'csrf_invalid']);
        }
        if ($action === 'logout' && !empty($body['csrf']) && !hash_equals((string)$_SESSION['bh_csrf'], (string)$body['csrf'])) {
            bh_auth_response(403, ['ok' => false, 'error' => 'csrf_invalid']);
        }
    }

    if ($action === 'leader_register') {
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $password = (string)($body['password'] ?? '');
        $confirm = (string)($body['confirm_password'] ?? '');
        // Leader sign-up is intentionally as simple as family sign-up.
        // Class/business details are completed later in the leader portal.
        $organisation = trim((string)($body['organisation_name'] ?? ''));
        $phone = trim((string)($body['phone'] ?? ''));
        $website = trim((string)($body['website'] ?? ''));
        if ($organisation === '') $organisation = 'New Bubba Hub Leader';
        $terms = !empty($body['terms']);
        $csrf = (string)($body['csrf'] ?? '');

        if (empty($_SESSION['bh_csrf']) || $csrf === '' || !hash_equals((string)$_SESSION['bh_csrf'], $csrf)) {
            bh_auth_response(403, ['ok'=>false,'error'=>'csrf_invalid','message'=>'Please refresh the page and try again.']);
        }
        if (mb_strlen($organisation) > 190) bh_auth_response(422, ['ok'=>false,'error'=>'organisation_invalid','message'=>'Please check your class or business name.']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) bh_auth_response(422, ['ok'=>false,'error'=>'invalid_email','message'=>'Please enter a valid email address.']);
        if ($phone !== '' && mb_strlen($phone) > 80) bh_auth_response(422, ['ok'=>false,'error'=>'invalid_phone','message'=>'Please check your phone number.']);
        if ($website !== '' && (!filter_var($website, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $website))) bh_auth_response(422, ['ok'=>false,'error'=>'invalid_website','message'=>'Please enter a full website address starting with http:// or https://.']);
        if (strlen($password) < 8) bh_auth_response(422, ['ok'=>false,'error'=>'password_too_short','message'=>'Please choose a password with at least 8 characters.']);
        if ($password !== $confirm) bh_auth_response(422, ['ok'=>false,'error'=>'password_mismatch','message'=>'The passwords do not match.']);
        if (!$terms) bh_auth_response(422, ['ok'=>false,'error'=>'terms_required','message'=>'Please confirm that you agree to the Bubba Hub organiser terms.']);

        $check = $db->prepare("SELECT id,role,status FROM bh_users WHERE email=? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) {
            bh_auth_response(409, ['ok'=>false,'error'=>'email_exists','message'=>'An account already exists for this email. Please sign in instead.']);
        }

        try {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO bh_users (email,password_hash,role,status) VALUES (?,?, 'leader','active')");
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int)$db->lastInsertId();

            $organiserId = bh_create_leader_organiser($db, $userId, $email, $organisation, $phone, $website);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Bubba Hub leader registration failed: '.$e->getMessage());
            bh_auth_response(500, ['ok'=>false,'error'=>'leader_registration_error','message'=>'We could not create your leader account just yet. Please try again.']);
        }

        session_regenerate_id(true);
        $_SESSION['bh_user_id'] = $userId;
        $_SESSION['bh_leader_authenticated'] = true;
        unset($_SESSION['bh_family_authenticated']);
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));

        bh_auth_response(201, [
            'ok'=>true,
            'authenticated'=>true,
            'leader_authenticated'=>true,
            'pending_review'=>true,
            'organiser_id'=>$organiserId,
            'user'=>['id'=>$userId,'email'=>$email,'role'=>'leader','status'=>'active'],
            'csrf'=>$_SESSION['bh_csrf'],
            'message'=>'Your leader account is ready. Your organiser profile is pending review.'
        ]);
    }

    if ($action === 'login') {
        $context = trim((string)($body['context'] ?? 'family'));
        if (!in_array($context, ['family','leader'], true)) $context = 'family';
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $password = (string)($body['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            bh_auth_response(422, ['ok' => false, 'error' => 'email_password_required']);
        }

        $stmt = $db->prepare("SELECT id,email,password_hash,role,status FROM bh_users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            bh_auth_response(401, ['ok' => false, 'error' => 'invalid_login', 'message' => 'That email or password is not correct.']);
        }

        if (($user['status'] ?? '') !== 'active') {
            bh_auth_response(403, ['ok' => false, 'error' => 'account_not_active', 'message' => 'This account is not currently active.']);
        }
        $role = (string)($user['role'] ?? '');
        if ($context === 'leader' && $role !== 'leader') bh_auth_response(403, ['ok'=>false,'error'=>'leader_account_required','message'=>'That email is not a class leader account. Please use the family sign in or create a leader account.']);
        if ($context === 'family' && !in_array($role, ['family','organiser'], true)) bh_auth_response(403, ['ok'=>false,'error'=>'family_account_required','message'=>'Please use the Class Leader sign in for this account.']);

        session_regenerate_id(true);
        $_SESSION['bh_user_id'] = (int)$user['id'];
        unset($_SESSION['bh_family_authenticated'], $_SESSION['bh_leader_authenticated']);
        if ($context === 'leader') $_SESSION['bh_leader_authenticated'] = true;
        else $_SESSION['bh_family_authenticated'] = true;
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));

        bh_auth_response(200, [
            'ok' => true,
            'authenticated' => true,
            'user' => ['id' => (int)$user['id'], 'email' => $user['email'], 'role' => $user['role'], 'status' => $user['status']],
            'csrf' => $_SESSION['bh_csrf'],
        ]);
    }

    if ($action === 'facebook') {
        $accessToken = trim((string)($body['access_token'] ?? ''));
        if ($accessToken === '') bh_auth_response(422, ['ok'=>false,'error'=>'facebook_access_token_required','message'=>'Facebook sign-in could not be started.']);
        $authContext = trim((string)($body['context'] ?? 'family'));
        if (!in_array($authContext, ['family','leader'], true)) $authContext = 'family';
        $facebook = is_array($config['facebook'] ?? null) ? $config['facebook'] : [];
        $appId = trim((string)($facebook['app_id'] ?? ''));
        $appSecret = trim((string)($facebook['app_secret'] ?? ''));
        if ($appId === '' || $appSecret === '') bh_auth_response(503, ['ok'=>false,'error'=>'facebook_not_configured','message'=>'Facebook sign-in is not configured yet.']);
        try {
            $ctx = stream_context_create(['http'=>['method'=>'GET','timeout'=>8,'ignore_errors'=>true,'header'=>"Accept: application/json\r\n"]]);
            $debugUrl = 'https://graph.facebook.com/debug_token?input_token=' . rawurlencode($accessToken) . '&access_token=' . rawurlencode($appId . '|' . $appSecret);
            $debugRaw = @file_get_contents($debugUrl, false, $ctx);
            $debug = is_string($debugRaw) ? json_decode($debugRaw, true) : null;
            $data = is_array($debug['data'] ?? null) ? $debug['data'] : [];
            if (empty($data['is_valid']) || (string)($data['app_id'] ?? '') !== $appId || empty($data['user_id'])) throw new RuntimeException('Facebook access token could not be verified.');
            $meUrl = 'https://graph.facebook.com/me?fields=id,name,email&access_token=' . rawurlencode($accessToken);
            $meRaw = @file_get_contents($meUrl, false, $ctx);
            $me = is_string($meRaw) ? json_decode($meRaw, true) : null;
            if (!is_array($me) || !empty($me['error'])) throw new RuntimeException('Facebook account details could not be retrieved.');
            $facebookId = trim((string)($me['id'] ?? ($data['user_id'] ?? '')));
            $email = strtolower(trim((string)($me['email'] ?? '')));
            if ($facebookId === '') throw new RuntimeException('Facebook account identifier missing.');
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) bh_auth_response(422,['ok'=>false,'error'=>'facebook_email_missing','message'=>'Facebook did not provide an email address. Please allow the email permission and try again.']);
            $db->exec("CREATE TABLE IF NOT EXISTS bh_social_accounts (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,provider VARCHAR(32) NOT NULL,provider_user_id VARCHAR(191) NOT NULL,email VARCHAR(255) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_provider_user(provider,provider_user_id),UNIQUE KEY uq_user_provider(user_id,provider),KEY idx_social_user(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $stmt=$db->prepare("SELECT user_id FROM bh_social_accounts WHERE provider='facebook' AND provider_user_id=? LIMIT 1"); $stmt->execute([$facebookId]); $linked=$stmt->fetch(); $newSocialUser=false;
            if($linked){
                $userId=(int)$linked['user_id'];
                $stmt=$db->prepare("SELECT id,role,status FROM bh_users WHERE id=? LIMIT 1"); $stmt->execute([$userId]); $user=$stmt->fetch();
                if(!$user || ($user['status']??'')!=='active') bh_auth_response(403,['ok'=>false,'error'=>'account_not_active','message'=>'This account is not currently active.']);
                if($authContext==='leader' && ($user['role']??'')!=='leader') bh_auth_response(403,['ok'=>false,'error'=>'leader_account_required','message'=>'This social account is linked to a family account. Please use a separate leader email.']);
                if($authContext==='family' && ($user['role']??'')==='leader') bh_auth_response(403,['ok'=>false,'error'=>'family_account_required','message'=>'Please use the Class Leader sign in for this account.']);
            }else{
                $stmt=$db->prepare("SELECT id,role,status FROM bh_users WHERE email=? LIMIT 1"); $stmt->execute([$email]); $user=$stmt->fetch();
                if($user){
                    if(($user['status']??'')!=='active') bh_auth_response(403,['ok'=>false,'error'=>'account_not_active','message'=>'This account is not currently active.']);
                    if($authContext==='leader' && ($user['role']??'')!=='leader') bh_auth_response(403,['ok'=>false,'error'=>'leader_account_required','message'=>'This email is already a family account. Use a different email for your leader account.']);
                    if($authContext==='family' && ($user['role']??'')==='leader') bh_auth_response(403,['ok'=>false,'error'=>'family_account_required','message'=>'Please use the Class Leader sign in for this account.']);
                    $userId=(int)$user['id'];
                }else{
                    $randomPassword=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
                    $role=$authContext==='leader'?'leader':'family';
                    $stmt=$db->prepare("INSERT INTO bh_users (email,password_hash,role,status) VALUES (?,?,?,'active')");
                    $stmt->execute([$email,$randomPassword,$role]);
                    $userId=(int)$db->lastInsertId();
                    $newSocialUser=true;
                    if($authContext==='leader') bh_create_leader_organiser($db,$userId,$email,(string)($me['name']??'New organiser'));
                }
                $stmt=$db->prepare("INSERT INTO bh_social_accounts (user_id,provider,provider_user_id,email) VALUES (?, 'facebook', ?, ?)");
                $stmt->execute([$userId,$facebookId,$email]);
            }
            if($authContext==='leader' && isset($user) && ($user['role']??'')!=='leader') bh_auth_response(403,['ok'=>false,'error'=>'leader_account_required','message'=>'This email is already a family account. Use a different email for your leader account.']); if($authContext==='family' && isset($user) && ($user['role']??'')==='leader') bh_auth_response(403,['ok'=>false,'error'=>'family_account_required','message'=>'Please use the Class Leader sign in for this account.']); session_regenerate_id(true); $_SESSION['bh_user_id']=$userId; unset($_SESSION['bh_family_authenticated'],$_SESSION['bh_leader_authenticated']); if($authContext==='leader') $_SESSION['bh_leader_authenticated']=true; else $_SESSION['bh_family_authenticated']=true; $_SESSION['bh_csrf']=bin2hex(random_bytes(24));
            $stmt=$db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1");$stmt->execute([$userId]);$user=$stmt->fetch();
            $welcomeEmailSent = ($authContext==='family' && $newSocialUser) ? bh_send_family_welcome($email, '') : false;
            bh_auth_response(200,['ok'=>true,'authenticated'=>true,'user'=>['id'=>(int)$user['id'],'email'=>$user['email'],'role'=>$user['role'],'status'=>$user['status']],'csrf'=>$_SESSION['bh_csrf'],'provider'=>'facebook','leader_authenticated'=>($authContext==='leader'),'family_authenticated'=>($authContext==='family'),'created_leader'=>($authContext==='leader' && $newSocialUser),'pending_review'=>($authContext==='leader' && $newSocialUser),'welcome_email_sent'=>($authContext==='family' ? $welcomeEmailSent : false)]);
        } catch (Throwable $e) {
            bh_auth_response(401,['ok'=>false,'error'=>'facebook_invalid_token','message'=>$e->getMessage() ?: 'Facebook could not verify this sign-in. Please try again.']);
        }
    }

    if ($action === 'google') {
        $credential=trim((string)($body['credential']??''));
        if($credential==='') bh_auth_response(422,['ok'=>false,'error'=>'google_credential_required','message'=>'Google sign-in could not be started.']);
        $clientId=(string)($config['google']['client_id']??'');
        if($clientId==='') bh_auth_response(503,['ok'=>false,'error'=>'google_not_configured','message'=>'Google sign-in is not configured yet.']);
        $context=stream_context_create(['http'=>['method'=>'GET','timeout'=>8,'ignore_errors'=>true,'header'=>"Accept: application/json\r\n"]]);
        $verify=@file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token='.rawurlencode($credential),false,$context); $google=is_string($verify)?json_decode($verify,true):null;
        if(!is_array($google)||!empty($google['error'])||(string)($google['aud']??'')!==$clientId||(string)($google['iss']??'')!=='https://accounts.google.com') bh_auth_response(401,['ok'=>false,'error'=>'google_invalid_token','message'=>'Google could not verify this sign-in. Please try again.']);
        $email=strtolower(trim((string)($google['email']??''))); $googleId=trim((string)($google['sub']??'')); $emailVerified=filter_var($google['email_verified']??false,FILTER_VALIDATE_BOOLEAN); $authContext=trim((string)($body['context']??'family')); if(!in_array($authContext,['family','leader'],true)) $authContext='family';
        if($email===''||$googleId===''||!$emailVerified||!filter_var($email,FILTER_VALIDATE_EMAIL)) bh_auth_response(401,['ok'=>false,'error'=>'google_unverified_email','message'=>'Google did not provide a verified email address.']);
        $db->exec("CREATE TABLE IF NOT EXISTS bh_social_accounts (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,provider VARCHAR(32) NOT NULL,provider_user_id VARCHAR(191) NOT NULL,email VARCHAR(255) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_provider_user(provider,provider_user_id),UNIQUE KEY uq_user_provider(user_id,provider),KEY idx_social_user(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt=$db->prepare("SELECT user_id FROM bh_social_accounts WHERE provider='google' AND provider_user_id=? LIMIT 1"); $stmt->execute([$googleId]); $linked=$stmt->fetch();
        $newSocialUser=false;
        if($linked){
            $userId=(int)$linked['user_id'];
            $stmt=$db->prepare("SELECT id,role,status FROM bh_users WHERE id=? LIMIT 1"); $stmt->execute([$userId]); $user=$stmt->fetch();
        }else{
            $stmt=$db->prepare("SELECT id,role,status FROM bh_users WHERE email=? LIMIT 1"); $stmt->execute([$email]); $user=$stmt->fetch();
            if($user){
                if(($user['status']??'')!=='active') bh_auth_response(403,['ok'=>false,'error'=>'account_not_active','message'=>'This account is not currently active.']);
                if($authContext==='leader' && ($user['role']??'')!=='leader') bh_auth_response(403,['ok'=>false,'error'=>'leader_account_required','message'=>'This email is already a family account. Use a different email for your leader account.']);
                if($authContext==='family' && ($user['role']??'')==='leader') bh_auth_response(403,['ok'=>false,'error'=>'family_account_required','message'=>'Please use the Class Leader sign in for this account.']);
                $userId=(int)$user['id'];
            }else{
                $randomPassword=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
                $role=$authContext==='leader'?'leader':'family';
                $stmt=$db->prepare("INSERT INTO bh_users (email,password_hash,role,status) VALUES (?,?,?,'active')");
                $stmt->execute([$email,$randomPassword,$role]);
                $userId=(int)$db->lastInsertId();
                $newSocialUser=true;
                if($authContext==='leader') bh_create_leader_organiser($db,$userId,$email,(string)($google['name']??'New organiser'));
            }
            $stmt=$db->prepare("INSERT INTO bh_social_accounts (user_id,provider,provider_user_id,email) VALUES (?, 'google', ?, ?)");
            $stmt->execute([$userId,$googleId,$email]);
        }
        session_regenerate_id(true); $_SESSION['bh_user_id']=$userId; $_SESSION['bh_csrf']=bin2hex(random_bytes(24));
        $stmt=$db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1");$stmt->execute([$userId]);$user=$stmt->fetch();
        $welcomeEmailSent = $newSocialUser ? bh_send_family_welcome($email, '') : false;
        unset($_SESSION['bh_family_authenticated'],$_SESSION['bh_leader_authenticated']);
        if($authContext==='leader') $_SESSION['bh_leader_authenticated']=true; else $_SESSION['bh_family_authenticated']=true;
        bh_auth_response(200,['ok'=>true,'authenticated'=>true,'user'=>['id'=>(int)$user['id'],'email'=>$user['email'],'role'=>$user['role'],'status'=>$user['status']],'csrf'=>$_SESSION['bh_csrf'],'provider'=>'google','leader_authenticated'=>($authContext==='leader'),'family_authenticated'=>($authContext==='family'),'created_leader'=>($authContext==='leader' && $newSocialUser),'pending_review'=>($authContext==='leader' && $newSocialUser),'welcome_email_sent'=>($authContext==='family' ? $welcomeEmailSent : false)]);
    }

    if ($action === 'forgot_password') {
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $generic = 'If an account exists for that email, a password reset link has been sent.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            bh_auth_response(200, ['ok' => true, 'message' => $generic]);
        }

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

        $stmt = $db->prepare("SELECT id,status FROM bh_users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && ($user['status'] ?? '') === 'active') {
            $rawToken = bin2hex(random_bytes(32));
            $hash = hash('sha256', $rawToken);
            $expires = date('Y-m-d H:i:s', time() + 3600);

            $db->prepare("UPDATE bh_password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL")->execute([(int)$user['id']]);
            $db->prepare("INSERT INTO bh_password_resets (user_id,token_hash,expires_at) VALUES (?,?,?)")->execute([(int)$user['id'],$hash,$expires]);

            $base = rtrim((string)($config['app']['base_url'] ?? getenv('BUBBAHUB_BASE_URL') ?: 'https://bubbahub.co.uk'), '/');
            $resetUrl = $base . '/reset-password.html?token=' . rawurlencode($rawToken);
            $apiKey = (string)($config['resend']['api_key'] ?? getenv('RESEND_API_KEY') ?: '');
            $from = (string)($config['resend']['from'] ?? getenv('RESEND_FROM') ?: 'Bubba Hub <noreply@bubbahub.co.uk>');

            if ($apiKey !== '') {
                $payload = json_encode([
                    'from' => $from,
                    'to' => [$email],
                    'subject' => 'Reset your Bubba Hub password',
                    'html' => '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#144400"><h1 style="color:#416651">Reset your Bubba Hub password</h1><p>We received a request to reset your Bubba Hub password.</p><p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 18px;background:#416651;color:#fff;text-decoration:none;border-radius:10px">Reset my password</a></p><p>This link expires in 1 hour and can only be used once.</p><p>If you did not request this, you can safely ignore this email.</p></div>',
                    'text' => "Reset your Bubba Hub password\n\nOpen this link within 1 hour:\n{$resetUrl}\n\nIf you did not request this, ignore this email."
                ], JSON_UNESCAPED_SLASHES);

                $ch = curl_init('https://api.resend.com/emails');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
                    CURLOPT_POSTFIELDS => $payload,
                ]);
                $response = curl_exec($ch);
                $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http < 200 || $http >= 300) {
                    bh_auth_response(500, ['ok' => false, 'error' => 'reset_email_failed', 'message' => 'We could not send the reset email right now. Please try again shortly.']);
                }
            } else {
                bh_auth_response(503, ['ok' => false, 'error' => 'reset_email_not_configured', 'message' => 'Password reset email is not configured yet.']);
            }
        }

        bh_auth_response(200, ['ok' => true, 'message' => $generic]);
    }

    if ($action === 'reset_password') {
        $token = trim((string)($body['token'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $confirm = (string)($body['confirm_password'] ?? '');

        if ($token === '' || strlen($password) < 8 || $password !== $confirm) {
            bh_auth_response(422, ['ok' => false, 'error' => 'invalid_reset_request', 'message' => 'Please enter matching passwords with at least 8 characters.']);
        }

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

        $hash = hash('sha256', $token);
        $stmt = $db->prepare("SELECT id,user_id FROM bh_password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$hash]);
        $reset = $stmt->fetch();

        if (!$reset) {
            bh_auth_response(400, ['ok' => false, 'error' => 'reset_token_invalid', 'message' => 'This password reset link has expired or has already been used.']);
        }

        $stmt = $db->prepare("UPDATE bh_users SET password_hash=? WHERE id=? AND status='active'");
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int)$reset['user_id']]);
        $db->prepare("UPDATE bh_password_resets SET used_at=NOW() WHERE id=?")->execute([(int)$reset['id']]);

        bh_auth_response(200, ['ok' => true, 'message' => 'Your password has been updated.']);
    }

    if ($action === 'register') {
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $password = (string)($body['password'] ?? '');
        $confirm = (string)($body['confirm_password'] ?? '');
        $firstName = trim((string)($body['first_name'] ?? ''));
        $lastName = trim((string)($body['last_name'] ?? ''));
        $eventCode = strtoupper(trim((string)($body['event_code'] ?? '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            bh_auth_response(422, ['ok' => false, 'error' => 'invalid_email', 'message' => 'Please enter a valid email address.']);
        }
        if (strlen($password) < 8) {
            bh_auth_response(422, ['ok' => false, 'error' => 'password_too_short', 'message' => 'Please choose a password with at least 8 characters.']);
        }
        if ($password !== $confirm) {
            bh_auth_response(422, ['ok' => false, 'error' => 'password_mismatch', 'message' => 'The passwords do not match.']);
        }

        $check = $db->prepare("SELECT id FROM bh_users WHERE email=? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) {
            bh_auth_response(409, ['ok' => false, 'error' => 'email_exists', 'message' => 'An account already exists for this email.']);
        }

        // Name columns were added during the account upgrade. Check the live
        // schema before inserting so registration still works on older databases
        // where the web user cannot ALTER TABLE.
        $userColumns = [];
        try {
            $cq = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_users'");
            $userColumns = array_map('strval', $cq->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $ignored) {}
        $columns = ['email','password_hash','role','status'];
        $values = [$email, password_hash($password, PASSWORD_DEFAULT), 'family', 'active'];
        if (in_array('first_name', $userColumns, true)) {
            $columns[]='first_name'; $values[]=$firstName ?: null;
        }
        if (in_array('last_name', $userColumns, true)) {
            $columns[]='last_name'; $values[]=$lastName ?: null;
        }
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $stmt = $db->prepare("INSERT INTO bh_users (".implode(',', $columns).") VALUES (".$placeholders.")");
        $stmt->execute($values);
        $newUserId=(int)$db->lastInsertId();
        $welcomeEmailSent = bh_send_family_welcome($email, $firstName);

        // Event sign-ups can receive Pro access without touching the future payment flow.
        $eventPro=false;
        $configuredEventCode='';
        try {
            $cfgFile=__DIR__.'/config.php';
            $cfg=is_file($cfgFile)?require $cfgFile:[];
            $configuredEventCode=strtoupper(trim((string)($cfg['app']['event_signup_code'] ?? getenv('BUBBAHUB_EVENT_CODE') ?: 'BUBBAEVENT26')));
        } catch(Throwable $ignored) {}
        if($eventCode!=='' && $configuredEventCode!=='' && hash_equals($configuredEventCode,$eventCode)){
            try{
                $db->exec("CREATE TABLE IF NOT EXISTS bh_user_subscriptions (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,plan VARCHAR(40) NOT NULL DEFAULT 'family_pro',status VARCHAR(30) NOT NULL DEFAULT 'active',source VARCHAR(40) NOT NULL DEFAULT 'event',started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,expires_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_bh_user_subscription(user_id),KEY idx_bh_subscription_status(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $q=$db->prepare("INSERT INTO bh_user_subscriptions(user_id,plan,status,source,expires_at) VALUES(?,?,?,?,NULL) ON DUPLICATE KEY UPDATE plan=VALUES(plan),status='active',source=VALUES(source),expires_at=NULL");
                $q->execute([$newUserId,'family_pro','active','event']);
                $eventPro=true;
            }catch(Throwable $ignored){}
        }

        session_regenerate_id(true);
        $_SESSION['bh_user_id'] = $newUserId;
        $_SESSION['bh_family_authenticated'] = true;
        unset($_SESSION['bh_leader_authenticated']);
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));

        bh_auth_response(201, [
            'ok' => true,
            'authenticated' => true,
            'family_authenticated' => true,
            'leader_authenticated' => false,
            'user' => ['id' => (int)$_SESSION['bh_user_id'], 'email' => $email, 'role' => 'family', 'status' => 'active', 'first_name' => $firstName, 'last_name' => $lastName, 'pro' => $eventPro],
            'pro' => $eventPro,
            'welcome_email_sent' => $welcomeEmailSent,
            'csrf' => $_SESSION['bh_csrf'],
        ]);
    }

    if ($action === 'logout') {
        if (!empty($_SESSION['bh_admin_authenticated'])) {
            unset($_SESSION['bh_user_id'], $_SESSION['bh_family_authenticated'], $_SESSION['bh_leader_authenticated']);
            $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
            bh_auth_response(200, ['ok' => true, 'authenticated' => true, 'is_admin' => true]);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
        bh_auth_response(200, ['ok' => true, 'authenticated' => false]);
    }

    bh_auth_response(400, ['ok' => false, 'error' => 'unknown_action']);
} catch (Throwable $e) {
    error_log('Bubba Hub auth API error: '.$e->getMessage());
    bh_auth_response(500, ['ok' => false, 'error' => 'auth_error', 'message' => 'Authentication is temporarily unavailable. Please try again.']);
}
?>