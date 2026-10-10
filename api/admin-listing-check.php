<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('BUBBAHUB_ADMINSESSID');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function lc_out(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))lc_out(401,['ok'=>false,'error'=>'Admin login required.']);
try {
$db=bh_mysql();
$db->exec("CREATE TABLE IF NOT EXISTS bh_listing_check_results (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 activity_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL,
 website VARCHAR(500) NULL,
 website_key VARCHAR(500) NULL,
 status ENUM('same','changed','missing','new','dismissed') NOT NULL DEFAULT 'same',
 label VARCHAR(80) NOT NULL DEFAULT 'No change',
 confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
 town VARCHAR(120) NULL,
 region VARCHAR(120) NULL,
 website_evidence TEXT NULL,
 social_evidence TEXT NULL,
 changes_json LONGTEXT NULL,
 sources_json LONGTEXT NULL,
 draft_activity_id BIGINT UNSIGNED NULL,
 search_query VARCHAR(500) NULL,
 reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_lc_activity(activity_id),
 INDEX idx_lc_status(status),
 INDEX idx_lc_identity(title,website_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $db->exec("CREATE TABLE IF NOT EXISTS bh_listing_check_snapshots (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 source_url VARCHAR(500) NOT NULL,
 source_key CHAR(64) NOT NULL,
 content_hash CHAR(64) NOT NULL,
 page_title VARCHAR(500) NULL,
 excerpt TEXT NULL,
 checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_source_checked(source_key,checked_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch(Throwable $e) {
 lc_out(500,['ok'=>false,'error'=>'Listing Check database setup failed. Please check server logs.']);
}

function lc_has_county(PDO $db): bool {
 static $has=null;
 if($has===null){
  $q=$db->query("SHOW COLUMNS FROM bh_activities LIKE 'county'");
  $has=(bool)$q->fetch();
 }
 return $has;
}
function lc_insert_activity(PDO $db,array $values,?string $county): void {
 $columns='organiser_id,title,slug,description';$placeholders='?,?,?,?';
 if(lc_has_county($db)){$columns.=',county';$placeholders.=',?';$values[]=$county;}
 $db->prepare("INSERT INTO bh_activities (".$columns.",status) VALUES (".$placeholders.",'draft')")->execute($values);
}

function lc_config():array{
 $wpConfigs=array_filter([
  dirname(__DIR__).'/wp-config.php',
  dirname($_SERVER['DOCUMENT_ROOT']??'').'/wp-config.php',
  ($_SERVER['DOCUMENT_ROOT']??'').'/wp-config.php',
  '/public_html/wp-config.php',
 ]);
 foreach($wpConfigs as $f){
  if(is_file($f)){
   require_once $f;
   break;
  }
 }
 return [
  'google_search_api_key'=>defined('BH_GOOGLE_SEARCH_API_KEY')?(string)BH_GOOGLE_SEARCH_API_KEY:'',
  'google_search_cx'=>defined('BH_GOOGLE_SEARCH_CX')?(string)BH_GOOGLE_SEARCH_CX:'',
 ];
}
function lc_key(string $title,string $url):string{
 $title=mb_strtolower(trim($title));$url=trim($url);
 if($url!==''){ $p=parse_url($url); if(is_array($p)){ $host=strtolower((string)($p['host']??''));$host=preg_replace('/^www\./','',$host);$path=rtrim((string)($p['path']??'/'),'/');$url=$host.$path; } }
 return preg_replace('/\s+/',' ',$title).'|'.$url;
}
function lc_norm_url(string $url):string{
 $url=trim($url);if($url==='')return '';if(!preg_match('#^https?://#i',$url))$url='https://'.$url;
 $p=parse_url($url);if(!$p||empty($p['host']))return $url;
 $host=strtolower((string)$p['host']);$host=preg_replace('/^www\./','',$host);$path=rtrim((string)($p['path']??'/'),'/');
 return 'https://'.$host.($path&&$path!=='/'?$path:'');
}
// Listing Check deliberately uses no paid APIs or free-trial services.
function lc_google(string $q,array $cfg):array {return [];}
function lc_safe_url(string $url):string {
 $url=trim($url);
 if($url===''||strlen($url)>500||preg_match('/[\\x00-\\x20\\x7f]/',$url))return '';
 if(!preg_match('#^https?://#i',$url))$url='https://'.$url;
 $p=parse_url($url);
 if(!is_array($p)||!in_array(strtolower((string)($p['scheme']??'')),['http','https'],true))return '';
 if(isset($p['user'])||isset($p['pass'])||isset($p['port']))return '';
 $host=strtolower((string)($p['host']??''));
 if($host===''||$host==='localhost'||str_ends_with($host,'.local')||str_ends_with($host,'.internal')||filter_var($host,FILTER_VALIDATE_IP))return '';
 if(!preg_match('/^[a-z0-9.-]+$/',$host)||!str_contains($host,'.'))return '';
 return $url;
}
function lc_public_ipv4(string $url):?array {
 $host=(string)parse_url($url,PHP_URL_HOST);
 $ips=gethostbynamel($host);
 if(!$ips)return null;
 foreach($ips as $ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return null;
 return ['host'=>$host,'ip'=>$ips[0]];
}
function lc_public_sources(array $input):array {
 $urls=$input['sources']??[];
 if(!is_array($urls))return [];
 $out=[];
 foreach(array_slice($urls,0,10) as $url){
  if(!is_string($url))continue;
  $safe=lc_safe_url($url);
  if($safe!=='')$out[]=$safe;
 }
 return array_values(array_unique($out));
}
function lc_fetch(string $url):array{
 $url=lc_safe_url($url);if($url==='')return ['ok'=>false,'text'=>'','title'=>''];
 $resolved=lc_public_ipv4($url);if(!$resolved)return ['ok'=>false,'text'=>'','title'=>''];
 $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RESOLVE=>[$resolved['host'].':443:'.$resolved['ip'],$resolved['host'].':80:'.$resolved['ip']],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_MAXFILESIZE=>1048576,CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; BubbaHubListingCheck/1.0)']);$raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$final=(string)curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);curl_close($ch);
 if($raw===false||$http>=300||strlen((string)$raw)>1048576)return ['ok'=>false,'http'=>$http,'text'=>'','title'=>'','url'=>$final?:$url];
 $title='';if(preg_match('/<title[^>]*>(.*?)<\/title>/is',$raw,$m))$title=trim(html_entity_decode(strip_tags($m[1]),ENT_QUOTES|ENT_HTML5,'UTF-8'));
 $text=preg_replace('/\s+/',' ',strip_tags($raw));$text=trim(html_entity_decode((string)$text,ENT_QUOTES|ENT_HTML5,'UTF-8'));
 $signals=[];
 foreach(['closed','no longer running','no longer trading','permanently closed','ceased trading','we have closed','we are closing','relocated','new venue','moved to','new timetable','returning soon'] as $term)if(mb_stripos($text,$term)!==false)$signals[]=$term;
 $social=[];if(preg_match_all("#https?://(?:www\\.)?(?:facebook\\.com|instagram\\.com|tiktok\\.com)/[^\\\"'\\s<>]+#i",$raw,$sm))$social=array_values(array_unique($sm[0]));
 return ['ok'=>true,'http'=>$http,'title'=>$title,'text'=>mb_substr($text,0,30000),'signals'=>$signals,'social'=>$social,'url'=>$final?:$url];
}
function lc_snapshot(PDO $db,string $url,array $page):array {
 $key=hash('sha256',$url);
 $q=$db->prepare("SELECT content_hash,checked_at FROM bh_listing_check_snapshots WHERE source_key=? ORDER BY id DESC LIMIT 1");
 $q->execute([$key]);$previous=$q->fetch(PDO::FETCH_ASSOC)?:null;
 if(empty($page['ok']))return ['state'=>'unavailable','previous'=>$previous];
 $text=trim((string)($page['text']??''));
 $hash=hash('sha256',preg_replace('/\\s+/u',' ',$text));
 $changed=$previous&&$previous['content_hash']!==$hash;
 $q=$db->prepare("INSERT INTO bh_listing_check_snapshots (source_url,source_key,content_hash,page_title,excerpt) VALUES (?,?,?,?,?)");
 $q->execute([$url,$key,$hash,mb_substr((string)($page['title']??''),0,500),mb_substr($text,0,1500)]);
 return ['state'=>$previous?($changed?'changed':'unchanged'):'baseline','previous'=>$previous];
}
function lc_title_tokens(string $title):array {
 $title=mb_strtolower(html_entity_decode(strip_tags($title),ENT_QUOTES|ENT_HTML5,'UTF-8'));
 $title=preg_replace('/[^\\p{L}\\p{N}]+/u',' ',$title);
 $stop=['the','and','for','with','classes','class','activities','activity','home','welcome','events','devon','cornwall'];
 $words=array_filter(explode(' ',trim($title)),static fn($w)=>mb_strlen($w)>2&&!in_array($w,$stop,true));
 return array_values(array_unique($words));
}
function lc_existing_match(PDO $db,string $title,string $url,string $region=''):?array {
 $host=strtolower((string)(parse_url($url,PHP_URL_HOST)??''));
 $host=preg_replace('/^www\\./','',$host);
 $q=$db->query("SELECT a.id,a.title,COALESCE(o.website,'') website FROM bh_activities a LEFT JOIN bh_organisers o ON o.id=a.organiser_id ORDER BY a.id DESC LIMIT 1000");
 $candidate=lc_title_tokens($title);
 $best=null;$bestScore=0;
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
  $actual=strtolower((string)(parse_url(lc_norm_url((string)$row['website']),PHP_URL_HOST)??''));
  $actual=preg_replace('/^www\\./','',$actual);
  $tokens=lc_title_tokens((string)$row['title']);
  $intersection=count(array_intersect($candidate,$tokens));
  $union=count(array_unique(array_merge($candidate,$tokens)));
  $titleScore=$union?($intersection/$union):0;
  $sameHost=$host!==''&&$actual!==''&&$host===$actual;
  $sameRegion=false; // Venue region is not available in this organiser-only matching query.
  $score=(int)round($titleScore*70)+($sameHost?25:0)+($sameRegion?5:0);
  // A shared organiser domain alone is not enough to match distinct classes.
  if($titleScore>=0.55&&$score>$bestScore){$bestScore=$score;$best=$row;}
 }
 return $bestScore>=55?['activity'=>$best,'score'=>$bestScore]:null;
}
function lc_match(array $items,string $title,string $website):?array{
 $nk=mb_strtolower(preg_replace('/[^a-z0-9]+/i','',html_entity_decode($title)));
 $domain=parse_url(lc_norm_url($website),PHP_URL_HOST);$domain=$domain?preg_replace('/^www\./','',strtolower($domain)):'';
 $best=null;$score=0;
 foreach($items as $it){$rt=(string)($it['title']??'');$rl=(string)($it['link']??'');$rn=mb_strtolower(preg_replace('/[^a-z0-9]+/i','',html_entity_decode($rt)));$s=0;
  if($domain&&stripos((string)parse_url($rl,PHP_URL_HOST),$domain)!==false)$s+=70;
  if($nk!==''&&($rn===$nk||str_contains($rn,$nk)||str_contains($nk,$rn)))$s+=30;
  if($s>$score){$score=$s;$best=$it;}
 }
 return $best?['item'=>$best,'score'=>$score]:null;
}
function lc_changes(array $activity,array $source,array $page):array{
 $changes=[];
 $newTitle=trim((string)($source['title']??''));if($newTitle!==''&&mb_strtolower($newTitle)!==mb_strtolower((string)$activity['title']))$changes[]=['field'=>'Title','before'=>$activity['title'],'after'=>strip_tags($newTitle)];
 $foundUrl=lc_norm_url((string)($source['link']??''));$oldUrl=lc_norm_url((string)($activity['website']??''));if($foundUrl!==''&&$oldUrl!==''&&parse_url($foundUrl,PHP_URL_HOST)!==parse_url($oldUrl,PHP_URL_HOST))$changes[]=['field'=>'Website','before'=>$oldUrl,'after'=>$foundUrl];
 if(!empty($page['signals']))$changes[]=['field'=>'Website notice','before'=>'No closure/change notice recorded','after'=>implode(', ',$page['signals'])];
 return $changes;
}
function lc_results(PDO $db):array{
 $rows=$db->query("SELECT * FROM bh_listing_check_results WHERE status<>'dismissed' ORDER BY FIELD(status,'changed','missing','new','same'),confidence DESC,updated_at DESC")->fetchAll();
 foreach($rows as &$r){$r['changes']=json_decode((string)($r['changes_json']??'[]'),true)?:[];$r['sources']=json_decode((string)($r['sources_json']??'[]'),true)?:[];}
 return $rows;
}
function lc_summary(array $rows):array{
 $s=['total'=>0,'changed'=>0,'missing'=>0,'new'=>0,'same'=>0];foreach($rows as $r){$s['total']++;if(isset($s[$r['status']]))$s[$r['status']]++;}return $s;
}
$input=json_decode((string)file_get_contents('php://input'),true);$action=(string)($input['action']??'results');
if($action==='results'){ $rows=lc_results($db);lc_out(200,['ok'=>true,'results'=>$rows,'summary'=>lc_summary($rows),'processed'=>isset($activities)?count($activities):count($sourcesToCheck??[]),'next_offset'=>isset($activities)&&count($activities)===20?$offset+20:null]); }
if($action==='dismiss'){ $id=(int)($input['id']??0);$q=$db->prepare("UPDATE bh_listing_check_results SET status='dismissed',reviewed_at=NOW() WHERE id=?");$q->execute([$id]);lc_out(200,['ok'=>true]); }
if($action==='create_draft'){
 $id=(int)($input['id']??0);$q=$db->prepare("SELECT * FROM bh_listing_check_results WHERE id=?");$q->execute([$id]);$r=$q->fetch();if(!$r)lc_out(404,['ok'=>false,'error'=>'Listing Check result not found.']);if((int)($r['draft_activity_id']??0)>0)lc_out(200,['ok'=>true,'draft_activity_id'=>(int)$r['draft_activity_id']]);
 $orgSlug='listing-check-'.strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',(string)$r['title']),'-')).'-'.$id;
 $orgName=trim((string)$r['title'])?:'New listing';
 $db->prepare("INSERT INTO bh_organisers (organisation_name,slug,description,website,status) VALUES (?,?,?,?, 'draft')")->execute([$orgName,$orgSlug,'Discovered by Listing Check. Assign/claim the real organiser before publishing.',($r['website']?:null)]);
 $org=(int)$db->lastInsertId();
 $slug=trim((string)$r['title']);$slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$slug),'-'));if($slug==='')$slug='listing-check-'.$id;$base=$slug;$n=2;while(true){$x=$db->prepare("SELECT COUNT(*) FROM bh_activities WHERE slug=?");$x->execute([$slug]);if(!(int)$x->fetchColumn())break;$slug=$base.'-'.$n++;}
 $desc='Discovered by Bubba Hub Listing Check. Review the external source before publishing.';
 $county=in_array($r['region'],['Plymouth','Torbay'],true)?$r['region']:(str_contains(strtolower((string)$r['region']),'cornwall')?'Cornwall':(str_contains(strtolower((string)$r['region']),'devon')?'Devon':null));
 lc_insert_activity($db,[$org,$r['title'],$slug,$desc],$county);$aid=(int)$db->lastInsertId();
 $db->prepare("UPDATE bh_listing_check_results SET draft_activity_id=?,updated_at=NOW() WHERE id=?")->execute([$aid,$id]);lc_out(200,['ok'=>true,'draft_activity_id'=>$aid]);
}
if($action!=='scan')lc_out(400,['ok'=>false,'error'=>'Unknown action.']);
$mode=(string)($input['mode']??'existing');$region=trim((string)($input['region']??''));$query=trim((string)($input['query']??''));$cfg=[];
try{
 $items=[];
 if($mode==='existing'){
  $countySql=lc_has_county($db)?'a.county':'NULL';
  $hasLink=(bool)$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activity_venues'")->fetchColumn();
  $hasSessions=(bool)$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_sessions'")->fetchColumn();
  $venueCols=$db->query("SHOW COLUMNS FROM bh_venues")->fetchAll(PDO::FETCH_COLUMN);
  if($hasLink)$venueJoin="LEFT JOIN bh_venues v ON v.id=(SELECT av.venue_id FROM bh_activity_venues av WHERE av.activity_id=a.id LIMIT 1)";
  elseif($hasSessions)$venueJoin="LEFT JOIN bh_venues v ON v.id=(SELECT ss.venue_id FROM bh_sessions ss WHERE ss.activity_id=a.id AND ss.venue_id IS NOT NULL LIMIT 1)";
  elseif(in_array('activity_id',$venueCols,true))$venueJoin="LEFT JOIN bh_venues v ON v.id=(SELECT vv.id FROM bh_venues vv WHERE vv.activity_id=a.id LIMIT 1)";
  else $venueJoin="LEFT JOIN bh_venues v ON 1=0";
  $sql="SELECT a.id,a.title,a.slug,a.status,a.description,a.booking_url,$countySql AS county,COALESCE(v.town,'') town,COALESCE(v.region,'') region,COALESCE(o.website,'') website FROM bh_activities a $venueJoin LEFT JOIN bh_organisers o ON o.id=a.organiser_id WHERE a.status IN ('published','pending','draft')";
  $params=[];if($region){$sql.=" AND (v.region=?".($countySql==='NULL'?'':" OR ".$countySql."=?").")";$params=$countySql==='NULL'?[$region]:[$region,$region];}$sql.=" ORDER BY a.id";
  $offset=max(0,min(100000,(int)($input['offset']??0)));
  $sql.=' LIMIT 20 OFFSET '.$offset;
  $st=$db->prepare($sql);$st->execute($params);$activities=$st->fetchAll();
  foreach($activities as $a){
   $title=trim((string)$a['title']);$website=trim((string)$a['website']);$q='"'.$title.'"';if($region)$q.=' '. $region;if($query)$q.=' '.$query;
   $page=$website?lc_fetch($website):['ok'=>false,'signals'=>[],'text'=>'','title'=>''];
   $snapshot=$website?lc_snapshot($db,lc_norm_url($website),$page):['state'=>'unavailable'];
   $sources=[];if($website&&lc_safe_url($website)!=='')$sources[]=['label'=>'Organiser website','url'=>lc_safe_url($website)];
   $changes=[];$score=0;
   if(!empty($page['ok'])&&!empty($page['signals'])){
    $changes[]=['field'=>'Possible website notice','before'=>'Requires review','after'=>implode(', ',$page['signals'])];
    $score=30;
   }
   if(($snapshot['state']??'')==='changed'){$changes[]=['field'=>'Website content','before'=>'Previous snapshot','after'=>'Page content changed since last successful check'];$score=max($score,65);}
   $stt=$changes?'changed':'same';
   $label=$changes?'Review website notice':'Not independently verified';
   $websiteEvidence=empty($website)?'No website recorded.':(!empty($page['ok'])?'Website reachable; snapshot: '.($snapshot['state']??'unknown').'. Review differences manually.':'Website unavailable or blocked; NOT evidence of closure.');
   $socialEvidence='Social posts not independently searched; add public URLs to the source check.';
   $key=lc_key($title,$website);$existing=$db->prepare("SELECT id FROM bh_listing_check_results WHERE title=? AND website_key=? AND activity_id=? ORDER BY id DESC LIMIT 1");$existing->execute([$title,$key,$a['id']]);$rid=$existing->fetchColumn();
   $payload=[$a['id'],$title,$website,$key,$stt,$label,min(100,max(0,$score)),$a['town'],$a['region'],$websiteEvidence,$socialEvidence,json_encode($changes),json_encode(array_slice($sources,0,10)),$q];
   if($rid){$u=$db->prepare("UPDATE bh_listing_check_results SET activity_id=?,title=?,website=?,website_key=?,status=?,label=?,confidence=?,town=?,region=?,website_evidence=?,social_evidence=?,changes_json=?,sources_json=?,search_query=?,reviewed_at=NULL WHERE id=? AND status<>'dismissed'");$u->execute([$a['id'],$title,$website,$key,$stt,$label,min(100,max(0,$score)),$a['town'],$a['region'],$websiteEvidence,$socialEvidence,json_encode($changes),json_encode(array_slice($sources,0,10)),$q,$rid]);}
   else{$u=$db->prepare("INSERT INTO bh_listing_check_results (activity_id,title,website,website_key,status,label,confidence,town,region,website_evidence,social_evidence,changes_json,sources_json,search_query) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");$u->execute($payload);}
  }
 }else{
  // Free, targeted discovery from administrator-supplied public pages.
  // Never claim that this crawls the whole internet or bypasses social login.
  $scope=trim($query.' '.$region);
  $sourcesToCheck=lc_public_sources($input);
  if(!$sourcesToCheck)lc_out(422,['ok'=>false,'error'=>'Enter one or more public source URLs (websites, event pages or accessible social posts). No paid search API is used.']);
  foreach($sourcesToCheck as $url){
   $page=lc_fetch($url);
   if(empty($page['ok']))continue;
   $snapshot=lc_snapshot($db,$url,$page);
   $title=trim((string)($page['title']??''));if($title==='')continue;
   if($query!==''&&!str_contains(mb_strtolower($title.' '.($page['text']??'')),mb_strtolower($query)))continue;
   $key=lc_key($title,$url);
   $matched=lc_existing_match($db,$title,$url,$region);
   if($matched)continue;
   $dup=$db->prepare("SELECT id FROM bh_listing_check_results WHERE website_key=? AND title=? LIMIT 1");
   $dup->execute([$key,$title]);if($dup->fetchColumn())continue;
   $ins=$db->prepare("INSERT INTO bh_listing_check_results (title,website,website_key,status,label,confidence,town,region,website_evidence,social_evidence,sources_json,search_query) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
   $ins->execute([$title,$url,$key,'new','Review candidate',20,'',$region,'Public page available; activity details not independently verified.','Social platform restrictions may prevent access.',json_encode([['label'=>'Public source','url'=>$url]]),$scope]);
   // Never auto-create organiser or activity records from unverified pages.
  }
 }
 $rows=lc_results($db);lc_out(200,['ok'=>true,'results'=>$rows,'summary'=>lc_summary($rows)]);
}catch(Throwable $e){error_log('Listing Check scan failed: '.$e->getMessage());lc_out(500,['ok'=>false,'error'=>'Listing Check could not finish. Check the server error log.']);}
?>