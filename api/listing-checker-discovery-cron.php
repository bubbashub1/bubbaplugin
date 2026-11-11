<?php
declare(strict_types=1);
/**
 * CLI-only discovery worker. Uses Bing's public RSS search response; no paid API key.
 * Results are unverified leads for admin review, never new/published listings.
 * Run alongside the health worker from server cron every four hours.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/db.php';
$db=bh_mysql();
$required=['bh_listing_checker_settings','bh_listing_discovery_terms','bh_listing_check_runs','bh_listing_check_findings'];
foreach($required as $table){$q=$db->prepare('SHOW TABLES LIKE ?');$q->execute([$table]);if(!$q->fetchColumn()){fwrite(STDERR,"Migration required: apply database/listing-checker-schema.sql\n");exit(2);}}
$canonicalizeDiscoveryUrl=static function(string $url): ?string {
 $p=parse_url(trim($url));
 if(!is_array($p)||!in_array(strtolower((string)($p['scheme']??'')),['http','https'],true)||empty($p['host'])||isset($p['user'])||isset($p['pass']))return null;
 $scheme=strtolower((string)$p['scheme']);$host=strtolower(rtrim((string)$p['host'],'.'));
 if(!preg_match('/^[a-z0-9.-]+$/',$host)||str_contains($host,'..'))return null;
 $port=isset($p['port'])?(int)$p['port']:null;
 if(($scheme==='http'&&$port===80)||($scheme==='https'&&$port===443))$port=null;
 $path=(string)($p['path']??'');if($path==='')$path='/';elseif($path!=='/')$path=rtrim($path,'/');
 $query=(string)($p['query']??'');
 if($query!==''){
  $pairs=explode('&',$query);
  $pairs=array_values(array_filter($pairs,static function(string $pair): bool {
   $key=strtolower(rawurldecode(explode('=',$pair,2)[0]));
   return !preg_match('/^(utm_[a-z0-9_]+|fbclid|gclid|msclkid|dclid|mc_cid|mc_eid)$/',$key);
  }));
  sort($pairs,SORT_STRING);$query=implode('&',$pairs);
 }
 return $scheme.'://'.$host.($port!==null?':'.$port:'').$path.($query!==''?'?'.$query:'');
};
$settings=[];foreach($db->query('SELECT setting_key,setting_value FROM bh_listing_checker_settings')->fetchAll(PDO::FETCH_ASSOC) as $r)$settings[$r['setting_key']]=$r['setting_value'];
$get=static fn($k,$d='')=>(string)($settings[$k]??$d);
if($get('discovery_enabled','0')!=='1'){echo "New listing discovery disabled\n";exit;}
if($get('discovery_paused','0')==='1'){echo "Discovery checks paused\\n";exit;}
$manualRun=$get('discovery_run_now','0')==='1';
$tz=new DateTimeZone($get('timezone','Europe/London'));$now=new DateTimeImmutable('now',$tz);$clock=$now->format('H:i');
$days=json_decode($get('active_days','[1,2,3,4,5,6,7]'),true)?:[1,2,3,4,5,6,7];
if(!in_array((int)$now->format('N'),array_map('intval',$days),true)){echo "Not an active day\n";exit;}
$start=$get('window_start','00:00');$end=$get('window_end','23:59');
if(($start<=$end&&($clock<$start||$clock>$end))||($start>$end&&$clock>$end&&$clock<$start)){echo "Outside run window\n";exit;}
$interval=max(1,min(24,(int)$get('interval_hours','4')));$preferred=$get('preferred_start_time','00:00');
$last=$db->query("SELECT MAX(finished_at) FROM bh_listing_check_runs WHERE check_type='discovery' AND status='completed'")->fetchColumn();
if(!$manualRun&&!$last&&$clock<$preferred){echo "Waiting for preferred first-run time\n";exit;}
if(!$manualRun&&$last&&new DateTimeImmutable((string)$last,$tz)>$now->modify('-'.$interval.' hours')){echo "Not due yet\n";exit;}
$lock=$db->query("SELECT GET_LOCK('bh_listing_checker_discovery',0)")->fetchColumn();if((int)$lock!==1){echo "Discovery worker already running\n";exit;}
$runId=0;
try{
 $run=$db->prepare("INSERT INTO bh_listing_check_runs(check_type,status,started_at) VALUES('discovery','running',NOW())");$run->execute();$runId=(int)$db->lastInsertId();
 if($manualRun){$clear=$db->prepare("INSERT INTO bh_listing_checker_settings(setting_key,setting_value) VALUES(?, '0') ON DUPLICATE KEY UPDATE setting_value='0'");$clear->execute(['discovery_run_now']);}
 $batch=max(1,min(100,(int)$get('batch_size','30')));$requestLimit=max(1,min(200,(int)$get('request_limit','30')));$limit=min($batch,$requestLimit);$runtime=max(30,min(600,(int)$get('max_runtime_seconds','240')));$deadline=microtime(true)+$runtime;
 $terms=$db->query("SELECT id,term,term_group,regions_json FROM bh_listing_discovery_terms WHERE enabled=1 ORDER BY COALESCE(last_searched_at,'1970-01-01 00:00:00') ASC,id ASC LIMIT ".$limit)->fetchAll(PDO::FETCH_ASSOC);
 if(!$terms){$summary=json_encode(['processed'=>0,'candidates'=>0,'errors'=>0,'message'=>'No enabled discovery terms']);$u=$db->prepare("UPDATE bh_listing_check_runs SET status='completed',finished_at=NOW(),summary_json=? WHERE id=?");$u->execute([$summary,$runId]);echo $summary."\n";exit;}
 if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL extension is required for discovery searches.');
 if(!function_exists('simplexml_load_string'))throw new RuntimeException('PHP SimpleXML extension is required for discovery searches.');
 $processed=0;$candidates=0;$errors=0;$duplicates=0;$alreadyListed=0;
 $insert=$db->prepare("INSERT INTO bh_listing_check_findings(check_type,activity_id,term_id,candidate_json,finding_type,confidence,source_url,checked_at,review_status) VALUES('discovery',NULL,?,?,?,?,?,NOW(),'new')");
 $existingWebsites=[];
 foreach($db->query("SELECT website FROM bh_activities WHERE website IS NOT NULL AND TRIM(website)<>''")->fetchAll(PDO::FETCH_COLUMN) as $existingWebsite){$existingCanonical=$canonicalizeDiscoveryUrl((string)$existingWebsite);if($existingCanonical!==null)$existingWebsites[$existingCanonical]=true;}
 $seen=$db->prepare("SELECT id FROM bh_listing_check_findings WHERE check_type='discovery' AND source_url=? LIMIT 1");
 $touch=$db->prepare('UPDATE bh_listing_discovery_terms SET last_searched_at=NOW() WHERE id=?');
 foreach($terms as $term){
  if(microtime(true)>$deadline)break;
  try{
   $regions=json_decode((string)$term['regions_json'],true);if(!is_array($regions)||!$regions)$regions=['all'];
   $scope=in_array('all',$regions,true)?'Devon OR Cornwall':implode(' OR ',$regions);
   $query=trim((string)$term['term'].' ('.$scope.') family baby toddler activity class');
   $url='https://www.bing.com/search?format=rss&q='.rawurlencode($query);
   $ch=curl_init($url);$bodyBuffer='';curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk) use (&$bodyBuffer): int { if(strlen($bodyBuffer)+strlen($chunk)>2097152)return 0;$bodyBuffer.=$chunk;return strlen($chunk);},CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>18,CURLOPT_USERAGENT=>'BubbaHub-ListingChecker/1.0 (+https://bubbahub.co.uk)',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS]);
   $ok=curl_exec($ch);$body=$bodyBuffer;$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$curlError=curl_error($ch);curl_close($ch);
   if($ok!==true||!is_string($body)||$http<200||$http>=300)throw new RuntimeException('Search provider returned HTTP '.$http.($curlError!==''?': '.$curlError:''));
   libxml_use_internal_errors(true);$xml=simplexml_load_string($body,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOCDATA);libxml_clear_errors();
   if(!$xml||!isset($xml->channel->item))throw new RuntimeException('Search provider response was not valid RSS.');
   $count=0;
   foreach($xml->channel->item as $entry){
    if($count>=10)break;
    $title=trim((string)$entry->title);$source=trim((string)$entry->link);$snippet=trim(strip_tags((string)$entry->description));
    if($title===''||$source===''||strlen($source)>1000)continue;
    $parts=parse_url($source);if(!is_array($parts)||!in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))continue;
    if(preg_match('/(^|\.)bing\.com$/i',(string)$parts['host'])){parse_str((string)($parts['query']??''),$bp);$encoded=(string)($bp['u']??'');if(str_starts_with($encoded,'a1')){$decoded=base64_decode(strtr(substr($encoded,2),'-_','+/'));if(is_string($decoded))$source=$decoded;}}
    $canonicalUrl=$canonicalizeDiscoveryUrl($source);if($canonicalUrl===null||strlen($canonicalUrl)>1000)continue;
    if(isset($existingWebsites[$canonicalUrl])){$alreadyListed++;continue;}
    $seen->execute([$canonicalUrl]);if($seen->fetchColumn()){$duplicates++;continue;}
    $candidate=['title'=>$title,'snippet'=>substr($snippet,0,1200),'website'=>$source,'canonical_website'=>$canonicalUrl,'search_term'=>(string)$term['term'],'term_group'=>(string)$term['term_group'],'region_scope'=>$scope,'provider'=>'Bing RSS','evidence_note'=>'Search result only. Verify the organiser, current activity, location and dates directly before treating this as a valid listing.'];
    $insert->execute([(int)$term['id'],json_encode($candidate,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE),'discovery_candidate',45,$canonicalUrl]);
    $count++;$candidates++;
   }
   $touch->execute([(int)$term['id']]);$processed++;
  }catch(Throwable $e){$errors++;try{$touch->execute([(int)$term['id']]);}catch(Throwable $touchError){error_log('Listing discovery term timestamp update failed for term '.(int)$term['id'].': '.$touchError->getMessage());}error_log('Listing discovery term '.(int)$term['id'].': '.$e->getMessage());}
 }
 $remaining=(int)$db->query('SELECT COUNT(*) FROM bh_listing_discovery_terms WHERE enabled=1')->fetchColumn();
 $summary=json_encode(['processed_terms'=>$processed,'candidates_added'=>$candidates,'already_seen'=>$duplicates,'already_listed'=>$alreadyListed,'errors'=>$errors,'enabled_terms'=>$remaining,'request_limit'=>$requestLimit]);
 $finish=$db->prepare("UPDATE bh_listing_check_runs SET status='completed',finished_at=NOW(),processed_count=?,error_count=?,summary_json=? WHERE id=?");$finish->execute([$processed,$errors,$summary,$runId]);echo $summary."\n";
}catch(Throwable $e){if($runId){$s=$db->prepare("UPDATE bh_listing_check_runs SET status='failed',finished_at=NOW(),summary_json=? WHERE id=?");$s->execute([json_encode(['error'=>$e->getMessage()]),$runId]);}error_log('Listing discovery worker: '.$e->getMessage());fwrite(STDERR,"Discovery worker failed; see PHP error log\n");exit(1);}
finally{$db->query("SELECT RELEASE_LOCK('bh_listing_checker_discovery')");}
