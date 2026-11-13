<?php
declare(strict_types=1);
require_once __DIR__.'/api/db.php';
$slug=strtolower(trim((string)($_GET['slug']??'')));
if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)){http_response_code(404);exit('Page not found.');}
$preview=isset($_GET['preview'])&&$_GET['preview']==='1';
if($preview){
 if(session_status()!==PHP_SESSION_ACTIVE){$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';session_name('BUBBAHUB_ADMINSESSID');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();}
 $last=(int)($_SESSION['bh_admin_last_activity']??0);if(empty($_SESSION['bh_admin_authenticated'])||$last===0||time()-$last>1800){http_response_code(403);exit('Admin sign-in required to preview drafts.');}$_SESSION['bh_admin_last_activity']=time();
}
try{
 $page=($preview && isset($_SESSION['bh_page_preview'][$slug]) && is_array($_SESSION['bh_page_preview'][$slug]))
  ? $_SESSION['bh_page_preview'][$slug]
  : null;
 if(!$page){
  $sql="SELECT title,slug,parent_id,description,content_html,meta_description,status FROM bh_pages WHERE slug=?".($preview?"":" AND status='published'")." LIMIT 1";
  $q=bh_mysql()->prepare($sql);$q->execute([$slug]);$page=$q->fetch();
 }
}catch(Throwable $e){error_log('Bubba Hub public page lookup failed: '.$e->getMessage());http_response_code(503);exit('This page is temporarily unavailable.');}
if(!$page){http_response_code(404);exit('Page not found.');}
$title=htmlspecialchars((string)$page['title'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$description=htmlspecialchars((string)($page['meta_description']?:$page['description']),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$content=(string)$page['content_html'];
$children=[];if(!$preview){$childQ=bh_mysql()->prepare("SELECT title,slug,description FROM bh_pages WHERE parent_id=(SELECT id FROM bh_pages WHERE slug=? AND status='published' LIMIT 1) AND status='published' ORDER BY title");$childQ->execute([$slug]);$children=$childQ->fetchAll();}
$parentTitle='';$parentSlug='';if(!$preview&&!empty($page['parent_id'])){$parentQ=bh_mysql()->prepare("SELECT title,slug FROM bh_pages WHERE id=? AND status='published' LIMIT 1");$parentQ->execute([(int)$page['parent_id']]);$parent=$parentQ->fetch();if($parent){$parentTitle=(string)$parent['title'];$parentSlug=(string)$parent['slug'];}}
?><!doctype html>
<html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=$title?> · Bubba Hub</title><?php if($description!==''): ?><meta name="description" content="<?=$description?>"><?php endif; ?><link rel="stylesheet" href="/assets/css/styles.css"><link rel="stylesheet" href="/assets/css/subpages-v2.css"></head>
<body class="bh-subpage-v2"><header class="site-header"><a class="brand" href="/index.html" aria-label="Bubba Hub home"><img class="brand-logo" src="/wp-content/uploads/logo/logoheader.png" alt="Bubba Hub"></a><nav class="main-nav" aria-label="Main menu"><a href="/directory.html">Find activities</a><a href="/events.html">Events</a><a href="/venues.html">Venues</a><a href="/my-hub.html">My Hub</a><a href="/leader.html">Class Leaders</a></nav><a class="button button-soft" href="/account.html">My account</a></header>
<main class="app-shell" style="max-width:1000px;padding:36px 16px 64px"><article class="bh-managed-page"><header class="page-heading"><div><h1><?=$title?></h1><?php if(trim((string)$page['description'])!==''): ?><p><?=htmlspecialchars((string)$page['description'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></p><?php endif; ?></div></header><div class="bh-managed-page-content"><?=$content?></div><?php if($children): ?><section class="bh-managed-page-children"><h2>Explore this section</h2><ul><?php foreach($children as $child): ?><li><a href="/content/<?=rawurlencode((string)$child['slug']) ?>/"><?=htmlspecialchars((string)$child['title'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></a><?php if(trim((string)$child['description'])!==''): ?> — <?=htmlspecialchars((string)$child['description'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?><?php endif; ?></li><?php endforeach; ?></ul></section><?php endif; ?><?php if($parentTitle!==''): ?><p class="bh-managed-page-parent"><a href="/content/<?=rawurlencode($parentSlug) ?>/">← Back to <?=htmlspecialchars($parentTitle,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></a></p><?php endif; ?></article></main>
<footer class="site-footer"><div class="site-footer-bottom"><span>&copy; 2026 Bubba Hub</span><span>Devon &amp; Cornwall</span></div></footer>
</body></html>