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
 }
 // Release the admin session lock once authentication and CSRF checks are complete.
 if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $db->exec("CREATE TABLE IF NOT EXISTS bh_school_holidays (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,area VARCHAR(40) NOT NULL,holiday_name VARCHAR(100) NOT NULL,start_date DATE NOT NULL,end_date DATE NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,KEY idx_bh_school_holidays_area_dates(area,start_date,end_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 if($method==='GET'&&$action==='admin'){
  if(empty($_SESSION['bh_school_holidays_csrf']))$_SESSION['bh_school_holidays_csrf']=bin2hex(random_bytes(32));
  $rows=$db->query('SELECT id,area,holiday_name,start_date,end_date FROM bh_school_holidays ORDER BY start_date,area,holiday_name')->fetchAll(PDO::FETCH_ASSOC);
  bhHolidayReply(200,['ok'=>true,'csrf_token'=>$_SESSION['bh_school_holidays_csrf'],'areas'=>$areas,'holidays'=>$rows]);
 }
 if($method==='GET'){
  $area=trim((string)($_GET['area']??''));$from=(string)($_GET['from']??'');$to=(string)($_GET['to']??'');
  if(!in_array($area,$areas,true)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)||$to<$from)bhHolidayReply(422,['ok'=>false,'error'=>'invalid_date_range']);
  $q=$db->prepare('SELECT id,area,holiday_name,start_date,end_date FROM bh_school_holidays WHERE area=? AND start_date<=? AND end_date>=? ORDER BY start_date');$q->execute([$area,$to,$from]);
  bhHolidayReply(200,['ok'=>true,'area'=>$area,'holidays'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
 }
 if($method!=='POST'){header('Allow: GET, POST');bhHolidayReply(405,['ok'=>false,'error'=>'method_not_allowed']);}
 if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)bhHolidayReply(413,['ok'=>false,'error'=>'request_too_large']);
 $input=json_decode((string)file_get_contents('php://input'),true);if(!is_array($input))bhHolidayReply(400,['ok'=>false,'error'=>'invalid_json']);
 $action=(string)($input['action']??'');
 if($action==='delete'){$id=filter_var($input['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id)bhHolidayReply(422,['ok'=>false,'error'=>'invalid_id']);$q=$db->prepare('DELETE FROM bh_school_holidays WHERE id=?');$q->execute([$id]);if(!$q->rowCount())bhHolidayReply(404,['ok'=>false,'error'=>'holiday_not_found']);bhHolidayReply(200,['ok'=>true,'deleted_id'=>$id]);}
 if($action!=='save')bhHolidayReply(422,['ok'=>false,'error'=>'invalid_action']);
 $id=filter_var($input['id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);$area=trim((string)($input['area']??''));$name=trim((string)($input['holiday_name']??''));$start=(string)($input['start_date']??'');$end=(string)($input['end_date']??'');
 $validDate=static function(string $v):bool{$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v,new DateTimeZone('Europe/London'));return $d instanceof DateTimeImmutable&&$d->format('Y-m-d')===$v;};
 if($id===false||!in_array($area,$areas,true)||$name===''||mb_strlen($name)>100||!$validDate($start)||!$validDate($end)||$end<$start)bhHolidayReply(422,['ok'=>false,'error'=>'check_area_name_and_dates']);
 if($id>0){$q=$db->prepare('UPDATE bh_school_holidays SET area=?,holiday_name=?,start_date=?,end_date=? WHERE id=?');$q->execute([$area,$name,$start,$end,$id]);if(!$q->rowCount()){$check=$db->prepare('SELECT id FROM bh_school_holidays WHERE id=?');$check->execute([$id]);if(!$check->fetchColumn())bhHolidayReply(404,['ok'=>false,'error'=>'holiday_not_found']);}}
 else{$q=$db->prepare('INSERT INTO bh_school_holidays(area,holiday_name,start_date,end_date) VALUES(?,?,?,?)');$q->execute([$area,$name,$start,$end]);$id=(int)$db->lastInsertId();}
 $q=$db->prepare('SELECT id,area,holiday_name,start_date,end_date FROM bh_school_holidays WHERE id=?');$q->execute([$id]);bhHolidayReply(200,['ok'=>true,'holiday'=>$q->fetch(PDO::FETCH_ASSOC)]);
}catch(Throwable $e){error_log('school_holidays_api: '.get_class($e));bhHolidayReply(503,['ok'=>false,'error'=>'school_holidays_unavailable']);}
