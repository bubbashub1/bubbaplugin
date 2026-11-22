<?php
declare(strict_types=1);
/**
 * Bubba Hub Listing Monitor website checker.
 * CLI only. Reads activity websites and writes private findings/history only.
 * It never edits, closes, hides or deletes an activity.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/db.php';
$db=bh_mysql();
$required=['bh_listing_checker_settings','bh_listing_monitor_website_state','bh_listing_monitor_website_history','bh_listing_check_findings','bh_activities'];
$exists=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
foreach($required as $table){$exists->execute([$table]);if(!(int)$exists->fetchColumn()){fwrite(STDERR,"Listing Monitor migration required: ".$table." is missing.\n");exit(2);}}
$settings=[];foreach($db->query('SELECT setting_key,setting_value FROM bh_listing_checker_settings')->fetchAll(PDO::FETCH_ASSOC) as $row)$settings[$row['setting_key']]=$row['setting_value'];
$get=static fn(string $key,string $default=''):(string)($settings[$key]??$default);
if($get('health_enabled','0')!=='1'){echo "Website monitoring is disabled.\n";exit;}
if($get('health_paused','0')==='1'){echo "Website monitoring is paused.\n";exit;}
$lock=$db->query("SELECT GET_LOCK('bh_listing_monitor_websites',0)")->fetchColumn();
if((int)$lock!==1){echo "Website monitor is already running.\n";exit;}
function bh_lm_public_host(string $host): array {
 $host=strtolower(rtrim($host,'.'));
 if($host===''||strlen($host)>253||preg_match('/[^a-z0-9.:-]/',$host)||$host==='localhost'||str_ends_with($host,'.localhost')||str_ends_with($host,'.local'))throw new RuntimeException('Unsafe or invalid host.');
 if(filter_var($host,FILTER_VALIDATE_IP))$ips=[$host];else{$ips=gethostbynamel($host);if(!$ips)throw new RuntimeException('DNS lookup failed (NXDOMAIN or no IPv4 address).');}
 foreach($ips as $ip){if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new RuntimeException('Host resolves to a private or reserved IP address.');}
 return array_values(array_unique($ips));
}
function bh_lm_fetch(string $url): array {
 if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL is unavailable.');
 for($hop=0;$hop<=3;$hop++){
  if(strlen($url)>1000)throw new RuntimeException('Website URL is too long.');
  $p=parse_url($url);
  if(!is_array($p)||!in_array(strtolower((string)($p['scheme']??'')),['http','https'],true)||empty($p['host'])||isset($p['user'])||isset($p['pass']))throw new RuntimeException('Website URL is invalid or uses an unsupported scheme.');
  $scheme=strtolower((string)$p['scheme']);$host=strtolower((string)$p['host']);$port=(int)($p['port']??($scheme==='https'?443:80));
  if(!in_array($port,[80,443],true))throw new RuntimeException('Non-standard website port blocked.');
  $ips=bh_lm_public_host($host);$resolve=[];
  foreach($ips as $ip){if(str_contains($ip,':'))continue;$resolve[]=$host.':'.$port.':'.$ip;}
  if(!$resolve)throw new RuntimeException('No safe IPv4 address available for website check.');
  $body='';$ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>15,CURLOPT_MAXREDIRS=>0,CURLOPT_USERAGENT=>'BubbaHub-ListingMonitor/1.0 (+https://bubbahub.co.uk)',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_RESOLVE=>$resolve,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>1048576)return 0;$body.=$chunk;return strlen($chunk);}]);
  $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);$location=(string)curl_getinfo($ch,CURLINFO_REDIRECT_URL);curl_close($ch);
  if($ok===false&&$status===0)throw new RuntimeException('Website could not be reached: '.($error?:'connection failed'));
  if(in_array($status,[301,302,303,307,308],true)){
   if($hop===3)throw new RuntimeException('Website redirected too many times.');
   if($location==='')throw new RuntimeException('Website redirect did not provide a destination.');
   if(!str_starts_with($location,'http://')&&!str_starts_with($location,'https://')){
    $base=$scheme.'://'.$host.($port!==($scheme==='https'?443:80)?':'.$port:'');
    $location=str_starts_with($location,'/')?$base.$location:$base.'/'.($p['path']??'').'/'.$location;
   }
   $url=$location;continue;
  }
  return ['status'=>$status,'body'=>$body,'url'=>$url];
 }
 throw new RuntimeException('Website redirect could not be checked.');
}
try{
 $columns=$db->query('SHOW COLUMNS FROM bh_activities')->fetchAll(PDO::FETCH_COLUMN);
 if(!in_array('id',$columns,true)||!in_array('website',$columns,true))throw new RuntimeException('Activity website field is unavailable.');
 // Seed/update monitor state without changing activity records.
 $db->exec("INSERT INTO bh_listing_monitor_website_state(activity_id,website_url,next_check_at) SELECT id,website,NOW() FROM bh_activities WHERE website IS NOT NULL AND TRIM(website)<>'' ON DUPLICATE KEY UPDATE website_url=IF(website_url<>VALUES(website_url),VALUES(website_url),website_url),next_check_at=IF(website_url<>VALUES(website_url),NOW(),next_check_at)");
 $batch=max(1,min(30,(int)$get('batch_size','30')));
 $q=$db->prepare("SELECT s.activity_id,s.website_url FROM bh_listing_monitor_website_state s WHERE s.website_url IS NOT NULL AND TRIM(s.website_url)<>'' AND (s.next_check_at IS NULL OR s.next_check_at<=NOW()) ORDER BY COALESCE(s.next_check_at,'1970-01-01'),s.activity_id LIMIT ?");
 $q->bindValue(1,$batch,PDO::PARAM_INT);$q->execute();$items=$q->fetchAll(PDO::FETCH_ASSOC);
 $history=$db->prepare('INSERT INTO bh_listing_monitor_website_history(activity_id,website_url,outcome,http_status,evidence) VALUES(?,?,?,?,?)');
 $state=$db->prepare('UPDATE bh_listing_monitor_website_state SET last_checked_at=NOW(),next_check_at=DATE_ADD(NOW(),INTERVAL 4 HOUR),last_outcome=?,consecutive_dead_checks=IF(? IN ("possible_closure","closure_indicated"),consecutive_dead_checks+1,IF(?="healthy",0,consecutive_dead_checks)) WHERE activity_id=?');
 $find=$db->prepare("SELECT id,candidate_json FROM bh_listing_check_findings WHERE check_type='health' AND activity_id=? AND finding_type=? ORDER BY id DESC LIMIT 1");
 $insert=$db->prepare("INSERT INTO bh_listing_check_findings(check_type,activity_id,candidate_json,finding_type,confidence,source_url,checked_at,review_status) VALUES('health',?,?,?,?,?,NOW(),'new')");
 $refresh=$db->prepare("UPDATE bh_listing_check_findings SET candidate_json=?,confidence=?,source_url=?,checked_at=NOW(),review_status=IF(review_status='resolved','new',review_status),reviewed_at=IF(review_status='resolved',NULL,reviewed_at) WHERE id=?");
 $counts=['healthy'=>0,'possible_closure'=>0,'closure_indicated'=>0,'temporary_closure'=>0,'unable_to_verify'=>0];
 foreach($items as $item){
  $id=(int)$item['activity_id'];$url=trim((string)$item['website_url']);$status=null;$evidence='';$outcome='unable_to_verify';$finalUrl=$url;
  try{
   $result=bh_lm_fetch($url);$status=$result['status'];$finalUrl=$result['url'];$plain=html_entity_decode(strip_tags($result['body']),ENT_QUOTES|ENT_HTML5,'UTF-8');$plain=preg_replace('/\s+/u',' ',trim($plain));$lower=strtolower($plain);
   if(preg_match('/\b(permanently closed|business has closed|we have closed|ceased trading|no longer trading|closing permanently)\b/u',$lower,$m)){ $outcome='closure_indicated';$pos=stripos($lower,$m[0]);$evidence=substr($plain,max(0,(int)$pos-160),360); }
   elseif(preg_match('/\b(temporarily closed|closed for the holidays|seasonal closure|closed today|reopening on|back open on)\b/u',$lower,$m)){ $outcome='temporary_closure';$pos=mb_stripos($lower,$m[0]);$evidence=mb_substr($plain,max(0,(int)$pos-160),360); }
   elseif($status===404||$status===410){$outcome='possible_closure';$evidence='Website returned HTTP '.$status.'. This is a dead-link warning, not proof that the activity has closed.';}
   elseif($status>=200&&$status<400){$outcome='healthy';$evidence='Website responded successfully (HTTP '.$status.').';}
   elseif($status>=500){$outcome='unable_to_verify';$evidence='Website server error HTTP '.$status.'.';}
   else{$outcome='unable_to_verify';$evidence='Website returned HTTP '.$status.'.';}
  }catch(Throwable $e){
   $message=$e->getMessage();$outcome=(stripos($message,'DNS lookup failed')!==false)?'possible_closure':'unable_to_verify';
   $evidence=substr($message,0,500);
  }
  $counts[$outcome]++;$history->execute([$id,$url,$outcome,$status,$evidence]);
  $state->execute([$outcome,$outcome,$outcome,$id]);
  if(in_array($outcome,['possible_closure','closure_indicated','temporary_closure','unable_to_verify'],true)){
   $type='website_'.$outcome;$candidate=['website'=>$url,'checked_url'=>$finalUrl,'http_status'=>$status,'outcome'=>$outcome,'evidence_note'=>$evidence,'checked_at'=>gmdate('c'),'source'=>'Bubba Hub Listing Monitor','note'=>'Investigate this evidence. No listing status or public content has been changed.'];
   $find->execute([$id,$type]);$existing=$find->fetch(PDO::FETCH_ASSOC);$json=json_encode($candidate,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
   if($existing)$refresh->execute([$json,$outcome==='closure_indicated'?95:($outcome==='possible_closure'?70:50),$url,(int)$existing['id']]);
   else $insert->execute([$id,$json,$type,$outcome==='closure_indicated'?95:($outcome==='possible_closure'?70:50),$url]);
  }
 }
 $summary=['checked'=>count($items),'results'=>$counts,'message'=>'Website checks only create private findings. No activity listings were edited or unpublished.'];
 echo json_encode($summary,JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){error_log('Listing Monitor website worker: '.$e->getMessage());fwrite(STDERR,'Website monitor failed: '.$e->getMessage()."\n");exit(1);}
finally{$db->query("SELECT RELEASE_LOCK('bh_listing_monitor_websites')");}
