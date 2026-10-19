<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);exit;}
// The selection layout is for active exhibitor applications only.
$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
if(!$secure){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'https_required']);exit;}
if(session_status()!==PHP_SESSION_ACTIVE){
 ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
 session_name('BH_EXPO_APPLY');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
 session_start();
}
$token=(string)($_SERVER['HTTP_X_EXPO_APPLY_CSRF']??'');
if(empty($_SESSION['expo_apply_csrf'])||!hash_equals((string)$_SESSION['expo_apply_csrf'],$token)){
 http_response_code(403);echo json_encode(['ok'=>false,'error'=>'application_session_required']);exit;
}
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $q=$db->prepare('SELECT layout_json,revision FROM bh_expo_floor_plans WHERE event_key=?');
 $q->execute(['bubba-me-2027']);$row=$q->fetch(PDO::FETCH_ASSOC);
 if(!$row){http_response_code(503);echo json_encode(['ok'=>false,'error'=>'floor_plan_not_ready']);exit;}
 $items=json_decode((string)$row['layout_json'],true);
 if(!is_array($items))throw new RuntimeException('invalid_layout');
 $q=$db->prepare("SELECT preferred_table FROM bh_expo_exhibitors WHERE event_key=? AND application_status IN ('pending','approved','confirmed') AND preferred_table IS NOT NULL");
 $q->execute(['bubba-me-2027']);$taken=array_fill_keys(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)),true);
 $tables=[];
 foreach($items as $item){
  if(($item['type']??'')!=='table')continue;
  $id=(int)($item['id']??0);
  if($id<1)continue;
  $tables[]=['id'=>$id,'label'=>mb_substr((string)($item['label']??$id),0,35),'x'=>(float)($item['x']??0),'y'=>(float)($item['y']??0),'angle'=>(int)($item['angle']??0),'available'=>!isset($taken[$id])&&empty($item['exhibitor'])];
 }
 echo json_encode(['ok'=>true,'revision'=>(int)$row['revision'],'tables'=>$tables],JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){error_log('expo table selection: '.get_class($e));http_response_code(503);echo json_encode(['ok'=>false,'error'=>'table_selection_unavailable']);}
