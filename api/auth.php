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
        if (empty($_SESSION['bh_user_id'])) {
            bh_auth_response(200, ['ok' => true, 'authenticated' => false, 'csrf' => $_SESSION['bh_csrf']]);
        }

        $stmt = $db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1");
        $stmt->execute([(int)$_SESSION['bh_user_id']]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active') {
            $_SESSION = [];
            bh_auth_response(200, ['ok' => true, 'authenticated' => false, 'csrf' => $_SESSION['bh_csrf'] ?? null]);
        }

        bh_auth_response(200, [
            'ok' => true,
            'authenticated' => true,
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