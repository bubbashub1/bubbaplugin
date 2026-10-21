<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { header('Allow: GET'); http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit; }
try {
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 $q=$db->prepare('SELECT business_name,website_url,logo_url FROM bh_expo_public_sponsors WHERE event_key=? AND approved=1 AND public_visible=1 ORDER BY sort_order ASC, business_name ASC LIMIT 60');
 $q->execute(['bubba-me-2027']);
 $sponsors=[];
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
  $url=static function($v):?string { $v=trim((string)$v); return filter_var($v,FILTER_VALIDATE_URL) && strtolower((string)parse_url($v,PHP_URL_SCHEME))==='https' ? $v : null; };
  $sponsors[]=['name'=>mb_substr((string)$row['business_name'],0,160),'website_url'=>$url($row['website_url']),'logo_url'=>$url($row['logo_url'])];
 }
 echo json_encode(['ok'=>true,'sponsors'=>$sponsors],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
} catch(Throwable $e) { http_response_code(503); echo json_encode(['ok'=>false,'error'=>'sponsors_unavailable']); }
