<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function bhBlogReply(int $code,array $body):never{http_response_code($code);echo json_encode($body,JSON_UNESCAPED_SLASHES);exit;}
try{
require_once __DIR__.'/db.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$uid=(int)($_SESSION['bh_user_id']??0);
if(!$uid)bhBlogReply(401,['ok'=>false,'message'=>'Sign in as a leader.']);
$db=bh_mysql();
$q=$db->prepare("SELECT role,status,email FROM bh_users WHERE id=? LIMIT 1");$q->execute([$uid]);$user=$q->fetch(PDO::FETCH_ASSOC);
if(!$user||$user['role']!=='leader'||$user['status']!=='active')bhBlogReply(403,['ok'=>false,'message'=>'Leader access required.']);
$cols=$db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers'")->fetchAll(PDO::FETCH_COLUMN);
if(in_array('user_id',$cols,true)){$q=$db->prepare("SELECT * FROM bh_organisers WHERE user_id=? LIMIT 1");$q->execute([$uid]);}
else{$q=$db->prepare("SELECT * FROM bh_organisers WHERE email=? LIMIT 1");$q->execute([$user['email']]);}
$org=$q->fetch(PDO::FETCH_ASSOC);
if(!$org)bhBlogReply(403,['ok'=>false,'message'=>'Organiser profile required.']);
$pro=false;foreach(['plan','membership_plan','membership_tier','subscription_plan','tier'] as $column){if(array_key_exists($column,$org)){$pro=in_array(strtolower(trim((string)$org[$column])),['pro','premium','ultimate'],true);break;}}
if(!$pro)bhBlogReply(403,['ok'=>false,'message'=>'Blog publishing requires Leader Pro.']);
$wp=dirname(__DIR__).'/wp-load.php';
if(!is_file($wp))bhBlogReply(503,['ok'=>false,'message'=>'WordPress is not available.']);
require_once $wp;
if(!function_exists('wp_insert_post'))bhBlogReply(503,['ok'=>false,'message'=>'WordPress publishing unavailable.']);
$oid=(int)$org['id'];$metaKey='_bh_leader_organiser_id';
if($_SERVER['REQUEST_METHOD']==='GET'){
 $posts=get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish'],'posts_per_page'=>100,'meta_key'=>$metaKey,'meta_value'=>(string)$oid]);
 $out=[];foreach($posts as $p)$out[]=['id'=>$p->ID,'title'=>['rendered'=>get_the_title($p)],'raw_title'=>$p->post_title,'raw_content'=>$p->post_content,'status'=>$p->post_status];
 bhBlogReply(200,['ok'=>true,'posts'=>$out]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')bhBlogReply(405,['ok'=>false,'message'=>'Method not allowed.']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if($origin!==''&&parse_url($origin,PHP_URL_HOST)!==($_SERVER['HTTP_HOST']??''))bhBlogReply(403,['ok'=>false,'message'=>'Invalid origin.']);
$body=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($body)||($body['action']??'')!=='save')bhBlogReply(400,['ok'=>false,'message'=>'Invalid request.']);
$title=trim((string)($body['title']??''));$content=trim((string)($body['content']??''));
if($title===''||$content===''||mb_strlen($title)>180||mb_strlen($content)>30000)bhBlogReply(422,['ok'=>false,'message'=>'Enter a title and article within the length limits.']);
$id=(int)($body['post_id']??0);
if($id){$post=get_post($id);if(!$post||$post->post_type!=='post'||(int)get_post_meta($id,$metaKey,true)!==$oid)bhBlogReply(404,['ok'=>false,'message'=>'Article not found.']);}
$payload=['post_title'=>sanitize_text_field($title),'post_content'=>wp_kses_post($content),'post_status'=>'pending','post_type'=>'post'];
if($id)$payload['ID']=$id;
$result=wp_insert_post($payload,true);
if(is_wp_error($result))bhBlogReply(500,['ok'=>false,'message'=>'Could not save article.']);
update_post_meta($result,$metaKey,$oid);
bhBlogReply(200,['ok'=>true,'post_id'=>$result,'status'=>'pending']);
}catch(Throwable $e){error_log('Leader blog: '.$e->getMessage());bhBlogReply(500,['ok'=>false,'message'=>'Blog service temporarily unavailable.']);}
