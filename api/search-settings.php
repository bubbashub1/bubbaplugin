<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('BUBBAHUB_ADMINSESSID');
session_start();

$defaults=[
 'category'=>true,
 'region'=>true,
 'town'=>true,
 'nearby'=>true,
 'age'=>true,
 'day'=>true,
 'price'=>true,
 'session_length'=>true,
 'sen'=>true,
 'term_time'=>true,
 'booking'=>true,
 'accessibility'=>true,
 'free'=>true
];

function respond(int $status,array $data):never{
 http_response_code($status);
 echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
 exit;
}

try{
 require __DIR__.'/db.php';
 $db=bh_mysql();
 $db->exec("CREATE TABLE IF NOT EXISTS bh_site_settings(
   setting_key VARCHAR(80) PRIMARY KEY,
   setting_json LONGTEXT NOT NULL,
   updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

 $row=$db->prepare("SELECT setting_json FROM bh_site_settings WHERE setting_key=? LIMIT 1");
 $row->execute(['advanced_filters']);
 $stored=$row->fetchColumn();
 $settings=$defaults;
 if(is_string($stored)){
   $decoded=json_decode($stored,true);
   if(is_array($decoded)){
     foreach($defaults as $key=>$value){
       if(array_key_exists($key,$decoded)) $settings[$key]=(bool)$decoded[$key];
     }
   }
 }

 if($_SERVER['REQUEST_METHOD']==='GET'){
   respond(200,['ok'=>true,'data'=>$settings]);
 }

 if($_SERVER['REQUEST_METHOD']!=='POST') respond(405,['ok'=>false,'error'=>'GET or POST required.']);
 if(empty($_SESSION['bh_admin_authenticated'])) respond(401,['ok'=>false,'error'=>'Admin login required.']);

 $input=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($input)||!is_array($input['filters']??null)) respond(400,['ok'=>false,'error'=>'Invalid filter settings.']);

 foreach($defaults as $key=>$value){
   if(array_key_exists($key,$input['filters'])) $settings[$key]=(bool)$input['filters'][$key];
 }

 $stmt=$db->prepare("INSERT INTO bh_site_settings(setting_key,setting_json) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_json=VALUES(setting_json)");
 $stmt->execute(['advanced_filters',json_encode($settings,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
 respond(200,['ok'=>true,'data'=>$settings,'message'=>'Advanced filter settings saved.']);
}catch(Throwable $e){
 respond(500,['ok'=>false,'error'=>'Unable to load or save advanced filter settings.','message'=>$e->getMessage()]);
}
?>