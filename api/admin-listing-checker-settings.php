<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/admin-session-guard.php';
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
    bh_lc_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_listing_checker_csrf'],'settings'=>$settings,'terms'=>$terms]);
  }
  if($method!=='POST') bh_lc_reply(405,['ok'=>false,'error'=>'Method not allowed']);
  $input=json_decode((string)file_get_contents('php://input'),true);
  if(!is_array($input)) bh_lc_reply(400,['ok'=>false,'error'=>'Invalid JSON']);
  if(!hash_equals((string)$_SESSION['bh_listing_checker_csrf'],(string)($input['csrf']??''))) bh_lc_reply(403,['ok'=>false,'error'=>'Security check failed. Refresh the page and try again.']);
  $action=(string)($input['action']??'');
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
    if($term===''||mb_strlen($term)>190||$group===''||mb_strlen($group)>100) bh_lc_reply(422,['ok'=>false,'error'=>'Enter a search term and group']);
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
