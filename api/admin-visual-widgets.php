<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__.'/admin-session-guard.php';
require_once __DIR__.'/db.php';
function bh_wl_out(int $status,array $data):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
if(empty($_SESSION['bh_widget_library_csrf']))$_SESSION['bh_widget_library_csrf']=bin2hex(random_bytes(32));
$csrf=(string)$_SESSION['bh_widget_library_csrf'];$method=$_SERVER['REQUEST_METHOD']??'GET';$body=[];
if($method==='POST'){$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))bh_wl_out(400,['ok'=>false,'error'=>'Invalid request body.']);if(!hash_equals($csrf,(string)($body['csrf']??'')))bh_wl_out(403,['ok'=>false,'error'=>'Security check failed. Refresh and try again.']);}
elseif($method!=='GET')bh_wl_out(405,['ok'=>false,'error'=>'Method not allowed.']);
try{
$db=bh_mysql();
$db->exec("CREATE TABLE IF NOT EXISTS bh_visual_widget_library(
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 widget_key VARCHAR(80) NOT NULL UNIQUE,
 widget_type VARCHAR(60) NOT NULL,
 name VARCHAR(120) NOT NULL,
 category VARCHAR(40) NOT NULL,
 description VARCHAR(500) NOT NULL DEFAULT '',
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 settings_json TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_bh_widget_enabled_category(enabled,category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$presets=[
['rich-text','rich_text','Rich text editor','Content','Formatted text with headings, links and lists.',1,['label'=>'Text','placeholder'=>'','help_text'=>'','required'=>false,'options'=>[]]],
['divider','divider','Divider','Content','A visual separator between sections.',1,['label'=>'','placeholder'=>'','help_text'=>'','required'=>false,'options'=>[]]],
['spacer','spacer','Spacer','Content','Adjustable blank space.',1,['height'=>24]],
['icon','icon','Icon','Content','An icon with optional label.',1,['label'=>'','icon_name'=>'star']],
['image-caption','image_caption','Image with caption','Content','An image with accessible alternative text and caption.',1,['label'=>'Image','caption'=>'','alt'=>'']],
['date-picker','date_picker','Date picker','Forms','A date input with optional date limits.',1,['label'=>'Choose a date','placeholder'=>'','help_text'=>'','required'=>false,'min_date'=>'','max_date'=>'']],
['text-field','text_field','Text field','Forms','A single-line text input.',1,['label'=>'Your answer','placeholder'=>'','help_text'=>'','required'=>false]],
['email-field','email_field','Email field','Forms','An email address input.',1,['label'=>'Email address','placeholder'=>'','help_text'=>'','required'=>false]],
['telephone-field','telephone_field','Telephone field','Forms','A telephone number input.',1,['label'=>'Telephone number','placeholder'=>'','help_text'=>'','required'=>false]],
['dropdown','dropdown','Dropdown','Forms','Choose one option from a list.',1,['label'=>'Please choose','placeholder'=>'Select an option','help_text'=>'','required'=>false,'options'=>[]]],
['checkbox','checkbox','Checkbox','Forms','A tick box for an agreement or choice.',1,['label'=>'I agree','help_text'=>'','required'=>false]],
['radio','radio','Radio buttons','Forms','Choose one option from a short list.',1,['label'=>'Choose one','help_text'=>'','required'=>false,'options'=>[]]],
['submit','submit','Submit button','Forms','A form submission control (requires form handling).',1,['label'=>'Submit','help_text'=>'']],
['accordion','accordion','Accordion / FAQs','Interactive','Expandable questions and answers.',1,['label'=>'Frequently asked questions','items'=>[]]],
['tabs','tabs','Tabs','Interactive','Tabbed content panels.',1,['label'=>'Information','items'=>[]]],
['notice','notice','Notice / callout','Interactive','A highlighted message or important note.',1,['label'=>'Important information','tone'=>'info']],
['columns','columns','Columns','Layout','A responsive multi-column layout.',1,['columns'=>2,'gap'=>16]],
['feature-box','feature_box','Feature box','Layout','A feature with icon, heading and text.',1,['label'=>'Feature heading','icon_name'=>'star']],
['reusable-section','reusable_section','Reusable section','Layout','A saved section that can be reused on pages.',1,['label'=>'Reusable section']],
['directory-search','directory_search','Search widget','Content','A search bar for finding activities, businesses and other directory listings.',1,['label'=>'Search Bubba Hub','placeholder'=>'What are you looking for?','help_text'=>'','search_scope'=>'all','show_filters'=>true]]
];
$ins=$db->prepare("INSERT IGNORE INTO bh_visual_widget_library(widget_key,widget_type,name,category,description,enabled,settings_json) VALUES(?,?,?,?,?,?,?)");foreach($presets as $w)$ins->execute([$w[0],$w[1],$w[2],$w[3],$w[4],$w[5],json_encode($w[6],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
$directoryTables = [
 'activities'=>['label'=>'Activities / Classes','table'=>'bh_activities','date_table'=>null],
 'businesses'=>['label'=>'Businesses','table'=>'bh_directory_businesses','date_table'=>null],
 'childcare'=>['label'=>'Childcare','table'=>'bh_directory_childcare','date_table'=>null],
 'events'=>['label'=>'Events','table'=>'bh_directory_events','date_table'=>'bh_directory_event_occurrences'],
 'places'=>['label'=>'Places','table'=>'bh_directory_places','date_table'=>null],
 'school_clubs'=>['label'=>'School Clubs','table'=>'bh_directory_school_clubs','date_table'=>null],
];
$directorySchema=[];
foreach($directoryTables as $key=>$config){
 $columns=[];
 $columnQ=$db->query("SHOW COLUMNS FROM `".$config['table']."`");
 foreach($columnQ->fetchAll(PDO::FETCH_ASSOC) as $column){
  $field=(string)$column['Field'];$type=strtolower((string)$column['Type']);
  if(in_array($field,['id','organiser_id','leader_id','created_by_user_id','reviewed_by_user_id','review_note','submitted_at','reviewed_at','created_at','updated_at','status','slug','facebook_url','instagram_url','contact_email','contact_phone','website','website_url','booking_url','verification_url'],true))continue;
  $textual=(bool)preg_match('/^(varchar|char|text|mediumtext|longtext|tinytext)/',$type);
  $numeric=(bool)preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double)/',$type);
  $columns[]=['field'=>$field,'type'=>$type,'searchable'=>$textual,'filterable'=>in_array($field,['category','town','region','age_min','age_max','age_min_months','age_max_months','price','price_from','price_type','term_time_only','school_name','venue_name'],true)];
 }
 $directorySchema[$key]=['label'=>$config['label'],'table'=>$config['table'],'columns'=>$columns,'date_table'=>$config['date_table']];
}
if($directorySchema['events']['date_table']){
 $dateQ=$db->query("SHOW COLUMNS FROM `bh_directory_event_occurrences`");
 $directorySchema['events']['date_fields']=array_map(fn($c)=>(string)$c['Field'],$dateQ->fetchAll(PDO::FETCH_ASSOC));
}

if($method==='GET'){$q=$db->query("SELECT id,widget_key,widget_type,name,category,description,enabled,settings_json,updated_at FROM bh_visual_widget_library ORDER BY FIELD(category,'Content','Forms','Interactive','Layout'),name");$items=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$row['enabled']=(bool)$row['enabled'];$row['settings']=json_decode((string)$row['settings_json'],true)?:[];unset($row['settings_json']);$items[]=$row;}bh_wl_out(200,['ok'=>true,'csrf'=>$csrf,'widgets'=>$items,'directory_schema'=>$directorySchema]);}
$action=(string)($body['action']??'');
if($action==='save_widget'){
$id=filter_var($body['id']??0,FILTER_VALIDATE_INT)?:0;$key=strtolower(trim((string)($body['widget_key']??'')));$type=strtolower(trim((string)($body['widget_type']??'')));$name=trim((string)($body['name']??''));$category=(string)($body['category']??'Content');$description=trim((string)($body['description']??''));$settings=$body['settings']??[];
if(!preg_match('/^[a-z0-9][a-z0-9-]{1,79}$/',$key)||!preg_match('/^[a-z][a-z0-9_]{1,59}$/',$type)||$name===''||mb_strlen($name)>120||!in_array($category,['Content','Forms','Interactive','Layout'],true)||mb_strlen($description)>500||!is_array($settings))bh_wl_out(422,['ok'=>false,'error'=>'Check the widget name, key, category and settings.']);
$encoded=json_encode($settings,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($encoded===false||strlen($encoded)>12000)bh_wl_out(422,['ok'=>false,'error'=>'Widget settings are too large or invalid.']);
$enabled=!empty($body['enabled'])?1:0;
if($id){$st=$db->prepare("UPDATE bh_visual_widget_library SET widget_key=?,widget_type=?,name=?,category=?,description=?,enabled=?,settings_json=? WHERE id=?");$st->execute([$key,$type,$name,$category,$description,$enabled,$encoded,$id]);}
else{$st=$db->prepare("INSERT INTO bh_visual_widget_library(widget_key,widget_type,name,category,description,enabled,settings_json) VALUES(?,?,?,?,?,?,?)");$st->execute([$key,$type,$name,$category,$description,$enabled,$encoded]);}
bh_wl_out(200,['ok'=>true,'message'=>'Widget settings saved.','id'=>$id?: (int)$db->lastInsertId()]);
}
if($action==='delete_widget'){$id=filter_var($body['id']??null,FILTER_VALIDATE_INT);if(!$id||$id<1)bh_wl_out(422,['ok'=>false,'error'=>'A valid widget is required.']);$st=$db->prepare("DELETE FROM bh_visual_widget_library WHERE id=?");$st->execute([$id]);bh_wl_out(200,['ok'=>true,'message'=>'Widget removed from the library.']);}
bh_wl_out(400,['ok'=>false,'error'=>'Unknown action.']);
}catch(Throwable $e){error_log('Widget library: '.$e->getMessage());bh_wl_out(500,['ok'=>false,'error'=>'The widget library could not be loaded. Please try again.']);}
