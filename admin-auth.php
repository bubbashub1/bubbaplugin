<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status,array $data):never{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$configFile=dirname(__DIR__).'/github-deploy-config.php';
if(!is_file($configFile))respond(503,['ok'=>false,'error'=>'Admin authentication is not configured on the server.']);
$config=require $configFile;
if(!is_array($config))respond(503,['ok'=>false,'error'=>'Invalid server configuration.']);

$expected=(string)($config['admin_key']??($config['file_manager_key']??''));
$provided=(string)($_SERVER['HTTP_X_BUBBA_ADMIN_KEY']??'');

if($expected===''||$provided===''||!hash_equals($expected,$provided)){
    respond(401,['ok'=>false,'error'=>'Invalid Admin key.']);
}

respond(200,['ok'=>true]);
