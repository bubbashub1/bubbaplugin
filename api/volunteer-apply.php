<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function reply(int $status,array $data): never {http_response_code($status);echo json_encode($data);exit;}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$raw=file_get_contents('php://input');
if ($raw===false) reply(400,['ok'=>false,'error'=>'invalid_body']);
if (strlen($raw) > 12000) reply(413,['ok'=>false,'error'=>'too_large']);
$input=json_decode($raw,true);
if (!is_array($input)) reply(400,['ok'=>false,'error'=>'invalid_json']);
if (!empty($input['website'])) reply(200,['ok'=>true]); // Honeypot
$name=trim((string)($input['name']??''));$email=trim((string)($input['email']??''));
$town=trim((string)($input['town']??''));$region=trim((string)($input['region']??''));
$role=trim((string)($input['role']??''));$availability=trim((string)($input['availability']??''));
$motivation=trim((string)($input['motivation']??''));
$allowed=['Area coordinator','Listings and database','Events','Marketing and social media','General support'];
if(mb_strlen($name)<2||mb_strlen($name)>160||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||mb_strlen($town)>120||mb_strlen($region)>120||!in_array($role,$allowed,true)||mb_strlen($availability)>500||mb_strlen($motivation)>2000||empty($input['consent'])) reply(422,['ok'=>false,'error'=>'invalid_fields']);
try {
require __DIR__.'/db.php';$db=bh_mysql();
// Bound duplicate submissions for the same email address.
$recent=$db->prepare("SELECT COUNT(*) FROM bh_volunteer_applications WHERE email=? AND created_at > UTC_TIMESTAMP() - INTERVAL 1 DAY");
$recent->execute([strtolower($email)]);
if ((int)$recent->fetchColumn() >= 3) reply(429,['ok'=>false,'error'=>'too_many_applications']);
$tableCheck=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_volunteer_applications'");
$tableCheck->execute();
if(!(int)$tableCheck->fetchColumn())reply(503,['ok'=>false,'error'=>'application_storage_unavailable']);
$stmt=$db->prepare("INSERT INTO bh_volunteer_applications (name,email,town,region,preferred_role,availability,motivation,consent_at,created_at) VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$stmt->execute([$name,$email,$town,$region,$role,$availability,$motivation]);
reply(201,['ok'=>true,'message'=>'Thank you! Your application has been received.']);
} catch(Throwable $e){error_log('Volunteer application failed: '.$e->getMessage());reply(500,['ok'=>false,'error'=>'temporarily_unavailable']);}
