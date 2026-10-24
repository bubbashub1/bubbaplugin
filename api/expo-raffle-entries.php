<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);exit;}
$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_start();
function bh_ticket_answer(int $status,array $data):never{http_response_code($status);echo json_encode($data);exit;}
if(empty($_SESSION['bh_admin_authenticated']))bh_ticket_answer(401,['ok'=>false,'error'=>'admin_required']);
if(!empty($_SESSION['bh_admin_last_activity'])&&time()-(int)$_SESSION['bh_admin_last_activity']>1800)bh_ticket_answer(401,['ok'=>false,'error'=>'session_expired']);
$_SESSION['bh_admin_last_activity']=time();
session_write_close();
$root=dirname(__DIR__);
if(!is_file($root.'/wp-load.php'))bh_ticket_answer(503,['ok'=>false,'error'=>'wordpress_unavailable']);
require_once $root.'/wp-load.php';
global $wpdb;
$entries=$wpdb->prefix.'frmt_form_entry';$meta=$wpdb->prefix.'frmt_form_entry_meta';
foreach([$entries,$meta] as $table)if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))!==$table)bh_ticket_answer(503,['ok'=>false,'error'=>'forminator_tables_unavailable']);
$entryCols=$wpdb->get_col('SHOW COLUMNS FROM '.$entries,0);
$metaCols=$wpdb->get_col('SHOW COLUMNS FROM '.$meta,0);
if(count(array_intersect(['entry_id','form_id'],$entryCols))!==2||count(array_intersect(['entry_id','meta_key','meta_value'],$metaCols))!==3)bh_ticket_answer(503,['ok'=>false,'error'=>'forminator_schema_unavailable']);
$dateCol=in_array('date_created',$entryCols,true)?'date_created':(in_array('date_updated',$entryCols,true)?'date_updated':null);
$select=$dateCol?',e.'.$dateCol.' AS submitted_at':",'' AS submitted_at";
$rows=$wpdb->get_results($wpdb->prepare("SELECT e.entry_id{$select} FROM {$entries} e WHERE e.form_id=%d ORDER BY e.entry_id DESC LIMIT 200",76),ARRAY_A);
$ids=array_map('intval',array_column($rows,'entry_id'));
$fields=[];
if($ids){
 $placeholders=implode(',',array_fill(0,count($ids),'%d'));
 $query=$wpdb->prepare("SELECT entry_id,meta_key,meta_value FROM {$meta} WHERE entry_id IN ($placeholders) AND meta_key IN ('name-1','email-1','select-1')",...$ids);
 foreach($wpdb->get_results($query,ARRAY_A) as $m){
  $id=(int)$m['entry_id'];$key=$m['meta_key'];$value=maybe_unserialize($m['meta_value']);
  if($key==='name-1'&&is_array($value))$value=trim(implode(' ',array_filter(array_map('strval',array_intersect_key($value,array_flip(['first-name','middle-name','last-name']))))));
  if(!is_scalar($value))$value='';
  $fields[$id][$key]=mb_substr(trim((string)$value),0,180);
 }
}
$out=[];
foreach($rows as $row){
 $id=(int)$row['entry_id'];$f=$fields[$id]??[];
 $quantity=filter_var($f['select-1']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]);
 $out[]=['id'=>$id,'submitted_at'=>$row['submitted_at'],'name'=>$f['name-1']??'','email'=>$f['email-1']??'','quantity'=>$quantity?:null,'payment_status'=>'unverified'];
}
bh_ticket_answer(200,['ok'=>true,'form_id'=>76,'entries'=>$out,'limited'=>count($rows)===200,'notice'=>'Form submissions only. No payment status has been verified.']);
