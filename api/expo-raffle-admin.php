<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();
function answer(int $status,array $data):never{http_response_code($status);echo json_encode($data);exit;}
if(empty($_SESSION['bh_admin_authenticated']))answer(401,['ok'=>false,'error'=>'admin_required']);
if(!empty($_SESSION['bh_admin_last_activity'])&&time()-(int)$_SESSION['bh_admin_last_activity']>1800)answer(401,['ok'=>false,'error'=>'session_expired']);
$_SESSION['bh_admin_last_activity']=time();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST');answer(405,['ok'=>false,'error'=>'method_not_allowed']);}
if($method==='POST'){
 $host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');$ref=(string)($_SERVER['HTTP_REFERER']??'');
 $source=$origin!==''?$origin:$ref;
 if(parse_url($source,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($source,PHP_URL_HOST))!==$host)answer(403,['ok'=>false,'error'=>'invalid_origin']);
 if(empty($_SESSION['bh_raffle_csrf'])||!hash_equals($_SESSION['bh_raffle_csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))answer(403,['ok'=>false,'error'=>'invalid_csrf']);
}
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 if($method==='GET' && ($_GET['view']??'')==='submissions'){
  if(empty($_SESSION['bh_raffle_csrf']))$_SESSION['bh_raffle_csrf']=bin2hex(random_bytes(32));
  $q=$db->prepare("SELECT s.id,s.user_id,s.organiser_id,s.title,s.description,s.image_url,s.prize_value,s.redemption_terms,s.fulfilment_contact,s.status,s.review_note,s.created_at,o.name AS organiser_name FROM bh_expo_raffle_submissions s LEFT JOIN bh_organisers o ON o.id=s.organiser_id WHERE s.event_key=? ORDER BY FIELD(s.status,'pending','changes_requested','approved','rejected','withdrawn'),s.created_at DESC LIMIT 200");
  $q->execute(['bubba-me-2027']);answer(200,['ok'=>true,'csrf'=>$_SESSION['bh_raffle_csrf'],'submissions'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
 }
 if($method==='GET'){
  if(empty($_SESSION['bh_raffle_csrf']))$_SESSION['bh_raffle_csrf']=bin2hex(random_bytes(32));
  $q=$db->prepare('SELECT id,title,description,image_url,donor_name,donor_url,prize_value,entry_type,status,sort_order FROM bh_expo_raffle_prizes WHERE event_key=? ORDER BY sort_order,id LIMIT 250');
  $q->execute(['bubba-me-2027']);answer(200,['ok'=>true,'csrf'=>$_SESSION['bh_raffle_csrf'],'prizes'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
 }
 $v=json_decode(file_get_contents('php://input'),true);
 if(!is_array($v))answer(400,['ok'=>false,'error'=>'invalid_json']);
 if(($v['action']??'')==='review_submission'){
  $sid=(int)($v['submission_id']??0);$decision=(string)($v['decision']??'');$note=trim((string)($v['review_note']??''));
  if($sid<=0||!in_array($decision,['approved','rejected','changes_requested'],true)||mb_strlen($note)>2000)answer(422,['ok'=>false,'error'=>'invalid_review']);
  $db->beginTransaction();
  try{
   $q=$db->prepare("SELECT s.*,o.name AS organiser_name FROM bh_expo_raffle_submissions s LEFT JOIN bh_organisers o ON o.id=s.organiser_id WHERE s.id=? AND s.event_key=? FOR UPDATE");
   $q->execute([$sid,'bubba-me-2027']);$submission=$q->fetch(PDO::FETCH_ASSOC);
   if(!$submission){$db->rollBack();answer(404,['ok'=>false,'error'=>'submission_not_found']);}
   if(!in_array($submission['status'],['pending','changes_requested'],true)){$db->rollBack();answer(409,['ok'=>false,'error'=>'already_reviewed']);}
   if($decision==='approved'){
    $p=$db->prepare("INSERT INTO bh_expo_raffle_prizes (event_key,title,description,image_url,donor_name,donor_url,prize_value,entry_type,status,sort_order) VALUES (?,?,?,?,?,?,?,'raffle','draft',0)");
    $p->execute(['bubba-me-2027',$submission['title'],$submission['description'],$submission['image_url'],$submission['organiser_name']??'',null,$submission['prize_value']]);
   }
   $q=$db->prepare("UPDATE bh_expo_raffle_submissions SET status=?,review_note=?,reviewed_at=NOW() WHERE id=? AND status IN ('pending','changes_requested')");
   $q->execute([$decision,$note,$sid]);$db->commit();answer(200,['ok'=>true,'status'=>$decision]);
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
 $title=trim((string)($v['title']??''));if($title===''||mb_strlen($title)>160)answer(422,['ok'=>false,'error'=>'invalid_title']);
 $description=trim((string)($v['description']??''));
 if(mb_strlen($description)>3000)answer(422,['ok'=>false,'error'=>'description_too_long']);
 $urls=[];
 foreach(['image_url','donor_url'] as $key){$url=trim((string)($v[$key]??''));if($url!==''&&(strlen($url)>500||!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https'))answer(422,['ok'=>false,'error'=>'invalid_'.$key]);$urls[$key]=$url?:null;}
 $donor=mb_substr(trim((string)($v['donor_name']??'')),0,160);
 $value=mb_substr(trim((string)($v['prize_value']??'')),0,60);
 $type=(string)($v['entry_type']??'raffle');$status=(string)($v['status']??'draft');
 if(!in_array($type,['raffle','giveaway'],true)||!in_array($status,['draft','published','claimed'],true))answer(422,['ok'=>false,'error'=>'invalid_choice']);
 $order=max(-10000,min(10000,(int)($v['sort_order']??0)));$id=(int)($v['id']??0);
 $params=[$title,$description,$urls['image_url'],$donor,$urls['donor_url'],$value,$type,$status,$order];
 if($id>0){$q=$db->prepare('UPDATE bh_expo_raffle_prizes SET title=?,description=?,image_url=?,donor_name=?,donor_url=?,prize_value=?,entry_type=?,status=?,sort_order=? WHERE id=? AND event_key=?');$q->execute([...$params,$id,'bubba-me-2027']);if(!$q->rowCount()){ $check=$db->prepare('SELECT id FROM bh_expo_raffle_prizes WHERE id=? AND event_key=?');$check->execute([$id,'bubba-me-2027']);if(!$check->fetchColumn())answer(404,['ok'=>false,'error'=>'not_found']);}}
 else{$q=$db->prepare('INSERT INTO bh_expo_raffle_prizes (title,description,image_url,donor_name,donor_url,prize_value,entry_type,status,sort_order,event_key) VALUES (?,?,?,?,?,?,?,?,?,?)');$q->execute([...$params,'bubba-me-2027']);$id=(int)$db->lastInsertId();}
 answer(200,['ok'=>true,'id'=>$id]);
}catch(Throwable $e){answer(503,['ok'=>false,'error'=>'database_unavailable']);}
