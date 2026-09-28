<?php
declare(strict_types=1);
header('Content-Type: text/csv; charset=utf-8');
header('Cache-Control: no-store');
$secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['bh_admin_authenticated'])){
  http_response_code(401); header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false,'error'=>'Admin login required.']); exit;
}
require __DIR__.'/db.php';
$db=bh_mysql();

function csv_row(array $row): void { echo implode(',',array_map(function($v){$v=(string)($v??'');return '"'.str_replace('"','""',$v).'"';},$row))."\r\n"; }
function clean_header(string $s): string {
  $s=preg_replace('/^\xEF\xBB\xBF/','',$s);
  $s=strtolower(trim($s));
  return preg_replace('/[^a-z0-9]+/','_',trim($s,'_'));
}
function firstv(array $row,array $aliases,string $default=''): string {
  foreach($aliases as $a){$k=clean_header($a); if(array_key_exists($k,$row) && trim((string)$row[$k])!=='') return trim((string)$row[$k]);}
  return $default;
}
function bool_status(string $s): string { return in_array(strtolower(trim($s)),['draft','published'],true)?strtolower(trim($s)):'published'; }
function day_num(string $s): int {
  $s=strtolower(trim($s));
  $map=['mon'=>1,'monday'=>1,'tue'=>2,'tues'=>2,'tuesday'=>2,'wed'=>3,'wednesday'=>3,'thu'=>4,'thur'=>4,'thurs'=>4,'thursday'=>4,'fri'=>5,'friday'=>5,'sat'=>6,'saturday'=>6,'sun'=>7,'sunday'=>7];
  return $map[$s]??(int)$s;
}
function import_session_parts(string $value): array {
  $out=[];
  foreach(preg_split('/\s*;\s*/',$value) as $part){
    $part=trim($part); if($part==='') continue;
    if(!preg_match('/^([^\s]+)\s+([0-9]{1,2}:[0-9]{2})(?:\s*[-–]\s*([0-9]{1,2}:[0-9]{2}))?(?:\s*\|\s*(.*))?$/u',$part,$m)) continue;
    $day=day_num($m[1]); if($day<1||$day>7) continue;
    $out[]=['day'=>$day,'start'=>$m[2].':00','end'=>!empty($m[3])?$m[3].':00':null,'term'=>!empty($m[4])&&preg_match('/term/i',$m[4])];
  }
  return $out;
}

if($_SERVER['REQUEST_METHOD']==='GET'){
  $mode=$_GET['mode']??'export';
  if($mode!=='export'){http_response_code(400);header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>'Unknown export mode.']);exit;}
  header('Content-Disposition: attachment; filename="bubba-hub-activities-'.date('Y-m-d').'.csv"');
  $ac=false; try{$q=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='accessibility'");$ac=(int)$q->fetchColumn()>0;}catch(Throwable $e){}
  $access=$ac?'a.accessibility':'NULL';
  csv_row(['title','category','description','age_range','county','price_from','booking_url','image_path','status','organisation_name','email','phone','website','venue_name','address','town','region','postcode','latitude','longitude','accessibility','sessions']);
  $sql="SELECT a.title,a.category,a.description,a.age_range,a.county,a.price_from,a.booking_url,a.image_path,a.status,o.organisation_name,o.email,o.phone,o.website,v.venue_name,v.address,v.town,v.region,v.postcode,v.latitude,v.longitude,$access AS accessibility FROM bh_activities a LEFT JOIN bh_organisers o ON o.id=a.organiser_id LEFT JOIN bh_venues v ON v.activity_id=a.id AND v.id=(SELECT MIN(v2.id) FROM bh_venues v2 WHERE v2.activity_id=a.id) ORDER BY a.id";
  foreach($db->query($sql) as $a){
    $sq=$db->prepare("SELECT day_of_week,start_time,end_time,term_time_only FROM bh_sessions WHERE venue_id=(SELECT MIN(v3.id) FROM bh_venues v3 WHERE v3.activity_id=?) ORDER BY day_of_week,start_time");$sq->execute([(int)$a['id']]);
    $sessions=[]; foreach($sq->fetchAll() as $s){$days=[1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];$sessions[]=$days[(int)$s['day_of_week']].' '.substr((string)$s['start_time'],0,5).(!empty($s['end_time'])?'-'.substr((string)$s['end_time'],0,5):'').(!empty($s['term_time_only'])?' | term':'');}
    csv_row([$a['title'],$a['category'],$a['description'],$a['age_range'],$a['county'],$a['price_from'],$a['booking_url'],$a['image_path'],$a['status'],$a['organisation_name'],$a['email'],$a['phone'],$a['website'],$a['venue_name'],$a['address'],$a['town'],$a['region'],$a['postcode'],$a['latitude'],$a['longitude'],$a['accessibility'],$sessions?implode('; ',$sessions):'']);
  }
  exit;
}

