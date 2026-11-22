<?php
declare(strict_types=1);
/**
 * CLI-only Listing Monitor website checker.
 * Reuses the existing findings/settings tables and appends check history.
 * It deliberately does not share the legacy check queue or run log: the
 * existing health worker uses those to control its own schedule.
 * It never edits, closes, hides or deletes an activity.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/listing-monitor-website-rules.php';
require_once __DIR__.'/db.php';
$db=bh_mysql();
$required=['bh_listing_checker_settings','bh_listing_check_findings','bh_listing_monitor_website_history','bh_activities'];
$exists=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
foreach($required as $table){$exists->execute([$table]);if(!(int)$exists->fetchColumn()){fwrite(STDERR,"Listing Monitor migration required: ".$table." is missing.\n");exit(2);}}
$settings=[];foreach($db->query('SELECT setting_key,setting_value FROM bh_listing_checker_settings')->fetchAll(PDO::FETCH_ASSOC) as $row)$settings[$row['setting_key']]=$row['setting_value'];
$get=static fn(string $key,string $default=''): string => (string)($settings[$key]??$default);
if($get('website_monitor_enabled','0')!=='1'){echo "Website monitoring is disabled.\n";exit;}
if($get('website_monitor_paused','0')==='1'){echo "Website monitoring is paused.\n";exit;}
$lock=$db->query("SELECT GET_LOCK('bh_listing_monitor_websites',0)")->fetchColumn();
if((int)$lock!==1){echo "Website monitor is already running.\n";exit;}
function bh_lm_public_host(string $host): array {
 $host=strtolower(rtrim($host,'.'));
 if($host===''||strlen($host)>253||preg_match('/[^a-z0-9.:-]/',$host)||$host==='localhost'||str_ends_with($host,'.localhost')||str_ends_with($host,'.local'))throw new RuntimeException('Unsafe or invalid host.');
 if(filter_var($host,FILTER_VALIDATE_IP))$ips=[$host];else{
  $records=@dns_get_record($host,DNS_A|DNS_AAAA);
  if(!is_array($records)||!$records)throw new RuntimeException('DNS lookup failed (NXDOMAIN or no address records).');
  $ips=[];$ipv4=[];
  foreach($records as $record){$ip=(string)($record['ip']??$record['ipv6']??'');if($ip!==''){$ips[]=$ip;if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))$ipv4[]=$ip;}}
  if(!$ips)throw new RuntimeException('DNS lookup failed (no usable address records).');
  if(!$ipv4)throw new RuntimeException('IPv6-only website cannot be checked by this worker yet.');
 }
 foreach($ips as $ip){if(!bh_lm_is_public_ip($ip))throw new RuntimeException('Host resolves to a private or reserved IP address.');}
 return array_values(array_unique(array_filter($ips,static fn($ip)=>filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))));
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
  foreach($ips as $ip){if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))$resolve[]=$host.':'.$port.':'.$ip;}
  if(!$resolve)throw new RuntimeException('No safe IPv4 address available for website check.');
  $body='';$bodyTooLarge=false;$redirectLocation='';$ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>15,CURLOPT_MAXREDIRS=>0,CURLOPT_USERAGENT=>'BubbaHub-ListingMonitor/1.0 (+https://bubbahub.co.uk)',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_RESOLVE=>$resolve,CURLOPT_HEADERFUNCTION=>static function($ch,string $line)use(&$redirectLocation):int{if(stripos($line,'Location:')===0)$redirectLocation=trim(substr($line,9));return strlen($line);},CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body,&$bodyTooLarge):int{if(strlen($body)+strlen($chunk)>1048576){$bodyTooLarge=true;return 0;}$body.=$chunk;return strlen($chunk);}]);
  $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);$location=$redirectLocation;curl_close($ch);
  if($bodyTooLarge)throw new RuntimeException('Website response exceeded the 1 MB safety limit; result is incomplete.');
  if($ok===false&&$status===0)throw new RuntimeException('Website could not be reached: '.($error?:'connection failed'));
  if(in_array($status,[301,302,303,307,308],true)){
   if($hop===3)throw new RuntimeException('Website redirected too many times.');
   if($location==='')throw new RuntimeException('Website redirect did not provide a destination.');
   $url=bh_lm_resolve_redirect($location,$scheme,$host,$port,(string)($p['path']??'/'));continue;
  }
  return ['status'=>$status,'body'=>$body,'url'=>$url];
 }
 throw new RuntimeException('Website redirect could not be checked.');
}
try{
 $columns=$db->query('SHOW COLUMNS FROM bh_activities')->fetchAll(PDO::FETCH_COLUMN);
 if(!in_array('id',$columns,true)||!in_array('website',$columns,true))throw new RuntimeException('Activity website field is unavailable.');
 $batch=max(1,min(30,(int)$get('batch_size','30')));
 $interval=max(1,min(24,(int)$get('interval_hours','4')));
 // A changed URL is immediately due because the history match includes website_url.
 $sql="SELECT a.id AS activity_id,a.website AS website_url
       FROM bh_activities a
       WHERE a.website IS NOT NULL AND TRIM(a.website)<>''
       AND NOT EXISTS (
         SELECT 1 FROM bh_listing_monitor_website_history h
         WHERE h.activity_id=a.id AND h.website_url=a.website
           AND h.checked_at>DATE_SUB(NOW(),INTERVAL ".$interval." HOUR)
       )
       ORDER BY a.id ASC LIMIT ".$batch;
 $items=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
 $history=$db->prepare('INSERT INTO bh_listing_monitor_website_history(activity_id,website_url,outcome,http_status,evidence) VALUES(?,?,?,?,?)');
 $find=$db->prepare("SELECT id FROM bh_listing_check_findings WHERE check_type='health' AND activity_id=? AND finding_type=? ORDER BY id DESC LIMIT 1");
 $insert=$db->prepare("INSERT INTO bh_listing_check_findings(check_type,activity_id,candidate_json,finding_type,confidence,source_url,checked_at,review_status) VALUES('health',?,?,?,?,?,NOW(),'new')");
 $refresh=$db->prepare("UPDATE bh_listing_check_findings SET candidate_json=?,confidence=?,source_url=?,checked_at=NOW(),reviewed_at=IF(review_status='resolved',NULL,reviewed_at),review_status=IF(review_status='resolved','new',review_status) WHERE id=?");
 $resolve=$db->prepare("UPDATE bh_listing_check_findings SET review_status='resolved',reviewed_at=NOW() WHERE check_type='health' AND activity_id=? AND finding_type IN ('website_possible_closure','website_closure_indicated','website_temporary_closure','website_unable_to_verify') AND review_status IN ('new','follow_up')");
 $recent=$db->prepare('SELECT outcome FROM bh_listing_monitor_website_history WHERE activity_id=? AND website_url=? ORDER BY checked_at DESC,id DESC LIMIT 3');
 $counts=['healthy'=>0,'possible_closure'=>0,'closure_indicated'=>0,'temporary_closure'=>0,'unable_to_verify'=>0];
 foreach($items as $item){
  $id=(int)$item['activity_id'];$url=trim((string)$item['website_url']);$status=null;$evidence='';$outcome='unable_to_verify';$finalUrl=$url;
  try{
   $result=bh_lm_fetch($url);$status=$result['status'];$finalUrl=$result['url'];$classification=bh_lm_classify_response($status,$result['body']);$outcome=$classification['outcome'];$evidence=$classification['evidence'];
  }catch(Throwable $e){$outcome='unable_to_verify';$evidence=substr($e->getMessage(),0,500);}
  $counts[$outcome]++;
  $recent->execute([$id,$url]);$previous=$recent->fetchAll(PDO::FETCH_COLUMN);
  $dead=in_array($outcome,['possible_closure','closure_indicated'],true);
  $consecutive=$dead?1:0;
  if($dead){foreach($previous as $prior){if(!in_array($prior,['possible_closure','closure_indicated'],true))break;$consecutive++;}}
  $history->execute([$id,$url,$outcome,$status,$evidence]);
  if($outcome==='healthy'){$resolve->execute([$id]);}
  if(in_array($outcome,['possible_closure','closure_indicated','temporary_closure','unable_to_verify'],true)){
   $type='website_'.$outcome;
   $candidate=['website'=>$url,'checked_url'=>$finalUrl,'http_status'=>$status,'outcome'=>$outcome,'evidence_note'=>$evidence,'consecutive_dead_checks'=>$consecutive,'checked_at'=>gmdate('c'),'source'=>'Bubba Hub Listing Monitor','note'=>'Investigate this evidence. No listing status or public content has been changed.'];
   $confidence=$outcome==='closure_indicated'?95:($outcome==='possible_closure'?70:50);
   $find->execute([$id,$type]);$existing=$find->fetchColumn();
   $json=json_encode($candidate,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
   if($existing)$refresh->execute([$json,$confidence,$url,(int)$existing]);
   else $insert->execute([$id,$json,$type,$confidence,$url]);
  }
 }
 echo json_encode(['checked'=>count($items),'results'=>$counts,'interval_hours'=>$interval,'message'=>'Website checks append private history and reuse the existing findings queue. No activity listings were edited or unpublished.'],JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){error_log('Listing Monitor website worker: '.$e->getMessage());fwrite(STDERR,'Website monitor failed: '.$e->getMessage()."\n");exit(1);}
finally{$db->query("SELECT RELEASE_LOCK('bh_listing_monitor_websites')");}
