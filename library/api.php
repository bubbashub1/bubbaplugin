<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
const MAX_IMAGE_BYTES=8388608; const ALLOWED_FOLDERS=['general','home','listings','logos']; const ALLOWED_EXTENSIONS=['jpg','jpeg','png','webp','gif'];
function respond(int $status,array $data):never{http_response_code($status);echo json_encode($data);exit;}
function config():array{
    $candidates=[
        dirname($_SERVER['DOCUMENT_ROOT'] ?? '') . '/github-deploy-config.php',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/github-deploy-config.php',
        '/github-deploy-config.php'
    ];
    foreach($candidates as $file){
        if(is_file($file)){
            $config=require $file;
            if(!is_array($config))respond(503,['ok'=>false,'error'=>'Invalid server configuration.']);
            return $config;
        }
    }
    respond(503,['ok'=>false,'error'=>'File Manager is not configured on the server.']);
}
function authenticate(array $config):void{if(empty($_SESSION["bh_admin_authenticated"]))respond(401,["ok"=>false,"error"=>"Admin login required."]);}
function github(array $config,string $method,string $url,?array $body=null):array{$token=(string)($config['github_token']??'');if($token==='')respond(503,['ok'=>false,'error'=>'GitHub deployment token is missing.']);$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Accept: application/vnd.github+json','Authorization: Bearer '.$token,'Content-Type: application/json','User-Agent: BubbaHub-File-Manager','X-GitHub-Api-Version: 2022-11-28'],CURLOPT_TIMEOUT=>30]);if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);if($raw===false)respond(502,['ok'=>false,'error'=>'GitHub request failed: '.$error]);$data=json_decode($raw,true);if($status<200||$status>=300){
    $message=is_array($data)?(string)($data['message']??'GitHub request failed'):'GitHub request failed';
    if($status===401||$status===403||stripos($message,'Resource not accessible by personal access token')!==false||stripos($message,'Bad credentials')!==false){
        respond(502,['ok'=>false,'error'=>'GitHub upload permission denied. Please check that the GitHub Personal Access Token in github-deploy-config.php has access to the bubbashub1/bubbaplugin repository with Contents: Read and write permission.']);
    }
    respond(502,['ok'=>false,'error'=>$message]);
}return is_array($data)?$data:[];}
$config=config();authenticate($config);$action=(string)($_GET['action']??'check');$base='https://api.github.com/repos/bubbashub1/bubbaplugin/contents/';$rawBase='https://raw.githubusercontent.com/bubbashub1/bubbaplugin/main/';
if($action==='check')respond(200,['ok'=>true]);
if($action==='list'){$folder=(string)($_GET['folder']??'listings');if(!in_array($folder,ALLOWED_FOLDERS,true))respond(400,['ok'=>false,'error'=>'Invalid image folder.']);$data=github($config,'GET',$base.'images/'.$folder.'?ref=main');$files=[];foreach($data as $item){if(($item['type']??'')!=='file')continue;$ext=strtolower(pathinfo((string)$item['name'],PATHINFO_EXTENSION));if(!in_array($ext,ALLOWED_EXTENSIONS,true))continue;$files[]=['name'=>$item['name'],'path'=>$item['path'],'size'=>(int)($item['size']??0),'url'=>$rawBase.$item['path']];}usort($files,fn($a,$b)=>strcasecmp($a['name'],$b['name']));respond(200,['ok'=>true,'label'=>ucfirst($folder),'files'=>$files]);}
if($action==='upload'){$folder=(string)($_POST['folder']??'listings');if(!in_array($folder,ALLOWED_FOLDERS,true))respond(400,['ok'=>false,'error'=>'Invalid image folder.']);if(empty($_FILES['files']))respond(400,['ok'=>false,'error'=>'No images selected.']);$files=$_FILES['files'];$count=count((array)$files['name']);$uploaded=0;for($i=0;$i<$count;$i++){if((int)$files['error'][$i]!==UPLOAD_ERR_OK)continue;$tmp=(string)$files['tmp_name'][$i];$size=(int)$files['size'][$i];$name=(string)$files['name'][$i];if($size<=0||$size>MAX_IMAGE_BYTES)continue;$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!in_array($ext,ALLOWED_EXTENSIONS,true))continue;$safe=preg_replace('/[^a-zA-Z0-9._-]+/','-',pathinfo($name,PATHINFO_FILENAME));$safe=trim((string)$safe,'-_.');if($safe==='')$safe='image';$safe=strtolower($safe).'-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.'.$ext;$path='images/'.$folder.'/'.$safe;$content=base64_encode((string)file_get_contents($tmp));github($config,'PUT',$base.$path,['message'=>'Add '.$folder.' image '.$safe,'content'=>$content,'branch'=>'main']);$uploaded++;}if($uploaded===0)respond(400,['ok'=>false,'error'=>'No valid images were uploaded. Use JPG, PNG, WebP or GIF up to 8 MB each.']);respond(200,['ok'=>true,'message'=>$uploaded.' image'.($uploaded===1?'':'s').' uploaded.']);}
if($action==='delete'){$input=json_decode((string)file_get_contents('php://input'),true);$path=is_array($input)?(string)($input['path']??''):'';if(!preg_match('#^images/(general|home|listings|logos)/[a-zA-Z0-9._-]+$#',$path))respond(400,['ok'=>false,'error'=>'Invalid image path.']);$current=github($config,'GET',$base.$path.'?ref=main');github($config,'DELETE',$base.$path,['message'=>'Delete image '.basename($path),'sha'=>(string)$current['sha'],'branch'=>'main']);respond(200,['ok'=>true]);}
respond(400,['ok'=>false,'error'=>'Unknown action.']);