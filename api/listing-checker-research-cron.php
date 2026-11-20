<?php
declare(strict_types=1);
/**
 * CLI-only missing-information research worker.
 * Bing RSS results and fetched public pages are untrusted evidence. Suggestions
 * are stored for admin review only; this worker never updates listing records.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/db.php';
$db=bh_mysql();
$required=['bh_listing_checker_settings','bh_listing_check_runs','bh_listing_check_findings','bh_activities'];
foreach($required as $table){$q=$db->prepare('SHOW TABLES LIKE ?');$q->execute([$table]);if(!$q->fetchColumn()){fwrite(STDERR,"Required Listing Checker table missing: ".$table."\n");exit(2);}}
$settings=[];foreach($db->query('SELECT setting_key,setting_value FROM bh_listing_checker_settings')->fetchAll(PDO::FETCH_ASSOC) as $r)$settings[$r['setting_key']]=$r['setting_value'];
$get=static fn(string $k,string $d='')=>(string)($settings[$k]??$d);
if($get('research_enabled','0')!=='1'){echo "Missing-information research disabled\n";exit;}
if($get('research_paused','0')==='1'){echo "Missing-information research paused\n";exit;}
$manual=$get('research_run_now','0')==='1';
$tz=new DateTimeZone($get('timezone','Europe/London'));$now=new DateTimeImmutable('now',$tz);
$days=json_decode($get('active_days','[1,2,3,4,5,6,7]'),true)?:[1,2,3,4,5,6,7];
if(!in_array((int)$now->format('N'),array_map('intval',$days),true)){echo "Not an active day\n";exit;}
$clock=$now->format('H:i');$start=$get('window_start','00:00');$end=$get('window_end','23:59');
if(($start<=$end&&($clock<$start||$clock>$end))||($start>$end&&$clock>$end&&$clock<$start)){echo "Outside run window\n";exit;}
$interval=max(1,min(24,(int)$get('interval_hours','4')));
$last=$db->query("SELECT MAX(finished_at) FROM bh_listing_check_runs WHERE check_type='health' AND status='completed'")->fetchColumn();
if(!$manual&&$last&&new DateTimeImmutable((string)$last,$tz)>$now->modify('-'.$interval.' hours')){echo "Not due yet\n";exit;}
$lock=$db->query("SELECT GET_LOCK('bh_listing_missing_research',0)")->fetchColumn();if((int)$lock!==1){echo "Research worker already running\n";exit;}
$runId=0;
try{
 if(!function_exists('curl_init')||!function_exists('simplexml_load_string'))throw new RuntimeException('PHP cURL and SimpleXML are required.');
 $cols=$db->query('SHOW COLUMNS FROM bh_activities')->fetchAll(PDO::FETCH_COLUMN);
 $orgCols=[];$hasOrg=in_array('organiser_id',$cols,true);
 if($hasOrg){$q=$db->query("SHOW TABLES LIKE 'bh_organisers'");if($q->fetchColumn())$orgCols=$db->query('SHOW COLUMNS FROM bh_organisers')->fetchAll(PDO::FETCH_COLUMN);}
 $select=['a.id'];$fields=['title','description','short_description','category','age_range','website','booking_url','contact_email','contact_phone','facebook_url','instagram_url','town','region','postcode'];
 foreach($fields as $f)if(in_array($f,$cols,true))$select[]='a.`'.$f.'`';
 if($hasOrg&&in_array('id',$orgCols,true))foreach(['organisation_name','website','email','phone'] as $f)if(in_array($f,$orgCols,true))$select[]='o.`'.$f.'` AS organiser_'.$f;
 $join=$hasOrg&&in_array('id',$orgCols,true)?' LEFT JOIN bh_organisers o ON o.id=a.organiser_id':'';
 $limit=max(1,min(30,(int)$get('research_batch_size','10')));
 $requestLimit=max(1,min(30,(int)$get('request_limit','30')));
 $runtime=max(30,min(240,(int)$get('max_runtime_seconds','180')));$deadline=microtime(true)+$runtime;
 $query='SELECT '.implode(',',$select).' FROM bh_activities a'.$join.' WHERE NOT EXISTS (SELECT 1 FROM bh_listing_check_findings rf WHERE rf.check_type=\'health\' AND rf.activity_id=a.id AND rf.finding_type IN (\'auto_research_suggestions\',\'auto_research_no_suggestions\')) ORDER BY a.id LIMIT 5000';
 $rows=$db->query($query)->fetchAll(PDO::FETCH_ASSOC);
 $run=$db->prepare("INSERT INTO bh_listing_check_runs(check_type,status,started_at) VALUES('health','running',NOW())");$run->execute();$runId=(int)$db->lastInsertId();
 if($manual){$clear=$db->prepare("INSERT INTO bh_listing_checker_settings(setting_key,setting_value) VALUES('research_run_now','0') ON DUPLICATE KEY UPDATE setting_value='0'");$clear->execute();}
 $missing=[];$keys=['description'=>['description','short_description'],'website'=>['website','organiser_website'],'booking_url'=>['booking_url'],'contact_email'=>['contact_email','organiser_email'],'contact_phone'=>['contact_phone','organiser_phone'],'age_range'=>['age_range'],'category'=>['category'],'town'=>['town'],'postcode'=>['postcode'],'facebook_url'=>['facebook_url'],'instagram_url'=>['instagram_url']];
 foreach($rows as $row){$want=[];foreach($keys as $field=>$sources){$supported=array_filter($sources,static fn($k)=>array_key_exists($k,$row));if(!$supported)continue;$present=false;foreach($supported as $k)if(trim((string)($row[$k]??''))!=='')$present=true;if(!$present)$want[]=$field;}if($want&&count($missing)<$limit)$missing[]=['row'=>$row,'fields'=>$want];}
 $insert=$db->prepare("INSERT INTO bh_listing_check_findings(check_type,activity_id,term_id,candidate_json,finding_type,confidence,source_url,checked_at,review_status) VALUES('health',?,NULL,?,?,NULL,?,NOW(),?)");
 $find=$db->prepare("SELECT id,review_status FROM bh_listing_check_findings WHERE check_type='health' AND activity_id=? AND finding_type='auto_research_suggestions' ORDER BY id DESC LIMIT 1");
 $refresh=$db->prepare("UPDATE bh_listing_check_findings SET candidate_json=?,source_url=?,checked_at=NOW() WHERE id=? AND review_status IN('new','follow_up')");
 $safeUrl=static function(string $url): ?string{$p=parse_url(trim($url));if(!is_array($p)||strtolower((string)($p['scheme']??''))!=='https'||empty($p['host'])||isset($p['user'])||isset($p['pass']))return null;$h=strtolower((string)$p['host']);if(filter_var($h,FILTER_VALIDATE_IP)||!preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/',$h)||str_contains($h,'..')||in_array($h,['localhost','localhost.localdomain'],true))return null;return $url;};
 $fetch=static function(string $url,int $cap=1048576): string{$buf='';$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_WRITEFUNCTION=>static function($h,string $chunk)use(&$buf,$cap):int{if(strlen($buf)+strlen($chunk)>$cap)return 0;$buf.=$chunk;return strlen($chunk);},CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>12,CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; ListingResearch/1.0)',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return $ok===true&&$status>=200&&$status<300?$buf:'';};
 $processed=0;$suggestions=0;$requests=0;$errors=0;
 foreach($missing as $item){if(microtime(true)>$deadline||$requests >= $requestLimit)break;$row=$item['row'];$aid=(int)$row['id'];$title=trim((string)($row['title']??''));if($title==='')continue;
  try{
   $parts=array_filter([$title,$row['organiser_organisation_name']??'',$row['town']??'',$row['region']??'',$row['category']??'']);
   // Never include the platform/brand name in external search keywords.
   $query=trim(preg_replace('/\bBubba\s*Hub\b/i','',implode(' ',$parts))??implode(' ',$parts));
   $query=trim(preg_replace('/\s+/',' ',$query)??$query);if($query==='')continue;
   $rss='https://www.bing.com/search?format=rss&q='.rawurlencode($query);
   $xmlText=$fetch($rss,1048576);$requests++;if($xmlText==='')continue;
   libxml_use_internal_errors(true);$xml=simplexml_load_string($xmlText,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOCDATA);libxml_clear_errors();if(!$xml||!isset($xml->channel->item))continue;
   $suggested=[];$sources=[];$resultCount=0;
   foreach($xml->channel->item as $entry){if($resultCount>=4||$requests >= $requestLimit||microtime(true)>$deadline)break;$source=$safeUrl(trim((string)$entry->link));if(!$source)continue;$html=$fetch($source);$requests++;if($html==='')continue;$resultCount++;
    $dom=new DOMDocument();@$dom->loadHTML($html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);$xp=new DOMXPath($dom);
    $meta='';foreach(["//meta[@name='description']/@content","//meta[@property='og:description']/@content"] as $path){$n=$xp->query($path)?->item(0);if($n){$meta=trim((string)$n->nodeValue);if($meta!=='')break;}}
    $pageTitle=trim((string)($xp->query('//title')?->item(0)?->textContent??''));$text=html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8');$text=preg_replace('/\s+/u',' ',$text)??$text;
    $propose=static function(string $field,string $value,string $url,string $reason)use(&$suggested):void{$value=trim($value);if($value===''||isset($suggested[$field]))return;$suggested[$field]=['value'=>substr($value,0,1200),'source_url'=>$url,'evidence'=>$reason,'confidence'=>60];};
    if(in_array('website',$item['fields'],true))$propose('website',$source,$source,'Official/public page returned by the search result; confirm it belongs to this organiser.');
    if(in_array('description',$item['fields'],true)&&$meta!=='')$propose('description',$meta,$source,'Page metadata description; check that it describes this activity.');
    if(in_array('contact_email',$item['fields'],true)&&preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',$text,$m))$propose('contact_email',$m[0],$source,'Email address found in page text.');
    if(in_array('contact_phone',$item['fields'],true)&&preg_match('/(?:\+44\s?\(?0?\)?\s?|0)(?:\d[\s().-]?){9,10}/',$text,$m))$propose('contact_phone',trim($m[0]),$source,'Possible UK telephone number found in page text.');
    if(in_array('age_range',$item['fields'],true)&&preg_match('/\b(?:babies|baby|toddlers?|children|ages?)\b[^.]{0,70}\b(?:\d{1,2}\s*(?:-|to|–)\s*\d{1,2}\s*(?:months?|years?|yrs?)|under\s*\d{1,2})\b/i',$text,$m))$propose('age_range',$m[0],$source,'Possible age range phrase found in page text.');
    if(in_array('facebook_url',$item['fields'],true)||in_array('instagram_url',$item['fields'],true)||in_array('booking_url',$item['fields'],true)){
     foreach($xp->query('//a[@href]') as $a){$href=trim((string)$a->getAttribute('href'));$abs=$href;if(str_starts_with($href,'/')){$base=parse_url($source);$abs='https://'.$base['host'].$href;}if(!filter_var($abs,FILTER_VALIDATE_URL))continue;$host=strtolower((string)parse_url($abs,PHP_URL_HOST));$label=strtolower(trim((string)$a->textContent));
      if(in_array('facebook_url',$item['fields'],true)&&str_contains($host,'facebook.com'))$propose('facebook_url',$abs,$source,'Social link found on source page.');
      if(in_array('instagram_url',$item['fields'],true)&&str_contains($host,'instagram.com'))$propose('instagram_url',$abs,$source,'Social link found on source page.');
      if(in_array('booking_url',$item['fields'],true)&&preg_match('/book|booking|tickets|register|reserve|sign.?up/',$label))$propose('booking_url',$abs,$source,'Link labelled as a booking or registration action.');
     }
    }
    $sources[]=['url'=>$source,'title'=>$pageTitle,'snippet'=>substr($meta,0,500)];
   }
   if(!$suggested){$noResult=['activity_title'=>$title,'missing_fields'=>$item['fields'],'suggested_fields'=>[],'sources'=>$sources,'search_query'=>$query,'provider'=>'Bing RSS + public page metadata','evidence_note'=>'No usable missing-field suggestions were found. This attempt is recorded to avoid repeatedly searching the same listing.'];$insert->execute([$aid,json_encode($noResult,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE),'auto_research_no_suggestions',null,'resolved']);$processed++;continue;}
   $candidate=['activity_title'=>$title,'missing_fields'=>$item['fields'],'suggested_fields'=>$suggested,'sources'=>$sources,'search_query'=>$query,'provider'=>'Bing RSS + public page metadata','evidence_note'=>'Automatically collected suggestions from public search results/pages. Values are unverified; inspect each source and approve or edit each field before applying. No listing data was changed.'];
   $json=json_encode($candidate,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);$sourceUrl=(string)array_values($suggested)[0]['source_url'];
   $find->execute([$aid]);$existing=$find->fetch(PDO::FETCH_ASSOC);
   if($existing){if(in_array($existing['review_status'],['new','follow_up'],true))$refresh->execute([$json,$sourceUrl,(int)$existing['id']]);}
   else $insert->execute([$aid,$json,'auto_research_suggestions',$sourceUrl,'new']);
   $suggestions++;$processed++;
  }catch(Throwable $e){$errors++;error_log('Missing-information research activity '.$aid.': '.$e->getMessage());}
 }
 $summary=json_encode(['listings_considered'=>count($missing),'processed'=>$processed,'suggestions_saved'=>$suggestions,'external_requests'=>$requests,'errors'=>$errors,'remaining_limit'=>$requestLimit-$requests]);
 $finish=$db->prepare("UPDATE bh_listing_check_runs SET status='completed',finished_at=NOW(),processed_count=?,error_count=?,summary_json=? WHERE id=?");$finish->execute([$processed,$errors,$summary,$runId]);echo $summary."\n";
}catch(Throwable $e){if($runId){$u=$db->prepare("UPDATE bh_listing_check_runs SET status='failed',finished_at=NOW(),summary_json=? WHERE id=?");$u->execute([json_encode(['error'=>$e->getMessage()]),$runId]);}error_log('Missing-information research worker: '.$e->getMessage());fwrite(STDERR,"Research worker failed; see PHP error log\n");exit(1);}
finally{$db->query("SELECT RELEASE_LOCK('bh_listing_missing_research')");}
