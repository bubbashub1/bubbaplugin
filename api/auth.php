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
function bh_b64url_encode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}
function bh_b64url_decode(string $value): string|false {
    $value = strtr($value, '-_', '+/');
    $pad = strlen($value) % 4;
    if ($pad) $value .= str_repeat('=', 4 - $pad);
    return base64_decode($value, true);
}
function bh_der_length(int $length): string {
    if ($length < 128) return chr($length);
    $hex = ltrim(bin2hex(pack('N', $length)), '0');
    if ($hex === '') $hex = '00';
    if (strlen($hex) % 2) $hex = '0' . $hex;
    return chr(0x80 | (strlen($hex) / 2)) . hex2bin($hex);
}
function bh_ecdsa_der_to_raw(string $der, int $size = 32): string {
    $i = 0;
    if (($der[$i++] ?? '') !== "\x30") throw new RuntimeException('Invalid ECDSA signature.');
    $len = ord($der[$i++]);
    if ($len & 0x80) { $n = $len & 0x7f; $i += $n; }
    if (($der[$i++] ?? '') !== "\x02") throw new RuntimeException('Invalid ECDSA signature.');
    $rLen = ord($der[$i++]); $r = substr($der, $i, $rLen); $i += $rLen;
    if (($der[$i++] ?? '') !== "\x02") throw new RuntimeException('Invalid ECDSA signature.');
    $sLen = ord($der[$i++]); $s = substr($der, $i, $sLen);
    $r = ltrim($r, "\x00"); $s = ltrim($s, "\x00");
    return str_pad($r, $size, "\x00", STR_PAD_LEFT) . str_pad($s, $size, "\x00", STR_PAD_LEFT);
}
function bh_ecdsa_raw_to_der(string $raw, int $size = 32): string {
    if (strlen($raw) !== $size * 2) throw new RuntimeException('Invalid ECDSA signature.');
    $parts = [substr($raw, 0, $size), substr($raw, $size, $size)];
    $out = '';
    foreach ($parts as $part) {
        $part = ltrim($part, "\x00");
        if ($part === '') $part = "\x00";
        if (ord($part[0]) & 0x80) $part = "\x00" . $part;
        $out .= "\x02" . bh_der_length(strlen($part)) . $part;
    }
    return "\x30" . bh_der_length(strlen($out)) . $out;
}
function bh_apple_client_secret(array $apple): string {
    $team = trim((string)($apple['team_id'] ?? ''));
    $client = trim((string)($apple['client_id'] ?? ''));
    $keyId = trim((string)($apple['key_id'] ?? ''));
    $private = (string)($apple['private_key'] ?? '');
    if ($private === '' && !empty($apple['private_key_file'])) {
        $private = (string)@file_get_contents((string)$apple['private_key_file']);
    }
    if ($team === '' || $client === '' || $keyId === '' || $private === '') throw new RuntimeException('Apple Sign in with Apple server configuration is incomplete.');
    $header = bh_b64url_encode(json_encode(['alg'=>'ES256','kid'=>$keyId], JSON_UNESCAPED_SLASHES));
    $now = time();
    $payload = bh_b64url_encode(json_encode(['iss'=>$team,'iat'=>$now,'exp'=>$now + 15777000,'aud'=>'https://appleid.apple.com','sub'=>$client], JSON_UNESCAPED_SLASHES));
    $data = $header . '.' . $payload;
    $key = openssl_pkey_get_private($private);
    if (!$key || !openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) throw new RuntimeException('Apple private key could not sign the client secret.');
    if (is_object($key)) openssl_free_key($key);
    $raw = bh_ecdsa_der_to_raw($signature);
    return $data . '.' . bh_b64url_encode($raw);
}
function bh_apple_public_key_pem(array $jwk): string {
    if (($jwk['kty'] ?? '') !== 'EC' || ($jwk['crv'] ?? '') !== 'P-256') throw new RuntimeException('Unsupported Apple signing key.');
    $x = bh_b64url_decode((string)($jwk['x'] ?? ''));
    $y = bh_b64url_decode((string)($jwk['y'] ?? ''));
    if ($x === false || $y === false || strlen($x) !== 32 || strlen($y) !== 32) throw new RuntimeException('Invalid Apple signing key.');
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d03010703420004') . $x . $y;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}
function bh_apple_verify_id_token(string $jwt, string $clientId, string $nonce): array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) throw new RuntimeException('Apple identity token is invalid.');
    $header = json_decode((string)bh_b64url_decode($parts[0]), true);
    $claims = json_decode((string)bh_b64url_decode($parts[1]), true);
    $signature = bh_b64url_decode($parts[2]);
    if (!is_array($header) || !is_array($claims) || $signature === false || ($header['alg'] ?? '') !== 'ES256' || strlen($signature) !== 64) throw new RuntimeException('Apple identity token is invalid.');
    $ctx = stream_context_create(['http'=>['method'=>'GET','timeout'=>8,'ignore_errors'=>true,'header'=>"Accept: application/json\r\n"]]);
    $keysRaw = @file_get_contents('https://appleid.apple.com/auth/keys', false, $ctx);
    $keys = is_string($keysRaw) ? json_decode($keysRaw, true) : null;
    $selected = null;
    foreach (($keys['keys'] ?? []) as $key) if (($key['kid'] ?? '') === ($header['kid'] ?? '')) { $selected = $key; break; }
    if (!$selected) throw new RuntimeException('Apple signing key could not be found.');
    $public = openssl_pkey_get_public(bh_apple_public_key_pem($selected));
    if (!$public) throw new RuntimeException('Apple signing key could not be loaded.');
    $ok = openssl_verify($parts[0] . '.' . $parts[1], bh_ecdsa_raw_to_der($signature), $public, OPENSSL_ALGO_SHA256);
    if (is_object($public)) openssl_free_key($public);
    if ($ok !== 1) throw new RuntimeException('Apple identity token signature could not be verified.');
    if (($claims['iss'] ?? '') !== 'https://appleid.apple.com' || ($claims['aud'] ?? '') !== $clientId || empty($claims['sub']) || empty($claims['exp']) || (int)$claims['exp'] <= time()) throw new RuntimeException('Apple identity token claims are invalid.');
    $tokenNonce = (string)($claims['nonce'] ?? '');
    if ($nonce === '' || !hash_equals($nonce, $tokenNonce) && !hash_equals(hash('sha256', $nonce), $tokenNonce)) throw new RuntimeException('Apple identity token nonce could not be verified.');
    return $claims;
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
    $configFile = __DIR__ . '/config.php';
    $config = is_file($configFile) ? require $configFile : [];
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

    if ($action === 'apple_config') {
        $apple = is_array($config['apple'] ?? null) ? $config['apple'] : [];
        $clientId = trim((string)($apple['client_id'] ?? ''));
        $redirectUri = trim((string)($apple['redirect_uri'] ?? ($config['app']['base_url'] ?? getenv('BUBBAHUB_BASE_URL') ?: 'https://bubbahub.co.uk') . '/auth.html'));
        if ($clientId === '') bh_auth_response(503, ['ok'=>false,'error'=>'apple_not_configured','message'=>'Sign in with Apple is not configured yet.']);
        $_SESSION['bh_apple_state'] = bin2hex(random_bytes(24));
        $_SESSION['bh_apple_nonce'] = bin2hex(random_bytes(24));
        bh_auth_response(200, ['ok'=>true,'client_id'=>$clientId,'redirect_uri'=>$redirectUri,'state'=>$_SESSION['bh_apple_state'],'nonce'=>$_SESSION['bh_apple_nonce']]);
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

    if ($action === 'leader_register') {
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $password = (string)($body['password'] ?? '');
        $confirm = (string)($body['confirm_password'] ?? '');
        $csrf = (string)($body['csrf'] ?? '');
        if (empty($_SESSION['bh_csrf']) || $csrf === '' || !hash_equals((string)$_SESSION['bh_csrf'], $csrf)) {
            bh_auth_response(403, ['ok'=>false,'error'=>'csrf_invalid','message'=>'Please refresh the page and try again.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) bh_auth_response(422, ['ok'=>false,'error'=>'invalid_email','message'=>'Please enter a valid email address.']);
        if (strlen($password) < 8) bh_auth_response(422, ['ok'=>false,'error'=>'password_too_short','message'=>'Please choose a password with at least 8 characters.']);
        if ($password !== $confirm) bh_auth_response(422, ['ok'=>false,'error'=>'password_mismatch','message'=>'The passwords do not match.']);

        $org = false;
        try {
            $hasUserId = false;
            $cc = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='user_id'");
            $cc->execute();
            $hasUserId = ((int)$cc->fetchColumn()) > 0;
            if ($hasUserId) {
                $q = $db->prepare("SELECT id,name,email,user_id FROM bh_organisers WHERE LOWER(email)=? LIMIT 1");
            } else {
                $q = $db->prepare("SELECT id,name,email FROM bh_organisers WHERE LOWER(email)=? LIMIT 1");
            }
            $q->execute([$email]);
            $org = $q->fetch();
        } catch (Throwable $e) {
            bh_auth_response(500, ['ok'=>false,'error'=>'leader_registration_unavailable','message'=>'Leader account setup is not available yet. Please contact Bubba Hub support.']);
        }
        if (!$org) bh_auth_response(403, ['ok'=>false,'error'=>'leader_not_invited','message'=>'We could not find an organiser account for that email. Please contact Bubba Hub to have your leader access set up.']);

        $check = $db->prepare("SELECT id,role,status FROM bh_users WHERE email=? LIMIT 1");
        $check->execute([$email]);
        $existing = $check->fetch();
        if ($existing && ($existing['role'] ?? '') !== 'leader') {
            $stmt = $db->prepare("UPDATE bh_users SET password_hash=?,role='leader',status='active' WHERE id=?");
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int)$existing['id']]);
            $userId = (int)$existing['id'];
        } elseif ($existing) {
            $stmt = $db->prepare("UPDATE bh_users SET password_hash=?,status='active' WHERE id=?");
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int)$existing['id']]);
            $userId = (int)$existing['id'];
        } else {
            $stmt = $db->prepare("INSERT INTO bh_users (email,password_hash,role,status) VALUES (?,?, 'leader','active')");
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int)$db->lastInsertId();
        }

        try {
            $cc = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='user_id'");
            $cc->execute();
            if ((int)$cc->fetchColumn() > 0) {
                $db->prepare("UPDATE bh_organisers SET user_id=? WHERE id=?")->execute([$userId, (int)$org['id']]);
            }
        } catch (Throwable $ignored) {}

        session_regenerate_id(true);
        $_SESSION['bh_user_id'] = $userId;
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
        bh_auth_response(201, [
            'ok'=>true,'authenticated'=>true,
            'user'=>['id'=>$userId,'email'=>$email,'role'=>'leader','status'=>'active'],
            'csrf'=>$_SESSION['bh_csrf']
        ]);
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

    if ($action === 'apple') {
        $code = trim((string)($body['code'] ?? ''));
        $idToken = trim((string)($body['id_token'] ?? ''));
        $state = trim((string)($body['state'] ?? ''));
        $returnedUser = is_array($body['user'] ?? null) ? $body['user'] : [];
        $expectedState = (string)($_SESSION['bh_apple_state'] ?? '');
        $expectedNonce = (string)($_SESSION['bh_apple_nonce'] ?? '');
        unset($_SESSION['bh_apple_state'], $_SESSION['bh_apple_nonce']);
        if ($code === '' || $idToken === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
            bh_auth_response(401, ['ok'=>false,'error'=>'apple_invalid_state','message'=>'Apple sign-in could not be verified. Please try again.']);
        }
        $apple = is_array($config['apple'] ?? null) ? $config['apple'] : [];
        $clientId = trim((string)($apple['client_id'] ?? ''));
        $redirectUri = trim((string)($apple['redirect_uri'] ?? ($config['app']['base_url'] ?? getenv('BUBBAHUB_BASE_URL') ?: 'https://bubbahub.co.uk') . '/auth.html'));
        if ($clientId === '' || empty($apple['team_id']) || empty($apple['key_id']) || (empty($apple['private_key']) && empty($apple['private_key_file']))) {
            bh_auth_response(503, ['ok'=>false,'error'=>'apple_not_configured','message'=>'Sign in with Apple is not configured yet.']);
        }
        try {
            $clientSecret = bh_apple_client_secret($apple);
            $post = http_build_query(['client_id'=>$clientId,'client_secret'=>$clientSecret,'code'=>$code,'grant_type'=>'authorization_code','redirect_uri'=>$redirectUri]);
            $ch = curl_init('https://appleid.apple.com/auth/token');
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_POSTFIELDS=>$post]);
            $tokenResponse = curl_exec($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
            $tokenData = is_string($tokenResponse) ? json_decode($tokenResponse,true) : null;
            if ($http < 200 || $http >= 300 || !is_array($tokenData) || empty($tokenData['id_token'])) throw new RuntimeException('Apple token exchange failed.');
            $claims = bh_apple_verify_id_token((string)$tokenData['id_token'],$clientId,$expectedNonce);
            $appleId = trim((string)$claims['sub']);
            $email = strtolower(trim((string)($claims['email'] ?? ($returnedUser['email'] ?? ''))));
            if ($appleId === '') throw new RuntimeException('Apple account identifier missing.');
        $newSocialUser = false;
        $db->exec("CREATE TABLE IF NOT EXISTS bh_social_accounts (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,provider VARCHAR(32) NOT NULL,provider_user_id VARCHAR(191) NOT NULL,email VARCHAR(255) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_provider_user(provider,provider_user_id),UNIQUE KEY uq_user_provider(user_id,provider),KEY idx_social_user(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $stmt=$db->prepare("SELECT user_id FROM bh_social_accounts WHERE provider='apple' AND provider_user_id=? LIMIT 1"); $stmt->execute([$appleId]); $linked=$stmt->fetch();
            if($linked){$userId=(int)$linked['user_id'];}
            else{
                $stmt=$db->prepare("SELECT id,status FROM bh_users WHERE email=? LIMIT 1"); $stmt->execute([$email]); $user=$email!==''?$stmt->fetch():false;
                if($user){if(($user['status']??'')!=='active') bh_auth_response(403,['ok'=>false,'error'=>'account_not_active','message'=>'This account is not currently active.']); $userId=(int)$user['id'];}
                else{
                    if($email==='') bh_auth_response(422,['ok'=>false,'error'=>'apple_email_missing','message'=>'Apple did not provide an email address. Please try again.']);
                    $randomPassword=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
                    $stmt=$db->prepare("INSERT INTO bh_users (email,password_hash,role,status) VALUES (?,?,'family','active')"); $stmt->execute([$email,$randomPassword]); $userId=(int)$db->lastInsertId(); $newSocialUser=true;
                }
                $stmt=$db->prepare("INSERT INTO bh_social_accounts (user_id,provider,provider_user_id,email) VALUES (?, 'apple', ?, ?)"); $stmt->execute([$userId,$appleId,$email]);
            }
            session_regenerate_id(true); $_SESSION['bh_user_id']=$userId; $_SESSION['bh_csrf']=bin2hex(random_bytes(24));
            $stmt=$db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1"); $stmt->execute([$userId]); $user=$stmt->fetch();
            $welcomeEmailSent = $newSocialUser ? bh_send_family_welcome($email, (string)($returnedUser['name']['firstName'] ?? '')) : false;
            bh_auth_response(200,['ok'=>true,'authenticated'=>true,'user'=>['id'=>(int)$user['id'],'email'=>$user['email'],'role'=>$user['role'],'status'=>$user['status']],'csrf'=>$_SESSION['bh_csrf'],'provider'=>'apple','welcome_email_sent'=>$welcomeEmailSent]);
        } catch (Throwable $e) {
            bh_auth_response(401,['ok'=>false,'error'=>'apple_signin_failed','message'=>'Apple sign-in could not be completed. Please try again.']);
        }
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
            else{$randomPassword=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);$stmt=$db->prepare("INSERT INTO bh_users (email,password_hash,role,status) VALUES (?,?,'family','active')");$stmt->execute([$email,$randomPassword]);$userId=(int)$db->lastInsertId();$newSocialUser=true;}
            $stmt=$db->prepare("INSERT INTO bh_social_accounts (user_id,provider,provider_user_id,email) VALUES (?, 'google', ?, ?)");$stmt->execute([$userId,$googleId,$email]);
        }
        session_regenerate_id(true); $_SESSION['bh_user_id']=$userId; $_SESSION['bh_csrf']=bin2hex(random_bytes(24));
        $stmt=$db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1");$stmt->execute([$userId]);$user=$stmt->fetch();
        $welcomeEmailSent = $newSocialUser ? bh_send_family_welcome($email, '') : false;
        bh_auth_response(200,['ok'=>true,'authenticated'=>true,'user'=>['id'=>(int)$user['id'],'email'=>$user['email'],'role'=>$user['role'],'status'=>$user['status']],'csrf'=>$_SESSION['bh_csrf'],'provider'=>'google','welcome_email_sent'=>$welcomeEmailSent]);
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
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));

        bh_auth_response(201, [
            'ok' => true,
            'authenticated' => true,
            'user' => ['id' => (int)$_SESSION['bh_user_id'], 'email' => $email, 'role' => 'family', 'status' => 'active', 'first_name' => $firstName, 'last_name' => $lastName, 'pro' => $eventPro],
            'pro' => $eventPro,
            'welcome_email_sent' => $welcomeEmailSent,
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