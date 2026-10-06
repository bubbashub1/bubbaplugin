<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/db.php';

function bh_leader_signup_response(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        bh_leader_signup_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
    }

    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) {
        bh_leader_signup_response(400, ['ok' => false, 'error' => 'invalid_json', 'message' => 'The signup request could not be read.']);
    }

    $csrf = trim((string)($body['csrf'] ?? ''));
    if ($csrf === '' || empty($_SESSION['bh_csrf']) || !hash_equals((string)$_SESSION['bh_csrf'], $csrf)) {
        bh_leader_signup_response(403, ['ok' => false, 'error' => 'csrf_invalid', 'message' => 'Please refresh the page and try again.']);
    }

    $organisation = trim((string)($body['organisation_name'] ?? ''));
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $phone = trim((string)($body['phone'] ?? ''));
    $website = trim((string)($body['website'] ?? ''));
    $password = (string)($body['password'] ?? '');
    $confirm = (string)($body['confirm_password'] ?? '');
    $terms = !empty($body['terms']);

    if ($organisation === '' || mb_strlen($organisation) > 190) {
        bh_leader_signup_response(422, ['ok' => false, 'error' => 'organisation_required', 'message' => 'Please enter the name of your class, business or organisation.']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        bh_leader_signup_response(422, ['ok' => false, 'error' => 'invalid_email', 'message' => 'Please enter a valid email address.']);
    }
    if ($phone !== '' && mb_strlen($phone) > 80) {
        bh_leader_signup_response(422, ['ok' => false, 'error' => 'invalid_phone', 'message' => 'Please check your phone number.']);
    }
    if ($website !== '' && (!filter_var($website, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $website))) {
        bh_leader_signup_response(422, ['ok' => false, 'error' => 'invalid_website', 'message' => 'Please enter a full website address starting with http:// or https://.']);
    }
    if (strlen($password) < 8) {
        bh_leader_signup_response(422, ['ok' => false, 'error' => 'password_too_short', 'message' => 'Please choose a password with at least 8 characters.']);
    }
    if ($password !== $confirm) {
        bh_leader_signup_response(422, ['ok' => false, 'error' => 'password_mismatch', 'message' => 'The passwords do not match.']);
    }
    if (!$terms) {
        bh_leader_signup_response(422, ['ok' => false, 'error' => 'terms_required', 'message' => 'Please confirm that you agree to the Bubba Hub organiser terms.']);
    }

    $db = bh_mysql();

    $existingUser = $db->prepare("SELECT id,role,status FROM bh_users WHERE email=? LIMIT 1");
    $existingUser->execute([$email]);
    $user = $existingUser->fetch();
    if ($user) {
        bh_leader_signup_response(409, [
            'ok' => false,
            'error' => 'email_exists',
            'message' => (($user['role'] ?? '') === 'leader')
                ? 'A leader account already exists for this email. Please sign in instead.'
                : 'This email is already linked to a Bubba Hub account. Leader accounts are separate from family accounts, so please use a different email address.',
        ]);
    }

    $hasOrgUserId = false;
    try {
        $columnCheck = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='user_id'");
        $columnCheck->execute();
        $hasOrgUserId = (int)$columnCheck->fetchColumn() > 0;
    } catch (Throwable $ignored) {}

    $org = false;
    $orgQuery = $db->prepare("SELECT * FROM bh_organisers WHERE LOWER(email)=? LIMIT 1");
    $orgQuery->execute([$email]);
    $org = $orgQuery->fetch();

    if ($org) {
        if ($hasOrgUserId && !empty($org['user_id'])) {
            bh_leader_signup_response(409, ['ok' => false, 'error' => 'organiser_claimed', 'message' => 'An organiser profile is already linked to this email. Please sign in or contact Bubba Hub support.']);
        }
        if (($org['status'] ?? '') === 'suspended') {
            bh_leader_signup_response(403, ['ok' => false, 'error' => 'organiser_suspended', 'message' => 'This organiser profile is currently suspended. Please contact Bubba Hub support.']);
        }
    }

    $slugBase = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $organisation), '-'));
    $slugBase = $slugBase !== '' ? $slugBase : 'leader';
    $slug = $slugBase;
    $n = 2;
    if (!$org) {
        while (true) {
            $slugCheck = $db->prepare("SELECT id FROM bh_organisers WHERE slug=? LIMIT 1");
            $slugCheck->execute([$slug]);
            if (!$slugCheck->fetch()) break;
            $slug = $slugBase . '-' . $n++;
        }
    }

    $db->beginTransaction();

    try {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $userInsert = $db->prepare("INSERT INTO bh_users (email,password_hash,role,status) VALUES (?,?,'leader','active')");
        $userInsert->execute([$email, $passwordHash]);
        $userId = (int)$db->lastInsertId();

        if ($org) {
            if ($hasOrgUserId) {
                $update = $db->prepare("UPDATE bh_organisers SET user_id=?,phone=?,website=? WHERE id=?");
                $update->execute([$userId, $phone !== '' ? $phone : ($org['phone'] ?? null), $website !== '' ? $website : ($org['website'] ?? null), (int)$org['id']]);
            } else {
                $update = $db->prepare("UPDATE bh_organisers SET phone=?,website=? WHERE id=?");
                $update->execute([$phone !== '' ? $phone : ($org['phone'] ?? null), $website !== '' ? $website : ($org['website'] ?? null), (int)$org['id']]);
            }
            $organiserId = (int)$org['id'];
        } elseif ($hasOrgUserId) {
            $insert = $db->prepare("INSERT INTO bh_organisers (user_id,organisation_name,slug,description,email,phone,website,status) VALUES (?,?,?,?,?,?,?,'pending')");
            $insert->execute([$userId,$organisation,$slug,'',$email,$phone !== '' ? $phone : null,$website !== '' ? $website : null]);
            $organiserId = (int)$db->lastInsertId();
        } else {
            $insert = $db->prepare("INSERT INTO bh_organisers (organisation_name,slug,description,email,phone,website,status) VALUES (?,?,?,?,?,?, 'pending')");
            $insert->execute([$organisation,$slug,'',$email,$phone !== '' ? $phone : null,$website !== '' ? $website : null]);
            $organiserId = (int)$db->lastInsertId();
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    session_regenerate_id(true);
    $_SESSION['bh_user_id'] = $userId;
    $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));

    bh_leader_signup_response(201, [
        'ok' => true,
        'authenticated' => true,
        'pending_review' => true,
        'organiser_id' => $organiserId,
        'user' => [
            'id' => $userId,
            'email' => $email,
            'role' => 'leader',
            'status' => 'active',
        ],
        'csrf' => $_SESSION['bh_csrf'],
        'message' => 'Your leader account is ready. Your organiser profile is pending review.',
    ]);
} catch (Throwable $e) {
    error_log('Bubba Hub leader signup failed: ' . $e->getMessage());
    bh_leader_signup_response(500, ['ok' => false, 'error' => 'leader_signup_error', 'message' => 'We could not create your leader account just yet. Please try again.']);
}
?>
