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

    // Include both junction links and older direct venue.activity_id links.
    $sql = "
        SELECT a.id, a.title, a.status,
               GROUP_CONCAT(DISTINCT NULLIF(TRIM(v.town), '') ORDER BY v.town SEPARATOR ', ') AS town,
               GROUP_CONCAT(DISTINCT NULLIF(TRIM(v.region), '') ORDER BY v.region SEPARATOR ', ') AS region
        FROM bh_activities a
        JOIN (
            SELECT activity_id, venue_id FROM bh_activity_venues
            UNION
            SELECT activity_id, id AS venue_id FROM bh_venues WHERE activity_id IS NOT NULL
        ) links ON links.activity_id = a.id
        JOIN bh_venues v ON v.id = links.venue_id
        WHERE a.status = 'published'
          AND EXISTS (
              SELECT 1 FROM bh_volunteer_assignments va
              WHERE va.user_id = :assigned_user AND va.is_active = 1
                AND LOWER(TRIM(va.region)) = LOWER(TRIM(v.region))
          )
          AND NOT EXISTS (
              SELECT 1 FROM (
                  SELECT activity_id, venue_id FROM bh_activity_venues
                  UNION
                  SELECT activity_id, id AS venue_id FROM bh_venues WHERE activity_id IS NOT NULL
              ) other_links
              LEFT JOIN bh_venues other_venue ON other_venue.id = other_links.venue_id
              WHERE other_links.activity_id = a.id
                AND (other_venue.id IS NULL OR NOT EXISTS (
                    SELECT 1 FROM bh_volunteer_assignments va_other
                    WHERE va_other.user_id = :other_user AND va_other.is_active = 1
                      AND LOWER(TRIM(va_other.region)) = LOWER(TRIM(other_venue.region))
                ))
          )
        GROUP BY a.id, a.title, a.status
        ORDER BY a.title
        LIMIT 200
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([':assigned_user' => $userId, ':other_user' => $userId]);
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
