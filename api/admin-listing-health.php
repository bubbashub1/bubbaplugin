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
 $select=['a.id AS id',$pick($activityCols,'title','title'),$pick($activityCols,'description','description'),$pick($activityCols,'short_description','short_description'),$pick($activityCols,'category','category'),$pick($activityCols,'age_range','age_range'),$pick($activityCols,'website','activity_website'),$pick($activityCols,'booking_url','booking_url'),$pick($activityCols,'contact_email','contact_email'),$pick($activityCols,'contact_phone','activity_phone'),$pick($activityCols,'facebook_url','facebook_url'),$pick($activityCols,'instagram_url','instagram_url'),$pick($activityCols,'town','town'),$pick($activityCols,'region','region'),$pick($activityCols,'postcode','postcode'),$pick($activityCols,'image_path','image_path'),$pick($activityCols,'session_summary','session_summary'),$pick($activityCols,'status','status'),$pick($activityCols,'organiser_id','organiser_id'),$pick($activityCols,'session_start','session_start'),$pick($activityCols,'start_date','start_date'),$pick($activityCols,'end_date','end_date'),$pick($activityCols,'next_session_date','next_session_date'),$pick($activityCols,'updated_at','updated_at')];
 $join='';
 if(in_array('id',$organiserCols,true)&&in_array('organiser_id',$activityCols,true)){
  $join=' LEFT JOIN bh_organisers o ON o.id=a.organiser_id';
  foreach(['organisation_name','email','phone','website'] as $field) $select[]=$pick($organiserCols,$field,'organiser_'.$field,'o');
 }
 $activityVenueCols=$columns($db,'bh_activity_venues');
 $hasVenueLink=in_array('activity_id',$activityVenueCols,true)&&in_array('venue_id',$activityVenueCols,true)&&in_array('id',$venueCols,true);
 $hasDirectVenue=in_array('activity_id',$venueCols,true)&&in_array('id',$venueCols,true);
 if(in_array('id',$venueCols,true)&&($hasVenueLink||$hasDirectVenue)){
  $venueExpressions=[
   'venue_name'=>in_array('venue_name',$venueCols,true)&&in_array('name',$venueCols,true) ? "COALESCE(NULLIF(v.`venue_name`,''),v.`name`)" : (in_array('venue_name',$venueCols,true)?'v.`venue_name`':(in_array('name',$venueCols,true)?'v.`name`':null)),
   'address'=>in_array('address',$venueCols,true)&&in_array('address_line_1',$venueCols,true) ? "COALESCE(NULLIF(v.`address`,''),v.`address_line_1`)" : (in_array('address',$venueCols,true)?'v.`address`':(in_array('address_line_1',$venueCols,true)?'v.`address_line_1`':null)),
   'town'=>in_array('town',$venueCols,true)?'v.`town`':null,
   'region'=>in_array('region',$venueCols,true)?'v.`region`':null,
   'postcode'=>in_array('postcode',$venueCols,true)?'v.`postcode`':null
  ];
  foreach($venueExpressions as $alias=>$expression){
   if($expression===null) continue;
   $parts=[];
   if($hasVenueLink){
    $order=in_array('is_primary',$activityVenueCols,true)?'av.is_primary DESC,av.venue_id':'av.venue_id';
    $parts[]='(SELECT '.$expression.' FROM bh_activity_venues av INNER JOIN bh_venues v ON v.id=av.venue_id WHERE av.activity_id=a.id ORDER BY '.$order.' LIMIT 1)';
   }
   if($hasDirectVenue) $parts[]='(SELECT '.$expression.' FROM bh_venues v WHERE v.activity_id=a.id ORDER BY v.id LIMIT 1)';
   $select[]='COALESCE('.implode(',',$parts).') AS `venue_'.$alias.'`';
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
 // A health dimension is assessed only when at least one real source column supports it.
 $availableAliases=[];
 $activityAliasColumns=['description'=>'description','short_description'=>'short_description','activity_website'=>'website','booking_url'=>'booking_url','contact_email'=>'contact_email','activity_phone'=>'contact_phone','facebook_url'=>'facebook_url','instagram_url'=>'instagram_url','town'=>'town','region'=>'region','postcode'=>'postcode','image_path'=>'image_path','age_range'=>'age_range','category'=>'category'];
 foreach($activityAliasColumns as $alias=>$column) if(in_array($column,$activityCols,true)) $availableAliases[]=$alias;
 if($join!=='') foreach(['organisation_name','email','phone','website'] as $field) if(in_array($field,$organiserCols,true)) $availableAliases[]='organiser_'.$field;
 if(in_array('id',$venueCols,true)&&($hasVenueLink||$hasDirectVenue)){
  foreach(['venue_name','address','town','region','postcode'] as $field){
   $source=($field==='venue_name' ? (in_array('venue_name',$venueCols,true)||in_array('name',$venueCols,true)) : ($field==='address' ? (in_array('address',$venueCols,true)||in_array('address_line_1',$venueCols,true)) : in_array($field,$venueCols,true)));
   if($source) $availableAliases[]='venue_'.$field;
  }
 }
 foreach($fieldMap as $label=>$keys){
  $supported=array_values(array_filter($keys,static fn($key)=>in_array($key,$availableAliases,true)));
  if($supported) $fieldMap[$label]=$supported; else unset($fieldMap[$label]);
 }
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
 // Add validation-only checks and likely duplicate flags without changing source records.
 $normalise=static function(string $value): string {
  $value=strtolower(trim($value));
  $value=preg_replace('/[^\pL\pN]+/u',' ',$value)??$value;
  return trim(preg_replace('/\s+/u',' ',$value)??$value);
 };
 $duplicateGroups=[];
 foreach($rows as $index=>$row){
  $titleKey=$normalise((string)($row['title']??''));
  $placeKey=$normalise((string)($row['town']??$row['venue_town']??$row['venue_venue_name']??''));
  if($titleKey!=='') $duplicateGroups[$titleKey.'|'.$placeKey][]=$index;
 }
 foreach($rows as &$row){
  $issues=[];
  foreach(['activity_website'=>'Website','booking_url'=>'Booking link','facebook_url'=>'Facebook','instagram_url'=>'Instagram'] as $key=>$label){
   $value=trim((string)($row[$key]??''));
   if($value!=='' && !filter_var($value,FILTER_VALIDATE_URL)) $issues[]=$label.' may be an invalid URL';
  }
  $email=trim((string)($row['contact_email']??$row['organiser_email']??''));
  if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) $issues[]='Email may be invalid';
  $phone=trim((string)($row['activity_phone']??$row['organiser_phone']??''));
  if($phone!=='' && !preg_match('/^[0-9+() .-]{7,25}$/',$phone)) $issues[]='Telephone format needs checking';
  $postcode=trim((string)($row['postcode']??$row['venue_postcode']??''));
  if($postcode!=='' && !preg_match('/^[A-Z]{1,2}[0-9][A-Z0-9]?\s*[0-9][A-Z]{2}$/i',$postcode)) $issues[]='Postcode format needs checking';
  foreach(['end_date','next_session_date','session_start','start_date'] as $dateKey){
   $date=trim((string)($row[$dateKey]??''));
   if($date!=='' && strtotime($date)!==false && strtotime($date)<strtotime('today')) {$issues[]='Past date in '.$dateKey;break;}
  }
  $titleKey=$normalise((string)($row['title']??''));
  $placeKey=$normalise((string)($row['town']??$row['venue_town']??$row['venue_venue_name']??''));
  $group=$titleKey.'|'.$placeKey;
  $row['possible_duplicate']=($titleKey!=='' && count($duplicateGroups[$group]??[])>1);
  $row['validation_issues']=$issues;
  $row['issue_count']=count($issues)+($row['possible_duplicate']?1:0);
 }
 unset($row);
 $totals=['listings'=>count($rows),'with_missing'=>0,'description_missing'=>0,'average_health'=>0,'validation_issues'=>0,'possible_duplicates'=>0];
 $score=0;
 foreach($rows as $row){ if(count($row['missing']))$totals['with_missing']++; if(in_array('Description',$row['missing'],true))$totals['description_missing']++; $totals['validation_issues']+=count($row['validation_issues']??[]); if(!empty($row['possible_duplicate']))$totals['possible_duplicates']++; $score+=(int)$row['health_score']; }
 $totals['average_health']=$totals['listings']?(int)round($score/$totals['listings']):0;
 bh_health_reply(200,['ok'=>true,'read_only'=>true,'totals'=>$totals,'missing_counts'=>$missingCounts,'data'=>$rows,'message'=>'Read-only checks include completeness, format validation, past-date flags and likely same-title/same-location duplicates. Findings are suggestions only; no listings are changed.']);
} catch(Throwable $e) {
 error_log('Listing health checker: '.$e->getMessage());
 bh_health_reply(500,['ok'=>false,'error'=>'Listing health check could not be completed.']);
}
