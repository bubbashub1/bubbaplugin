<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__.'/admin-session-guard.php';
require_once __DIR__.'/db.php';
function bhsm_out(int $status,array $data): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit; }
if(empty($_SESSION['bh_site_manager_csrf'])) $_SESSION['bh_site_manager_csrf']=bin2hex(random_bytes(32));
$csrf=(string)$_SESSION['bh_site_manager_csrf'];
$defaults=['primary'=>'#416651','accent'=>'#3c8e96','surface'=>'#f4f8f7','text'=>'#17221c','heading_font'=>'Quicksand','body_font'=>'Quicksand','card_radius'=>16,'button_radius'=>999,'section_gap'=>24];
try {
 $db=bh_mysql(); $method=$_SERVER['REQUEST_METHOD']??'GET';
 $table=$db->query("SHOW TABLES LIKE 'bh_site_settings'")->fetchColumn();
 if($method==='GET'){
  if(!$table)bhsm_out(200,['ok'=>true,'csrf'=>$csrf,'setup_required'=>true,'draft'=>$defaults,'published'=>null]);
  $row=$db->query("SELECT draft_json,published_json,updated_at FROM bh_site_settings WHERE setting_id=1 LIMIT 1")->fetch();
  $draft=$row&&$row['draft_json']?json_decode((string)$row['draft_json'],true):$defaults;
  $published=$row&&$row['published_json']?json_decode((string)$row['published_json'],true):null;
  bhsm_out(200,['ok'=>true,'csrf'=>$csrf,'setup_required'=>false,'draft'=>is_array($draft)?array_merge($defaults,$draft):$defaults,'published'=>is_array($published)?array_merge($defaults,$published):null,'updated_at'=>$row['updated_at']??null]);
 }
 if($method!=='POST')bhsm_out(405,['ok'=>false,'error'=>'Method not allowed.']);
 $body=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($body))bhsm_out(400,['ok'=>false,'error'=>'Invalid request body.']);
 if(!hash_equals($csrf,(string)($body['csrf']??'')))bhsm_out(403,['ok'=>false,'error'=>'Security check failed. Refresh the page and try again.']);
 $action=(string)($body['action']??'');
 if($action==='setup'){
  $db->exec("CREATE TABLE IF NOT EXISTS bh_site_settings (setting_id TINYINT UNSIGNED NOT NULL PRIMARY KEY, draft_json MEDIUMTEXT NULL, published_json MEDIUMTEXT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $q=$db->prepare("INSERT IGNORE INTO bh_site_settings (setting_id,draft_json,published_json) VALUES (1,?,NULL)");
  $q->execute([json_encode($defaults,JSON_UNESCAPED_SLASHES)]);
  bhsm_out(200,['ok'=>true,'csrf'=>$csrf,'message'=>'Site Manager settings storage is ready.']);
 }
 if(!$table)bhsm_out(409,['ok'=>false,'error'=>'Run the one-time Site Manager setup first.']);
 $input=$body['settings']??null;
 if(!is_array($input))bhsm_out(422,['ok'=>false,'error'=>'Design settings are missing.']);
 $settings=$defaults;
 foreach(['primary','accent','surface','text'] as $key){$value=(string)($input[$key]??'');if(!preg_match('/^#[0-9a-fA-F]{6}$/',$value))bhsm_out(422,['ok'=>false,'error'=>'Choose a valid six-digit colour for '.$key.'.']);$settings[$key]=strtolower($value);}
 foreach(['heading_font','body_font'] as $key){$value=(string)($input[$key]??'');if(!in_array($value,['Quicksand','Arial','Calibri','Georgia','Verdana'],true))bhsm_out(422,['ok'=>false,'error'=>'Choose a supported font.']);$settings[$key]=$value;}
 foreach(['card_radius'=>[0,4,8,12,16,20,24,28],'button_radius'=>[4,8,12,16,20,999],'section_gap'=>[8,12,16,20,24,32,40,48]] as $key=>$allowed){$value=filter_var($input[$key]??null,FILTER_VALIDATE_INT);if($value===false||!in_array($value,$allowed,true))bhsm_out(422,['ok'=>false,'error'=>'Choose an available '.$key.' value.']);$settings[$key]=$value;}
 $json=json_encode($settings,JSON_UNESCAPED_SLASHES);
 if($action==='save_draft'){$q=$db->prepare("UPDATE bh_site_settings SET draft_json=? WHERE setting_id=1");$q->execute([$json]);bhsm_out(200,['ok'=>true,'csrf'=>$csrf,'message'=>'Draft saved. The public website has not been changed.']);}
 if($action==='publish'){$q=$db->prepare("UPDATE bh_site_settings SET draft_json=?,published_json=? WHERE setting_id=1");$q->execute([$json,$json]);bhsm_out(200,['ok'=>true,'csrf'=>$csrf,'message'=>'Published settings saved. Supported shared design tokens will apply on pages using the shared public loader; some legacy page components still need integration.']);}
 bhsm_out(400,['ok'=>false,'error'=>'Unknown action.']);
} catch(Throwable $e){error_log('Site Manager settings error: '.$e->getMessage());bhsm_out(500,['ok'=>false,'error'=>'Site Manager could not complete the request. Check the server error log.']);}
