<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function answer(int $status,array $data):never{http_response_code($status);echo json_encode($data);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')answer(405,['ok'=>false,'error'=>'method_not_allowed']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
if(parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)answer(403,['ok'=>false,'error'=>'invalid_origin']);
$isAdmin=false;
session_name('BUBBAHUB_ADMINSESSID');session_start();$isAdmin=!empty($_SESSION['bh_admin_authenticated']);$adminToken=(string)($_SESSION['bh_expo_csrf']??'');session_write_close();
if(!$isAdmin){session_name('BH_EXPO_APPLY');session_start();$token=(string)($_SESSION['expo_apply_csrf']??'');session_write_close();}
else $token=$adminToken;
$given=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
if($token===''||!hash_equals($token,$given))answer(403,['ok'=>false,'error'=>'invalid_csrf']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>2200000)answer(413,['ok'=>false,'error'=>'file_too_large']);
if(!isset($_FILES['logo'])||!is_uploaded_file($_FILES['logo']['tmp_name']))answer(422,['ok'=>false,'error'=>'file_required']);
$file=$_FILES['logo'];if($file['error']!==UPLOAD_ERR_OK||$file['size']<1||$file['size']>2097152)answer(422,['ok'=>false,'error'=>'file_too_large']);
if(!function_exists('getimagesize')||!function_exists('imagecreatefromstring'))answer(503,['ok'=>false,'error'=>'image_processing_unavailable']);
$info=@getimagesize($file['tmp_name']);if(!$info||$info[0]>4000||$info[1]>4000||$info[0]<16||$info[1]<16||!in_array($info[2],[IMAGETYPE_PNG,IMAGETYPE_JPEG,IMAGETYPE_WEBP],true))answer(422,['ok'=>false,'error'=>'unsupported_image']);
$bytes=@file_get_contents($file['tmp_name']);$image=$bytes!==false?@imagecreatefromstring($bytes):false;
if(!$image)answer(422,['ok'=>false,'error'=>'invalid_image']);
$w=imagesx($image);$h=imagesy($image);$scale=min(1,800/max($w,$h));$nw=max(1,(int)round($w*$scale));$nh=max(1,(int)round($h*$scale));
$canvas=imagecreatetruecolor($nw,$nh);imagealphablending($canvas,false);imagesavealpha($canvas,true);$clear=imagecolorallocatealpha($canvas,0,0,0,127);imagefill($canvas,0,0,$clear);imagecopyresampled($canvas,$image,0,0,0,0,$nw,$nh,$w,$h);imagedestroy($image);
$directory=dirname(__DIR__).'/wp-content/uploads/expo-logos';
if(!is_dir($directory)&&!@mkdir($directory,0755,true))answer(503,['ok'=>false,'error'=>'storage_unavailable']);
$deny=$directory.'/.htaccess';if(!file_exists($deny))@file_put_contents($deny,"Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py|sh)$\">\nRequire all denied\n</FilesMatch>\n");
$name=bin2hex(random_bytes(16)).'.png';$path=$directory.'/'.$name;
if(!imagepng($canvas,$path,7)){imagedestroy($canvas);answer(503,['ok'=>false,'error'=>'storage_unavailable']);}imagedestroy($canvas);@chmod($path,0644);
answer(200,['ok'=>true,'logo_url'=>'https://'.$host.'/wp-content/uploads/expo-logos/'.$name]);
