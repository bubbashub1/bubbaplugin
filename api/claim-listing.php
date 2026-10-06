<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function bh_claim_response(int $status, array $data): void { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_SLASHES); exit; }
try {
 require_once __DIR__ . '/db.php'; session_start();
 if ($_SERVER['REQUEST_METHOD'] !== 'POST') bh_claim_response(405,['ok'=>false,'error'=>'method_not_allowed']);
 $userId=(int)($_SESSION['bh_user_id']??0);
 if($userId<1) bh_claim_response(401,['ok'=>false,'error'=>'login_required','message'=>'Please sign in to claim a listing.']);
 $body=json_decode((string)file_get_contents('php://input'),true); if(!is_array($body))$body=[];
 $csrf=(string)($body['csrf']??'');
 if($csrf===''||empty($_SESSION['bh_csrf'])||!hash_equals((string)$_SESSION['bh_csrf'],$csrf)) bh_claim_response(403,['ok'=>false,'error'=>'csrf_invalid','message'=>'Your session has expired. Please refresh and try again.']);
 $db=bh_mysql();
 $u=$db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1");$u->execute([$userId]);$user=$u->fetch(PDO::FETCH_ASSOC);
 if(!$user||($user['status']??'')!=='active') bh_claim_response(403,['ok'=>false,'error'=>'account_inactive','message'=>'Your account is not active.']);
 if(($user['role']??'')!=='leader') bh_claim_response(403,['ok'=>false,'error'=>'leader_role_required','message'=>'A Class Leader account is required to claim a listing.']);
 $activityId=(int)($body['activity_id']??0); if($activityId<1) bh_claim_response(422,['ok'=>false,'error'=>'activity_required','message'=>'The activity could not be identified.']);
 $q=$db->prepare("SELECT id,title,organiser_id,status FROM bh_activities WHERE id=? LIMIT 1");$q->execute([$activityId]);$activity=$q->fetch(PDO::FETCH_ASSOC);
 if(!$activity) bh_claim_response(404,['ok'=>false,'error'=>'activity_not_found','message'=>'This listing could not be found.']);
 $db->exec("CREATE TABLE IF NOT EXISTS bh_listing_claims (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,activity_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,organiser_id BIGINT UNSIGNED NULL,status VARCHAR(20) NOT NULL DEFAULT 'pending',message TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,reviewed_at DATETIME NULL,reviewed_by BIGINT UNSIGNED NULL,PRIMARY KEY(id),KEY idx_claim_activity_status(activity_id,status),KEY idx_claim_user_status(user_id,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $e=$db->prepare("SELECT id,status FROM bh_listing_claims WHERE activity_id=? AND user_id=? AND status='pending' LIMIT 1");$e->execute([$activityId,$userId]);
 if($e->fetch()) bh_claim_response(200,['ok'=>true,'already_submitted'=>true,'message'=>'Your claim request is already with Bubba Hub for review.']);
 $message=trim((string)($body['message']??'')); if(mb_strlen($message)>2000)$message=mb_substr($message,0,2000);
 $i=$db->prepare("INSERT INTO bh_listing_claims (activity_id,user_id,organiser_id,status,message) VALUES (?,?,?,'pending',?)");
 $i->execute([$activityId,$userId,!empty($activity['organiser_id'])?(int)$activity['organiser_id']:null,$message!==''?$message:null]);
 bh_claim_response(201,['ok'=>true,'claim_id'=>(int)$db->lastInsertId(),'message'=>'Claim request sent. Bubba Hub will review it and get back to you.']);
} catch(Throwable $e){ bh_claim_response(500,['ok'=>false,'error'=>'claim_error','message'=>'We could not submit the claim right now. Please try again.']); }
?>