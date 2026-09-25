<?php
declare(strict_types=1);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $s,array $d):never{http_response_code($s);echo json_encode($d);exit;}

$configCandidates = array_filter([
    dirname($_SERVER['DOCUMENT_ROOT'] ?? '') . '/github-deploy-config.php',
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/github-deploy-config.php',
    '/github-deploy-config.php'
]);
$f = '';
foreach ($configCandidates as $candidate) {
    if (is_file($candidate)) {
        $f = $candidate;
        break;
    }
}
if(!is_file($f))respond(503,['ok'=>false,'error'=>'Admin authentication is not configured on the server.']);
$c=require $f;
if(!is_array($c))respond(503,['ok'=>false,'error'=>'Invalid server configuration.']);

$a=(string)($_GET['action']??'check');

if($a==='login'){
    if($_SERVER['REQUEST_METHOD']!=='POST')respond(405,['ok'=>false,'error'=>'POST required']);
    $i=json_decode((string)file_get_contents('php://input'),true);
    $u=is_array($i)?trim((string)($i['username']??'')):'';
    $p=is_array($i)?(string)($i['password']??''):'';
    $eu=(string)($c['admin_username']??'');
    $ep=(string)($c['admin_password']??'');
    if($eu===''||$ep===''||!hash_equals($eu,$u)||!hash_equals($ep,$p))respond(401,['ok'=>false,'error'=>'Invalid username or password.']);
    session_regenerate_id(true);
    $_SESSION['bh_admin_authenticated']=true;
    respond(200,['ok'=>true]);
}

if($a==='logout'){$_SESSION=[];session_destroy();respond(200,['ok'=>true]);}

if(empty($_SESSION['bh_admin_authenticated']))respond(401,['ok'=>false,'error'=>'Admin login required.']);
respond(200,['ok'=>true]);
?>