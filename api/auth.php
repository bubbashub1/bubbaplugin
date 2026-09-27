<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_auth_response(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
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

    $db = bh_mysql();
    $action = trim((string)($_GET['action'] ?? 'me'));

    if (!isset($_SESSION['bh_csrf'])) {
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
    }

    if ($action === 'me') {
        $adminAuthenticated = !empty($_SESSION['bh_admin_authenticated']);
        if (empty($_SESSION['bh_user_id'])) {
            if ($adminAuthenticated) {
                bh_auth_response(200, [
                    'ok' => true,
                    'authenticated' => true,
                    'is_admin' => true,
                    'user' => ['id' => 0, 'email' => 'Admin access', 'role' => 'admin', 'status' => 'active'],
                    'csrf' => $_SESSION['bh_csrf'],
                ]);
            }
            bh_auth_response(200, ['ok' => true, 'authenticated' => false, 'is_admin' => false, 'csrf' => $_SESSION['bh_csrf']]);
        }

        $stmt = $db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1");
        $stmt->execute([(int)$_SESSION['bh_user_id']]);
        $user = $stmt->fetch();

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
            'authenticated' => true,
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

    if ($action === 'login') {
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

        session_regenerate_id(true);
        $_SESSION['bh_user_id'] = (int)$user['id'];
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));

        bh_auth_response(200, [
            'ok' => true,
            'authenticated' => true,
            'user' => ['id' => (int)$user['id'], 'email' => $user['email'], 'role' => $user['role'], 'status' => $user['status']],
            'csrf' => $_SESSION['bh_csrf'],
        ]);
    }

    if ($action === 'google') {
        $credential=trim((string)($body['credential']??''));
        if($credential==='') bh_auth_response(422,['ok'=>false,'error'=>'google_credential_required','message'=>'Google sign-in could not be started.']);
        $configFile=__DIR__.'/config.php'; $config=is_file($configFile)?require $configFile:[]; $clientId=(string)($config['google']['client_id']??'');
        if($clientId==='') bh_auth_response(503,['ok'=>false,'error'=>'google_not_configured','message'=>'Google sign-in is not configured yet.']);
        $context=stream_context_create(['http'=>['method'=>'GET','timeout'=>8,'ignore_errors'=>true,'header'=>"Accept: application/json\r\n"]]);
        $verify=@file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token='.rawurlencode($credential),false,$context); $google=is_string($verify)?json_decode($verify,true):null;
        if(!is_array($google)||!empty($google['error'])||(string)($google['aud']??'')!==$clientId||(string)($google['iss']??'')!=='https://accounts.google.com') bh_auth_response(401,['ok'=>false,'error'=>'google_invalid_token','message'=>'Google could not verify this sign-in. Please try again.']);
        $email=strtolower(trim((string)($google['email']??''))); $googleId=trim((string)($google['sub']??'')); $emailVerified=filter_var($google['email_verified']??false,FILTER_VALIDATE_BOOLEAN);
        if($email===''||$googleId===''||!$emailVerified||!filter_var($email,FILTER_VALIDATE_EMAIL)) bh_auth_response(401,['ok'=>false,'error'=>'google_unverified_email','message'=>'Google did not provide a verified email address.']);
        $db->exec("CREATE TABLE IF NOT EXISTS bh_social_accounts (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,provider VARCHAR(32) NOT NULL,provider_user_id VARCHAR(191) NOT NULL,email VARCHAR(255) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_provider_user(provider,provider_user_id),UNIQUE KEY uq_user_provider(user_id,provider),KEY idx_social_user(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt=$db->prepare("SELECT user_id FROM bh_social_accounts WHERE provider='google' AND provider_user_id=? LIMIT 1"); $stmt->execute([$googleId]); $linked=$stmt->fetch();
        if($linked){$userId=(int)$linked['user_id'];}else{
            $stmt=$db->prepare("SELECT id,status FROM bh_users WHERE email=? LIMIT 1"); $stmt->execute([$email]); $user=$stmt->fetch();
            if($user){if(($user['status']??'')!=='active') bh_auth_response(403,['ok'=>false,'error'=>'account_not_active','message'=>'This account is not currently active.']); $userId=(int)$user['id'];}
            else{$randomPassword=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);$stmt=$db->prepare("INSERT INTO bh_users (email,password_hash,role,status) VALUES (?,?,'family','active')");$stmt->execute([$email,$randomPassword]);$userId=(int)$db->lastInsertId();}
            $stmt=$db->prepare("INSERT INTO bh_social_accounts (user_id,provider,provider_user_id,email) VALUES (?, 'google', ?, ?)");$stmt->execute([$userId,$googleId,$email]);
        }
        session_regenerate_id(true); $_SESSION['bh_user_id']=$userId; $_SESSION['bh_csrf']=bin2hex(random_bytes(24));
        $stmt=$db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1");$stmt->execute([$userId]);$user=$stmt->fetch();
        bh_auth_response(200,['ok'=>true,'authenticated'=>true,'user'=>['id'=>(int)$user['id'],'email'=>$user['email'],'role'=>$user['role'],'status'=>$user['status']],'csrf'=>$_SESSION['bh_csrf'],'provider'=>'google']);
    }

    if ($action === 'register') {
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $password = (string)($body['password'] ?? '');
        $confirm = (string)($body['confirm_password'] ?? '');

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

        $stmt = $db->prepare("INSERT INTO bh_users (email,password_hash,role,status) VALUES (?,?, 'family','active')");
        $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);

        session_regenerate_id(true);
        $_SESSION['bh_user_id'] = (int)$db->lastInsertId();
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));

        bh_auth_response(201, [
            'ok' => true,
            'authenticated' => true,
            'user' => ['id' => (int)$_SESSION['bh_user_id'], 'email' => $email, 'role' => 'family', 'status' => 'active'],
            'csrf' => $_SESSION['bh_csrf'],
        ]);
    }

    if ($action === 'logout') {
        if (!empty($_SESSION['bh_admin_authenticated'])) {
            unset($_SESSION['bh_user_id']);
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
    bh_auth_response(500, ['ok' => false, 'error' => 'auth_error', 'message' => $e->getMessage()]);
}
?>