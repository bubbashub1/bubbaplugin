<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_start();
function bh_lha_reply(int $status,array $data):never{
 http_response_code($status);
 echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
 exit;
}
if(empty($_SESSION['bh_admin_authenticated'])) bh_lha_reply(401,['ok'=>false,'error'=>'Admin login required.']);
try{
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 $_SESSION['bh_listing_health_approval_csrf']??=bin2hex(random_bytes(32));
 $db->exec("CREATE TABLE IF NOT EXISTS bh_listing_health_approvals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  activity_id BIGINT UNSIGNED NOT NULL,
  activity_title VARCHAR(190) NULL,
  field_name VARCHAR(64) NOT NULL,
  original_value MEDIUMTEXT NULL,
  proposed_value MEDIUMTEXT NOT NULL,
  source_url VARCHAR(2048) NOT NULL,
  evidence TEXT NULL,
  source_platform VARCHAR(40) NULL,
  confidence TINYINT UNSIGNED NULL,
  status ENUM('pending','approved','rejected','stale') NOT NULL DEFAULT 'pending',
  imported_by VARCHAR(80) NOT NULL DEFAULT 'admin',
  reviewed_by VARCHAR(80) NULL,
  reviewed_at DATETIME NULL,
  applied_value MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_lha_status_created(status,created_at),
  INDEX idx_lha_activity(activity_id),
  INDEX idx_lha_source(source_url(191))
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $activityCols=$db->query('SHOW COLUMNS FROM bh_activities')->fetchAll(PDO::FETCH_COLUMN);
 $allowed=[
  'title'=>190,'description'=>10000,'short_description'=>2000,'website'=>2048,
  'booking_url'=>2048,'contact_email'=>190,'contact_phone'=>60,'facebook_url'=>2048,
  'instagram_url'=>2048,'age_range'=>120,'category'=>255,'town'=>120,'region'=>120,'postcode'=>20
 ];
 $actor=static function():string{
  foreach(['bh_admin_username','bh_admin_email','admin_username','admin_email'] as $key){
   if(!empty($_SESSION[$key])&&is_scalar($_SESSION[$key])) return mb_substr((string)$_SESSION[$key],0,80);
  }
  return 'authenticated-admin';
 };
 $validUrl=static function(string $url):bool{
  if($url===''||mb_strlen($url)>2048||!filter_var($url,FILTER_VALIDATE_URL)) return false;
  $parts=parse_url($url);
  if(!is_array($parts))return false;
  $scheme=strtolower((string)($parts['scheme']??''));
  if(!in_array($scheme,['http','https'],true)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))return false;
  $port=(int)($parts['port']??($scheme==='https'?443:80));
  return $port===($scheme==='https'?443:80);
 };
 if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
  $q=$db->query("SELECT id,activity_id,activity_title,field_name,original_value,proposed_value,source_url,evidence,source_platform,confidence,status,created_at FROM bh_listing_health_approvals WHERE status='pending' ORDER BY created_at DESC,id DESC LIMIT 500");
  bh_lha_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_listing_health_approval_csrf'],'data'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
 }
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST') bh_lha_reply(405,['ok'=>false,'error'=>'GET or POST required.']);
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');$host=(string)($_SERVER['HTTP_HOST']??'');
 if($origin!==''&&(!filter_var($origin,FILTER_VALIDATE_URL)||strcasecmp((string)parse_url($origin,PHP_URL_HOST),preg_replace('/:\\d+$/','',$host))!==0)) bh_lha_reply(403,['ok'=>false,'error'=>'Cross-origin request blocked.']);
 if((string)($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site') bh_lha_reply(403,['ok'=>false,'error'=>'Cross-site request blocked.']);
 if((int)($_SERVER['CONTENT_LENGTH']??0)>262144) bh_lha_reply(413,['ok'=>false,'error'=>'Request too large.']);
 $input=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($input)) bh_lha_reply(400,['ok'=>false,'error'=>'Invalid JSON request.']);
 if(!hash_equals($_SESSION['bh_listing_health_approval_csrf'],(string)($input['csrf']??''))) bh_lha_reply(403,['ok'=>false,'error'=>'Security token expired. Refresh the page and try again.']);
 $action=(string)($input['action']??'');
 if($action==='import'){
  $findings=$input['findings']??null;
  if(!is_array($findings)||count($findings)>100) bh_lha_reply(422,['ok'=>false,'error'=>'Import must contain a findings array of no more than 100 items.']);
  $inserted=0;$skipped=0;$errors=[];
  $db->beginTransaction();
  $add=$db->prepare("INSERT INTO bh_listing_health_approvals (activity_id,activity_title,field_name,original_value,proposed_value,source_url,evidence,source_platform,confidence,imported_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
  foreach($findings as $index=>$finding){
   if(!is_array($finding)){$skipped++;continue;}
   $id=filter_var($finding['activity_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
   $field=(string)($finding['field']??'description');
   $value=$finding['proposed_value']??$finding['suggested_value']??($field==='description'?($finding['suggested_description']??null):null);
   $url=trim((string)($finding['source_url']??$finding['url']??''));
   if(is_string($value))$value=trim(strip_tags($value));
   if(!$id||!isset($allowed[$field])||!in_array($field,$activityCols,true)||!is_string($value)||trim($value)===''||mb_strlen($value)>$allowed[$field]||!$validUrl($url)){
    $skipped++;$errors[]='Finding '.($index+1).': needs a valid listing ID, supported field, proposed value and HTTP(S) source URL.';continue;
   }
   $titleSelect=in_array('title',$activityCols,true)?'title':'NULL AS title';
   $readSql='SELECT '.$titleSelect.',`'.$field.'` AS current_value FROM bh_activities WHERE id=?';
   $read=$db->prepare($readSql);$read->execute([$id]);$current=$read->fetch(PDO::FETCH_ASSOC);
   if(!$current){$skipped++;$errors[]='Finding '.($index+1).': listing #'.$id.' was not found.';continue;}
   $proposed=trim($value);
   if(in_array($field,['website','booking_url','facebook_url','instagram_url'],true)&&$proposed!==''&&!$validUrl($proposed)){
    $skipped++;$errors[]='Finding '.($index+1).': proposed web address is invalid.';continue;
   }
   if($field==='contact_email'&&$proposed!==''&&!filter_var($proposed,FILTER_VALIDATE_EMAIL)){
    $skipped++;$errors[]='Finding '.($index+1).': proposed email address is invalid.';continue;
   }
   $duplicate=$db->prepare("SELECT id FROM bh_listing_health_approvals WHERE activity_id=? AND field_name=? AND proposed_value=? AND source_url=? AND status='pending' LIMIT 1");
   $duplicate->execute([(int)$id,$field,$proposed,$url]);
   if($duplicate->fetchColumn()){$skipped++;continue;}
   $confidence=null;
   if(isset($finding['confidence'])){
    $rawConfidence=$finding['confidence'];
    $confidence=is_numeric($rawConfidence)?max(0,min(100,(int)$rawConfidence)):(['low'=>35,'medium'=>65,'high'=>85][strtolower((string)$rawConfidence)]??null);
   }
   $evidence=mb_substr(trim((string)($finding['evidence']??$finding['reason']??'')),0,5000);
   $platform=mb_substr(trim((string)($finding['source_platform']??'')),0,40);
   $add->execute([(int)$id,(string)$current['title'],$field,(string)($current['current_value']??''),$proposed,$url,$evidence?:null,$platform?:null,$confidence,$actor()]);
   $inserted++;
  }
  $db->commit();
  bh_lha_reply(200,['ok'=>true,'inserted'=>$inserted,'skipped'=>$skipped,'errors'=>array_slice($errors,0,20),'message'=>$inserted.' finding(s) added for approval. No listing has been changed.']);
 }
 if($action==='fetch_source'){
  $url=trim((string)($input['url']??''));
  if(!$validUrl($url)) bh_lha_reply(422,['ok'=>false,'error'=>'Enter a valid public HTTP or HTTPS page URL.']);
  $parts=parse_url($url);$host=strtolower((string)($parts['host']??''));$scheme=strtolower((string)($parts['scheme']??''));
  $port=(int)($parts['port']??($scheme==='https'?443:80));
  if($port!==($scheme==='https'?443:80)||isset($parts['user'])||isset($parts['pass'])||($port!==80&&$port!==443)||filter_var($host,FILTER_VALIDATE_IP)||in_array($host,['localhost'],true)||str_ends_with($host,'.local')||str_ends_with($host,'.internal')){
   bh_lha_reply(422,['ok'=>false,'error'=>'This address is not an allowed public website URL.']);
  }
  if(!function_exists('curl_init')||!function_exists('gethostbynamel')) bh_lha_reply(503,['ok'=>false,'error'=>'Public webpage preview is not available on this server.']);
  $ips=gethostbynamel($host);
  if(!$ips) bh_lha_reply(422,['ok'=>false,'error'=>'The website host could not be resolved to a public address.']);
  foreach($ips as $ip){
   if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)){
    bh_lha_reply(422,['ok'=>false,'error'=>'The website host resolves to a non-public address and cannot be fetched.']);
   }
  }
  $ip=$ips[0];$buffer='';
  $ch=curl_init($url);
  $options=[
   CURLOPT_RESOLVE=>[$host.':'.$port.':'.$ip],
   CURLOPT_RETURNTRANSFER=>false,
   CURLOPT_FOLLOWLOCATION=>false,
   CURLOPT_CONNECTTIMEOUT=>5,
   CURLOPT_TIMEOUT=>12,
   CURLOPT_MAXFILESIZE=>1048576,
   CURLOPT_USERAGENT=>'BubbaHub-ListingHealth-SourcePreview/1.0 (+https://bubbahub.co.uk)',
   CURLOPT_SSL_VERIFYPEER=>true,
   CURLOPT_SSL_VERIFYHOST=>2,
   CURLOPT_PROTOCOLS=>$scheme==='https'?CURLPROTO_HTTPS:CURLPROTO_HTTP,
   CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk) use (&$buffer):int{
    if(strlen($buffer)+strlen($chunk)>1048576)return 0;
    $buffer.=$chunk;return strlen($chunk);
   }
  ];
  curl_setopt_array($ch,$options);
  $ok=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$contentType=strtolower((string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE));curl_close($ch);
  if($ok!==true||$http<200||$http>=300||$buffer==='') bh_lha_reply(422,['ok'=>false,'error'=>'The page could not be fetched successfully. Redirects are not followed; enter the final public page URL.']);
  if($contentType!==''&&!str_contains($contentType,'text/html')&&!str_contains($contentType,'application/xhtml+xml')&&!str_contains($contentType,'text/plain')){
   bh_lha_reply(415,['ok'=>false,'error'=>'Only public HTML or plain-text pages can be previewed.']);
  }
  $title='';
  if(preg_match('/<title\\b[^>]*>(.*?)<\\/title>/is',$buffer,$match))$title=trim(html_entity_decode(strip_tags($match[1]),ENT_QUOTES|ENT_HTML5,'UTF-8'));
  $text=preg_replace('/<\\s*(script|style|noscript|svg)\\b[^>]*>.*?<\\/\\s*\\1\\s*>/is',' ',$buffer)??$buffer;
  $text=preg_replace('/<!--.*?-->/s',' ',$text)??$text;
  $excerpt=trim(html_entity_decode(preg_replace('/\\s+/u',' ',strip_tags($text))??strip_tags($text),ENT_QUOTES|ENT_HTML5,'UTF-8'));
  bh_lha_reply(200,['ok'=>true,'url'=>$url,'title'=>mb_substr($title,0,500),'excerpt'=>mb_substr($excerpt,0,4000),'http_status'=>$http,'message'=>'Page preview loaded. It has not changed a listing.']);
 }
 if($action==='manual_import'){
  $activityId=filter_var($input['activity_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  $field=(string)($input['field']??'');$value=$input['proposed_value']??null;if(is_string($value))$value=trim(strip_tags($value));$url=trim((string)($input['source_url']??''));$evidence=mb_substr(trim((string)($input['evidence']??'')),0,5000);
  if(!$activityId||!isset($allowed[$field])||!in_array($field,$activityCols,true)||!is_string($value)||trim($value)===''||mb_strlen($value)>$allowed[$field]||!$validUrl($url)){
   bh_lha_reply(422,['ok'=>false,'error'=>'Choose a valid listing and supported field, enter a proposed value and provide its public source URL.']);
  }
  $value=trim($value);
  if(in_array($field,['website','booking_url','facebook_url','instagram_url'],true)&&!$validUrl($value))bh_lha_reply(422,['ok'=>false,'error'=>'Enter a valid HTTP or HTTPS value for this field.']);
  if($field==='contact_email'&&!filter_var($value,FILTER_VALIDATE_EMAIL))bh_lha_reply(422,['ok'=>false,'error'=>'Enter a valid email address.']);
  $titleSelect=in_array('title',$activityCols,true)?'title':'NULL AS title';
  $q=$db->prepare('SELECT '.$titleSelect.',`'.$field.'` AS current_value FROM bh_activities WHERE id=?');$q->execute([(int)$activityId]);$current=$q->fetch(PDO::FETCH_ASSOC);
  if(!$current)bh_lha_reply(404,['ok'=>false,'error'=>'The selected listing was not found.']);
  $duplicate=$db->prepare("SELECT id FROM bh_listing_health_approvals WHERE activity_id=? AND field_name=? AND proposed_value=? AND source_url=? AND status='pending' LIMIT 1");
  $duplicate->execute([(int)$activityId,$field,$value,$url]);
  if($duplicate->fetchColumn())bh_lha_reply(409,['ok'=>false,'error'=>'This exact suggestion is already waiting for approval.']);
  $add=$db->prepare("INSERT INTO bh_listing_health_approvals (activity_id,activity_title,field_name,original_value,proposed_value,source_url,evidence,source_platform,confidence,imported_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
  $add->execute([(int)$activityId,(string)($current['title']??''),$field,(string)($current['current_value']??''),$value,$url,$evidence?:null,'manual',null,$actor()]);
  bh_lha_reply(200,['ok'=>true,'id'=>(int)$db->lastInsertId(),'message'=>'Manual source suggestion added to the approval queue. No listing has been changed.']);
 }
 $id=filter_var($input['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if(!$id) bh_lha_reply(422,['ok'=>false,'error'=>'A valid review item ID is required.']);
 if($action==='reject'){
  $q=$db->prepare("UPDATE bh_listing_health_approvals SET status='rejected',reviewed_by=?,reviewed_at=NOW() WHERE id=? AND status='pending'");
  $q->execute([$actor(),$id]);
  bh_lha_reply($q->rowCount()?200:409,['ok'=>(bool)$q->rowCount(),'error'=>$q->rowCount()?null:'This item is no longer pending review.']);
 }
 if($action==='edit'){
  $value=$input['proposed_value']??null;if(is_string($value))$value=trim(strip_tags($value));
  $q=$db->prepare("SELECT field_name FROM bh_listing_health_approvals WHERE id=? AND status='pending'");
  $q->execute([$id]);$field=$q->fetchColumn();
  if(!$field||!isset($allowed[$field])||!is_string($value)||trim($value)===''||mb_strlen($value)>$allowed[$field]) bh_lha_reply(422,['ok'=>false,'error'=>'The revised value is empty, too long or no longer editable.']);
  $value=trim($value);
  if(in_array($field,['website','booking_url','facebook_url','instagram_url'],true)&&!$validUrl($value)) bh_lha_reply(422,['ok'=>false,'error'=>'Enter a valid HTTP or HTTPS address.']);
  if($field==='contact_email'&&!filter_var($value,FILTER_VALIDATE_EMAIL)) bh_lha_reply(422,['ok'=>false,'error'=>'Enter a valid email address.']);
  $q=$db->prepare("UPDATE bh_listing_health_approvals SET proposed_value=? WHERE id=? AND status='pending'");
  $q->execute([$value,$id]);
  bh_lha_reply(200,['ok'=>true,'message'=>'Suggested value updated. It still needs your approval.']);
 }
 if($action==='approve'){
  $db->beginTransaction();
  $q=$db->prepare("SELECT * FROM bh_listing_health_approvals WHERE id=? AND status='pending' FOR UPDATE");
  $q->execute([$id]);$item=$q->fetch(PDO::FETCH_ASSOC);
  if(!$item){$db->rollBack();bh_lha_reply(409,['ok'=>false,'error'=>'This item is no longer pending review.']);}
  $field=(string)$item['field_name'];
  if(!isset($allowed[$field])||!in_array($field,$activityCols,true)){ $db->rollBack();bh_lha_reply(422,['ok'=>false,'error'=>'This field is not supported by the current activity schema.']);}
  $q=$db->prepare('SELECT `'.$field.'` FROM bh_activities WHERE id=? FOR UPDATE');$q->execute([(int)$item['activity_id']]);$current=$q->fetchColumn();
  if($current===false){$db->rollBack();bh_lha_reply(404,['ok'=>false,'error'=>'The listing no longer exists.']);}
  if((string)($current??'')!==(string)($item['original_value']??'')){
   $db->prepare("UPDATE bh_listing_health_approvals SET status='stale',reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$actor(),$id]);
   $db->commit();
   bh_lha_reply(409,['ok'=>false,'stale'=>true,'error'=>'The live listing changed after this finding was imported. It has been marked stale; refresh the listing and review a new suggestion.']);
  }
  $value=trim(strip_tags((string)$item['proposed_value']));
  if(mb_strlen($value)>$allowed[$field]){ $db->rollBack();bh_lha_reply(422,['ok'=>false,'error'=>'The proposed value is too long.']);}
  if(in_array($field,['website','booking_url','facebook_url','instagram_url'],true)&&$value!==''&&!$validUrl($value)){ $db->rollBack();bh_lha_reply(422,['ok'=>false,'error'=>'The proposed web address is invalid.']);}
  if($field==='contact_email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL)){ $db->rollBack();bh_lha_reply(422,['ok'=>false,'error'=>'The proposed email address is invalid.']);}
  $db->prepare('UPDATE bh_activities SET `'.$field.'`=? WHERE id=?')->execute([$value,(int)$item['activity_id']]);
  $db->prepare("UPDATE bh_listing_health_approvals SET status='approved',reviewed_by=?,reviewed_at=NOW(),applied_value=? WHERE id=?")->execute([$actor(),$value,$id]);
  $db->commit();
  bh_lha_reply(200,['ok'=>true,'message'=>'Approved change saved to listing #'.(int)$item['activity_id'].'.']);
 }
 bh_lha_reply(400,['ok'=>false,'error'=>'Unknown review action.']);
}catch(Throwable $e){
 if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
 error_log('Listing Health approval queue: '.$e->getMessage());
 bh_lha_reply(500,['ok'=>false,'error'=>'The approval request could not be completed. No partial listing changes were saved.']);
}
