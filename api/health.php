<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    require __DIR__.'/db.php';
    $db=bh_mysql();
    $db->query('SELECT 1');
    echo json_encode(['ok'=>true,'database'=>'connected']);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['ok'=>false,'database'=>'not_connected']);
}
