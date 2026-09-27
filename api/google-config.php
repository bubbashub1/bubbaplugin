<?php
declare(strict_types=1);
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store');
$configFile=__DIR__.'/config.php'; $clientId='';
if(is_file($configFile)){ $config=require $configFile; $clientId=(string)($config['google']['client_id'] ?? ''); }
echo 'window.BUBBAHUB_GOOGLE_CLIENT_ID='.json_encode($clientId,JSON_UNESCAPED_SLASHES).';';