if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>'POST or GET required.']);exit;}
$csvTmp=null;
$csvUrl=trim((string)($_POST['csv_url']??''));
if($csvUrl!==''){
  // Bubba Hub only accepts Google Sheets' published CSV endpoint:
  // https://docs.google.com/spreadsheets/d/e/.../pub?...&output=csv
  $parts=parse_url($csvUrl);$host=strtolower((string)($parts['host']??''));$path=(string)($parts['path']??'');
  $isPublished=$host==='docs.google.com' && (bool)preg_match('~/spreadsheets/d/e/[a-zA-Z0-9_-]+/pub$~',$path) && strtolower((string)($parts['scheme']??''))==='https';
  parse_str((string)($parts['query']??''),$qp);
  if(!$isPublished || strtolower((string)($qp['output']??''))!=='csv'){
    http_response_code(422);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>'Please use the published Google Sheets CSV link: File → Share → Publish to web → CSV.']);exit;
  }
  // Google can return one or more redirects before serving a published CSV.
  // Follow them, then verify the final destination is still a Google HTTPS host.
  $ch=curl_init($csvUrl);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>4,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_USERAGENT=>'Bubba Hub published Google Sheets importer/1.0']);
  $body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);$finalUrl=(string)curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);curl_close($ch);
  $finalParts=parse_url($finalUrl);$finalScheme=strtolower((string)($finalParts['scheme']??''));$finalHost=strtolower((string)($finalParts['host']??''));
  $safeFinal=$finalScheme==='https' && in_array($finalHost,['docs.google.com','docs.googleusercontent.com','googleusercontent.com'],true);
  if($body===false||$err!==''||$http<200||$http>=300||!$safeFinal){
    http_response_code(422);header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'Could not fetch the published Google Sheets CSV link. Google returned HTTP '.$http.'. Please check that the sheet is still published as CSV.']);exit;
  }
  if(strlen($body)>10*1024*1024){http_response_code(422);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>'CSV files must be 10 MB or smaller.']);exit;}
  $csvTmp=tempnam(sys_get_temp_dir(),'bhcsv_');if($csvTmp===false||file_put_contents($csvTmp,$body)===false){http_response_code(500);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>'Could not prepare the downloaded CSV.']);exit;}$fh=fopen($csvTmp,'rb');

}else{
  if(empty($_FILES['csv'])||$_FILES['csv']['error']!==UPLOAD_ERR_OK){http_response_code(422);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>'Choose a CSV file or paste a Google Sheets link.']);exit;}
  if($_FILES['csv']['size']>10*1024*1024){http_response_code(422);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>'CSV files must be 10 MB or smaller.']);exit;}$fh=fopen($_FILES['csv']['tmp_name'],'rb');
}
if(!$fh){if($csvTmp)@unlink($csvTmp);http_response_code(422);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>'Could not read the CSV.']);exit;}
$headers=fgetcsv($fh); if(!$headers || count($headers)<1){http_response_code(422);header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>'The CSV has no header row.']);exit;}
$headers=array_map('clean_header',$headers); $rows=[]; while(($r=fgetcsv($fh))!==false){if(count(array_filter($r,fn($x)=>trim((string)$x)!==''))===0)continue;$r=array_pad($r,count($headers),'');$row=[];foreach($headers as $i=>$h){$row[$h]=trim((string)($r[$i]??''));}$rows[]=$row;} fclose($fh);
$mode=($_POST['mode']??'update')==='skip'?'skip':'update';
$ac=false;try{$q=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='accessibility'");$ac=(int)$q->fetchColumn()>0;}catch(Throwable $e){}
$created=$updated=$skipped=$failed=0;$errors=[];
foreach($rows as $idx=>$row){
  $line=$idx+2;
  try{
    $title=firstv($row,['title','activity_title','activity_name','activity','class_name','class','name']);$category=firstv($row,['category','activity_category','type','class_type']);$org=firstv($row,['organisation_name','organisation','organization','organizer','organiser','company','company_name','provider','provider_name','leader','leader_name']);
    $venue=firstv($row,['venue_name','venue','venue_name_location','location','location_name','venue_location','address_name']);$town=firstv($row,['town','village_town_or_city','village_town_city','village_town_or_city_name','city','town_city','town_or_city','village','location_town']);
    if($title===''||$category===''||$org===''||$venue===''||$town===''){ $missing=[]; if($title==='')$missing[]='title'; if($category==='')$missing[]='category'; if($org==='')$missing[]='organisation'; if($venue==='')$missing[]='venue'; if($town==='')$missing[]='town'; throw new RuntimeException('Required fields missing: '.implode(', ',$missing).'.'); }
    $desc=firstv($row,['description','activity_description','details']);$age=firstv($row,['age_range','ages','age','age_group','age_groups']);$county=firstv($row,['county','area','county_area']);
    if(!in_array($county,['Devon','Cornwall','Plymouth','Torbay'],true))$county='';
    $price=firstv($row,['price_from','price','cost']);$price=$price===''?null:(float)preg_replace('/[^0-9.\-]/','',$price);
    $status=bool_status(firstv($row,['status'],'published'));$booking=firstv($row,['booking_url','booking','booking_link']);$image=firstv($row,['image_path','image','image_url']);
    $email=firstv($row,['email','email_address','contact_email']);$phone=firstv($row,['phone','telephone','phone_number','contact_phone']);$website=firstv($row,['website','website_url','web','url']);
    $address=firstv($row,['address','venue_address','full_address','street_address']);$region=firstv($row,['region','area_region','locality','district']);$postcode=firstv($row,['postcode','post_code','postal_code','zip']);
    $lat=firstv($row,['latitude','lat']);$lat=$lat===''?null:(float)$lat;$lng=firstv($row,['longitude','lng','lon']);$lng=$lng===''?null:(float)$lng;
    $accessRaw=firstv($row,['accessibility','accessibility_options']);$access=[];
    foreach(preg_split('/\s*[;,|]\s*/',$accessRaw) as $v){$v=trim($v);if($v!=='')$access[]=$v;}
    $access=array_values(array_unique($access));$sessions=import_session_parts(firstv($row,['sessions','session_times','opening_times','schedule']));
    $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$title),'-'));$base=$slug;$n=2;
    $q=$db->prepare("SELECT id,organiser_id FROM bh_activities WHERE slug=? LIMIT 1");$q->execute([$slug]);$existing=$q->fetch();
    if($existing && $mode==='skip'){ $skipped++; continue; }
    if($existing){$id=(int)$existing['id'];$organiserId=(int)$existing['organiser_id'];}
    else{$q=$db->prepare("SELECT id FROM bh_organisers WHERE organisation_name=? LIMIT 1");$q->execute([$org]);$organiserId=(int)$q->fetchColumn();if(!$organiserId){$oslug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$org),'-'));$ob=$oslug;$on=2;while(true){$q=$db->prepare("SELECT id FROM bh_organisers WHERE slug=? LIMIT 1");$q->execute([$oslug]);if(!$q->fetch())break;$slug2=$ob.'-'.$on++;$oslug=$slug2;}$q=$db->prepare("INSERT INTO bh_organisers (organisation_name,slug,email,phone,website,status) VALUES (?,?,?,?,?,'published')");$q->execute([$org,$oslug,$email,$phone,$website]);$organiserId=(int)$db->lastInsertId();}}
    $db->beginTransaction();
    $q=$db->prepare("UPDATE bh_organisers SET organisation_name=?,email=?,phone=?,website=? WHERE id=?");$q->execute([$org,$email,$phone,$website,$organiserId]);
    if($existing){$sql=$ac?"UPDATE bh_activities SET title=?,description=?,category=?,age_range=?,county=?,price_from=?,booking_url=?,image_path=?,status=?,accessibility=? WHERE id=?":"UPDATE bh_activities SET title=?,description=?,category=?,age_range=?,county=?,price_from=?,booking_url=?,image_path=?,status=? WHERE id=?";$args=[$title,$desc,$category,$age,$county,$price,$booking,$image,$status];if($ac)$args[] = json_encode($access,JSON_UNESCAPED_SLASHES);$args[]=$id;$q=$db->prepare($sql);$q->execute($args);}
    else{while(true){$q=$db->prepare("SELECT id FROM bh_activities WHERE slug=? LIMIT 1");$q->execute([$slug]);if(!$q->fetch())break;$slug=$base.'-'.$n++;}$sql=$ac?"INSERT INTO bh_activities (organiser_id,title,slug,description,category,age_range,county,price_from,booking_url,image_path,status,accessibility) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)":"INSERT INTO bh_activities (organiser_id,title,slug,description,category,age_range,county,price_from,booking_url,image_path,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)";$args=[$organiserId,$title,$slug,$desc,$category,$age,$county,$price,$booking,$image,$status];if($ac)$args[] = json_encode($access,JSON_UNESCAPED_SLASHES);$q=$db->prepare($sql);$q->execute($args);$id=(int)$db->lastInsertId();}
    $q=$db->prepare("SELECT id FROM bh_venues WHERE activity_id=? ORDER BY id LIMIT 1");$q->execute([$id]);$vid=(int)$q->fetchColumn();
    if($vid){$q=$db->prepare("UPDATE bh_venues SET venue_name=?,address=?,town=?,region=?,postcode=?,latitude=?,longitude=? WHERE id=?");$q->execute([$venue,$address,$town,$region,$postcode,$lat,$lng,$vid]);}
    else{$q=$db->prepare("INSERT INTO bh_venues (activity_id,venue_name,address,town,region,postcode,latitude,longitude,notes) VALUES (?,?,?,?,?,?,?,?,?)");$q->execute([$id,$venue,$address,$town,$region,$postcode,$lat,$lng,'']);$vid=(int)$db->lastInsertId();}
    $db->prepare("DELETE FROM bh_sessions WHERE venue_id=?")->execute([$vid]);
    foreach($sessions as $s){$dur=null;if($s['end']){$aa=strtotime($s['start']);$bb=strtotime($s['end']);if($aa!==false&&$bb!==false){$dur=(int)(($bb-$aa)/60);if($dur<0)$dur+=1440;}}$q=$db->prepare("INSERT INTO bh_sessions (venue_id,day_of_week,start_time,end_time,duration_minutes,price,term_time_only,frequency,start_date,end_date) VALUES (?,?,?,?,?,?,?,?,?,?)");$q->execute([$vid,$s['day'],$s['start'],$s['end'],$dur,$price,$s['term']?1:0,'weekly',null,null]);}
    $db->commit(); if($existing)$updated++;else $created++;
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$failed++;if(count($errors)<20)$errors[]='Row '.$line.': '.$e->getMessage();}
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true,'rows'=>count($rows),'created'=>$created,'updated'=>$updated,'skipped'=>$skipped,'failed'=>$failed,'errors'=>$errors],JSON_UNESCAPED_SLASHES);
?>