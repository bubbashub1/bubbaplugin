<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function br_json(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function br_csrf(): string {
    if (!isset($_SESSION['bh_csrf'])) $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
    return (string)$_SESSION['bh_csrf'];
}

try {
    require_once __DIR__ . '/db.php';
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('BUBBAHUBSESSID');
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
        session_start();
    }
    $userId = (int)($_SESSION['bh_user_id'] ?? 0);
    if ($userId < 1) br_json(401, ['ok'=>false,'error'=>'login_required','message'=>'Please sign in to make or view bookings.']);
    $db = bh_mysql();
    $userQ = $db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1");
    $userQ->execute([$userId]);
    $user = $userQ->fetch(PDO::FETCH_ASSOC);
    if (!$user || ($user['status'] ?? '') !== 'active') br_json(403, ['ok'=>false,'error'=>'account_not_active']);
    $role = (string)($user['role'] ?? '');

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($role === 'leader') {
            $orgQ = $db->prepare("SELECT id FROM bh_organisers WHERE user_id=? OR email=? ORDER BY (user_id=?) DESC LIMIT 1");
            $orgQ->execute([$userId, strtolower((string)$user['email']), $userId]);
            $organiserId = (int)$orgQ->fetchColumn();
            if ($organiserId < 1) br_json(403, ['ok'=>false,'error'=>'organiser_required']);
            $q = $db->prepare("SELECT r.id,r.public_reference,r.status,r.quantity,r.parent_name,r.parent_email,r.parent_phone,r.child_name,r.child_age,r.message,r.requested_at,s.starts_at,s.ends_at,a.title,v.venue_name
                FROM bh_booking_requests r
                JOIN bh_booking_slots s ON s.id=r.slot_id
                JOIN bh_activities a ON a.id=r.activity_id
                LEFT JOIN bh_venues v ON v.id=s.venue_id
                WHERE r.organiser_id=? ORDER BY s.starts_at ASC,r.requested_at DESC");
            $q->execute([$organiserId]);
            br_json(200, ['ok'=>true,'role'=>'leader','csrf'=>br_csrf(),'bookings'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if ($role !== 'family') br_json(403, ['ok'=>false,'error'=>'family_account_required']);
        $q = $db->prepare("SELECT r.id,r.public_reference,r.status,r.quantity,r.child_name,r.requested_at,s.starts_at,s.ends_at,a.title,v.venue_name
            FROM bh_booking_requests r JOIN bh_booking_slots s ON s.id=r.slot_id JOIN bh_activities a ON a.id=r.activity_id
            LEFT JOIN bh_venues v ON v.id=s.venue_id WHERE r.user_id=? ORDER BY s.starts_at ASC");
        $q->execute([$userId]);
        br_json(200, ['ok'=>true,'role'=>'family','csrf'=>br_csrf(),'bookings'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') br_json(405, ['ok'=>false,'error'=>'method_not_allowed']);
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) br_json(400, ['ok'=>false,'error'=>'invalid_json']);
    if (!hash_equals(br_csrf(), (string)($body['csrf'] ?? ''))) br_json(403, ['ok'=>false,'error'=>'csrf_invalid']);

    if (($body['action'] ?? '') === 'update_status') {
        if ($role !== 'leader') br_json(403, ['ok'=>false,'error'=>'leader_account_required']);
        $requestId = (int)($body['request_id'] ?? 0);
        $newStatus = (string)($body['status'] ?? '');
        if ($requestId < 1 || !in_array($newStatus, ['confirmed','cancelled','attended'], true)) br_json(422, ['ok'=>false,'error'=>'invalid_booking_update']);
        $orgQ = $db->prepare("SELECT id FROM bh_organisers WHERE user_id=? OR email=? ORDER BY (user_id=?) DESC LIMIT 1");
        $orgQ->execute([$userId, strtolower((string)$user['email']), $userId]);
        $organiserId = (int)$orgQ->fetchColumn();
        $u = $db->prepare("UPDATE bh_booking_requests SET status=? WHERE id=? AND organiser_id=? AND status IN ('pending','confirmed')");
        $u->execute([$newStatus,$requestId,$organiserId]);
        if ($u->rowCount() !== 1) br_json(404, ['ok'=>false,'error'=>'booking_not_found_or_unchanged']);
        br_json(200, ['ok'=>true,'status'=>$newStatus]);
    }

    if ($role !== 'family') br_json(403, ['ok'=>false,'error'=>'family_account_required']);
    $slotId = (int)($body['slot_id'] ?? 0);
    $childId = (int)($body['child_id'] ?? 0);
    $quantity = max(1, min(10, (int)($body['quantity'] ?? 1)));
    $parentName = trim((string)($body['parent_name'] ?? ''));
    $parentEmail = strtolower(trim((string)($body['parent_email'] ?? $user['email'])));
    $parentPhone = trim((string)($body['parent_phone'] ?? ''));
    $childName = trim((string)($body['child_name'] ?? ''));
    $childAge = trim((string)($body['child_age'] ?? ''));
    $message = trim((string)($body['message'] ?? ''));
    $consent = $body['consent_snapshot'] ?? null;
    if ($slotId < 1 || $parentName === '' || !filter_var($parentEmail,FILTER_VALIDATE_EMAIL) || $childName === '' || !is_array($consent) || empty($consent['consent_to_share_with_organiser']) || empty($consent['consent_given_at']) || empty($consent['consent_version'])) {
        br_json(422, ['ok'=>false,'error'=>'required_fields_missing','message'=>'Please complete the parent, child and booking consent details.']);
    }
    if ($childId > 0) {
        $childQ = $db->prepare("SELECT id,name,date_of_birth FROM bh_children WHERE id=? AND user_id=? LIMIT 1");
        $childQ->execute([$childId,$userId]);
        $child = $childQ->fetch(PDO::FETCH_ASSOC);
        if (!$child) br_json(403, ['ok'=>false,'error'=>'child_profile_not_owned']);
        if (mb_strtolower(trim((string)$child['name'])) !== mb_strtolower($childName)) br_json(422, ['ok'=>false,'error'=>'child_name_mismatch']);
    }

    $db->beginTransaction();
    try {
        $slotQ = $db->prepare("SELECT s.id,s.activity_id,s.starts_at,s.capacity,s.status,s.booking_mode,a.organiser_id,a.title
            FROM bh_booking_slots s JOIN bh_activities a ON a.id=s.activity_id
            WHERE s.id=? AND a.status='published' FOR UPDATE");
        $slotQ->execute([$slotId]);
        $slot = $slotQ->fetch(PDO::FETCH_ASSOC);
        if (!$slot || $slot['status'] !== 'open' || $slot['booking_mode'] !== 'bubba') {
            $db->rollBack(); br_json(409, ['ok'=>false,'error'=>'slot_unavailable','message'=>'This session is no longer available to book.']);
        }
        if (strtotime((string)$slot['starts_at']) <= time()) {
            $db->rollBack(); br_json(409, ['ok'=>false,'error'=>'slot_in_past']);
        }
        $dup = $db->prepare("SELECT id FROM bh_booking_requests WHERE slot_id=? AND user_id=? AND status IN ('pending','confirmed') LIMIT 1");
        $dup->execute([$slotId,$userId]);
        if ($dup->fetchColumn()) {
            $db->rollBack(); br_json(409, ['ok'=>false,'error'=>'already_booked','message'=>'Your family already has a request for this session.']);
        }
        if ($slot['capacity'] !== null) {
            $count = $db->prepare("SELECT COALESCE(SUM(quantity),0) FROM bh_booking_requests WHERE slot_id=? AND status IN ('pending','confirmed')");
            $count->execute([$slotId]);
            if ((int)$count->fetchColumn() + $quantity > (int)$slot['capacity']) {
                $db->rollBack(); br_json(409, ['ok'=>false,'error'=>'not_enough_places','message'=>'There are not enough places remaining for this request.']);
            }
        }
        $reference = strtoupper(bin2hex(random_bytes(6)));
        $insert = $db->prepare("INSERT INTO bh_booking_requests (public_reference,slot_id,activity_id,organiser_id,user_id,parent_name,parent_email,parent_phone,child_id,child_name,child_age,quantity,message,consent_snapshot)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $insert->execute([$reference,$slotId,(int)$slot['activity_id'],(int)$slot['organiser_id'],$userId,$parentName,$parentEmail,$parentPhone?:null,$childId?:null,$childName,$childAge?:null,$quantity,$message?:null,json_encode($consent,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        $requestId = (int)$db->lastInsertId();
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e instanceof PDOException && (string)$e->getCode() === '23000') br_json(409, ['ok'=>false,'error'=>'already_booked']);
        throw $e;
    }
    br_json(201, ['ok'=>true,'booking_id'=>$requestId,'reference'=>$reference,'status'=>'pending','activity'=>$slot['title'],'starts_at'=>$slot['starts_at']]);
} catch (Throwable $e) {
    error_log('Bubba Hub booking requests: '.$e->getMessage());
    br_json(500, ['ok'=>false,'error'=>'booking_service_unavailable','message'=>'The booking service is temporarily unavailable.']);
}
