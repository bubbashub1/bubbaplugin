<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('BUBBAHUB_ADMINSESSID');
session_start();

function bh_test_user_response(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['bh_admin_authenticated'])) {
    bh_test_user_response(401, ['ok'=>false,'error'=>'Admin login required.']);
}

require __DIR__ . '/db.php';

try {
    $db = bh_mysql();
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        $stmt = $db->query("SELECT id,email,role,status FROM bh_users WHERE email LIKE 'test+%@bubbahub.co.uk' ORDER BY id DESC");
        $users = [];
        foreach ($stmt->fetchAll() as $user) {
            $users[] = [
                'id'=>(int)$user['id'],
                'email'=>(string)$user['email'],
                'role'=>(string)$user['role'],
                'status'=>(string)$user['status']
            ];
        }
        bh_test_user_response(200, ['ok'=>true,'data'=>$users]);
    }

    if ($method === 'POST') {
        $body = json_decode((string)file_get_contents('php://input'), true);
        $body = is_array($body) ? $body : [];
        $requestedPassword = (string)($body['password'] ?? '');
        $role = strtolower(trim((string)($body['role'] ?? 'family')));
        if (!in_array($role, ['family', 'leader'], true)) {
            bh_test_user_response(422, ['ok'=>false,'error'=>'Test user role must be family or leader.']);
        }
        if ($requestedPassword !== '' && strlen($requestedPassword) < 8) {
            bh_test_user_response(422, ['ok'=>false,'error'=>'Password must be at least 8 characters.']);
        }

        $email = 'test+' . $role . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '@bubbahub.co.uk';
        $password = $requestedPassword !== '' ? $requestedPassword : ('Test!' . bin2hex(random_bytes(5)));
        $stmt = $db->prepare("INSERT INTO bh_users (email,password_hash,role,status) VALUES (?,?,?,'active')");
        $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $role]);
        $id = (int)$db->lastInsertId();

        bh_test_user_response(201, [
            'ok'=>true,
            'user'=>['id'=>$id,'email'=>$email,'role'=>$role,'status'=>'active'],
            'password'=>$password,
            'message'=>'Test ' . $role . ' account created. Save the password now; it is only shown once.'
        ]);
    }

    if ($method === 'DELETE') {
        $body = json_decode((string)file_get_contents('php://input'), true);
        $id = is_array($body) ? (int)($body['id'] ?? 0) : 0;
        if ($id < 1) bh_test_user_response(422, ['ok'=>false,'error'=>'A test user ID is required.']);

        $check = $db->prepare("SELECT id,email FROM bh_users WHERE id=? AND email LIKE 'test+%@bubbahub.co.uk' LIMIT 1");
        $check->execute([$id]);
        $user = $check->fetch();
        if (!$user) bh_test_user_response(404, ['ok'=>false,'error'=>'Test user not found.']);

        $stmt = $db->prepare("DELETE FROM bh_users WHERE id=? AND email LIKE 'test+%@bubbahub.co.uk'");
        $stmt->execute([$id]);
        bh_test_user_response(200, ['ok'=>true,'message'=>'Test user deleted.']);
    }

    bh_test_user_response(405, ['ok'=>false,'error'=>'Method not allowed.']);
} catch (Throwable $e) {
    bh_test_user_response(500, ['ok'=>false,'error'=>$e->getMessage()]);
}
?>