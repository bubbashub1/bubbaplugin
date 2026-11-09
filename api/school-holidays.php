<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
function bhHolidayReply(int $status,array $data): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_SLASHES); exit; }
$method=$_SERVER['REQUEST_METHOD']??'GET';$action=(string)($_GET['action']??'');
$areas=['Devon','Torbay','Plymouth','Cornwall'];$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
if($action==='admin'||$method==='POST'){
 if(session_status()!==PHP_SESSION_ACTIVE){ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');session_name('BUBBAHUB_ADMINSESSID');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();}
 if(empty($_SESSION['bh_admin_authenticated']))bhHolidayReply(401,['ok'=>false,'error'=>'admin_required']);
 if(!empty($_SESSION['bh_admin_last_activity'])&&time()-(int)$_SESSION['bh_admin_last_activity']>1800){unset($_SESSION['bh_admin_authenticated'],$_SESSION['bh_admin_last_activity']);bhHolidayReply(401,['ok'=>false,'error'=>'admin_session_expired']);}
 $_SESSION['bh_admin_last_activity']=time();
 if($method==='POST'){
  $host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);$origin=(string)($_SERVER['HTTP_ORIGIN']??'');$referer=(string)($_SERVER['HTTP_REFERER']??'');$source=$origin!==''?$origin:$referer;
  if(!$secure||!$source||parse_url($source,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($source,PHP_URL_HOST))!==$host)bhHolidayReply(403,['ok'=>false,'error'=>'invalid_origin']);
  $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');if(empty($_SESSION['bh_school_holidays_csrf'])||!hash_equals((string)$_SESSION['bh_school_holidays_csrf'],$token))bhHolidayReply(403,['ok'=>false,'error'=>'invalid_csrf']);
 }
 if($method==='GET'&&$action==='admin'){
  if(empty($_SESSION['bh_school_holidays_csrf']))$_SESSION['bh_school_holidays_csrf']=bin2hex(random_bytes(32));
  $adminCsrfToken=(string)$_SESSION['bh_school_holidays_csrf'];
 }
 }
 // Release the admin session lock once authentication and CSRF checks are complete.
 if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $db->exec("CREATE TABLE IF NOT EXISTS bh_school_holidays (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,area VARCHAR(40) NOT NULL,holiday_name VARCHAR(100) NOT NULL,start_date DATE NOT NULL,end_date DATE NOT NULL,is_recurring TINYINT(1) NOT NULL DEFAULT 0,recurrence_type VARCHAR(32) NOT NULL DEFAULT 'none',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,KEY idx_bh_school_holidays_area_dates(area,start_date,end_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
// Add the recurrence flag safely to existing installations.
$columns=$db->query('SHOW COLUMNS FROM bh_school_holidays')->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('is_recurring',$columns,true))$db->exec('ALTER TABLE bh_school_holidays ADD COLUMN is_recurring TINYINT(1) NOT NULL DEFAULT 0 AFTER end_date');
if(!in_array('recurrence_type',$columns,true))$db->exec("ALTER TABLE bh_school_holidays ADD COLUMN recurrence_type VARCHAR(32) NOT NULL DEFAULT 'none' AFTER is_recurring");
$db->exec("UPDATE bh_school_holidays SET recurrence_type='fixed_date' WHERE is_recurring=1 AND recurrence_type='none'");
 if($method==='GET'&&$action==='admin'){
   $rows=$db->query('SELECT id,area,holiday_name,start_date,end_date,is_recurring,recurrence_type FROM bh_school_holidays ORDER BY start_date,area,holiday_name')->fetchAll(PDO::FETCH_ASSOC);
  bhHolidayReply(200,['ok'=>true,'csrf_token'=>$adminCsrfToken,'areas'=>$areas,'holidays'=>$rows]);
 }
 if($method==='GET'){
  $area=trim((string)($_GET['area']??''));$from=(string)($_GET['from']??'');$to=(string)($_GET['to']??'');
  if(!in_array($area,$areas,true)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)||$to<$from)bhHolidayReply(422,['ok'=>false,'error'=>'invalid_date_range']);
  $q=$db->prepare('SELECT id,area,holiday_name,start_date,end_date,is_recurring,recurrence_type FROM bh_school_holidays WHERE area=? ORDER BY start_date');$q->execute([$area]);$all=$q->fetchAll(PDO::FETCH_ASSOC);$matches=[];$fromDate=new DateTimeImmutable($from);$toDate=new DateTimeImmutable($to);
  foreach($all as $holiday){
   if(empty($holiday['is_recurring'])){if($holiday['start_date']<=$to&&$holiday['end_date']>=$from)$matches[]=$holiday;continue;}
   $rule=(string)($holiday['recurrence_type']??'fixed_date');
   $originalStart=new DateTimeImmutable((string)$holiday['start_date']);
   $originalEnd=new DateTimeImmutable((string)$holiday['end_date']);
   $duration=(int)$originalStart->diff($originalEnd)->format('%a');
   $startParts=explode('-',substr((string)$holiday['start_date'],5));$endParts=explode('-',substr((string)$holiday['end_date'],5));$startMonth=(int)$startParts[0];$startDay=(int)$startParts[1];
   for($year=(int)$fromDate->format('Y')-1;$year<=(int)$toDate->format('Y');$year++){
    if($rule==='fixed_date'){
     if(!checkdate($startMonth,$startDay,$year))continue;
     $occurrenceStart=sprintf('%04d-%02d-%02d',$year,$startMonth,$startDay);
    }else{
     if(in_array($rule,['school_half_term','school_summer_holidays'],true)){
      // Repeat the starting date in the same weekday position within its month.
      // If that fifth weekday does not exist in a particular year, use the month's last occurrence.
      $weekday=(int)$originalStart->format('N');
      $ordinal=(int)ceil((int)$originalStart->format('j')/7);
      $month=(int)$originalStart->format('n');
      $first=new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month));
      $offset=(($weekday-(int)$first->format('N')+7)%7);
      $target=$first->modify('+'.($offset+(($ordinal-1)*7)).' days');
      if((int)$target->format('n')!==$month)$target=$target->modify('-7 days');
     }else{
      $month=5;$weekday=1;$ordinal=1;
      if($rule==='last_monday_may'){$ordinal=-1;}
      elseif($rule==='last_monday_august'){$month=8;$ordinal=-1;}
      elseif($rule!=='first_monday_may')continue;
      $first=new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month));
      if($ordinal===1){$offset=(($weekday-(int)$first->format('N')+7)%7);$target=$first->modify('+'.$offset.' days');}
      else{$last=$first->modify('last day of this month');$offset=(((int)$last->format('N')-$weekday+7)%7);$target=$last->modify('-'.$offset.' days');}
     }
     $occurrenceStart=$target->format('Y-m-d');
    }
    $occurrenceEnd=(new DateTimeImmutable($occurrenceStart))->modify('+'.$duration.' days')->format('Y-m-d');
    if($occurrenceStart<=$to&&$occurrenceEnd>=$from){$copy=$holiday;$copy['start_date']=$occurrenceStart;$copy['end_date']=$occurrenceEnd;$copy['occurrence_year']=$year;$matches[]=$copy;}
   }
  }
  usort($matches,static fn($a,$b)=>strcmp($a['start_date'],$b['start_date']));
  bhHolidayReply(200,['ok'=>true,'area'=>$area,'holidays'=>$matches]);
 }
 if($method!=='POST'){header('Allow: GET, POST');bhHolidayReply(405,['ok'=>false,'error'=>'method_not_allowed']);}
 if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)bhHolidayReply(413,['ok'=>false,'error'=>'request_too_large']);
 $input=json_decode((string)file_get_contents('php://input'),true);if(!is_array($input))bhHolidayReply(400,['ok'=>false,'error'=>'invalid_json']);
 $action=(string)($input['action']??'');
 if($action==='delete'){$ids=$input['ids']??[];if(!is_array($ids)&&isset($input['id']))$ids=[$input['id']];$ids=array_values(array_unique(array_filter(array_map(static fn($v)=>filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]),$ids))));if(!$ids)bhHolidayReply(422,['ok'=>false,'error'=>'invalid_id']);$marks=implode(',',array_fill(0,count($ids),'?'));$q=$db->prepare("DELETE FROM bh_school_holidays WHERE id IN ($marks)");$q->execute($ids);if(!$q->rowCount())bhHolidayReply(404,['ok'=>false,'error'=>'holiday_not_found']);bhHolidayReply(200,['ok'=>true,'deleted_ids'=>$ids]);}
 if($action!=='save')bhHolidayReply(422,['ok'=>false,'error'=>'invalid_action']);
 $ids=$input['ids']??[];if(!is_array($ids))$ids=[];$ids=array_values(array_unique(array_filter(array_map(static fn($v)=>filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]),$ids))));
 $legacyId=filter_var($input['id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);if(!$ids&&$legacyId>0)$ids=[$legacyId];
 $recurrenceType=(string)($input['recurrence_type']??(!empty($input['recurring'])?'fixed_date':'none'));$allowedRecurrence=['none','fixed_date','first_monday_may','last_monday_may','last_monday_august','school_half_term','school_summer_holidays'];if(!in_array($recurrenceType,$allowedRecurrence,true))bhHolidayReply(422,['ok'=>false,'error'=>'invalid_recurrence_type']);$recurring=$recurrenceType==='none'?0:1;$selected=$input['areas']??[];$name=trim((string)($input['holiday_name']??''));$start=(string)($input['start_date']??'');$end=(string)($input['end_date']??'');
 if(!is_array($selected))$selected=[$selected];$selected=array_values(array_unique(array_filter(array_map(static fn($v)=>trim((string)$v),$selected),static fn($v)=>$v!=='')));
 $validDate=static function(string $v):bool{$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v,new DateTimeZone('Europe/London'));return $d instanceof DateTimeImmutable&&$d->format('Y-m-d')===$v;};
 if($legacyId===false||count($ids)>20||!$selected||count(array_diff($selected,$areas))>0||$name===''||mb_strlen($name)>100||!$validDate($start)||!$validDate($end)||$end<$start)bhHolidayReply(422,['ok'=>false,'error'=>'check_area_name_and_dates']);
 if($recurrenceType!=='none'&&$recurrenceType!=='fixed_date'){
  $dt=new DateTimeImmutable($start);$month=(int)$dt->format('n');$day=(int)$dt->format('j');$weekday=(int)$dt->format('N');
  $expectedMonth=$recurrenceType==='last_monday_august'?8:5;
  if($month!==$expectedMonth||$weekday!==1)bhHolidayReply(422,['ok'=>false,'error'=>'start_date_must_match_repeat_pattern']);
  if($recurrenceType==='first_monday_may'&&$day>7)bhHolidayReply(422,['ok'=>false,'error'=>'start_date_must_match_repeat_pattern']);
  if($recurrenceType!=='first_monday_may'&&$day<25)bhHolidayReply(422,['ok'=>false,'error'=>'start_date_must_match_repeat_pattern']);
 }
 try{
  $db->beginTransaction();$existing=[];
  if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$q=$db->prepare("SELECT id,area FROM bh_school_holidays WHERE id IN ($marks) FOR UPDATE");$q->execute($ids);$existing=$q->fetchAll(PDO::FETCH_ASSOC);if(count($existing)!==count($ids)){ $db->rollBack();bhHolidayReply(404,['ok'=>false,'error'=>'holiday_not_found']); }}
  $byArea=[];foreach($existing as $row)$byArea[$row['area']] = (int)$row['id'];
  $update=$db->prepare('UPDATE bh_school_holidays SET holiday_name=?,start_date=?,end_date=?,is_recurring=?,recurrence_type=? WHERE id=?');
  $insert=$db->prepare('INSERT INTO bh_school_holidays(area,holiday_name,start_date,end_date,is_recurring,recurrence_type) VALUES(?,?,?,?,?,?)');
  $savedIds=[];foreach($selected as $area){if(isset($byArea[$area])){$update->execute([$name,$start,$end,$recurring,$recurrenceType,$byArea[$area]]);$savedIds[]=$byArea[$area];}else{$insert->execute([$area,$name,$start,$end,$recurring,$recurrenceType]);$savedIds[]=(int)$db->lastInsertId();}}
  $removeIds=[];foreach($existing as $row)if(!in_array($row['area'],$selected,true))$removeIds[]=(int)$row['id'];
  if($removeIds){$marks=implode(',',array_fill(0,count($removeIds),'?'));$delete=$db->prepare("DELETE FROM bh_school_holidays WHERE id IN ($marks)");$delete->execute($removeIds);}
  $db->commit();
  $placeholders=implode(',',array_fill(0,count($savedIds),'?'));$q=$db->prepare("SELECT id,area,holiday_name,start_date,end_date,is_recurring,recurrence_type FROM bh_school_holidays WHERE id IN ($placeholders) ORDER BY start_date,area");$q->execute($savedIds);bhHolidayReply(200,['ok'=>true,'holidays'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}catch(Throwable $e){error_log('school_holidays_api: '.get_class($e));bhHolidayReply(503,['ok'=>false,'error'=>'school_holidays_unavailable']);}
