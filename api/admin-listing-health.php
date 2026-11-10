<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');
session_start();
function bh_health_reply(int $status,array $data): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if(empty($_SESSION['bh_admin_authenticated'])) bh_health_reply(401,['ok'=>false,'error'=>'Admin login required.']);
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET') bh_health_reply(405,['ok'=>false,'error'=>'GET only. This first release is read-only.']);
try {
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 $columns=static function(PDO $db,string $table): array {
  if(!preg_match('/^[a-z_]+$/',$table)) return [];
  try { return $db->query("SHOW COLUMNS FROM `".$table."`")->fetchAll(PDO::FETCH_COLUMN); } catch(Throwable $e) { return []; }
 };
 $activityCols=$columns($db,'bh_activities');
 $organiserCols=$columns($db,'bh_organisers');
 $venueCols=$columns($db,'bh_venues');
 if(!$activityCols) bh_health_reply(503,['ok'=>false,'error'=>'Activity listing table is unavailable.']);
 $pick=static function(array $available,string $column,string $alias,string $tableAlias='a'): string {
  return in_array($column,$available,true) ? $tableAlias.'.`'.$column.'` AS `'.$alias.'`' : "NULL AS `".$alias."`";
 };
 $select=['a.id AS id',$pick($activityCols,'title','title'),$pick($activityCols,'description','description'),$pick($activityCols,'short_description','short_description'),$pick($activityCols,'category','category'),$pick($activityCols,'age_range','age_range'),$pick($activityCols,'website','activity_website'),$pick($activityCols,'booking_url','booking_url'),$pick($activityCols,'contact_email','contact_email'),$pick($activityCols,'phone','activity_phone'),$pick($activityCols,'facebook_url','facebook_url'),$pick($activityCols,'instagram_url','instagram_url'),$pick($activityCols,'town','town'),$pick($activityCols,'region','region'),$pick($activityCols,'postcode','postcode'),$pick($activityCols,'image_path','image_path'),$pick($activityCols,'session_summary','session_summary'),$pick($activityCols,'status','status'),$pick($activityCols,'organiser_id','organiser_id')];
 $join='';
 if(in_array('id',$organiserCols,true)&&in_array('organiser_id',$activityCols,true)){
  $join=' LEFT JOIN bh_organisers o ON o.id=a.organiser_id';
  foreach(['organisation_name','email','phone','website'] as $field) $select[]=$pick($organiserCols,$field,'organiser_'.$field,'o');
 }
 if(in_array('activity_id',$venueCols,true)){
  foreach(['venue_name','address','town','postcode'] as $field){
   if(in_array($field,$venueCols,true)) $select[]='(SELECT v.`'.$field.'` FROM bh_venues v WHERE v.activity_id=a.id ORDER BY v.id LIMIT 1) AS `venue_'.$field.'`';
  }
 }
 $order=in_array('title',$activityCols,true)?'a.title':'a.id';
 $sql='SELECT '.implode(',',$select).' FROM bh_activities a'.$join.' ORDER BY '.$order.' LIMIT 5000';
 $rows=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
 $fieldMap=[
  'Description'=>['description','short_description'],
  'Website'=>['activity_website','organiser_website'],
  'Booking link'=>['booking_url'],
  'Email'=>['contact_email','organiser_email'],
  'Telephone'=>['activity_phone','organiser_phone'],
  'Facebook'=>['facebook_url'],
  'Instagram'=>['instagram_url'],
  'Age range'=>['age_range'],
  'Category'=>['category'],
  'Location'=>['town','venue_town','venue_address'],
  'Postcode'=>['postcode','venue_postcode'],
  'Image'=>['image_path']
 ];
 $has=static function(array $row,array $keys): bool { foreach($keys as $key) if(trim((string)($row[$key]??''))!=='') return true; return false; };
 $missingCounts=array_fill_keys(array_keys($fieldMap),0);
 foreach($rows as &$row){
  $missing=[];
  foreach($fieldMap as $label=>$keys) if(!$has($row,$keys)){ $missing[]=$label; $missingCounts[$label]++; }
  $row['missing']=$missing;
  $row['health_score']=(int)round((count($fieldMap)-count($missing))/count($fieldMap)*100);
  $row['suggested_description']='';
  if(trim((string)($row['description']??''))==='' && trim((string)($row['short_description']??''))===''){
   $activity=trim((string)($row['title']??''));
   $org=trim((string)($row['organiser_organisation_name']??''));
   $kind=trim((string)($row['category']??''));
   $age=trim((string)($row['age_range']??''));
   $venue=trim((string)($row['venue_venue_name']??''));
   $town=trim((string)($row['town']??$row['venue_town']??''));
   $region=trim((string)($row['region']??''));
   $where=trim(implode(', ',array_filter([$venue,$town,$region])));
   $parts=[];
   if($activity!=='') $parts[]=$activity.($where!==''?' in '.$where:'').' is an activity'.($kind!==''?' in the '.$kind.' category':'').'.';
   if($org!=='') $parts[]='Run by '.$org.'.';
   if($age!=='') $parts[]='Suitable for '.$age.'.';
   if($where!=='' && $activity==='') $parts[]='Based at '.$where.'.';
   $parts[]='Please check the organiser’s current details for session times, availability and booking information before attending.';
   $row['suggested_description']=implode(' ',$parts);
  }
 }
 unset($row);
 $totals=['listings'=>count($rows),'with_missing'=>0,'description_missing'=>0,'average_health'=>0];
 $score=0;
 foreach($rows as $row){ if(count($row['missing']))$totals['with_missing']++; if(in_array('Description',$row['missing'],true))$totals['description_missing']++; $score+=(int)$row['health_score']; }
 $totals['average_health']=$totals['listings']?(int)round($score/$totals['listings']):0;
 bh_health_reply(200,['ok'=>true,'read_only'=>true,'totals'=>$totals,'missing_counts'=>$missingCounts,'data'=>$rows,'message'=>'This first release audits existing fields and drafts factual template descriptions. It does not crawl external websites or change listings.']);
} catch(Throwable $e) {
 error_log('Listing health checker: '.$e->getMessage());
 bh_health_reply(500,['ok'=>false,'error'=>'Listing health check could not be completed.']);
}
