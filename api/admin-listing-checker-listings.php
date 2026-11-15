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
function bh_lcl_reply(int $status,array $data): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if(empty($_SESSION['bh_admin_authenticated'])) bh_lcl_reply(401,['ok'=>false,'error'=>'Admin login required.']);
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET') bh_lcl_reply(405,['ok'=>false,'error'=>'GET only.']);
try {
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 $page=max(1,(int)($_GET['page']??1));
 $perPage=max(10,min(100,(int)($_GET['per_page']??50)));
 $offset=($page-1)*$perPage;
 $search=trim((string)($_GET['q']??''));
 $filter=(string)($_GET['status']??'all');
 $where=["q.check_type='health'"];
 $params=[];
 if($search!=='') { $where[]='a.title LIKE ?'; $params[]='%'.$search.'%'; }
 $statusSql=[
  'checked'=>"q.last_checked_at IS NOT NULL AND q.status NOT IN ('failed','skipped','processing')",
  'waiting'=>"q.last_checked_at IS NULL AND q.status='pending'",
  'failed'=>"q.status='failed'",
  'skipped'=>"q.status='skipped'",
  'processing'=>"q.status='processing'"
 ];
 if(isset($statusSql[$filter])) $where[]=$statusSql[$filter];
 $whereSql=implode(' AND ',$where);
 $count=$db->prepare('SELECT COUNT(*) FROM bh_listing_check_queue q INNER JOIN bh_activities a ON a.id=q.target_id WHERE '.$whereSql);
 $count->execute($params); $total=(int)$count->fetchColumn();
 $sql="SELECT q.id AS queue_id,q.target_id AS activity_id,q.status AS queue_status,q.last_checked_at,q.next_check_at,q.last_error,a.title
       FROM bh_listing_check_queue q INNER JOIN bh_activities a ON a.id=q.target_id
       WHERE $whereSql ORDER BY (q.last_checked_at IS NULL) DESC,q.last_checked_at DESC,a.title ASC LIMIT ? OFFSET ?";
 $stmt=$db->prepare($sql);
 foreach($params as $i=>$value) $stmt->bindValue($i+1,$value,PDO::PARAM_STR);
 $stmt->bindValue(count($params)+1,$perPage,PDO::PARAM_INT);
 $stmt->bindValue(count($params)+2,$offset,PDO::PARAM_INT);
 $stmt->execute(); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
 $ids=array_map(static fn($r)=>(int)$r['activity_id'],$rows);
 $findings=[];
 if($ids){
  $marks=implode(',',array_fill(0,count($ids),'?'));
  $f=$db->prepare("SELECT activity_id,finding_type,review_status,confidence,candidate_json,checked_at FROM bh_listing_check_findings WHERE check_type='health' AND activity_id IN ($marks) ORDER BY checked_at DESC,id DESC");
  $f->execute($ids);
  foreach($f->fetchAll(PDO::FETCH_ASSOC) as $item){
   $id=(int)$item['activity_id'];
   $candidate=json_decode((string)$item['candidate_json'],true);
   $findings[$id][]=['type'=>$item['finding_type'],'status'=>$item['review_status'],'confidence'=>$item['confidence'],'details'=>is_array($candidate)?$candidate:[],'checked_at'=>$item['checked_at']];
  }
 }
 foreach($rows as &$row){
  $qs=$row['queue_status'];
  $row['display_status']=$qs==='failed'?'Failed':($qs==='skipped'?'Skipped':($qs==='processing'?'In progress':(!empty($row['last_checked_at'])?'Checked':'Waiting')));
  $row['findings']=$findings[(int)$row['activity_id']]??[];
  $row['issue_count']=count(array_filter($row['findings'],static fn($f)=>!in_array($f['status'],['resolved','incorrect','rejected'],true)));
 }
 unset($row);
 bh_lcl_reply(200,['ok'=>true,'page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>max(1,(int)ceil($total/$perPage)),'data'=>$rows]);
} catch(Throwable $e) {
 error_log('Listing checker checked listings: '.$e->getMessage());
 bh_lcl_reply(500,['ok'=>false,'error'=>'Unable to load checked listings.']);
}
