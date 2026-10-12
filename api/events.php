<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function respond(int $status,array $body): never {http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try {
  require_once __DIR__.'/db.php';
  $action=(string)($_GET['action']??'list');
  if (!in_array($action,['list','submit','import'],true)) respond(404,['ok'=>false,'error'=>'Unknown action']);
  if ($action==='import') respond(422,['ok'=>false,'error'=>'Automatic URL extraction is not available yet. Please enter the event details manually.']);
  if (($action==='submit' && $_SERVER['REQUEST_METHOD']!=='POST') || ($action==='list' && $_SERVER['REQUEST_METHOD']!=='GET')) respond(405,['ok'=>false,'error'=>'Method not allowed']);
  if ($action==='submit' && (int)($_SERVER['CONTENT_LENGTH']??0)>20000) respond(413,['ok'=>false,'error'=>'Submission too large']);
  $db=bh_mysql();
  $db->exec("CREATE TABLE IF NOT EXISTS bh_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL, description TEXT NOT NULL, category VARCHAR(100) NOT NULL DEFAULT '',
    starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, location VARCHAR(255) NOT NULL,
    booking_mode VARCHAR(20) NOT NULL DEFAULT 'none', booking_url VARCHAR(1000) NOT NULL DEFAULT '',
    source_url VARCHAR(1000) NOT NULL DEFAULT '', contact_email VARCHAR(190) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_start (status,starts_at), INDEX idx_contact_created (contact_email,created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  if ($action==='list') {
    $stmt=$db->query("SELECT id,title,description,category,starts_at,ends_at,location,booking_mode,booking_url FROM bh_events WHERE status='published' AND ends_at>=NOW() AND starts_at<DATE_ADD(NOW(), INTERVAL 90 DAY) ORDER BY starts_at ASC LIMIT 100");
    respond(200,['ok'=>true,'events'=>$stmt->fetchAll()]);
  }
  $body=json_decode((string)file_get_contents('php://input'),true);
  if (!is_array($body)) respond(400,['ok'=>false,'error'=>'Invalid JSON']);
  if (trim((string)($body['website']??''))!=='') respond(200,['ok'=>true,'message'=>'Submitted for review']);
  $get=static fn(string $k,int $max): string => mb_substr(trim((string)($body[$k]??'')),0,$max);
  $title=$get('title',180);$description=$get('description',5000);$location=$get('location',255);
  $email=strtolower($get('contact_email',190));$category=$get('category',100);
  $start=$get('starts_at',30);$end=$get('ends_at',30);
  $mode=$get('booking_mode',20);$booking=$get('booking_url',1000);$source=$get('source_url',1000);
  $validDate=static function(string $s): ?DateTimeImmutable {
    $d=DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i',$s);
    return $d && $d->format('Y-m-d\\TH:i')===$s ? $d : null;
  };
  $a=$validDate($start);$b=$validDate($end);
  if (!$title||!$description||!$location||!filter_var($email,FILTER_VALIDATE_EMAIL)||!$a||!$b||$b<=$a||$a<new DateTimeImmutable('-1 day')||$a>new DateTimeImmutable('+2 years')) respond(422,['ok'=>false,'error'=>'Please check the title, description, email, venue and event dates.']);
  if (!in_array($mode,['external','none','free'],true)) respond(422,['ok'=>false,'error'=>'Invalid booking mode']);
  foreach ([$booking,$source] as $url) if ($url!=='' && (!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true))) respond(422,['ok'=>false,'error'=>'Use valid http or https links']);
  if ($mode==='external' && $booking==='') respond(422,['ok'=>false,'error'=>'An external booking link is required']);
  $rate=$db->prepare("SELECT COUNT(*) FROM bh_events WHERE contact_email=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 DAY)");
  $rate->execute([$email]);if ((int)$rate->fetchColumn()>=5) respond(429,['ok'=>false,'error'=>'Daily submission limit reached']);
  $insert=$db->prepare("INSERT INTO bh_events (title,description,category,starts_at,ends_at,location,booking_mode,booking_url,source_url,contact_email,status) VALUES (?,?,?,?,?,?,?,?,?,?,'pending')");
  $insert->execute([$title,$description,$category,$a->format('Y-m-d H:i:s'),$b->format('Y-m-d H:i:s'),$location,$mode,$booking,$source,$email]);
  respond(201,['ok'=>true,'message'=>'Event submitted for review']);
} catch(Throwable $e) {
  error_log('[Bubba Hub events] '.$e->getMessage());
  respond(500,['ok'=>false,'error'=>'Event service is temporarily unavailable']);
}
