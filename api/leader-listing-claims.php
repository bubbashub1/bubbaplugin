<?php
declare(strict_types=1);
/** Leader-only listing claim requests; requires migration 2026-10-listing-improvements.sql. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (session_status()!==PHP_SESSION_ACTIVE) { session_name('BUBBAHUBSESSID'); session_start(); }
function claim_reply(int $status,array $data): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if (empty($_SESSION['bh_user_id'])) claim_reply(401,['ok'=>false,'error'=>'login_required']);
try {
    require_once __DIR__.'/db.php';$db=bh_mysql();
    $userId=(int)$_SESSION['bh_user_id'];
    $user=$db->prepare("SELECT id,role,status,email FROM bh_users WHERE id=? LIMIT 1");
    $user->execute([$userId]);$account=$user->fetch();
    if (!$account || $account['role']!=='leader' || $account['status']!=='active') claim_reply(403,['ok'=>false,'error'=>'leader_account_required']);
    if (($_SERVER['REQUEST_METHOD']??'GET')==='GET') {
        $q=$db->prepare("SELECT c.id,c.activity_id,c.status,c.created_at,a.title FROM bh_listing_claims c JOIN bh_activities a ON a.id=c.activity_id WHERE c.requester_user_id=? ORDER BY c.id DESC LIMIT 50");
        $q->execute([$userId]);claim_reply(200,['ok'=>true,'claims'=>$q->fetchAll(),'csrf'=>$_SESSION['bh_claim_csrf']??=bin2hex(random_bytes(32))]);
    }
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') claim_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
    $payload=json_decode((string)file_get_contents('php://input'),true);
    if (!is_array($payload)) claim_reply(400,['ok'=>false,'error'=>'invalid_json']);
    $csrf=(string)($payload['csrf']??'');
    if (empty($_SESSION['bh_claim_csrf']) || !hash_equals((string)$_SESSION['bh_claim_csrf'],$csrf)) claim_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
    $activityId=(int)($payload['activity_id']??0);$evidence=trim((string)($payload['evidence']??''));
    if ($activityId<1 || mb_strlen($evidence)<15 || mb_strlen($evidence)>3000) claim_reply(422,['ok'=>false,'error'=>'evidence_required','message'=>'Please explain your connection to the listing (15–3000 characters).']);
    $hasUserId=(bool)$db->query("SHOW COLUMNS FROM bh_organisers LIKE 'user_id'")->fetch();
    if ($hasUserId) {$q=$db->prepare("SELECT id FROM bh_organisers WHERE user_id=? LIMIT 1");$q->execute([$userId]);}
    else {$q=$db->prepare("SELECT id FROM bh_organisers WHERE email=? LIMIT 1");$q->execute([$account['email']]);}
    $organiserId=(int)$q->fetchColumn();
    if (!$organiserId) claim_reply(403,['ok'=>false,'error'=>'organiser_profile_required']);
    $q=$db->prepare("SELECT organiser_id FROM bh_activities WHERE id=? LIMIT 1");$q->execute([$activityId]);$owner=$q->fetchColumn();
    if ($owner===false) claim_reply(404,['ok'=>false,'error'=>'listing_not_found']);
    if ((int)$owner===$organiserId) claim_reply(409,['ok'=>false,'error'=>'already_owned']);
    $q=$db->prepare("SELECT id FROM bh_listing_claims WHERE activity_id=? AND requester_user_id=? AND status='pending' LIMIT 1");$q->execute([$activityId,$userId]);
    if ($q->fetchColumn()) claim_reply(409,['ok'=>false,'error'=>'claim_already_pending']);
    $q=$db->prepare("INSERT INTO bh_listing_claims(activity_id,requester_user_id,proposed_organiser_id,evidence) VALUES(?,?,?,?)");
    $q->execute([$activityId,$userId,$organiserId,$evidence]);
    claim_reply(201,['ok'=>true,'claim_id'=>(int)$db->lastInsertId(),'status'=>'pending']);
} catch(Throwable $e) {error_log('Listing claims: '.$e->getMessage());claim_reply(500,['ok'=>false,'error'=>'claim_service_unavailable']);}
