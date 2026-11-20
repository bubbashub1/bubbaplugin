<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/admin-session-guard.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/page-document-validator-v1.php';
function bh_vb_out(int $status,array $data): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit; }
if(empty($_SESSION['bh_visual_builder_csrf']))$_SESSION['bh_visual_builder_csrf']=bin2hex(random_bytes(32));
$csrf=(string)$_SESSION['bh_visual_builder_csrf'];
$method=$_SERVER['REQUEST_METHOD']??'GET';$body=[];
if($method==='POST'){
 $raw=file_get_contents('php://input');$body=json_decode($raw?:'',true);
 if(!is_array($body))bh_vb_out(400,['ok'=>false,'error'=>'Invalid request body.']);
 if(!hash_equals($csrf,(string)($body['csrf']??'')))bh_vb_out(403,['ok'=>false,'error'=>'Security check failed. Refresh and try again.']);
}elseif($method!=='GET')bh_vb_out(405,['ok'=>false,'error'=>'Method not allowed.']);
try{
 $db=bh_mysql();
 if($method==='GET'){
  $check=$db->query("SHOW TABLES LIKE 'bh_visual_page_documents'")->fetchColumn();
  if(!$check)bh_vb_out(200,['ok'=>true,'csrf'=>$csrf,'setup_required'=>true]);
  $id=(int)($_GET['page_id']??0);if($id<1)bh_vb_out(400,['ok'=>false,'error'=>'A page ID is required.']);
  $q=$db->prepare("SELECT id,title,slug,status FROM bh_pages WHERE id=? LIMIT 1");$q->execute([$id]);$page=$q->fetch();if(!$page)bh_vb_out(404,['ok'=>false,'error'=>'Page not found.']);
  $d=$db->prepare("SELECT document_json,updated_at FROM bh_visual_page_documents WHERE page_id=?");$d->execute([$id]);$row=$d->fetch();
  bh_vb_out(200,['ok'=>true,'csrf'=>$csrf,'page'=>$page,'draft'=>$row?json_decode($row['document_json'],true):null,'updated_at'=>$row['updated_at']??null]);
 }
 $action=(string)($body['action']??'');
 if($action==='setup'){
  $db->exec("CREATE TABLE IF NOT EXISTS bh_visual_page_documents (page_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, document_json MEDIUMTEXT NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, CONSTRAINT fk_bh_visual_page_doc FOREIGN KEY (page_id) REFERENCES bh_pages(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $db->exec("CREATE TABLE IF NOT EXISTS bh_visual_page_document_revisions (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,page_id BIGINT UNSIGNED NOT NULL,document_json MEDIUMTEXT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_bh_visual_doc_rev (page_id,created_at),CONSTRAINT fk_bh_visual_page_rev FOREIGN KEY (page_id) REFERENCES bh_pages(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  bh_vb_out(200,['ok'=>true,'csrf'=>$csrf,'message'=>'Visual Builder draft storage set up.']);
 }
 if($action!=='save_draft')bh_vb_out(400,['ok'=>false,'error'=>'Unknown action.']);
 $id=(int)($body['page_id']??0);if($id<1)bh_vb_out(422,['ok'=>false,'error'=>'Page ID required.']);
 $doc=$body['document']??null;$validation=bh_validate_page_document_v1($doc);if(!$validation['ok'])bh_vb_out(422,['ok'=>false,'error'=>'Document validation failed.','details'=>$validation['errors']]);
 if(($doc['page']['slug']??'')==='')bh_vb_out(422,['ok'=>false,'error'=>'Invalid document slug.']);
 $pageQ=$db->prepare("SELECT id,title,slug,status FROM bh_pages WHERE id=? FOR UPDATE");$pageQ->execute([$id]);$page=$pageQ->fetch();if(!$page)bh_vb_out(404,['ok'=>false,'error'=>'Page not found.']);
 if($page['status']==='archived')bh_vb_out(409,['ok'=>false,'error'=>'Archived pages cannot be edited in the visual builder.']);
 // Page identity stays anchored to Page Manager; no slug or status changes here.
 $doc['page']['title']=$page['title'];$doc['page']['slug']=$page['slug'];
 $json=json_encode($doc,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!is_string($json))bh_vb_out(422,['ok'=>false,'error'=>'Document could not be encoded.']);
 $db->beginTransaction();
 $oldQ=$db->prepare("SELECT document_json FROM bh_visual_page_documents WHERE page_id=? FOR UPDATE");$oldQ->execute([$id]);$old=$oldQ->fetch();
 if($old){$rev=$db->prepare("INSERT INTO bh_visual_page_document_revisions (page_id,document_json) VALUES (?,?)");$rev->execute([$id,$old['document_json']]);}
 $save=$db->prepare("INSERT INTO bh_visual_page_documents (page_id,document_json) VALUES (?,?) ON DUPLICATE KEY UPDATE document_json=VALUES(document_json),updated_at=NOW()");$save->execute([$id,$json]);$db->commit();
 bh_vb_out(200,['ok'=>true,'csrf'=>$csrf,'message'=>'Draft document saved. Published page unchanged.','document'=>$doc]);
}catch(PDOException $e){if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();error_log('Visual builder draft API DB error: '.$e->getMessage());bh_vb_out(500,['ok'=>false,'error'=>'Draft storage is unavailable. Check the server log.']);}
catch(Throwable $e){if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();error_log('Visual builder draft API error: '.$e->getMessage());bh_vb_out(500,['ok'=>false,'error'=>'Visual Builder draft request failed.']);}
