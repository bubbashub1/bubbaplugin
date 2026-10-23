<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function volunteerReply(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    volunteerReply(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('BUBBAHUBSESSID');
    session_set_cookie_params([
        'path' => '/',
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

$userId = (int) ($_SESSION['bh_user_id'] ?? 0);
if ($userId < 1) {
    volunteerReply(401, ['ok' => false, 'error' => 'login_required']);
}

if (empty($_SESSION['bh_csrf'])) {
    $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
}

$body = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 12000) {
        volunteerReply(413, ['ok' => false, 'error' => 'invalid_body']);
    }
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        volunteerReply(400, ['ok' => false, 'error' => 'invalid_json']);
    }
    if (!hash_equals((string) $_SESSION['bh_csrf'], (string) ($body['csrf'] ?? ''))) {
        volunteerReply(403, ['ok' => false, 'error' => 'csrf_invalid']);
    }
}

$id = (int) ($method === 'GET' ? ($_GET['id'] ?? 0) : ($body['id'] ?? 0));
if ($id < 1) {
    volunteerReply(422, ['ok' => false, 'error' => 'invalid_activity']);
}

try {
    require __DIR__ . '/db.php';
    $db = bh_mysql();

    $user = $db->prepare("SELECT role FROM bh_users WHERE id = ? AND status = 'active' LIMIT 1");
    $user->execute([$userId]);
    $role = $user->fetchColumn();
    if ($role === false) {
        volunteerReply(403, ['ok' => false, 'error' => 'inactive_account']);
    }
    if ($role !== 'volunteer') {
        volunteerReply(403, ['ok' => false, 'error' => 'volunteer_role_required']);
    }

    // Require every venue from either link model to belong to an assigned region.
    $permissionSql = "
        SELECT COUNT(*) AS total,
               COALESCE(SUM(CASE WHEN v.id IS NOT NULL AND EXISTS (
                   SELECT 1 FROM bh_volunteer_assignments va
                   WHERE va.user_id = ? AND va.is_active = 1
                     AND LOWER(TRIM(va.region)) = LOWER(TRIM(v.region))
               ) THEN 1 ELSE 0 END), 0) AS allowed
        FROM (
            SELECT activity_id, venue_id FROM bh_activity_venues
            UNION
            SELECT activity_id, id AS venue_id FROM bh_venues WHERE activity_id IS NOT NULL
        ) links
        LEFT JOIN bh_venues v ON v.id = links.venue_id
        WHERE links.activity_id = ?
    ";
    $permission = $db->prepare($permissionSql);
    $permission->execute([$userId, $id]);
    $counts = $permission->fetch(PDO::FETCH_ASSOC);

    $isAllowed = (int) ($counts['total'] ?? 0) > 0
        && (int) $counts['allowed'] === (int) $counts['total'];
    if (!$isAllowed) {
        volunteerReply(403, ['ok' => false, 'error' => 'region_not_authorised']);
    }

    if ($method === 'GET') {
        $query = $db->prepare("SELECT id, title, description FROM bh_activities WHERE id = ? AND status = 'published' LIMIT 1");
        $query->execute([$id]);
        $activity = $query->fetch(PDO::FETCH_ASSOC);
        if (!$activity) {
            volunteerReply(404, ['ok' => false, 'error' => 'listing_not_found']);
        }
        volunteerReply(200, [
            'ok' => true,
            'activity' => $activity,
            'csrf' => $_SESSION['bh_csrf'],
        ]);
    }

    $title = trim((string) ($body['title'] ?? ''));
    $description = trim((string) ($body['description'] ?? ''));
    if (strlen($title) < 3 || strlen($title) > 200 || strlen($description) > 8000) {
        volunteerReply(422, ['ok' => false, 'error' => 'invalid_fields']);
    }

    $db->beginTransaction();
    $query = $db->prepare("SELECT id, title, description FROM bh_activities WHERE id = ? AND status = 'published' FOR UPDATE");
    $query->execute([$id]);
    $before = $query->fetch(PDO::FETCH_ASSOC);
    if (!$before) {
        $db->rollBack();
        volunteerReply(404, ['ok' => false, 'error' => 'listing_not_found']);
    }

    // Recheck permissions after locking the activity to reduce race windows.
    $permission->execute([$userId, $id]);
    $counts = $permission->fetch(PDO::FETCH_ASSOC);
    if ((int) ($counts['total'] ?? 0) === 0
        || (int) $counts['allowed'] !== (int) $counts['total']) {
        $db->rollBack();
        volunteerReply(403, ['ok' => false, 'error' => 'region_not_authorised']);
    }

    $after = ['id' => $id, 'title' => $title, 'description' => $description];
    $audit = $db->prepare(
        "INSERT INTO bh_volunteer_edit_audit
            (user_id, activity_id, before_json, after_json)
         VALUES (?, ?, ?, ?)"
    );
    $audit->execute([
        $userId,
        $id,
        json_encode($before, JSON_THROW_ON_ERROR),
        json_encode($after, JSON_THROW_ON_ERROR),
    ]);

    $update = $db->prepare("UPDATE bh_activities SET title = ?, description = ? WHERE id = ?");
    $update->execute([$title, $description, $id]);
    $db->commit();

    volunteerReply(200, ['ok' => true, 'message' => 'Listing saved', 'activity' => $after]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Volunteer listing edit failed: ' . $e->getMessage());
    volunteerReply(500, ['ok' => false, 'error' => 'temporarily_unavailable']);
}
