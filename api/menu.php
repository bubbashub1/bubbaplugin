<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'); session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']); session_start();
function defaults():array{return [
'main'=>[['label'=>'Find activities','url'=>'directory.html'],['label'=>'Events','url'=>'events.html'],['label'=>'Venues','url'=>'venues.html'],['label'=>'My planner','url'=>'planner.html'],['label'=>'Calendar','url'=>'calendar.html'],['label'=>'My Hub','url'=>'my-hub.html'],['label'=>'For class leaders','url'=>'leader.html'],['label'=>'Admin','url'=>'admin.html']],
'footer_main'=>[['label'=>'Find activities','url'=>'directory.html'],['label'=>'My Hub','url'=>'my-hub.html'],['label'=>'Support & Guidance','url'=>'help-support.html'],['label'=>'Class Leaders','url'=>'leader.html'],['label'=>'Account','url'=>'account.html']],
'footer_tools'=>[['label'=>'Events','url'=>'events.html'],['label'=>'Venues','url'=>'venues.html'],['label'=>'Planner','url'=>'planner.html'],['label'=>'Calendar','url'=>'calendar.html'],['label'=>'Admin','url'=>'admin.html']],
'footer_legal'=>[['label'=>'Privacy','url'=>'privacy.html'],['label'=>'Terms & Conditions','url'=>'terms.html'],['label'=>'Contact Bubba Hub','url'=>'mailto:contact@bubbahub.co.uk']]
];}
function out(int $s,array $d):never{http_response_code($s);echo json_encode($d,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
try{
 require __DIR__.'/db.php';$db=bh_mysql();
 $db->exec("CREATE TABLE IF NOT EXISTS bh_site_menus(menu_key VARCHAR(40) PRIMARY KEY,menu_json LONGTEXT NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $def=defaults();
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $out=$def;$q=$db->query("SELECT menu_key,menu_json FROM bh_site_menus");
  foreach($q->fetchAll() as $r){$v=json_decode($r['menu_json'],true);if(is_array($v))$out[$r['menu_key']]=$v;}
  foreach($out as &$items){$items=array_values(array_filter($items,fn($x)=>is_array($x)&&trim((string)($x['label']??''))!==''&&trim((string)($x['url']??''))!==''&&($x['visible']??true)!==false));}unset($items);
  out(200,['ok'=>true,'data'=>$out]);
 }
 if($_SERVER['REQUEST_METHOD']!=='POST')out(405,['ok'=>false,'error'=>'GET or POST required.']);
 if(empty($_SESSION['bh_admin_authenticated']))out(401,['ok'=>false,'error'=>'Admin login required.']);
 $in=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($in)||!is_array($in['menus']??null))out(400,['ok'=>false,'error'=>'Invalid menu data.']);
 $stmt=$db->prepare("INSERT INTO bh_site_menus(menu_key,menu_json) VALUES(?,?) ON DUPLICATE KEY UPDATE menu_json=VALUES(menu_json)");
 foreach(array_keys($def) as $key){
  $clean=[];$items=is_array($in['menus'][$key]??null)?$in['menus'][$key]:[];
  foreach($items as $item){if(!is_array($item))continue;$label=trim((string)($item['label']??''));$url=trim((string)($item['url']??''));if($label===''||$url==='')continue;$clean[]=['label'=>$label,'url'=>$url,'visible'=>($item['visible']??true)!==false,'order'=>count($clean)+1];}
  $stmt->execute([$key,json_encode($clean,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
 }
 out(200,['ok'=>true,'message'=>'Menus saved.']);
}catch(Throwable $e){out(500,['ok'=>false,'error'=>'Unable to load or save menus.','message'=>$e->getMessage()]);}
?>