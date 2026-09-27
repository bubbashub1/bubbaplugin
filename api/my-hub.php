<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_hub_json(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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

    $userId = (int)($_SESSION['bh_user_id'] ?? 0);
    if ($userId < 1) {
        bh_hub_json(401, ['ok' => false, 'error' => 'login_required', 'message' => 'Please sign in to use My Hub.']);
    }

    $db = bh_mysql();
    $userStmt = $db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    if (!$user || ($user['status'] ?? '') !== 'active') {
        bh_hub_json(401, ['ok' => false, 'error' => 'login_required']);
    }

    if (!isset($_SESSION['bh_csrf'])) {
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $children = [];
        $stmt = $db->prepare("SELECT id,name,gender,photo_path,date_of_birth,created_at,updated_at FROM bh_children WHERE user_id=? ORDER BY date_of_birth IS NULL,date_of_birth,name");
        $stmt->execute([$userId]);
        $children = $stmt->fetchAll();

        $bumps = [];
        $stmt = $db->prepare("SELECT id,nickname,photo_path,due_date,created_at,updated_at FROM bh_bumps WHERE user_id=? ORDER BY due_date IS NULL,due_date");
        $stmt->execute([$userId]);
        $bumps = $stmt->fetchAll();

        $saved = [];
        $stmt = $db->prepare("SELECT activity_id,created_at FROM bh_saved_activities WHERE user_id=? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) $saved[] = (string)$row['activity_id'];

        $planner = [];
        $stmt = $db->prepare("SELECT activity_id,visited FROM bh_planner WHERE user_id=? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) $planner[] = ['id' => (string)$row['activity_id'], 'visited' => (bool)$row['visited']];

        $bookings = [];
        try {
            $check = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('bh_booking_reservations','bh_booking_slots','bh_activities','bh_venues')");
            if ((int)$check->fetchColumn() === 4) {
                $stmt = $db->prepare(
                    "SELECT br.id,br.status,br.quantity,br.created_at,
                            bs.starts_at,bs.ends_at,bs.activity_id,bs.venue_id,
                            a.title,v.venue_name
                     FROM bh_booking_reservations br
                     INNER JOIN bh_booking_slots bs ON bs.id=br.slot_id
                     INNER JOIN bh_activities a ON a.id=bs.activity_id
                     LEFT JOIN bh_venues v ON v.id=bs.venue_id
                     WHERE br.user_id=?
                     ORDER BY bs.starts_at ASC,br.id ASC
                     LIMIT 20"
                );
                $stmt->execute([$userId]);
                $bookings = $stmt->fetchAll();
            }
        } catch (Throwable $ignored) {}

        bh_hub_json(200, [
            'ok' => true,
            'user' => ['id' => (int)$user['id'], 'email' => $user['email'], 'role' => $user['role']],
            'children' => $children,
            'bumps' => $bumps,
            'saved' => $saved,
            'planner' => $planner,
            'bookings' => $bookings,
            'csrf' => $_SESSION['bh_csrf'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        bh_hub_json(405, ['ok' => false, 'error' => 'method_not_allowed']);
    }

    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) bh_hub_json(400, ['ok' => false, 'error' => 'invalid_json']);

    $csrf = (string)($body['csrf'] ?? '');
    if ($csrf === '' || !hash_equals((string)$_SESSION['bh_csrf'], $csrf)) {
        bh_hub_json(403, ['ok' => false, 'error' => 'csrf_invalid']);
    }

    $action = (string)($body['action'] ?? '');

    if ($action === 'sync_saved') {
        $saved = array_values(array_unique(array_filter(array_map('intval', (array)($body['saved'] ?? [])), static fn($id) => $id > 0)));
        $db->beginTransaction();
        if ($saved) {
            $placeholders = implode(',', array_fill(0, count($saved), '?'));
            $stmt = $db->prepare("SELECT id FROM bh_activities WHERE id IN ($placeholders) AND status='published'");
            $stmt->execute($saved);
            $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } else {
            $valid = [];
        }

        $db->prepare("DELETE FROM bh_saved_activities WHERE user_id=?")->execute([$userId]);
        if ($valid) {
            $stmt = $db->prepare("INSERT INTO bh_saved_activities (user_id,activity_id) VALUES (?,?)");
            foreach ($valid as $activityId) $stmt->execute([$userId, $activityId]);
        }
        $db->commit();
        bh_hub_json(200, ['ok' => true, 'saved' => array_map('strval', $valid)]);
    }

    if ($action === 'save_child') {
        $id = (int)($body['id'] ?? 0);
        $name = trim((string)($body['name'] ?? ''));
        $gender = trim((string)($body['gender'] ?? ''));
        $dob = trim((string)($body['date_of_birth'] ?? ''));
        if ($name === '') bh_hub_json(422, ['ok' => false, 'error' => 'child_name_required']);
        $dobValue = null;
        if ($dob !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $dob);
            if (!$d || $d->format('Y-m-d') !== $dob) bh_hub_json(422, ['ok' => false, 'error' => 'invalid_date_of_birth']);
            $dobValue = $dob;
        }
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE bh_children SET name=?,gender=?,date_of_birth=? WHERE id=? AND user_id=?");
            $stmt->execute([$name,$gender ?: null,$dobValue,$id,$userId]);
            if (!$stmt->rowCount()) bh_hub_json(404, ['ok' => false, 'error' => 'child_not_found']);
        } else {
            $stmt = $db->prepare("INSERT INTO bh_children (user_id,name,gender,date_of_birth) VALUES (?,?,?,?)");
            $stmt->execute([$userId,$name,$gender ?: null,$dobValue]);
            $id = (int)$db->lastInsertId();
        }
        bh_hub_json(200, ['ok' => true, 'id' => $id]);
    }

    if ($action === 'delete_child') {
        $id = (int)($body['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM bh_children WHERE id=? AND user_id=?");
        $stmt->execute([$id,$userId]);
        bh_hub_json(200, ['ok' => true]);
    }

    if ($action === 'save_bump') {
        $id = (int)($body['id'] ?? 0);
        $nickname = trim((string)($body['nickname'] ?? ''));
        $due = trim((string)($body['due_date'] ?? ''));
        $dueValue = null;
        if ($due !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $due);
            if (!$d || $d->format('Y-m-d') !== $due) bh_hub_json(422, ['ok' => false, 'error' => 'invalid_due_date']);
            $dueValue = $due;
        }
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE bh_bumps SET nickname=?,due_date=? WHERE id=? AND user_id=?");
            $stmt->execute([$nickname ?: null,$dueValue,$id,$userId]);
            if (!$stmt->rowCount()) bh_hub_json(404, ['ok' => false, 'error' => 'bump_not_found']);
        } else {
            $stmt = $db->prepare("INSERT INTO bh_bumps (user_id,nickname,due_date) VALUES (?,?,?)");
            $stmt->execute([$userId,$nickname ?: null,$dueValue]);
            $id = (int)$db->lastInsertId();
        }
        bh_hub_json(200, ['ok' => true, 'id' => $id]);
    }

    if ($action === 'delete_bump') {
        $id = (int)($body['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM bh_bumps WHERE id=? AND user_id=?");
        $stmt->execute([$id,$userId]);
        bh_hub_json(200, ['ok' => true]);
    }

    bh_hub_json(400, ['ok' => false, 'error' => 'unknown_action']);
} catch (Throwable $e) {
    bh_hub_json(500, ['ok' => false, 'error' => 'my_hub_error', 'message' => $e->getMessage()]);
}
?>