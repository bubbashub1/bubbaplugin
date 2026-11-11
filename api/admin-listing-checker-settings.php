<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');
session_start();
function bh_lc_auth_reply(): never {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Admin login required.']);
  exit;
}
if(empty($_SESSION['bh_admin_authenticated'])) bh_lc_auth_reply();
require_once __DIR__.'/db.php';

function bh_lc_reply(int $status,array $payload): never {
  http_response_code($status);
  echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
  exit;
}
try {
  $db=bh_mysql();
  $tables=['bh_listing_checker_settings','bh_listing_discovery_terms'];
  foreach($tables as $table) {
    if(!$db->query('SHOW TABLES LIKE '.$db->quote($table))->fetchColumn()) {
      bh_lc_reply(503,['ok'=>false,'error'=>'Listing Checker database migration is required. Run database/listing-checker-schema.sql first.']);
    }
  }
  $_SESSION['bh_listing_checker_csrf']??=bin2hex(random_bytes(32));
  $method=$_SERVER['REQUEST_METHOD']??'GET';
  if($method==='GET') {
    $settings=[];
    foreach($db->query('SELECT setting_key,setting_value FROM bh_listing_checker_settings')->fetchAll(PDO::FETCH_ASSOC) as $row) $settings[$row['setting_key']]=$row['setting_value'];
    $terms=$db->query('SELECT id,term,term_group,regions_json,enabled,last_searched_at FROM bh_listing_discovery_terms ORDER BY term_group,term')->fetchAll(PDO::FETCH_ASSOC);
    foreach($terms as &$term) {$term['regions']=json_decode((string)$term['regions_json'],true)?:['all'];unset($term['regions_json']);$term['enabled']=(bool)$term['enabled'];}
    unset($term);
    $runs=$db->query("SELECT id,check_type,status,started_at,finished_at,processed_count,error_count,summary_json FROM bh_listing_check_runs ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    foreach($runs as &$run){$run['summary']=json_decode((string)$run['summary_json'],true)?:[];unset($run['summary_json']);}unset($run);
    bh_lc_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_listing_checker_csrf'],'settings'=>$settings,'terms'=>$terms,'runs'=>$runs]);
  }
  if($method!=='POST') bh_lc_reply(405,['ok'=>false,'error'=>'Method not allowed']);
  $origin=(string)($_SERVER['HTTP_ORIGIN']??'');$host=(string)($_SERVER['HTTP_HOST']??'');
  if($origin!==''&&(!filter_var($origin,FILTER_VALIDATE_URL)||strcasecmp((string)parse_url($origin,PHP_URL_HOST),preg_replace('/:\\d+$/','',$host))!==0)) bh_lc_reply(403,['ok'=>false,'error'=>'Cross-origin request blocked']);
  if(in_array((string)($_SERVER['HTTP_SEC_FETCH_SITE']??''),['cross-site'],true)) bh_lc_reply(403,['ok'=>false,'error'=>'Cross-site request blocked']);
  if((int)($_SERVER['CONTENT_LENGTH']??0)>65536) bh_lc_reply(413,['ok'=>false,'error'=>'Request too large']);
  $input=json_decode((string)file_get_contents('php://input'),true);
  if(!is_array($input)) bh_lc_reply(400,['ok'=>false,'error'=>'Invalid JSON']);
  if(!hash_equals((string)$_SESSION['bh_listing_checker_csrf'],(string)($input['csrf']??''))) bh_lc_reply(403,['ok'=>false,'error'=>'Security check failed. Refresh the page and try again.']);
  $action=(string)($input['action']??'');
  if($action==='control') {
    $control=(string)($input['control']??'');
    $allowed=['health_pause','health_resume','health_run_now','discovery_pause','discovery_resume','discovery_run_now'];
    if(!in_array($control,$allowed,true)) bh_lc_reply(422,['ok'=>false,'error'=>'Unknown worker control']);
    $map=[
      'health_pause'=>['health_paused','1'],'health_resume'=>['health_paused','0'],'health_run_now'=>['health_run_now','1'],
      'discovery_pause'=>['discovery_paused','1'],'discovery_resume'=>['discovery_paused','0'],'discovery_run_now'=>['discovery_run_now','1']
    ];
    [$key,$value]=$map[$control];
    $stmt=$db->prepare('INSERT INTO bh_listing_checker_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $stmt->execute([$key,$value]);
    bh_lc_reply(200,['ok'=>true,'message'=>str_ends_with($control,'run_now')?'Manual run queued. It will run only when the worker is enabled, resumed and inside its allowed day/time window.':'Worker control saved.']);
  }
  if($action==='save_settings') {
    $allowed=['health_enabled','discovery_enabled','interval_hours','preferred_start_time','window_start','window_end','active_days','timezone','batch_size','max_runtime_seconds','request_limit','stale_after_days'];
    $values=$input['settings']??[];
    if(!is_array($values)) bh_lc_reply(422,['ok'=>false,'error'=>'Settings are invalid']);
    $validated=[];
    foreach($allowed as $key) {
      if(!array_key_exists($key,$values)) continue;
      $v=$values[$key];
      if(in_array($key,['health_enabled','discovery_enabled'],true)) $v=$v?'1':'0';
      elseif($key==='active_days') {
        if(!is_array($v)) bh_lc_reply(422,['ok'=>false,'error'=>'Select at least one active day']);
        $days=array_values(array_unique(array_filter(array_map('intval',$v),static fn($d)=>$d>=1&&$d<=7)));
        if(!$days) bh_lc_reply(422,['ok'=>false,'error'=>'Select at least one active day']);
        $v=json_encode($days);
      } elseif($key==='timezone') {
        if(!in_array($v,DateTimeZone::listIdentifiers(),true)) bh_lc_reply(422,['ok'=>false,'error'=>'Invalid timezone']);
      } elseif(in_array($key,['preferred_start_time','window_start','window_end'],true)) {
        if(!is_string($v)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$v)) bh_lc_reply(422,['ok'=>false,'error'=>'Times must use 24-hour HH:MM format']);
      } else {
        $v=filter_var($v,FILTER_VALIDATE_INT);
        $limits=['interval_hours'=>[1,24],'batch_size'=>[1,100],'max_runtime_seconds'=>[30,600],'request_limit'=>[1,200],'stale_after_days'=>[30,1095]];
        [$min,$max]=$limits[$key];
        if($v===false||$v<$min||$v>$max) bh_lc_reply(422,['ok'=>false,'error'=>'One of the numeric settings is outside its allowed range']);
        $v=(string)$v;
      }
      $validated[$key]=(string)$v;
    }
    $stmt=$db->prepare('INSERT INTO bh_listing_checker_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    foreach($validated as $key=>$value) $stmt->execute([$key,$value]);
    bh_lc_reply(200,['ok'=>true,'message'=>'Listing Checker settings saved.']);
  }
  if($action==='save_term') {
    $id=max(0,(int)($input['id']??0));
    $term=trim((string)($input['term']??''));
    $group=trim((string)($input['term_group']??'General'));
    $regions=$input['regions']??['all'];
    if($term===''||strlen($term)>190||$group===''||strlen($group)>100) bh_lc_reply(422,['ok'=>false,'error'=>'Enter a search term and group']);
    if(!is_array($regions)) bh_lc_reply(422,['ok'=>false,'error'=>'Invalid region selection']);
    $allowedRegions=['all','East Cornwall','East Devon','Exeter','Mid Cornwall','Mid Devon','North Cornwall','North Devon','Plymouth','South Cornwall','South Hams','Teignbridge','Torbay','West Cornwall','West Devon'];
    $regions=array_values(array_unique(array_intersect($allowedRegions,$regions)));
    if(!$regions) bh_lc_reply(422,['ok'=>false,'error'=>'Choose at least one region']);
    if(in_array('all',$regions,true)) $regions=['all'];
    $enabled=!empty($input['enabled'])?1:0;
    $json=json_encode($regions,JSON_UNESCAPED_UNICODE);
    if($id>0) {
      $stmt=$db->prepare('UPDATE bh_listing_discovery_terms SET term=?,term_group=?,regions_json=?,enabled=? WHERE id=?');
      $stmt->execute([$term,$group,$json,$enabled,$id]);
    } else {
      $stmt=$db->prepare('INSERT INTO bh_listing_discovery_terms(term,term_group,regions_json,enabled) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE regions_json=VALUES(regions_json),enabled=VALUES(enabled)');
      $stmt->execute([$term,$group,$json,$enabled]);
    }
    bh_lc_reply(200,['ok'=>true,'message'=>'Search term saved.']);
  }
  if($action==='delete_term') {
    $id=(int)($input['id']??0);
    if($id<1) bh_lc_reply(422,['ok'=>false,'error'=>'Invalid search term']);
    $stmt=$db->prepare('DELETE FROM bh_listing_discovery_terms WHERE id=?');$stmt->execute([$id]);
    bh_lc_reply(200,['ok'=>true,'message'=>'Search term removed.']);
  }
  bh_lc_reply(422,['ok'=>false,'error'=>'Unknown action']);
} catch(Throwable $e) {
  error_log('Listing Checker settings: '.$e->getMessage());
  bh_lc_reply(500,['ok'=>false,'error'=>'Unable to load or save Listing Checker settings.']);
}
