<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function volunteerDashboardReply(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    volunteerDashboardReply(405, ['ok' => false, 'error' => 'method_not_allowed']);
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
    volunteerDashboardReply(401, ['ok' => false, 'error' => 'login_required']);
}

try {
    require __DIR__ . '/db.php';
    $db = bh_mysql();

    $user = $db->prepare("SELECT role FROM bh_users WHERE id = ? AND status = 'active' LIMIT 1");
    $user->execute([$userId]);
    $role = $user->fetchColumn();
    if ($role === false) {
        volunteerDashboardReply(403, ['ok' => false, 'error' => 'inactive_account']);
    }
    if ($role !== 'volunteer') {
        volunteerDashboardReply(403, ['ok' => false, 'error' => 'volunteer_role_required']);
    }

    $assignment = $db->prepare(
        "SELECT DISTINCT TRIM(region) AS region
         FROM bh_volunteer_assignments
         WHERE user_id = ? AND is_active = 1 AND TRIM(COALESCE(region, '')) <> ''
         ORDER BY region"
    );
    $assignment->execute([$userId]);
    $regions = $assignment->fetchAll(PDO::FETCH_COLUMN);

    if (!$regions) {
        volunteerDashboardReply(403, [
            'ok' => false,
            'error' => 'volunteer_assignment_required',
        ]);
    }

    $regionChecks = [];
    $params = [];
    foreach ($regions as $index => $region) {
        $key = ':region' . $index;
        $regionChecks[] = "LOWER(TRIM(v.region)) = LOWER(TRIM($key))";
        $params[$key] = $region;
    }

    // Use the canonical activity_venues junction so multi-venue listings are checked correctly.
    $sql = "
        SELECT
            a.id,
            a.title,
            a.status,
            GROUP_CONCAT(DISTINCT NULLIF(TRIM(v.town), '') ORDER BY v.town SEPARATOR ', ') AS town,
            GROUP_CONCAT(DISTINCT NULLIF(TRIM(v.region), '') ORDER BY v.region SEPARATOR ', ') AS region
        FROM bh_activities a
        INNER JOIN bh_activity_venues av ON av.activity_id = a.id
        INNER JOIN bh_venues v ON v.id = av.venue_id
        WHERE (" . implode(' OR ', $regionChecks) . ")
          AND NOT EXISTS (
              SELECT 1
              FROM bh_activity_venues av_other
              INNER JOIN bh_venues v_other ON v_other.id = av_other.venue_id
              WHERE av_other.activity_id = a.id
                AND NOT EXISTS (
                    SELECT 1
                    FROM bh_volunteer_assignments allowed_region
                    WHERE allowed_region.user_id = :assigned_user
                      AND allowed_region.is_active = 1
                      AND LOWER(TRIM(allowed_region.region)) = LOWER(TRIM(v_other.region))
                )
          )
        GROUP BY a.id, a.title, a.status
        ORDER BY a.title
        LIMIT 200
    ";

    $params[':assigned_user'] = $userId;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    volunteerDashboardReply(200, [
        'ok' => true,
        'regions' => $regions,
        'listings' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'editing_enabled' => true,
    ]);
} catch (Throwable $e) {
    error_log('Volunteer dashboard failed: ' . $e->getMessage());
    volunteerDashboardReply(500, ['ok' => false, 'error' => 'temporarily_unavailable']);
}
