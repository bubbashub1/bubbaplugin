<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_planner_response(int $status, array $data): never {
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
        bh_planner_response(401, ['ok' => false, 'error' => 'login_required', 'message' => 'Please sign in to sync your planner across devices.']);
    }

    $userId = (int)$_SESSION['bh_user_id'];

    $user = $db->prepare("SELECT id,role,status FROM bh_users WHERE id=? LIMIT 1");
    $user->execute([$userId]);
    $account = $user->fetch();
    if (!$account || ($account['status'] ?? '') !== 'active') {
        bh_planner_response(401, ['ok' => false, 'error' => 'login_required']);
    }

    if (!isset($_SESSION['bh_csrf'])) {
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $planned = [];
        $stmt = $db->prepare("SELECT activity_id,visited FROM bh_planner WHERE user_id=? ORDER BY created_at ASC");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            $planned[] = [
                'id' => (string)$row['activity_id'],
                'visited' => (bool)$row['visited'],
            ];
        }

        $hidden = [];
        $stmt = $db->prepare("SELECT day_of_week FROM bh_hidden_planner_days WHERE user_id=? ORDER BY day_of_week");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            $hidden[] = (string)(int)$row['day_of_week'];
        }

        bh_planner_response(200, [
            'ok' => true,
            'planned' => $planned,
            'hidden_days' => $hidden,
            'csrf' => $_SESSION['bh_csrf'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        bh_planner_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
    }

    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) {
        bh_planner_response(400, ['ok' => false, 'error' => 'invalid_json']);
    }

    $csrf = (string)($body['csrf'] ?? '');
    if ($csrf === '' || !hash_equals((string)$_SESSION['bh_csrf'], $csrf)) {
        bh_planner_response(403, ['ok' => false, 'error' => 'csrf_invalid']);
    }

    $action = (string)($body['action'] ?? '');

    if ($action === 'sync') {
        $planned = is_array($body['planned'] ?? null) ? $body['planned'] : [];
        $hidden = is_array($body['hidden_days'] ?? null) ? $body['hidden_days'] : [];
        $visitedMap = [];

        foreach ($planned as $item) {
            if (is_array($item)) {
                $id = (int)($item['id'] ?? 0);
                $visited = !empty($item['visited']);
            } else {
                $id = (int)$item;
                $visited = false;
            }
            if ($id > 0) $visitedMap[$id] = $visited;
        }

        $hiddenDays = [];
        foreach ($hidden as $day) {
            $day = (int)$day;
            if ($day >= 1 && $day <= 7) $hiddenDays[$day] = true;
        }

        $db->beginTransaction();
        $ins = $db->prepare("INSERT INTO bh_planner (user_id,activity_id,visited) VALUES (?,?,?) ON DUPLICATE KEY UPDATE visited=VALUES(visited)");
        foreach ($visitedMap as $activityId => $visited) {
            $ins->execute([$userId, $activityId, $visited ? 1 : 0]);
        }

        if ($visitedMap) {
            $placeholders = implode(',', array_fill(0, count($visitedMap), '?'));
            $params = array_merge([$userId], array_keys($visitedMap));
            $del = $db->prepare("DELETE FROM bh_planner WHERE user_id=? AND activity_id NOT IN ($placeholders)");
            $del->execute($params);
        } else {
            $db->prepare("DELETE FROM bh_planner WHERE user_id=?")->execute([$userId]);
        }

        $db->prepare("DELETE FROM bh_hidden_planner_days WHERE user_id=?")->execute([$userId]);
        if ($hiddenDays) {
            $hide = $db->prepare("INSERT INTO bh_hidden_planner_days (user_id,day_of_week) VALUES (?,?)");
            foreach (array_keys($hiddenDays) as $day) $hide->execute([$userId, $day]);
        }

        $db->commit();
        bh_planner_response(200, ['ok' => true]);
    }

    if ($action === 'set_activity') {
        $activityId = (int)($body['activity_id'] ?? 0);
        $planned = !empty($body['planned']);
        $visited = !empty($body['visited']);

        if ($activityId < 1) bh_planner_response(422, ['ok' => false, 'error' => 'activity_required']);

        $exists = $db->prepare("SELECT id FROM bh_activities WHERE id=? AND status='published' LIMIT 1");
        $exists->execute([$activityId]);
        if (!$exists->fetch()) bh_planner_response(404, ['ok' => false, 'error' => 'activity_not_found']);

        if ($planned) {
            $stmt = $db->prepare("INSERT INTO bh_planner (user_id,activity_id,visited) VALUES (?,?,?) ON DUPLICATE KEY UPDATE visited=VALUES(visited)");
            $stmt->execute([$userId, $activityId, $visited ? 1 : 0]);
        } else {
            $stmt = $db->prepare("DELETE FROM bh_planner WHERE user_id=? AND activity_id=?");
            $stmt->execute([$userId, $activityId]);
        }

        bh_planner_response(200, ['ok' => true]);
    }

    if ($action === 'set_visited') {
        $activityId = (int)($body['activity_id'] ?? 0);
        $visited = !empty($body['visited']);
        if ($activityId < 1) bh_planner_response(422, ['ok' => false, 'error' => 'activity_required']);

        $stmt = $db->prepare("UPDATE bh_planner SET visited=? WHERE user_id=? AND activity_id=?");
        $stmt->execute([$visited ? 1 : 0, $userId, $activityId]);
        bh_planner_response(200, ['ok' => true]);
    }

    if ($action === 'set_day') {
        $day = (int)($body['day'] ?? 0);
        $visible = !empty($body['visible']);
        if ($day < 1 || $day > 7) bh_planner_response(422, ['ok' => false, 'error' => 'day_required']);

        if ($visible) {
            $stmt = $db->prepare("DELETE FROM bh_hidden_planner_days WHERE user_id=? AND day_of_week=?");
            $stmt->execute([$userId, $day]);
        } else {
            $stmt = $db->prepare("INSERT IGNORE INTO bh_hidden_planner_days (user_id,day_of_week) VALUES (?,?)");
            $stmt->execute([$userId, $day]);
        }
        bh_planner_response(200, ['ok' => true]);
    }

    if ($action === 'show_all_days') {
        $db->prepare("DELETE FROM bh_hidden_planner_days WHERE user_id=?")->execute([$userId]);
        bh_planner_response(200, ['ok' => true]);
    }

    bh_planner_response(400, ['ok' => false, 'error' => 'unknown_action']);
} catch (Throwable $e) {
    bh_planner_response(500, ['ok' => false, 'error' => 'planner_error', 'message' => $e->getMessage()]);
}
?>