<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
function bh_sponsor_db(): PDO {
 $db=bh_mysql();
 $db->exec("CREATE TABLE IF NOT EXISTS bh_event_sponsor_approvals (
  stripe_session_id VARCHAR(255) NOT NULL PRIMARY KEY,
  event_key VARCHAR(80) NOT NULL,
  business_name VARCHAR(150) NOT NULL,
  tier SMALLINT UNSIGNED NOT NULL,
  website VARCHAR(500) NOT NULL DEFAULT '',
  logo_url VARCHAR(500) NOT NULL DEFAULT '',
  approval_status ENUM('approved','rejected') NOT NULL,
  reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_public (event_key,approval_status)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 return $db;
}
function bh_sponsor_stripe_key(): string {
 if(defined('BUBBAHUB_STRIPE_SECRET_KEY'))return trim((string)constant('BUBBAHUB_STRIPE_SECRET_KEY'));
 foreach(['/public_html/wp-config.php',dirname(__DIR__).'/wp-config.php',dirname(__DIR__,2).'/wp-config.php'] as $file){
  if(!is_file($file))continue;
  $contents=@file_get_contents($file);if($contents===false)continue;
  $pattern='/define\\s*\\(\\s*[\\x27\\x22 ]BUBBAHUB_STRIPE_SECRET_KEY[\\x27\\x22 ]\\s*,\\s*[\\x27\\x22 ](.*?)[\\x27\\x22 ]\\s*\\)\\s*;/s';
  if(preg_match($pattern,$contents,$m))return stripcslashes($m[1]);
 }
 $config=is_file(__DIR__.'/config.php')?require __DIR__.'/config.php':[];
 return trim((string)($config['stripe']['secret_key']??getenv('STRIPE_SECRET_KEY')?:''));
}
function bh_sponsor_session(string $id): ?array {
 if(!preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/',$id))return null;
 $key=bh_sponsor_stripe_key();if($key==='')throw new RuntimeException('stripe_not_configured');
 if(!function_exists('curl_init'))throw new RuntimeException('stripe_unavailable');
 $ch=curl_init('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($id));
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key]]);
 $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 $data=is_string($raw)?json_decode($raw,true):null;
 return $code===200&&is_array($data)?$data:null;
}
function bh_sponsor_verified(array $s): bool {
 $m=$s['metadata']??[];
 return ($m['type']??'')==='event_sponsorship'
  && ($s['mode']??'')==='payment' && ($s['payment_status']??'')==='paid'
  && ($s['currency']??'')==='gbp'
  && in_array((int)($m['tier']??0),[25,50,100],true)
  && (int)($s['amount_total']??0)===(int)($m['tier']??0)*100
  && ($m['event_key']??'')==='bubba-me-2027';
}
