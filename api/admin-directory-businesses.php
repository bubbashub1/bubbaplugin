<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID'); session_start();
function business_reply(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))business_reply(401,['ok'=>false,'error'=>'admin_login_required']);
require_once __DIR__.'/db.php'; $db=bh_mysql();
$exists=static function(string $table)use($db):bool{$q=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$q->execute([$table]);return (int)$q->fetchColumn()>0;};

if($_SERVER['REQUEST_METHOD']==='GET'){
 $_SESSION['bh_admin_business_csrf']??=bin2hex(random_bytes(32));$action=(string)($_GET['action']??'list');
 if(($action==='list'||$action==='get')&&(!$exists('bh_directory_businesses')||!$exists('bh_directory_business_listings')))business_reply(503,['ok'=>false,'error'=>'business_schema_not_installed','message'=>'Install the Businesses directory tables before loading storefront records.']);
 if($action==='organisers'){
  $s=$db->query("SELECT id,organisation_name,email,phone FROM bh_organisers ORDER BY organisation_name,id LIMIT 500");
  business_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_admin_business_csrf'],'organisers'=>$s->fetchAll(PDO::FETCH_ASSOC)]);
 }
 if($action==='list'){
  $q=trim((string)($_GET['q']??''));$sql="SELECT b.id,b.title,b.category,b.town,b.region,b.status,b.updated_at,o.organisation_name FROM bh_directory_businesses b LEFT JOIN bh_organisers o ON o.id=b.organiser_id";$args=[];
  if($q!==''){$sql.=" WHERE b.title LIKE ? OR b.category LIKE ? OR b.town LIKE ? OR o.organisation_name LIKE ?";$like='%'.$q.'%';$args=[$like,$like,$like,$like];}
  $sql.=" ORDER BY b.updated_at DESC,b.id DESC LIMIT 300";$s=$db->prepare($sql);$s->execute($args);business_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_admin_business_csrf'],'businesses'=>$s->fetchAll(PDO::FETCH_ASSOC)]);
 }
 if($action==='get'){
  $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);if(!$id||$id<1)business_reply(422,['ok'=>false,'error'=>'invalid_id']);
  $s=$db->prepare("SELECT b.*,o.organisation_name FROM bh_directory_businesses b LEFT JOIN bh_organisers o ON o.id=b.organiser_id WHERE b.id=?");$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)business_reply(404,['ok'=>false,'error'=>'business_not_found']);
  $s=$db->prepare("SELECT activity_id FROM bh_directory_business_listings WHERE business_id=? ORDER BY activity_id");$s->execute([$id]);$row['activity_ids']=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));business_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_admin_business_csrf'],'business'=>$row]);
 }
 business_reply(422,['ok'=>false,'error'=>'invalid_action']);
}
if($_SERVER['REQUEST_METHOD']!=='POST')business_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if(!$origin||parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)business_reply(403,['ok'=>false,'error'=>'invalid_origin']);
$raw=(string)file_get_contents('php://input');if(strlen($raw)>100000)business_reply(413,['ok'=>false,'error'=>'request_too_large']);

