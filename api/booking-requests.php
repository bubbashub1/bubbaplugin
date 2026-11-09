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
function br_organiser_id(PDO $db, int $userId, string $email): int {
    $check = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='user_id'");
    $check->execute();
    if ((int)$check->fetchColumn() > 0) {
        $q = $db->prepare("SELECT id FROM bh_organisers WHERE user_id=? LIMIT 1");
        $q->execute([$userId]);
        $id = (int)$q->fetchColumn();
        if ($id > 0) return $id;
    }
    $q = $db->prepare("SELECT id FROM bh_organisers WHERE LOWER(email)=? LIMIT 1");
    $q->execute([strtolower(trim($email))]);
    return (int)$q->fetchColumn();
}
function br_status_for_ui(string $status): string {
    return $status === 'reserved' ? 'pending' : $status;
}
function br_sync_slot_capacity(PDO $db, int $slotId): void {
    $q = $db->prepare("SELECT capacity FROM bh_booking_slots WHERE id=? FOR UPDATE");
    $q->execute([$slotId]);
    $capacity = $q->fetchColumn();
    if ($capacity === false) return;
    if ($capacity === null) {
        $db->prepare("UPDATE bh_booking_slots SET seats_remaining=NULL WHERE id=?")->execute([$slotId]);
        return;
    }
    $usedQ = $db->prepare("SELECT COALESCE(SUM(quantity),0) FROM bh_booking_reservations WHERE slot_id=? AND status IN ('reserved','confirmed','attended')");
    $usedQ->execute([$slotId]);
    $remaining = max(0, (int)$capacity - (int)$usedQ->fetchColumn());
    $status = $remaining < 1 ? 'full' : 'open';
    $db->prepare("UPDATE bh_booking_slots SET seats_remaining=?,status=? WHERE id=? AND status<>'cancelled'")->execute([$remaining,$status,$slotId]);
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

    $requiredTables = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('bh_booking_reservations','bh_booking_slots','bh_booking_consent_snapshots','bh_consent_profiles')");
    if ((int)$requiredTables->fetchColumn() !== 4) {
        br_json(503, ['ok'=>false,'error'=>'booking_schema_not_ready','message'=>'The booking database update must be installed before bookings can be enabled.']);
    }
    $requiredColumns = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_booking_reservations' AND COLUMN_NAME IN ('public_reference','organiser_id','parent_name','parent_email','parent_phone','child_id','child_name','child_age','message')");
    if ((int)$requiredColumns->fetchColumn() !== 9) {
        br_json(503, ['ok'=>false,'error'=>'booking_schema_update_required','message'=>'The booking database update must be installed before bookings can be enabled.']);
    }

    $role = (string)($user['role'] ?? '');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($role === 'leader') {
            $organiserId = br_organiser_id($db, $userId, (string)$user['email']);
            if ($organiserId < 1) br_json(403, ['ok'=>false,'error'=>'organiser_required']);
            if (($_GET['view'] ?? '') === 'manager') {
                $classesQ = $db->prepare("SELECT id,title FROM bh_activities WHERE organiser_id=? AND status<>'archived' ORDER BY title");
                $classesQ->execute([$organiserId]);
                $slotsQ = $db->prepare("SELECT s.id,s.activity_id,s.venue_id,s.starts_at,s.ends_at,s.capacity,s.price,s.status,a.title,v.venue_name
                    FROM bh_booking_slots s JOIN bh_activities a ON a.id=s.activity_id LEFT JOIN bh_venues v ON v.id=s.venue_id
                    WHERE a.organiser_id=? AND s.booking_mode='bubba' ORDER BY s.starts_at ASC");
                $slotsQ->execute([$organiserId]);
                br_json(200, ['ok'=>true,'role'=>'leader','csrf'=>br_csrf(),'classes'=>$classesQ->fetchAll(PDO::FETCH_ASSOC),'slots'=>$slotsQ->fetchAll(PDO::FETCH_ASSOC)]);
            }
            $q = $db->prepare("SELECT r.id,r.public_reference,r.status,r.quantity,r.parent_name,r.parent_email,r.parent_phone,r.child_name,r.child_age,r.message,r.created_at AS requested_at,s.starts_at,s.ends_at,a.title,v.venue_name,cs.consent_json
                FROM bh_booking_reservations r
                JOIN bh_booking_slots s ON s.id=r.slot_id
                JOIN bh_activities a ON a.id=s.activity_id
                LEFT JOIN bh_venues v ON v.id=s.venue_id
                LEFT JOIN bh_booking_consent_snapshots cs ON cs.id=(SELECT MAX(cs2.id) FROM bh_booking_consent_snapshots cs2 WHERE cs2.reservation_id=r.id)
                WHERE r.organiser_id=? ORDER BY s.starts_at ASC,r.id DESC");
            $q->execute([$organiserId]);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) $row['status'] = br_status_for_ui((string)$row['status']);
            unset($row);
            br_json(200, ['ok'=>true,'role'=>'leader','csrf'=>br_csrf(),'bookings'=>$rows]);
        }
        if ($role !== 'family') br_json(403, ['ok'=>false,'error'=>'family_account_required']);
        $q = $db->prepare("SELECT r.id,r.public_reference,r.status,r.quantity,r.child_name,r.created_at AS requested_at,s.starts_at,s.ends_at,a.title,v.venue_name
            FROM bh_booking_reservations r JOIN bh_booking_slots s ON s.id=r.slot_id JOIN bh_activities a ON a.id=s.activity_id
            LEFT JOIN bh_venues v ON v.id=s.venue_id WHERE r.user_id=? ORDER BY s.starts_at ASC,r.id DESC");
        $q->execute([$userId]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) $row['status'] = br_status_for_ui((string)$row['status']);
        unset($row);
        br_json(200, ['ok'=>true,'role'=>'family','csrf'=>br_csrf(),'bookings'=>$rows]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') br_json(405, ['ok'=>false,'error'=>'method_not_allowed']);
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) br_json(400, ['ok'=>false,'error'=>'invalid_json']);
    if (!hash_equals(br_csrf(), (string)($body['csrf'] ?? ''))) br_json(403, ['ok'=>false,'error'=>'csrf_invalid']);

    if (($body['action'] ?? '') === 'create_slot') {
        if ($role !== 'leader') br_json(403, ['ok'=>false,'error'=>'leader_account_required']);
        $organiserId = br_organiser_id($db, $userId, (string)$user['email']);
        $activityId = (int)($body['activity_id'] ?? 0);
        $venueId = (int)($body['venue_id'] ?? 0);
        $startsAt = trim((string)($body['starts_at'] ?? ''));
        $endsAt = trim((string)($body['ends_at'] ?? ''));
        $capacityRaw = $body['capacity'] ?? null;
        $capacity = filter_var($capacityRaw, FILTER_VALIDATE_INT);
        $priceRaw = $body['price'] ?? '';
        $price = $priceRaw === '' || $priceRaw === null ? null : filter_var($priceRaw, FILTER_VALIDATE_FLOAT);
        $date = DateTime::createFromFormat('!Y-m-d H:i', $startsAt);
        if ($organiserId < 1 || $activityId < 1 || !$date || $date->format('Y-m-d H:i') !== $startsAt || $date->getTimestamp() <= time() || $capacity === false || $capacity < 1 || $capacity > 500 || ($priceRaw !== '' && $price === false) || ($price !== null && ($price < 0 || $price > 99999))) {
            br_json(422, ['ok'=>false,'error'=>'invalid_slot_details','message'=>'Check the date, time, capacity and price and try again.']);
        }
        $owns = $db->prepare("SELECT id FROM bh_activities WHERE id=? AND organiser_id=? AND status<>'archived' LIMIT 1");
        $owns->execute([$activityId,$organiserId]);
        if (!$owns->fetchColumn()) br_json(404, ['ok'=>false,'error'=>'activity_not_found']);
        if ($venueId > 0) {
            $venue = $db->prepare("SELECT id FROM bh_venues WHERE id=? AND activity_id=? LIMIT 1");
            $venue->execute([$venueId,$activityId]);
            if (!$venue->fetchColumn()) br_json(422, ['ok'=>false,'error'=>'venue_not_linked_to_activity']);
        }
        $endValue = null;
        if ($endsAt !== '') {
            $endDate = DateTime::createFromFormat('!Y-m-d H:i', $endsAt);
            if (!$endDate || $endDate->format('Y-m-d H:i') !== $endsAt || $endDate <= $date) br_json(422, ['ok'=>false,'error'=>'invalid_slot_end']);
            $endValue = $endDate->format('Y-m-d H:i:s');
        }
        $ins = $db->prepare("INSERT INTO bh_booking_slots (activity_id,venue_id,starts_at,ends_at,capacity,seats_remaining,price,booking_mode,status) VALUES (?,?,?,?,?,?,?,'bubba','open')");
        $ins->execute([$activityId,$venueId ?: null,$date->format('Y-m-d H:i:s'),$endValue,$capacity,$capacity,$price]);
        br_json(201, ['ok'=>true,'slot_id'=>(int)$db->lastInsertId()]);
    }

    if (($body['action'] ?? '') === 'delete_slot') {
        if ($role !== 'leader') br_json(403, ['ok'=>false,'error'=>'leader_account_required']);
        $organiserId = br_organiser_id($db, $userId, (string)$user['email']);
        $slotId = (int)($body['slot_id'] ?? 0);
        $check = $db->prepare("SELECT s.id FROM bh_booking_slots s JOIN bh_activities a ON a.id=s.activity_id WHERE s.id=? AND a.organiser_id=? AND s.booking_mode='bubba' LIMIT 1");
        $check->execute([$slotId,$organiserId]);
        if (!$check->fetchColumn()) br_json(404, ['ok'=>false,'error'=>'slot_not_found']);
        $active = $db->prepare("SELECT COUNT(*) FROM bh_booking_reservations WHERE slot_id=? AND status IN ('reserved','confirmed')");
        $active->execute([$slotId]);
        if ((int)$active->fetchColumn() > 0) br_json(409, ['ok'=>false,'error'=>'slot_has_active_bookings','message'=>'Cancel or complete existing bookings before closing this session.']);
        $db->prepare("UPDATE bh_booking_slots SET status='cancelled' WHERE id=?")->execute([$slotId]);
        br_json(200, ['ok'=>true]);
    }

    if (($body['action'] ?? '') === 'update_status') {
        if ($role !== 'leader') br_json(403, ['ok'=>false,'error'=>'leader_account_required']);
        $requestId = (int)($body['request_id'] ?? 0);
        $requestedStatus = (string)($body['status'] ?? '');
        $newStatus = $requestedStatus === 'pending' ? 'reserved' : $requestedStatus;
        if ($requestId < 1 || !in_array($newStatus, ['confirmed','cancelled','attended'], true)) br_json(422, ['ok'=>false,'error'=>'invalid_booking_update']);
        $organiserId = br_organiser_id($db, $userId, (string)$user['email']);
        $u = $db->prepare("UPDATE bh_booking_reservations SET status=? WHERE id=? AND organiser_id=? AND status IN ('reserved','confirmed')");
        $u->execute([$newStatus,$requestId,$organiserId]);
        if ($u->rowCount() !== 1) br_json(404, ['ok'=>false,'error'=>'booking_not_found_or_unchanged']);
        $slotQ = $db->prepare("SELECT slot_id FROM bh_booking_reservations WHERE id=? AND organiser_id=?");
        $slotQ->execute([$requestId,$organiserId]);
        br_sync_slot_capacity($db,(int)$slotQ->fetchColumn());
        br_json(200, ['ok'=>true,'status'=>br_status_for_ui($newStatus)]);
    }

    if ($role !== 'family') br_json(403, ['ok'=>false,'error'=>'family_account_required']);
    $slotId = (int)($body['slot_id'] ?? 0);
    $childId = (int)($body['child_id'] ?? 0);
    $quantityRaw = filter_var($body['quantity'] ?? 1, FILTER_VALIDATE_INT);
    $quantity = $quantityRaw === false ? 0 : $quantityRaw;
    $parentName = trim((string)($body['parent_name'] ?? ''));
    $parentEmail = strtolower(trim((string)$user['email']));
    $parentPhone = trim((string)($body['parent_phone'] ?? ''));
    $childName = trim((string)($body['child_name'] ?? ''));
    $childAge = trim((string)($body['child_age'] ?? ''));
    $message = trim((string)($body['message'] ?? ''));
    if ($slotId < 1 || $quantity < 1 || $quantity > 10 || $parentName === '' || $childName === '' || mb_strlen($parentName)>190 || mb_strlen($childName)>120 || mb_strlen($parentPhone)>80 || mb_strlen($childAge)>30 || mb_strlen($message)>4000) {
        br_json(422, ['ok'=>false,'error'=>'required_fields_missing','message'=>'Please check the parent, child and number of places details.']);
    }

    $child = null;
    if ($childId > 0) {
        $childQ = $db->prepare("SELECT id,name,date_of_birth FROM bh_children WHERE id=? AND user_id=? LIMIT 1");
        $childQ->execute([$childId,$userId]);
        $child = $childQ->fetch(PDO::FETCH_ASSOC);
        if (!$child) br_json(403, ['ok'=>false,'error'=>'child_profile_not_owned']);
        if (mb_strtolower(trim((string)$child['name'])) !== mb_strtolower($childName)) br_json(422, ['ok'=>false,'error'=>'child_name_mismatch']);
    }

    // Consent is read from the authenticated family's saved server-side profile.
    // No browser-supplied consent JSON is accepted as evidence.
    if ($childId > 0) {
        $consentQ = $db->prepare("SELECT * FROM bh_consent_profiles WHERE user_id=? AND child_id=? AND consent_to_share_with_organiser=1 AND consent_given_at IS NOT NULL ORDER BY updated_at DESC,id DESC LIMIT 1");
        $consentQ->execute([$userId,$childId]);
    } else {
        $consentQ = $db->prepare("SELECT * FROM bh_consent_profiles WHERE user_id=? AND child_id IS NULL AND LOWER(child_name)=LOWER(?) AND consent_to_share_with_organiser=1 AND consent_given_at IS NOT NULL ORDER BY updated_at DESC,id DESC LIMIT 1");
        $consentQ->execute([$userId,$childName]);
    }
    $profile = $consentQ->fetch(PDO::FETCH_ASSOC);
    if (!$profile) br_json(422, ['ok'=>false,'error'=>'booking_consent_required','message'=>'Please save current booking consent for this child in your family account before requesting a place.']);
    if (mb_strtolower(trim((string)$profile['child_name'])) !== mb_strtolower($childName)) br_json(422, ['ok'=>false,'error'=>'consent_child_mismatch','message'=>'The saved consent profile does not match the selected child.']);

    $snapshot = [
        'child_name'=>(string)$profile['child_name'],
        'date_of_birth'=>$profile['date_of_birth'],
        'gender'=>$profile['gender'],
        'additional_needs'=>$profile['additional_needs'],
        'allergies'=>$profile['allergies'],
        'medical_conditions'=>$profile['medical_conditions'],
        'medications'=>$profile['medications'],
        'dietary_requirements'=>$profile['dietary_requirements'],
        'emergency_information'=>$profile['emergency_information'],
        'emergency_contact_name'=>$profile['emergency_contact_name'],
        'emergency_contact_phone'=>$profile['emergency_contact_phone'],
        'photo_activity'=>(bool)$profile['photo_activity'],
        'photo_bubbahub'=>(bool)$profile['photo_bubbahub'],
        'photo_organiser_marketing'=>(bool)$profile['photo_organiser_marketing'],
        'consent_to_share_with_organiser'=>true,
        'consent_version'=>(string)$profile['consent_version'],
        'consent_given_at'=>(string)$profile['consent_given_at']
    ];

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

        $existingQ = $db->prepare("SELECT id,status FROM bh_booking_reservations WHERE slot_id=? AND user_id=? FOR UPDATE");
        $existingQ->execute([$slotId,$userId]);
        $existing = $existingQ->fetch(PDO::FETCH_ASSOC);
        if ($existing && $existing['status'] !== 'cancelled') {
            $db->rollBack(); br_json(409, ['ok'=>false,'error'=>'already_booked','message'=>'Your family already has a booking for this session.']);
        }
        $count = $db->prepare("SELECT COALESCE(SUM(quantity),0) FROM bh_booking_reservations WHERE slot_id=? AND status IN ('reserved','confirmed','attended')");
        $count->execute([$slotId]);
        if ($slot['capacity'] !== null && (int)$count->fetchColumn() + $quantity > (int)$slot['capacity']) {
            $db->rollBack(); br_json(409, ['ok'=>false,'error'=>'not_enough_places','message'=>'There are not enough places remaining for this request.']);
        }

        $reference = strtoupper(bin2hex(random_bytes(6)));
        if ($existing) {
            $reservationId = (int)$existing['id'];
            $save = $db->prepare("UPDATE bh_booking_reservations SET status='reserved',quantity=?,public_reference=?,organiser_id=?,parent_name=?,parent_email=?,parent_phone=?,child_id=?,child_name=?,child_age=?,message=? WHERE id=? AND user_id=?");
            $save->execute([$quantity,$reference,(int)$slot['organiser_id'],$parentName,$parentEmail,$parentPhone ?: null,$childId ?: null,$childName,$childAge ?: null,$message ?: null,$reservationId,$userId]);
        } else {
            $save = $db->prepare("INSERT INTO bh_booking_reservations (slot_id,user_id,status,quantity,public_reference,organiser_id,parent_name,parent_email,parent_phone,child_id,child_name,child_age,message) VALUES (?,?, 'reserved', ?,?,?,?,?,?,?,?,?,?)");
            $save->execute([$slotId,$userId,$quantity,$reference,(int)$slot['organiser_id'],$parentName,$parentEmail,$parentPhone ?: null,$childId ?: null,$childName,$childAge ?: null,$message ?: null]);
            $reservationId = (int)$db->lastInsertId();
        }
        $snapshotInsert = $db->prepare("INSERT INTO bh_booking_consent_snapshots (user_id,reservation_id,consent_profile_id,consent_version,child_name,consent_json,consent_given_at) VALUES (?,?,?,?,?,?,?)");
        $snapshotInsert->execute([$userId,$reservationId,(int)$profile['id'],(string)$profile['consent_version'],$childName,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(string)$profile['consent_given_at']]);
        br_sync_slot_capacity($db,$slotId);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e instanceof PDOException && (string)$e->getCode() === '23000') br_json(409, ['ok'=>false,'error'=>'already_booked']);
        throw $e;
    }
    br_json(201, ['ok'=>true,'booking_id'=>$reservationId,'reference'=>$reference,'status'=>'pending','activity'=>$slot['title'],'starts_at'=>$slot['starts_at']]);
} catch (Throwable $e) {
    error_log('Bubba Hub booking service: '.$e->getMessage());
    br_json(500, ['ok'=>false,'error'=>'booking_service_unavailable','message'=>'The booking service is temporarily unavailable.']);
}
