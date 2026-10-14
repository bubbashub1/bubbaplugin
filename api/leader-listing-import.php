<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function fail(int $code,string $error): never {http_response_code($code);echo json_encode(['ok'=>false,'error'=>$error]);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')fail(405,'POST required');
if(strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE']??''))==='cross-site')fail(403,'Cross-site requests are not permitted');
session_name('BUBBAHUBSESSID');session_start();
$leaderId=(int)($_SESSION['bh_user_id']??0);
$isAdmin=false;
if(!$leaderId){
 session_write_close();
 session_name('BUBBAHUB_ADMINSESSID');session_start();
 $isAdmin=!empty($_SESSION['bh_admin_authenticated']);
 if(!$isAdmin)fail(401,'Sign in as a class leader or administrator');
}

require_once __DIR__.'/db.php';
$db=bh_mysql();
$org=null;$isPro=$isAdmin;
if(!$isAdmin){
 $q=$db->prepare("SELECT role,status FROM bh_users WHERE id=?");$q->execute([$leaderId]);$user=$q->fetch();
 if(!$user||$user['role']!=='leader'||$user['status']!=='active')fail(403,'Class leader account required');
 $orgQuery=$db->prepare("SELECT * FROM bh_organisers WHERE user_id=? LIMIT 1");
 try{$orgQuery->execute([$leaderId]);$org=$orgQuery->fetch(PDO::FETCH_ASSOC);}catch(Throwable $e){fail(503,'Organiser lookup unavailable');}
 if(!$org)fail(403,'Linked organiser account required');
 foreach(['plan','membership_plan','membership_tier','subscription_plan','tier'] as $column){if(isset($org[$column])&&in_array(strtolower(trim((string)$org[$column])),['pro','premium','ultimate'],true)){$isPro=true;break;}}
 try{$subscription=$db->prepare("SELECT COUNT(*) FROM bh_user_subscriptions WHERE user_id=? AND plan='leader_pro' AND status IN ('active','trialing') AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())");$subscription->execute([$leaderId]);if((int)$subscription->fetchColumn()>0)$isPro=true;}catch(Throwable $ignored){}
}
$raw=file_get_contents('php://input',false,null,0,4097);if(!is_string($raw)||strlen($raw)>4096)fail(413,'Request too large');
$input=json_decode($raw,true);if(!is_array($input))fail(400,'Invalid JSON');
$url=trim((string)($input['url']??''));$p=parse_url($url);
if(!$p||strtolower((string)($p['scheme']??''))!=='https'||empty($p['host'])||isset($p['user'])||isset($p['pass'])||isset($p['port'])||strlen($url)>1000)fail(422,'Enter a public HTTPS URL');
$host=strtolower((string)$p['host']);
if(!preg_match('/^[a-z0-9.-]+$/',$host)||!str_contains($host,'.'))fail(422,'Invalid host');
$ips=gethostbynamel($host);if(!$ips)fail(422,'Host not found');
foreach($ips as $ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))fail(422,'Public sites only');
if(!function_exists('curl_init'))fail(503,'URL import unavailable');
$ch=curl_init($url);
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_RESOLVE=>[$host.':443:'.$ips[0]],CURLOPT_MAXFILESIZE=>1048576,CURLOPT_USERAGENT=>'BubbaHubListingImporter/1.0',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
$body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
if(!is_string($body)||strlen($body)>1048576||$code!==200)fail(422,'Could not read this public page');
$mode=(string)($input['mode']??'website');
$listingId=(int)($input['listing_id']??0);
if(!in_array($mode,['website','schedule'],true))fail(422,'Unsupported import type');
if($listingId>0&&!$isAdmin){
 $ownership=$db->prepare("SELECT id FROM bh_activities WHERE id=? AND organiser_id=? LIMIT 1");
 $ownership->execute([$listingId,(int)$org['id']]);
 if(!$ownership->fetchColumn())fail(403,'This listing does not belong to your organisation');
}
$listingKey=$listingId>0?'listing:'.$listingId:'new-draft';
// A single server-side draft allowance prevents clients minting arbitrary draft IDs.
// Usage is transferred to the persisted listing when the class is created.
if(!$isPro){
 try{$db->exec("CREATE TABLE IF NOT EXISTS bh_listing_import_usage_v2 (user_id BIGINT UNSIGNED NOT NULL, listing_key VARCHAR(80) NOT NULL, import_type VARCHAR(16) NOT NULL, month_key CHAR(7) NOT NULL, used INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (user_id,listing_key,import_type,month_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");}
 catch(Throwable $e){fail(503,'Import quota service unavailable');}
}
function chargeImport(PDO $db,int $userId,string $listingKey,string $mode): void {
 $month=gmdate('Y-m');$limit=$mode==='website'?1:3;
 try{
  $db->beginTransaction();
  $db->prepare("INSERT IGNORE INTO bh_listing_import_usage_v2 (user_id,listing_key,import_type,month_key,used) VALUES (?,?,?,?,0)")->execute([$userId,$listingKey,$mode,$month]);
  $stmt=$db->prepare("UPDATE bh_listing_import_usage_v2 SET used=used+1 WHERE user_id=? AND listing_key=? AND import_type=? AND month_key=? AND used<?");
  $stmt->execute([$userId,$listingKey,$mode,$month,$limit]);
  if($stmt->rowCount()!==1){$db->rollBack();fail(429,'Monthly import allowance reached. Try again next month or upgrade to Leader Pro.');}
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();fail(503,'Import quota service unavailable');}
}
if(!$isPro){
 $check=$db->prepare("SELECT used FROM bh_listing_import_usage_v2 WHERE user_id=? AND listing_key=? AND import_type=? AND month_key=?");
 $check->execute([(int)$_SESSION['bh_user_id'],$listingKey,$mode,gmdate('Y-m')]);
 if((int)$check->fetchColumn()>=($mode==='website'?1:3))fail(429,'Monthly import allowance reached. Try again next month or upgrade to Leader Pro.');
}
if($mode==='schedule'){
 $sessions=[];
 if(str_contains($body,'BEGIN:VCALENDAR')){
  preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s',$body,$matches);
  foreach(array_slice($matches[1],0,150) as $event){
   if(str_contains($event,'RRULE:')||str_contains($event,'STATUS:CANCELLED'))continue;
   if(!preg_match('/^DTSTART(?:;[^:]*)?:(\\d{8}T\\d{6}Z?)/m',$event,$m))continue;
   try{$dt=new DateTimeImmutable($m[1],str_ends_with($m[1],'Z')?new DateTimeZone('UTC'):new DateTimeZone('Europe/London'));$dt=$dt->setTimezone(new DateTimeZone('Europe/London'));}catch(Throwable $e){continue;}
   $end='';if(preg_match('/^DTEND(?:;[^:]*)?:(\\d{8}T\\d{6}Z?)/m',$event,$em)){try{$end=(new DateTimeImmutable($em[1],str_ends_with($em[1],'Z')?new DateTimeZone('UTC'):new DateTimeZone('Europe/London')))->setTimezone(new DateTimeZone('Europe/London'))->format('H:i');}catch(Throwable $e){}}
   $sessions[]=['day'=>$dt->format('l'),'start'=>$dt->format('H:i'),'end'=>$end,'date'=>$dt->format('Y-m-d')];
  }
 }
 if(!$sessions)fail(422,'No importable dated calendar events found; allowance unchanged');
 if(!$isPro)chargeImport($db,(int)$_SESSION['bh_user_id'],$listingKey,$mode);
 echo json_encode(['ok'=>true,'sessions'=>$sessions,'note'=>'Only dated public iCalendar events are supported; recurring rules and dynamic pages need manual entry.']);exit;
}
if(!class_exists('DOMDocument'))fail(503,'HTML metadata reader unavailable');
$dom=new DOMDocument();$old=libxml_use_internal_errors(true);$dom->loadHTML($body,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);libxml_clear_errors();libxml_use_internal_errors($old);$xp=new DOMXPath($dom);
$read=static function(string $query)use($xp): string {$n=$xp->query($query);return $n&&$n->length?trim((string)$n->item(0)->nodeValue):'';};
$title=$read("//meta[@property='og:title']/@content")?:$read('//h1[1]')?:$read('//title[1]');
$desc=$read("//meta[@property='og:description']/@content")?:$read("//meta[@name='description']/@content");
$clean=static fn(string $s,int $n): string =>mb_substr(trim(preg_replace('/\\s+/u',' ',strip_tags($s))),0,$n);
if($title===''&&$desc==='')fail(422,'No usable metadata found; allowance unchanged');
if(!$isPro)chargeImport($db,(int)$_SESSION['bh_user_id'],$listingKey,$mode);
echo json_encode(['ok'=>true,'data'=>['title'=>$clean($title,150),'description'=>$clean($desc,4000),'booking_url'=>$url],'note'=>'Public metadata only; check all details.']);
