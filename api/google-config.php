<?php
declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store');

$clientId = defined('BH_GOOGLE_CLIENT_ID') ? (string)BH_GOOGLE_CLIENT_ID : '';

echo 'window.BUBBAHUB_GOOGLE_CLIENT_ID=' . json_encode($clientId, JSON_UNESCAPED_SLASHES) . ';';
