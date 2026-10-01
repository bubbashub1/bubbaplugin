<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_pro_response(int $status, array $data): never {
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

    if (empty($_SESSION['bh_user_id'])) {
        bh_pro_response(401, ['ok' => false, 'error' => 'login_required']);
    }

    $userId = (int)$_SESSION['bh_user_id'];
    $user = $db->prepare("SELECT id,status FROM bh_users WHERE id=? LIMIT 1");
    $user->execute([$userId]);
    $account = $user->fetch();

    if (!$account || ($account['status'] ?? '') !== 'active') {
        bh_pro_response(401, ['ok' => false, 'error' => 'login_required']);
    }

    if (!isset($_SESSION['bh_csrf'])) {
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare("SELECT state_json,updated_at FROM bh_planner_pro_state WHERE user_id=? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        $state = [];
        if ($row && !empty($row['state_json'])) {
            $decoded = json_decode((string)$row['state_json'], true);
            if (is_array($decoded)) $state = $decoded;
        }

        bh_pro_response(200, [
            'ok' => true,
            'state' => $state,
            'updated_at' => $row['updated_at'] ?? null,
            'csrf' => $_SESSION['bh_csrf'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        bh_pro_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
    }

    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) {
        bh_pro_response(400, ['ok' => false, 'error' => 'invalid_json']);
    }

    $csrf = (string)($body['csrf'] ?? '');
    if ($csrf === '' || !hash_equals((string)$_SESSION['bh_csrf'], $csrf)) {
        bh_pro_response(403, ['ok' => false, 'error' => 'csrf_invalid']);
    }

    $state = $body['state'] ?? null;
    if (!is_array($state)) {
        bh_pro_response(422, ['ok' => false, 'error' => 'state_required']);
    }

    $encoded = json_encode($state, JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        bh_pro_response(422, ['ok' => false, 'error' => 'state_invalid']);
    }
    if (strlen($encoded) > 500000) {
        bh_pro_response(413, ['ok' => false, 'error' => 'state_too_large']);
    }

    $stmt = $db->prepare(
        "INSERT INTO bh_planner_pro_state (user_id,state_json,created_at,updated_at)
         VALUES (?,?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE state_json=VALUES(state_json),updated_at=NOW()"
    );
    $stmt->execute([$userId, $encoded]);

    bh_pro_response(200, [
        'ok' => true,
        'updated_at' => date('Y-m-d H:i:s'),
        'csrf' => $_SESSION['bh_csrf'],
    ]);
} catch (Throwable $e) {
    bh_pro_response(500, ['ok' => false, 'error' => 'planner_pro_error', 'message' => $e->getMessage()]);
}
?>