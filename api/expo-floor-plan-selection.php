<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);exit;}
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $q=$db->prepare('SELECT layout_json,revision FROM bh_expo_floor_plans WHERE event_key=?');
 $q->execute(['bubba-me-2027']);$row=$q->fetch(PDO::FETCH_ASSOC);
 if(!$row){http_response_code(503);echo json_encode(['ok'=>false,'error'=>'floor_plan_not_ready']);exit;}
 $items=json_decode((string)$row['layout_json'],true);
 if(!is_array($items))throw new RuntimeException('invalid_layout');
 $q=$db->prepare("SELECT preferred_table,assigned_table FROM bh_expo_exhibitors WHERE event_key=? AND application_status IN ('pending','approved','confirmed') AND (preferred_table IS NOT NULL OR assigned_table IS NOT NULL)");
 $q->execute(['bubba-me-2027']);$taken=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $reservation){foreach(['preferred_table','assigned_table'] as $column){$number=(int)($reservation[$column]??0);if($number>0)$taken[$number]=true;}}
 $tables=[];
 foreach($items as $item){
  if(($item['type']??'')!=='table')continue;
  $id=(int)($item['id']??0);
  if($id<1)continue;
  $tables[]=['id'=>$id,'label'=>mb_substr((string)($item['label']??$id),0,35),'x'=>(float)($item['x']??0),'y'=>(float)($item['y']??0),'angle'=>(int)($item['angle']??0),'available'=>!isset($taken[$id])&&empty($item['exhibitor'])];
 }
 echo json_encode(['ok'=>true,'revision'=>(int)$row['revision'],'tables'=>$tables],JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){error_log('expo table selection: '.get_class($e));http_response_code(503);echo json_encode(['ok'=>false,'error'=>'table_selection_unavailable']);}
