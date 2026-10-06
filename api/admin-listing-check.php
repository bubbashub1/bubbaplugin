<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function lc_out(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))lc_out(401,['ok'=>false,'error'=>'Admin login required.']);
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

function lc_config():array{
 $files=array_filter([dirname(__DIR__).'/github-deploy-config.php',dirname($_SERVER['DOCUMENT_ROOT']??'').'/github-deploy-config.php',($_SERVER['DOCUMENT_ROOT']??'').'/github-deploy-config.php','/github-deploy-config.php']);
 foreach($files as $f)if(is_file($f)){ $c=require $f; if(is_array($c))return $c; }
 return [];
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
function lc_google(string $q,array $cfg):array{
 $key=(string)($cfg['google_search_api_key']??'');$cx=(string)($cfg['google_search_cx']??'');
 if($key===''||$cx==='')throw new RuntimeException('Google search is not configured. Add google_search_api_key and google_search_cx to the server-only github-deploy-config.php.');
 $url='https://www.googleapis.com/customsearch/v1?'.http_build_query(['key'=>$key,'cx'=>$cx,'q'=>$q,'num'=>10,'safe'=>'active']);
 $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_USERAGENT=>'Bubba Hub Listing Check/1.0']);$raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if($raw===false||$http>=400)throw new RuntimeException('Google search request failed (HTTP '.$http.').');
 $d=json_decode($raw,true);if(!is_array($d))return [];
 return is_array($d['items']??null)?$d['items']:[];
}
function lc_fetch(string $url):array{
 $url=lc_norm_url($url);if($url==='')return ['ok'=>false,'text'=>'','title'=>''];
 $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>4,CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; BubbaHubListingCheck/1.0)']);$raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$final=(string)curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);curl_close($ch);
 if($raw===false||$http>=400)return ['ok'=>false,'http'=>$http,'text'=>'','title'=>'','url'=>$final?:$url];
 $title='';if(preg_match('/<title[^>]*>(.*?)<\/title>/is',$raw,$m))$title=trim(html_entity_decode(strip_tags($m[1]),ENT_QUOTES|ENT_HTML5,'UTF-8'));
 $text=preg_replace('/\s+/',' ',strip_tags($raw));$text=trim(html_entity_decode((string)$text,ENT_QUOTES|ENT_HTML5,'UTF-8'));
 $signals=[];
 foreach(['closed','no longer running','no longer trading','permanently closed','ceased trading','we have closed','we are closing','relocated','new venue','moved to','new timetable','returning soon'] as $term)if(mb_stripos($text,$term)!==false)$signals[]=$term;
 $social=[];if(preg_match_all('#https?://(?:www\.)?(?:facebook\.com|instagram\.com|tiktok\.com)/[^\"\'\s<>]+#i',$raw,$sm))$social=array_values(array_unique($sm[0]));
 return ['ok'=>true,'http'=>$http,'title'=>$title,'text'=>mb_substr($text,0,30000),'signals'=>$signals,'social'=>$social,'url'=>$final?:$url];
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
if($action==='results'){ $rows=lc_results($db);lc_out(200,['ok'=>true,'results'=>$rows,'summary'=>lc_summary($rows)]); }
if($action==='dismiss'){ $id=(int)($input['id']??0);$q=$db->prepare("UPDATE bh_listing_check_results SET status='dismissed',reviewed_at=NOW() WHERE id=?");$q->execute([$id]);lc_out(200,['ok'=>true]); }
if($action==='create_draft'){
 $id=(int)($input['id']??0);$q=$db->prepare("SELECT * FROM bh_listing_check_results WHERE id=?");$q->execute([$id]);$r=$q->fetch();if(!$r)lc_out(404,['ok'=>false,'error'=>'Listing Check result not found.']);if((int)($r['draft_activity_id']??0)>0)lc_out(200,['ok'=>true,'draft_activity_id'=>(int)$r['draft_activity_id']]);
 $org=$db->query("SELECT id FROM bh_organisers WHERE slug='listing-check-unassigned' LIMIT 1")->fetchColumn();
 if(!$org){$db->prepare("INSERT INTO bh_organisers (organisation_name,slug,description,status) VALUES (?,?,?, 'draft')")->execute(['Listing Check — Unassigned','listing-check-unassigned','Temporary owner for newly discovered listings. Assign a real organiser before publishing.']);$org=$db->lastInsertId();}
 $slug=trim((string)$r['title']);$slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$slug),'-'));if($slug==='')$slug='listing-check-'.$id;$base=$slug;$n=2;while(true){$x=$db->prepare("SELECT COUNT(*) FROM bh_activities WHERE slug=?");$x->execute([$slug]);if(!(int)$x->fetchColumn())break;$slug=$base.'-'.$n++;}
 $desc=trim((string)($r['website_evidence']??''));$ins=$db->prepare("INSERT INTO bh_activities (organiser_id,title,slug,description,county,status) VALUES (?,?,?,?,?,'draft')");
 $county=in_array($r['region'],['Plymouth','Torbay'],true)?$r['region']:(str_contains(strtolower((string)$r['region']),'cornwall')?'Cornwall':(str_contains(strtolower((string)$r['region']),'devon')?'Devon':null));
 $ins->execute([(int)$org,$r['title'],$slug,$desc?:null,$county]);$aid=(int)$db->lastInsertId();
 if($r['website']){$db->prepare("UPDATE bh_organisers SET website=? WHERE id=?")->execute([$r['website'],(int)$org]);}
 $db->prepare("UPDATE bh_listing_check_results SET draft_activity_id=?,updated_at=NOW() WHERE id=?")->execute([$aid,$id]);lc_out(200,['ok'=>true,'draft_activity_id'=>$aid]);
}
if($action!=='scan')lc_out(400,['ok'=>false,'error'=>'Unknown action.']);
$mode=(string)($input['mode']??'existing');$region=trim((string)($input['region']??''));$query=trim((string)($input['query']??''));$cfg=lc_config();
try{
 $items=[];
 if($mode==='existing'){
  $sql="SELECT a.id,a.title,a.slug,a.status,a.description,a.booking_url,a.county,COALESCE(v.town,'') town,COALESCE(v.region,'') region,COALESCE(o.website,'') website FROM bh_activities a LEFT JOIN bh_venues v ON v.id=(SELECT vv.id FROM bh_venues vv WHERE vv.activity_id=a.id ORDER BY vv.id LIMIT 1) LEFT JOIN bh_organisers o ON o.id=a.organiser_id WHERE a.status IN ('published','pending','draft')";
  $params=[];if($region){$sql.=" AND (v.region=? OR a.county=?)";$params=[$region,$region];}$sql.=" ORDER BY a.id";
  $st=$db->prepare($sql);$st->execute($params);$activities=$st->fetchAll();
  foreach($activities as $a){
   $title=trim((string)$a['title']);$website=trim((string)$a['website']);$q='"'.$title.'"';if($region)$q.=' '. $region;if($query)$q.=' '.$query;
   $search=lc_google($q,$cfg);$match=lc_match($search,$title,$website);$page=$website?lc_fetch($website):['ok'=>false,'signals'=>[],'text'=>'','title'=>''];
   $socialSearch=lc_google('"'.$title.'" Facebook Instagram '.($region?:''),$cfg);
   $sources=[];foreach(array_merge($search,$socialSearch) as $it){if(!empty($it['link']))$sources[]=['label'=>(string)($it['title']??'Source'),'url'=>(string)$it['link']];}
   $changes=$match?lc_changes($a,$match['item'],$page):[];$score=$match?(int)$match['score']:0;
   $missing=(!$match&&(!$page['ok']||$page['signals']));
   if($page['ok']&&!empty($page['signals']))$score=max($score,80);
   $stt=$changes?'changed':($missing?'missing':'same');$label=$stt==='changed'?'Changed':($stt==='missing'?'Possible missing':'No change');
   if($page['ok']&&$page['signals']){$changes[]= ['field'=>'Website evidence','before'=>'Current listing','after'=>implode(', ',$page['signals'])];$stt='changed';$label='Changed';}
   $websiteEvidence=$page['ok']?('Website reachable'.($page['title']?' · '.$page['title']:'').(!empty($page['signals'])?' · Notice: '.implode(', ',$page['signals']):' · No closure/change notice detected')):'Website could not be reached.';
   $socialEvidence=count($socialSearch)?'Google found '.count($socialSearch).' relevant social/search results.':'No relevant social/search result found.';
   $key=lc_key($title,$website);$existing=$db->prepare("SELECT id FROM bh_listing_check_results WHERE title=? AND website_key=? AND activity_id=? ORDER BY id DESC LIMIT 1");$existing->execute([$title,$key,$a['id']]);$rid=$existing->fetchColumn();
   $payload=[$a['id'],$title,$website,$key,$stt,$label,min(100,max(0,$score)),$a['town'],$a['region'],$websiteEvidence,$socialEvidence,json_encode($changes),json_encode(array_slice($sources,0,10)),$q];
   if($rid){$u=$db->prepare("UPDATE bh_listing_check_results SET activity_id=?,title=?,website=?,website_key=?,status=?,label=?,confidence=?,town=?,region=?,website_evidence=?,social_evidence=?,changes_json=?,sources_json=?,search_query=?,reviewed_at=NULL WHERE id=?");$u->execute([$a['id'],$title,$website,$key,$stt,$label,min(100,max(0,$score)),$a['town'],$a['region'],$websiteEvidence,$socialEvidence,json_encode($changes),json_encode(array_slice($sources,0,10)),$q,$rid]);}
   else{$u=$db->prepare("INSERT INTO bh_listing_check_results (activity_id,title,website,website_key,status,label,confidence,town,region,website_evidence,social_evidence,changes_json,sources_json,search_query) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");$u->execute($payload);}
  }
 }else{
  $scope=trim($query.' '.($region?:'Devon Cornwall'));if($scope==='')$scope='baby toddler classes Devon Cornwall';
  $search=lc_google($scope,$cfg);
  foreach($search as $it){
   $title=trim(strip_tags((string)($it['title']??'')));$url=lc_norm_url((string)($it['link']??''));if($title===''||$url==='')continue;
   if(preg_match('#(wikipedia|youtube\.com|google\.com|tripadvisor|yell\.com)#i',$url))continue;
   $key=lc_key($title,$url);$dup=$db->prepare("SELECT id FROM bh_listing_check_results WHERE website_key=? AND title=? LIMIT 1");$dup->execute([$key,$title]);if($dup->fetchColumn())continue;
   $page=lc_fetch($url);$title2=$page['title']?:$title;$websiteEvidence=$page['ok']?'Website reachable'.(!empty($page['signals'])?' · '.implode(', ',$page['signals']):''):'Website could not be reached.';
   $social=lc_google('"'.$title.'" Facebook Instagram', $cfg);$sources=[['label'=>$title,'url'=>$url]];foreach($social as $si)if(!empty($si['link']))$sources[]=['label'=>(string)($si['title']??'Social result'),'url'=>(string)$si['link']];
   $town=$region;$confidence=$page['ok']?88:65;$stt='new';$label='New opportunity';$ins=$db->prepare("INSERT INTO bh_listing_check_results (title,website,website_key,status,label,confidence,town,region,website_evidence,social_evidence,sources_json,search_query) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");$ins->execute([$title2,$url,$key,$stt,$label,$confidence,$town,$region,$websiteEvidence,count($social)?'Relevant social/search results found.':'No social result found.',json_encode($sources),$scope]);
  }
 }
 $rows=lc_results($db);lc_out(200,['ok'=>true,'results'=>$rows,'summary'=>lc_summary($rows)]);
}catch(Throwable $e){lc_out(500,['ok'=>false,'error'=>$e->getMessage()]);}
?>