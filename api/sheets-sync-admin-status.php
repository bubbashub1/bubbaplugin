<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/admin-session-guard.php';
echo json_encode(['ok'=>true,'authenticated'=>true,'mode'=>'review_only','writes_enabled'=>false]);
