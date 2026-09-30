<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_listing_org_response(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function bh_listing_org_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function bh_listing_org_column(PDO $db, string $table, string $column): bool {
    $q = $db->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $q->execute([$table, $column]);
    return (int)$q->fetchColumn() > 0;
}

try {
    require __DIR__ . '/db.php';
    bh_listing_org_session();
    $db = bh_mysql();

    if (!isset($_SESSION['bh_csrf'])) {
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
    }

    $activityId = (int)($_GET['activity_id'] ?? $_POST['activity_id'] ?? 0);
    if ($activityId < 1) {
        bh_listing_org_response(422, ['ok' => false, 'error' => 'activity_id_required']);
    }

    $activityAuthorColumn = null;
    foreach (['author_id', 'user_id', 'created_by'] as $candidate) {
        if (bh_listing_org_column($db, 'bh_activities', $candidate)) {
            $activityAuthorColumn = $candidate;
            break;
        }
    }

    $authorId = 0;
    if ($activityAuthorColumn !== null) {
        $q = $db->prepare("SELECT {$activityAuthorColumn} FROM bh_activities WHERE id = ? LIMIT 1");
        $q->execute([$activityId]);
        $authorId = (int)($q->fetchColumn() ?: 0);
    }

    $q = $db->prepare(
        "SELECT a.id, a.title, a.organiser_id,
                o.organisation_name,
                o.email AS organiser_email
         FROM bh_activities a
         LEFT JOIN bh_organisers o ON o.id = a.organiser_id
         WHERE a.id = ? LIMIT 1"
    );
    $q->execute([$activityId]);
    $activity = $q->fetch();

    if (!$activity) {
        bh_listing_org_response(404, ['ok' => false, 'error' => 'listing_not_found']);
    }

    $admin = !empty($_SESSION['bh_admin_authenticated']);
    $userId = (int)($_SESSION['bh_user_id'] ?? 0);
    $user = null;

    if ($userId > 0) {
        $q = $db->prepare("SELECT id, email, role, status FROM bh_users WHERE id = ? LIMIT 1");
        $q->execute([$userId]);
        $user = $q->fetch() ?: null;
    }

    $activeUser = $user && ($user['status'] ?? '') === 'active';

    // Resolve the organiser linked to the logged-in leader. Newer schemas use
    // bh_organisers.user_id; older schemas can still be matched by email.
    $linkedOrganiserId = 0;
    $hasOrgUserId = bh_listing_org_column($db, 'bh_organisers', 'user_id');

    if ($activeUser) {
        if ($hasOrgUserId) {
            $q = $db->prepare("SELECT id FROM bh_organisers WHERE user_id = ? LIMIT 1");
            $q->execute([$userId]);
            $linkedOrganiserId = (int)($q->fetchColumn() ?: 0);
        }

        if (!$linkedOrganiserId && !empty($user['email'])) {
            $q = $db->prepare("SELECT id FROM bh_organisers WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $q->execute([(string)$user['email']]);
            $linkedOrganiserId = (int)($q->fetchColumn() ?: 0);
        }
    }

    $assignedOrganiserId = (int)($activity['organiser_id'] ?? 0);

    $isAdmin = $admin;
    $isAssignedOrganiser = $activeUser && $linkedOrganiserId > 0 && $linkedOrganiserId === $assignedOrganiserId;
    $isAuthor = $activeUser && $authorId > 0 && $authorId === $userId;

    // In this custom Bubba Hub system there is no WordPress post-author concept.
    // If a legacy author_id/user_id/created_by column exists, it is honoured.
    $canEdit = $isAdmin || $isAssignedOrganiser || $isAuthor;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $organisers = [];
        if ($canEdit) {
            if ($isAdmin) {
                $q = $db->query(
                    "SELECT id, organisation_name, email
                     FROM bh_organisers
                     WHERE COALESCE(status, 'published') <> 'archived'
                     ORDER BY organisation_name ASC, id ASC"
                );
                $organisers = $q->fetchAll();
            } elseif ($assignedOrganiserId > 0) {
                $q = $db->prepare(
                    "SELECT id, organisation_name, email
                     FROM bh_organisers WHERE id = ? LIMIT 1"
                );
                $q->execute([$assignedOrganiserId]);
                $organisers = $q->fetchAll();
            }
        }

        bh_listing_org_response(200, [
            'ok' => true,
            'authenticated' => $activeUser || $admin,
            'permission' => [
                'can_edit' => $canEdit,
                'is_admin' => $isAdmin,
                'is_author' => $isAuthor,
                'is_assigned_organiser' => $isAssignedOrganiser,
            ],
            'activity' => [
                'id' => (int)$activity['id'],
                'title' => $activity['title'],
                'organiser_id' => $assignedOrganiserId ?: null,
                'organiser_name' => $activity['organisation_name'] ?: 'Unassigned',
            ],
            'organisers' => $organisers,
            'csrf' => $_SESSION['bh_csrf'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        bh_listing_org_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
    }

    if (!$canEdit) {
        bh_listing_org_response(403, ['ok' => false, 'error' => 'permission_denied', 'message' => 'You do not have permission to edit this listing.']);
    }

    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) {
        bh_listing_org_response(400, ['ok' => false, 'error' => 'invalid_json']);
    }

    $csrf = (string)($body['csrf'] ?? '');
    if ($csrf === '' || empty($_SESSION['bh_csrf']) || !hash_equals((string)$_SESSION['bh_csrf'], $csrf)) {
        bh_listing_org_response(403, ['ok' => false, 'error' => 'csrf_invalid', 'message' => 'Your session has expired. Please refresh the page and try again.']);
    }

    $newOrganiserId = (int)($body['organiser_id'] ?? 0);

    // Only administrators can transfer a listing to an arbitrary organiser.
    // Authors/assigned organisers retain front-end edit access, but cannot
    // transfer ownership to another organisation.
    if (!$isAdmin && $newOrganiserId !== $assignedOrganiserId) {
        bh_listing_org_response(403, [
            'ok' => false,
            'error' => 'organiser_transfer_denied',
            'message' => 'Only an administrator can change the assigned organiser.'
        ]);
    }

    if ($newOrganiserId < 1) {
        bh_listing_org_response(422, ['ok' => false, 'error' => 'organiser_required', 'message' => 'Please select an organiser.']);
    }

    $q = $db->prepare(
        "SELECT id, organisation_name, email
         FROM bh_organisers
         WHERE id = ? AND COALESCE(status, 'published') <> 'archived'
         LIMIT 1"
    );
    $q->execute([$newOrganiserId]);
    $newOrganiser = $q->fetch();

    if (!$newOrganiser) {
        bh_listing_org_response(422, ['ok' => false, 'error' => 'organiser_not_found', 'message' => 'The selected organiser could not be found.']);
    }

    $update = $db->prepare("UPDATE bh_activities SET organiser_id = ? WHERE id = ?");
    $update->execute([$newOrganiserId, $activityId]);

    bh_listing_org_response(200, [
        'ok' => true,
        'message' => 'Assigned organiser updated.',
        'activity_id' => $activityId,
        'organiser' => [
            'id' => (int)$newOrganiser['id'],
            'name' => $newOrganiser['organisation_name'],
            'email' => $newOrganiser['email'] ?? '',
        ],
    ]);
} catch (Throwable $e) {
    bh_listing_org_response(500, [
        'ok' => false,
        'error' => 'listing_organiser_error',
        'message' => $e->getMessage(),
    ]);
}
?>