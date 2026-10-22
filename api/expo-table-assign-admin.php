<?php
declare(strict_types=1);
/** Admin-only table assignment API. Never exposes floor plan publicly. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$secure=(!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS'])!=='off');
if(session_status()!==PHP_SESSION_ACTIVE){
 session_name('BUBBAHUB_ADMINSESSID');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
 session_start();
}
function out(int $code,array $data):never{http_response_code($code);echo json_encode($data);exit;}
if(empty($_SESSION['bh_admin_authenticated']))out(401,['ok'=>false,'error'=>'admin_required']);
if(!empty($_SESSION['bh_admin_last_activity'])&&time()-(int)$_SESSION['bh_admin_last_activity']>1800)out(401,['ok'=>false,'error'=>'session_expired']);
$_SESSION['bh_admin_last_activity']=time();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');out(405,['ok'=>false,'error'=>'method_not_allowed']);}
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
$origin=(string)($_SERVER['HTTP_ORIGIN']??($_SERVER['HTTP_REFERER']??''));
if(!$secure||parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)out(403,['ok'=>false,'error'=>'invalid_origin']);
if(empty($_SESSION['bh_floor_plan_csrf'])||!hash_equals($_SESSION['bh_floor_plan_csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))out(403,['ok'=>false,'error'=>'invalid_csrf']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>2048)out(413,['ok'=>false,'error'=>'too_large']);
$data=json_decode((string)file_get_contents('php://input'),true);
$id=filter_var($data['exhibitor_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$table=$data['table_id']??null;
if(!$id||!($table===null||(is_int($table)&&$table>0)))out(422,['ok'=>false,'error'=>'invalid_assignment']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();$db->beginTransaction();
 $plan=$db->prepare('SELECT layout_json FROM bh_expo_floor_plans WHERE event_key=? FOR UPDATE');
 $plan->execute(['bubba-me-2027']);$raw=$plan->fetchColumn();
 if($raw===false){$db->rollBack();out(409,['ok'=>false,'error'=>'save_floor_plan_first']);}
 $items=json_decode((string)$raw,true);$found=false;
 foreach(is_array($items)?$items:[] as $item){if($table!==null&&(int)($item['id']??0)===$table&&($item['type']??'')==='table'){if(!empty($item['exhibitor'])){$db->rollBack();out(409,['ok'=>false,'error'=>'table_has_manual_reservation']);}$found=true;}}
 if($table!==null&&!$found){$db->rollBack();out(422,['ok'=>false,'error'=>'table_not_found']);}
 $stmt=$db->prepare("SELECT id,application_status FROM bh_expo_exhibitors WHERE id=? AND event_key='bubba-me-2027' FOR UPDATE");
 $stmt->execute([$id]);$ex=$stmt->fetch(PDO::FETCH_ASSOC);
 if(!$ex){$db->rollBack();out(404,['ok'=>false,'error'=>'exhibitor_not_found']);}
 if($table!==null&&$ex['application_status']!=='approved'){$db->rollBack();out(422,['ok'=>false,'error'=>'approve_exhibitor_first']);}
 if($table!==null){
  $used=$db->prepare("SELECT id FROM bh_expo_exhibitors WHERE event_key='bubba-me-2027' AND application_status='approved' AND assigned_table=? AND id<>? LIMIT 1 FOR UPDATE");
  $used->execute([(string)$table,$id]);
  if($used->fetchColumn()){$db->rollBack();out(409,['ok'=>false,'error'=>'table_already_assigned']);}
 }
 if($table!==null){$held=$db->prepare("SELECT id FROM bh_expo_exhibitors WHERE event_key='bubba-me-2027' AND preferred_table=? AND application_status IN ('pending','approved','confirmed') AND id<>? LIMIT 1");$held->execute([(string)$table,$id]);if($held->fetchColumn()){$db->rollBack();out(409,['ok'=>false,'error'=>'table_reserved_by_applicant']);}}
 // When the admin moves an approved exhibitor, update their preferred table
 // atomically too; otherwise the old preference remains a ghost reservation.
 $update=$db->prepare("UPDATE bh_expo_exhibitors SET assigned_table=?,preferred_table=CASE WHEN ? IS NULL THEN preferred_table ELSE ? END WHERE id=? AND event_key='bubba-me-2027'");
 $update->execute([$table===null?null:(string)$table,$table===null?null:(string)$table,$table===null?null:(string)$table,$id]);$db->commit();
 out(200,['ok'=>true,'exhibitor_id'=>$id,'assigned_table'=>$table]);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('expo table assignment: '.get_class($e));out(503,['ok'=>false,'error'=>'assignment_unavailable']);}
