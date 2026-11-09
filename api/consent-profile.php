<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function cp_json(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
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
    if ($userId < 1) cp_json(401, ['ok'=>false,'error'=>'login_required','message'=>'Please sign in to save a consent profile.']);
    $db = bh_mysql();
    $userQ = $db->prepare("SELECT id,role,status FROM bh_users WHERE id=? LIMIT 1");
    $userQ->execute([$userId]);
    $user = $userQ->fetch(PDO::FETCH_ASSOC);
    if (!$user || ($user['status'] ?? '') !== 'active' || ($user['role'] ?? '') !== 'family') {
        cp_json(403, ['ok'=>false,'error'=>'family_account_required','message'=>'An active family account is required.']);
    }

    $tableQ = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('bh_consent_profiles','bh_children')");
    if ((int)$tableQ->fetchColumn() !== 2) {
        cp_json(503, ['ok'=>false,'error'=>'consent_storage_not_ready','message'=>'Consent storage needs to be installed before bookings can be enabled.']);
    }
    if (!isset($_SESSION['bh_csrf'])) $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $q = $db->prepare("SELECT id,child_id,child_name,date_of_birth,gender,additional_needs,allergies,medical_conditions,medications,dietary_requirements,emergency_information,emergency_contact_name,emergency_contact_phone,photo_activity,photo_bubbahub,photo_organiser_marketing,support_prefill_enabled,support_prefill_topics,consent_to_share_with_organiser,consent_version,consent_given_at,updated_at FROM bh_consent_profiles WHERE user_id=? ORDER BY updated_at DESC,id DESC");
        $q->execute([$userId]);
        cp_json(200, ['ok'=>true,'profiles'=>$q->fetchAll(PDO::FETCH_ASSOC),'csrf'=>$_SESSION['bh_csrf']]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') cp_json(405, ['ok'=>false,'error'=>'method_not_allowed']);
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) cp_json(400, ['ok'=>false,'error'=>'invalid_json']);
    $csrf = (string)($body['csrf'] ?? '');
    if ($csrf === '' || !hash_equals((string)$_SESSION['bh_csrf'], $csrf)) cp_json(403, ['ok'=>false,'error'=>'csrf_invalid']);

    $childId = max(0, (int)($body['child_id'] ?? 0));
    $childName = trim((string)($body['child_name'] ?? ''));
    $dob = trim((string)($body['date_of_birth'] ?? ''));
    if ($childId > 0) {
        $childQ = $db->prepare("SELECT id,name,date_of_birth FROM bh_children WHERE id=? AND user_id=? LIMIT 1");
        $childQ->execute([$childId,$userId]);
        $child = $childQ->fetch(PDO::FETCH_ASSOC);
        if (!$child) cp_json(403, ['ok'=>false,'error'=>'child_profile_not_owned']);
        $childName = (string)$child['name'];
        $dob = (string)($child['date_of_birth'] ?? '');
    }
    if ($childName === '' || mb_strlen($childName) > 120) cp_json(422, ['ok'=>false,'error'=>'child_name_required']);
    if ($dob !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$date || $date->format('Y-m-d') !== $dob || $date > new DateTime('today')) cp_json(422, ['ok'=>false,'error'=>'invalid_date_of_birth']);
    }
    $shareConsent = !empty($body['consent_to_share_with_organiser']) ? 1 : 0;

    $textFields = ['gender','additional_needs','allergies','medical_conditions','medications','dietary_requirements','emergency_information','emergency_contact_name','emergency_contact_phone'];
    $values = [];
    foreach ($textFields as $field) $values[$field] = trim((string)($body[$field] ?? '')) ?: null;
    $boolFields = ['photo_activity','photo_bubbahub','photo_organiser_marketing','support_prefill_enabled'];
    foreach ($boolFields as $field) $values[$field] = !empty($body[$field]) ? 1 : 0;
    $allowedTopics = ['baby-child-health','feeding-weaning','sleep','family-wellbeing'];
    $topics = array_values(array_intersect($allowedTopics, array_map('strval', (array)($body['support_prefill_topics'] ?? []))));
    $values['support_prefill_topics'] = $topics ? implode(',', $topics) : null;

    $find = $db->prepare($childId > 0
        ? "SELECT id FROM bh_consent_profiles WHERE user_id=? AND child_id=? ORDER BY updated_at DESC,id DESC LIMIT 1"
        : "SELECT id FROM bh_consent_profiles WHERE user_id=? AND child_id IS NULL AND child_name=? ORDER BY updated_at DESC,id DESC LIMIT 1");
    $childId > 0 ? $find->execute([$userId,$childId]) : $find->execute([$userId,$childName]);
    $profileId = (int)($find->fetchColumn() ?: 0);
    $version = '1.1';
    $givenAt = $shareConsent ? date('Y-m-d H:i:s') : null;
    if ($profileId > 0) {
        $sql = "UPDATE bh_consent_profiles SET child_id=?,child_name=?,date_of_birth=?,gender=?,additional_needs=?,allergies=?,medical_conditions=?,medications=?,dietary_requirements=?,emergency_information=?,emergency_contact_name=?,emergency_contact_phone=?,photo_activity=?,photo_bubbahub=?,photo_organiser_marketing=?,support_prefill_enabled=?,support_prefill_topics=?,consent_to_share_with_organiser=?,consent_version=?,consent_given_at=? WHERE id=? AND user_id=?";
        $params = [$childId ?: null,$childName,$dob ?: null,$values['gender'],$values['additional_needs'],$values['allergies'],$values['medical_conditions'],$values['medications'],$values['dietary_requirements'],$values['emergency_information'],$values['emergency_contact_name'],$values['emergency_contact_phone'],$values['photo_activity'],$values['photo_bubbahub'],$values['photo_organiser_marketing'],$values['support_prefill_enabled'],$values['support_prefill_topics'],$shareConsent,$version,$givenAt,$profileId,$userId];
        $db->prepare($sql)->execute($params);
    } else {
        $sql = "INSERT INTO bh_consent_profiles (user_id,child_id,child_name,date_of_birth,gender,additional_needs,allergies,medical_conditions,medications,dietary_requirements,emergency_information,emergency_contact_name,emergency_contact_phone,photo_activity,photo_bubbahub,photo_organiser_marketing,support_prefill_enabled,support_prefill_topics,consent_to_share_with_organiser,consent_version,consent_given_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $params = [$userId,$childId ?: null,$childName,$dob ?: null,$values['gender'],$values['additional_needs'],$values['allergies'],$values['medical_conditions'],$values['medications'],$values['dietary_requirements'],$values['emergency_information'],$values['emergency_contact_name'],$values['emergency_contact_phone'],$values['photo_activity'],$values['photo_bubbahub'],$values['photo_organiser_marketing'],$values['support_prefill_enabled'],$values['support_prefill_topics'],$shareConsent,$version,$givenAt];
        $db->prepare($sql)->execute($params);
        $profileId = (int)$db->lastInsertId();
    }

    cp_json(200, ['ok'=>true,'profile_id'=>$profileId,'child_id'=>$childId,'consent_version'=>$version,'consent_given_at'=>$givenAt,'consent_to_share_with_organiser'=>(bool)$shareConsent,'csrf'=>$_SESSION['bh_csrf']]);
} catch (Throwable $e) {
    error_log('Bubba Hub consent profile API: '.$e->getMessage());
    cp_json(500, ['ok'=>false,'error'=>'consent_profile_unavailable','message'=>'The consent profile could not be saved right now.']);
}