$b=json_decode($raw,true);if(!is_array($b)||empty($_SESSION['bh_admin_business_csrf'])||!is_string($b['csrf']??null)||!hash_equals($_SESSION['bh_admin_business_csrf'],$b['csrf']))business_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
if (($b['action']??'')==='install_business_directory_schema') {
 foreach (['bh_organisers','bh_users','bh_activities'] as $requiredTable) if (!$exists($requiredTable)) business_reply(503,['ok'=>false,'error'=>'business_prerequisite_missing','message'=>'Required Bubba Hub table '.$requiredTable.' is missing. No schema was installed.']);
 try {
  $db->exec("CREATE TABLE IF NOT EXISTS bh_directory_businesses (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
   organiser_id BIGINT UNSIGNED NULL,
   created_by_user_id BIGINT UNSIGNED NULL,
   title VARCHAR(190) NOT NULL,
   category VARCHAR(120) NOT NULL,
   summary VARCHAR(500) NULL,
   description MEDIUMTEXT NULL,
   town VARCHAR(120) NOT NULL,
   region VARCHAR(120) NULL,
   address VARCHAR(500) NULL,
   postcode VARCHAR(20) NULL,
   website_url VARCHAR(1000) NULL,
   contact_email VARCHAR(190) NULL,
   contact_phone VARCHAR(60) NULL,
   status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
   reviewed_at DATETIME NULL,
   reviewed_by_user_id BIGINT UNSIGNED NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   PRIMARY KEY (id),
   UNIQUE KEY uq_business_organiser (organiser_id),
   KEY idx_business_status (status),
   KEY idx_business_category (category),
   KEY idx_business_town (town),
   KEY idx_business_region (region),
   KEY idx_business_created_by (created_by_user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $db->exec("CREATE TABLE IF NOT EXISTS bh_directory_business_listings (
   business_id BIGINT UNSIGNED NOT NULL,
   activity_id BIGINT UNSIGNED NOT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY (business_id,activity_id),
   KEY idx_business_listing_activity (activity_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  if (!$exists('bh_directory_businesses') || !$exists('bh_directory_business_listings')) throw new RuntimeException('Business directory tables were not created.');
  business_reply(200,['ok'=>true,'installed'=>true,'message'=>'Businesses directory tables are installed. No existing activities or organiser records were changed.']);
 } catch(Throwable $e) {
  error_log('Businesses schema install: '.$e->getMessage());
  business_reply(500,['ok'=>false,'error'=>'business_schema_install_failed','message'=>'The Businesses schema could not be installed. No directory records were created.']);
 }
}
if (($b['action']??'')==='install_family_directory_schemas') {
 foreach (['bh_organisers','bh_users','bh_activities'] as $requiredTable) if (!$exists($requiredTable)) business_reply(503,['ok'=>false,'error'=>'directory_prerequisite_missing','message'=>'Required Bubba Hub table '.$requiredTable.' is missing. No directory schema was installed.']);
 try {
  $base="id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,title VARCHAR(190) NOT NULL,category VARCHAR(120) NOT NULL,summary VARCHAR(500) NULL,description MEDIUMTEXT NULL,town VARCHAR(120) NOT NULL,region VARCHAR(120) NULL,address VARCHAR(500) NULL,postcode VARCHAR(20) NULL,website_url VARCHAR(1000) NULL,contact_email VARCHAR(190) NULL,contact_phone VARCHAR(60) NULL,status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',reviewed_at DATETIME NULL,reviewed_by_user_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY idx_status(status),KEY idx_category(category),KEY idx_town(town),KEY idx_region(region)";
  $db->exec("CREATE TABLE IF NOT EXISTS bh_directory_childcare ($base,registration_body VARCHAR(190) NULL,registration_reference VARCHAR(190) NULL,verification_url VARCHAR(1000) NULL,verified_on DATE NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $db->exec("CREATE TABLE IF NOT EXISTS bh_directory_places ($base,facilities TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $db->exec("CREATE TABLE IF NOT EXISTS bh_directory_school_clubs ($base,school_name VARCHAR(190) NULL,age_min TINYINT UNSIGNED NULL,age_max TINYINT UNSIGNED NULL,days_times VARCHAR(500) NULL,collection_arrangements TEXT NULL,price_details VARCHAR(500) NULL,verification_url VARCHAR(1000) NULL,verified_on DATE NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  foreach (['bh_directory_childcare','bh_directory_places','bh_directory_school_clubs'] as $table) if (!$exists($table)) throw new RuntimeException($table.' was not created.');
  business_reply(200,['ok'=>true,'installed'=>true,'message'=>'Childcare & Schools, Places & Days Out and School Clubs tables are installed. Existing Activities, businesses and organiser records were not changed.']);
 } catch(Throwable $e) {
  error_log('Family directory schema install: '.$e->getMessage());
  business_reply(500,['ok'=>false,'error'=>'family_directory_schema_install_failed','message'=>'The three directory tables could not be installed. No existing directory records were changed.']);
 }
}
if (($b['action']??'')==='upgrade_family_directory_workflow') {
 $tables=['bh_directory_businesses','bh_directory_childcare','bh_directory_places','bh_directory_school_clubs'];
 foreach($tables as $table)if(!$exists($table))business_reply(503,['ok'=>false,'error'=>'directory_schema_missing','message'=>'Install all four Directory tables before enabling Leader submissions. No changes were made.']);
 $hasColumn=static function(string $table,string $column)use($db):bool{$q=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");$q->execute([$table,$column]);return (int)$q->fetchColumn()>0;};
 $hasIndex=static function(string $table,string $index)use($db):bool{$q=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?");$q->execute([$table,$index]);return (int)$q->fetchColumn()>0;};
 $addColumn=static function(string $table,string $column,string $definition)use($db,$hasColumn):void{if(!$hasColumn($table,$column))$db->exec("ALTER TABLE ".$table." ADD COLUMN ".$column." ".$definition);};
 try{
  $additions=[
   'bh_directory_businesses'=>['submitted_at'=>'DATETIME NULL','review_note'=>'VARCHAR(2000) NULL'],
   'bh_directory_childcare'=>['organiser_id'=>'BIGINT UNSIGNED NULL','created_by_user_id'=>'BIGINT UNSIGNED NULL','age_min'=>'SMALLINT UNSIGNED NULL','age_max'=>'SMALLINT UNSIGNED NULL','submitted_at'=>'DATETIME NULL','review_note'=>'VARCHAR(2000) NULL'],
   'bh_directory_places'=>['organiser_id'=>'BIGINT UNSIGNED NULL','created_by_user_id'=>'BIGINT UNSIGNED NULL','submitted_at'=>'DATETIME NULL','review_note'=>'VARCHAR(2000) NULL'],
   'bh_directory_school_clubs'=>['organiser_id'=>'BIGINT UNSIGNED NULL','created_by_user_id'=>'BIGINT UNSIGNED NULL','holiday_provision'=>'VARCHAR(500) NULL','registration_details'=>'MEDIUMTEXT NULL','submitted_at'=>'DATETIME NULL','review_note'=>'VARCHAR(2000) NULL']
  ];
  foreach($additions as $table=>$columns)foreach($columns as $column=>$definition)$addColumn($table,$column,$definition);
  foreach($tables as $table)$db->exec("ALTER TABLE ".$table." MODIFY COLUMN status ENUM('draft','pending','published','rejected','archived') NOT NULL DEFAULT 'draft'");
  foreach(['bh_directory_childcare'=>'idx_childcare_owner_status','bh_directory_places'=>'idx_places_owner_status','bh_directory_school_clubs'=>'idx_school_clubs_owner_status'] as $table=>$index)if(!$hasIndex($table,$index))$db->exec("ALTER TABLE ".$table." ADD INDEX ".$index." (organiser_id,status)");
  $db->exec("CREATE TABLE IF NOT EXISTS bh_directory_record_reviews (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
   section_key ENUM('event','business','childcare','place','school_club') NOT NULL,
   record_id BIGINT UNSIGNED NOT NULL,reviewer_user_id BIGINT UNSIGNED NULL,
   decision ENUM('approved','rejected','returned_for_edits','revision_approved','revision_rejected','revision_returned_for_edits') NOT NULL,
   note VARCHAR(2000) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   KEY idx_directory_record_review (section_key,record_id,created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $db->exec("CREATE TABLE IF NOT EXISTS bh_directory_record_revisions (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
   section_key ENUM('event','business','childcare','place','school_club') NOT NULL,
   record_id BIGINT UNSIGNED NOT NULL,organiser_id BIGINT UNSIGNED NOT NULL,
   submitted_by_user_id BIGINT UNSIGNED NOT NULL,payload MEDIUMTEXT NOT NULL,
   status ENUM('pending','approved','rejected','returned_for_edits') NOT NULL DEFAULT 'pending',
   submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,reviewed_at DATETIME NULL,
   reviewed_by_user_id BIGINT UNSIGNED NULL,review_note VARCHAR(2000) NULL,
   KEY idx_directory_revision_queue (status,submitted_at),
   KEY idx_directory_revision_record (section_key,record_id,status),
   KEY idx_directory_revision_owner (organiser_id,record_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  if(!$exists('bh_directory_record_reviews')||!$exists('bh_directory_record_revisions'))throw new RuntimeException('Directory review tables were not created.');
  business_reply(200,['ok'=>true,'upgraded'=>true,'message'=>'Leader submissions and Directory review fields are ready. Existing directory records and publication statuses were preserved.']);
 }catch(Throwable $e){error_log('Directory workflow schema upgrade: '.$e->getMessage());business_reply(500,['ok'=>false,'error'=>'directory_workflow_upgrade_failed','message'=>'The Directory workflow upgrade could not be completed. Check the staging database before retrying.']);}
}
if(!$exists('bh_directory_businesses')||!$exists('bh_directory_business_listings'))business_reply(503,['ok'=>false,'error'=>'business_schema_not_installed','message'=>'The Businesses directory schema has not been installed on staging. No records have been changed.']);
if (($b['action']??'')==='create_missing_leader_storefronts') {
 require_once __DIR__.'/lib/business-storefront.php'; $db->beginTransaction();
 try {
  $orgCols=$db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers'")->fetchAll(PDO::FETCH_COLUMN);
  $ownerJoin=in_array('user_id',$orgCols,true) ? "u.id=o.user_id" : "1=0";
  if(in_array('email',$orgCols,true)) $ownerJoin=in_array('user_id',$orgCols,true) ? "($ownerJoin OR (o.user_id IS NULL AND LOWER(u.email)=LOWER(o.email)))" : "LOWER(u.email)=LOWER(o.email)";
  $leaders=$db->query("SELECT o.id AS organiser_id,o.organisation_name,u.id AS user_id FROM bh_organisers o INNER JOIN bh_users u ON $ownerJoin WHERE u.role='leader' AND u.status='active' ORDER BY o.id");
  $created=0; $existing=0; $skipped=0;
  foreach ($leaders->fetchAll(PDO::FETCH_ASSOC) as $leader) {
   $before=$db->prepare("SELECT id FROM bh_directory_businesses WHERE organiser_id=? LIMIT 1"); $before->execute([(int)$leader['organiser_id']]);
   if ($before->fetchColumn()) { $existing++; continue; }
   $id=bh_ensure_business_storefront($db,(int)$leader['organiser_id'],(int)$leader['user_id'],(string)$leader['organisation_name']);
   if ($id) $created++; else $skipped++;
  }
  $db->commit(); business_reply(200,['ok'=>true,'created'=>$created,'already_had_storefront'=>$existing,'skipped'=>$skipped,'message'=>'Missing leader storefront drafts have been created. Nothing was published.']);
 } catch(Throwable $e) {
  if ($db->inTransaction()) $db->rollBack(); error_log('Admin storefront backfill: '.$e->getMessage());
  business_reply(500,['ok'=>false,'error'=>'storefront_backfill_failed','message'=>'No storefronts were changed because the backfill could not be completed.']);
 }
}
$title=trim((string)($b['title']??''));$category=trim((string)($b['category']??''));$town=trim((string)($b['town']??''));$description=trim((string)($b['description']??''));$summary=trim((string)($b['summary']??''));
if($title===''||mb_strlen($title)>190||$category===''||mb_strlen($category)>120||$town===''||mb_strlen($town)>120||mb_strlen($summary)>500||mb_strlen($description)>100000)business_reply(422,['ok'=>false,'error'=>'required_fields_invalid','message'=>'Enter a business name, category and town. Check field lengths.']);
$fields=['region'=>120,'address'=>500,'postcode'=>20,'website_url'=>1000,'contact_email'=>190,'contact_phone'=>60];$values=[];
foreach($fields as $key=>$max){$v=trim((string)($b[$key]??''));if(mb_strlen($v)>$max)business_reply(422,['ok'=>false,'error'=>'field_too_long','message'=>'The '.$key.' field is too long.']);$values[$key]=$v?:null;}
if($values['website_url']!==null&&(!filter_var($values['website_url'],FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($values['website_url'],PHP_URL_SCHEME)),['http','https'],true)))business_reply(422,['ok'=>false,'error'=>'invalid_website']);
if($values['contact_email']!==null&&!filter_var($values['contact_email'],FILTER_VALIDATE_EMAIL))business_reply(422,['ok'=>false,'error'=>'invalid_email']);
$organiserId=isset($b['organiser_id'])&&$b['organiser_id']!==''?(int)$b['organiser_id']:null;
if($organiserId!==null){$s=$db->prepare("SELECT id FROM bh_organisers WHERE id=?");$s->execute([$organiserId]);if(!$s->fetchColumn())business_reply(422,['ok'=>false,'error'=>'organiser_not_found']);}
$activityIds=$b['activity_ids']??[];if(!is_array($activityIds)||count($activityIds)>100)business_reply(422,['ok'=>false,'error'=>'invalid_activity_links']);
$activityIds=array_values(array_unique(array_filter(array_map('intval',$activityIds),static fn($v)=>$v>0)));
if($activityIds){$marks=implode(',',array_fill(0,count($activityIds),'?'));$s=$db->prepare("SELECT id FROM bh_activities WHERE id IN ($marks)");$s->execute($activityIds);if(count($s->fetchAll(PDO::FETCH_COLUMN))!==count($activityIds))business_reply(422,['ok'=>false,'error'=>'activity_link_not_found']);}
$id=isset($b['id'])?(int)$b['id']:0;$publish=($b['publish']??false)===true;
try{
 $db->beginTransaction();
 if($id>0){$s=$db->prepare("SELECT id FROM bh_directory_businesses WHERE id=? FOR UPDATE");$s->execute([$id]);if(!$s->fetchColumn()){$db->rollBack();business_reply(404,['ok'=>false,'error'=>'business_not_found']);}
  $sql="UPDATE bh_directory_businesses SET organiser_id=?,title=?,category=?,summary=?,description=?,town=?,region=?,address=?,postcode=?,website_url=?,contact_email=?,contact_phone=?,status=?,reviewed_at=?,reviewed_by_user_id=? WHERE id=?";
  $db->prepare($sql)->execute([$organiserId,$title,$category,$summary?:null,$description?:null,$town,$values['region'],$values['address'],$values['postcode'],$values['website_url'],$values['contact_email'],$values['contact_phone'],$publish?'published':'draft',$publish?date('Y-m-d H:i:s'):null,$publish?((int)($_SESSION['bh_user_id']??0)?:null):null,$id]);
 }else{
  $sql="INSERT INTO bh_directory_businesses (organiser_id,created_by_user_id,title,category,summary,description,town,region,address,postcode,website_url,contact_email,contact_phone,status,reviewed_at,reviewed_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $db->prepare($sql)->execute([$organiserId,(int)($_SESSION['bh_user_id']??0)?:null,$title,$category,$summary?:null,$description?:null,$town,$values['region'],$values['address'],$values['postcode'],$values['website_url'],$values['contact_email'],$values['contact_phone'],$publish?'published':'draft',$publish?date('Y-m-d H:i:s'):null,$publish?((int)($_SESSION['bh_user_id']??0)?:null):null]);$id=(int)$db->lastInsertId();
 }
 $db->prepare("DELETE FROM bh_directory_business_listings WHERE business_id=?")->execute([$id]);
 if($activityIds){$s=$db->prepare("INSERT INTO bh_directory_business_listings (business_id,activity_id) VALUES (?,?)");foreach($activityIds as $activityId)$s->execute([$id,$activityId]);}
 $db->commit();business_reply(200,['ok'=>true,'id'=>$id,'status'=>$publish?'published':'draft','message'=>$publish?'Business saved and published.':'Business saved as a draft.']);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Admin business manager: '.$e->getMessage());business_reply(500,['ok'=>false,'error'=>'business_save_failed']);}
