<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/admin-session-guard.php';
require_once __DIR__.'/db.php';
function pages_out(int $status,array $data): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit; }
if(empty($_SESSION['bh_pages_csrf'])) $_SESSION['bh_pages_csrf']=bin2hex(random_bytes(32));
$csrf=(string)$_SESSION['bh_pages_csrf'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 $raw=file_get_contents('php://input');$body=json_decode($raw?:'',true);
 if(!is_array($body))pages_out(400,['ok'=>false,'error'=>'Invalid request body.']);
 if(!hash_equals($csrf,(string)($body['csrf']??'')))pages_out(403,['ok'=>false,'error'=>'Security check failed. Refresh the page and try again.']);
}
try{
 $db=bh_mysql();
 $method=$_SERVER['REQUEST_METHOD']??'GET';
 if($method==='GET' && isset($_GET['id'])){
  $q=$db->prepare("SELECT id,title,slug,description,content_html,meta_description,status,created_at,updated_at FROM bh_pages WHERE id=? LIMIT 1");$q->execute([(int)$_GET['id']]);$page=$q->fetch();
  if(!$page)pages_out(404,['ok'=>false,'error'=>'Page not found.']);
  pages_out(200,['ok'=>true,'csrf'=>$csrf,'page'=>$page]);
 }
 if($method==='GET'){
  $rows=$db->query("SELECT id,title,slug,status,updated_at FROM bh_pages ORDER BY updated_at DESC,id DESC")->fetchAll();
  pages_out(200,['ok'=>true,'csrf'=>$csrf,'pages'=>$rows]);
 }
 if($method!=='POST')pages_out(405,['ok'=>false,'error'=>'Method not allowed.']);
 $action=(string)($body['action']??'');
 if(in_array($action,['archive','restore'],true)){
  $id=(int)($body['id']??0);if($id<1)pages_out(400,['ok'=>false,'error'=>'Invalid page ID.']);
  $status=$action==='archive'?'archived':'draft';
  $q=$db->prepare("UPDATE bh_pages SET status=?,updated_at=NOW() WHERE id=?");$q->execute([$status,$id]);
  if(!$q->rowCount()){$check=$db->prepare("SELECT id FROM bh_pages WHERE id=?");$check->execute([$id]);if(!$check->fetch())pages_out(404,['ok'=>false,'error'=>'Page not found.']);}
  pages_out(200,['ok'=>true,'csrf'=>$csrf,'message'=>'Page '.$status.'.']);
 }
 if($action!=='save')pages_out(400,['ok'=>false,'error'=>'Unknown action.']);
 $id=(int)($body['id']??0);$title=trim((string)($body['title']??''));$slug=strtolower(trim((string)($body['slug']??'')));
 $description=trim((string)($body['description']??''));$meta=trim((string)($body['meta_description']??''));$status=(string)($body['status']??'draft');
 if($title===''||mb_strlen($title)>190)pages_out(422,['ok'=>false,'error'=>'Enter a page title (maximum 190 characters).']);
 if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)||strlen($slug)>190)pages_out(422,['ok'=>false,'error'=>'Use a URL made from lowercase letters, numbers and hyphens.']);
 if(!in_array($status,['draft','published'],true))pages_out(422,['ok'=>false,'error'=>'Invalid publishing status.']);
 $protected=['index','directory','activities','venues','events','my-hub','planner','leader','account','admin','admin-login','page'];
 if(in_array($slug,$protected,true))pages_out(409,['ok'=>false,'error'=>'That URL is reserved for a Bubba Hub system page. Choose another URL.']);
 $html=(string)($body['content_html']??'');
 if(strlen($html)>500000)pages_out(422,['ok'=>false,'error'=>'Page content is too large (maximum 500 KB).']);
 $allowed='<p><br><h1><h2><h3><h4><strong><b><em><i><u><ul><ol><li><blockquote><a><hr><div><section><span>';
 $html=strip_tags($html,$allowed);
 $html=preg_replace('/\s(on[a-z]+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$html)??'';
 $html=preg_replace('/\s(href|src)\s*=\s*(["\'])\s*(javascript:|data:)[^"\']*\2/i','',$html)??'';
 $db->beginTransaction();
 if($id>0){
  $oldQ=$db->prepare("SELECT * FROM bh_pages WHERE id=? FOR UPDATE");$oldQ->execute([$id]);$old=$oldQ->fetch();
  if(!$old){$db->rollBack();pages_out(404,['ok'=>false,'error'=>'Page not found.']);}
  $rev=$db->prepare("INSERT INTO bh_page_revisions (page_id,title,slug,description,content_html,meta_description,status) VALUES (?,?,?,?,?,?,?)");
  $rev->execute([$id,$old['title'],$old['slug'],$old['description'],$old['content_html'],$old['meta_description'],$old['status']]);
  $q=$db->prepare("UPDATE bh_pages SET title=?,slug=?,description=?,content_html=?,meta_description=?,status=?,updated_at=NOW() WHERE id=?");
  $q->execute([$title,$slug,$description,$html,$meta,$status,$id]);
 }else{
  $q=$db->prepare("INSERT INTO bh_pages (title,slug,description,content_html,meta_description,status) VALUES (?,?,?,?,?,?)");
  $q->execute([$title,$slug,$description,$html,$meta,$status]);$id=(int)$db->lastInsertId();
 }
 $db->commit();
 $q=$db->prepare("SELECT id,title,slug,description,content_html,meta_description,status,created_at,updated_at FROM bh_pages WHERE id=?");$q->execute([$id]);
 pages_out(200,['ok'=>true,'csrf'=>$csrf,'page'=>$q->fetch()]);
}catch(PDOException $e){
 if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
 if((string)$e->getCode()==='23000')pages_out(409,['ok'=>false,'error'=>'That page URL is already in use. Choose a different URL.']);
 error_log('Bubba Hub page manager database error: '.$e->getMessage());
 pages_out(500,['ok'=>false,'error'=>'Page Manager database error. Check that the Page Manager migration has been applied.']);
}catch(Throwable $e){
 if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
 error_log('Bubba Hub page manager error: '.$e->getMessage());
 pages_out(500,['ok'=>false,'error'=>'Page Manager is temporarily unavailable.']);
}