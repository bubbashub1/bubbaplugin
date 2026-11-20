<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
header('X-Content-Type-Options: nosniff');
require_once __DIR__.'/db.php';
try {
 $db=bh_mysql();
 $exists=$db->query("SHOW TABLES LIKE 'bh_site_settings'")->fetchColumn();
 if(!$exists){http_response_code(200);echo json_encode(['ok'=>true,'published'=>null]);exit;}
 $q=$db->query("SELECT published_json FROM bh_site_settings WHERE setting_id=1 LIMIT 1");
 $json=$q->fetchColumn();
 $settings=is_string($json)?json_decode($json,true):null;
 if(!is_array($settings)){echo json_encode(['ok'=>true,'published'=>null]);exit;}
 $allowed=['primary','accent','surface','text','heading_font','body_font','card_radius','button_radius','section_gap','homepage_sections'];
 $safe=[];
 foreach($allowed as $key){if(array_key_exists($key,$settings))$safe[$key]=$settings[$key];}
 $sectionDefaults=['ticker'=>true,'categories'=>true,'quick_links'=>true,'latest'=>true,'regions'=>true,'cta'=>true];
 $sections=is_array($settings['homepage_sections']??null)?$settings['homepage_sections']:[];
 $safe['homepage_sections']=[];
 foreach($sectionDefaults as $key=>$default)$safe['homepage_sections'][$key]=array_key_exists($key,$sections)?($sections[$key]===false?false:true):$default;
 echo json_encode(['ok'=>true,'published'=>$safe],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){error_log('Public Site Manager settings read failed: '.$e->getMessage());http_response_code(200);echo json_encode(['ok'=>true,'published'=>null]);}
