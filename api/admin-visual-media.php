<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/admin-session-guard.php';

function bhvm_out(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    bhvm_out(405, ['ok'=>false, 'error'=>'This media endpoint is read-only.']);
}
$folder = (string)($_GET['folder'] ?? 'home');
$allowedFolders = ['general','home','listings','logos'];
$allowedExtensions = ['jpg','jpeg','png','webp','gif'];
$allowedMimes = ['image/jpeg','image/png','image/webp','image/gif'];
if (!in_array($folder, $allowedFolders, true)) {
    bhvm_out(400, ['ok'=>false, 'error'=>'Choose a supported image folder.']);
}
try {
    $imagesRoot = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images');
    if ($imagesRoot === false || !is_dir($imagesRoot)) {
        bhvm_out(503, ['ok'=>false, 'error'=>'The current deployment image directory is not available.']);
    }
    $folderPath = realpath($imagesRoot . DIRECTORY_SEPARATOR . $folder);
    if ($folderPath === false || !is_dir($folderPath) ||
        !str_starts_with($folderPath, $imagesRoot . DIRECTORY_SEPARATOR)) {
        bhvm_out(404, ['ok'=>false, 'error'=>'This image folder is not available in the current deployment.']);
    }
    $files = [];
    foreach (new DirectoryIterator($folderPath) as $entry) {
        if ($entry->isDot() || $entry->isLink() || !$entry->isFile()) continue;
        $name = $entry->getFilename();
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,180}$/', $name)) continue;
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true) || $extension !== pathinfo($name, PATHINFO_EXTENSION)) continue;
        $realFile = realpath($entry->getPathname());
        if ($realFile === false || !str_starts_with($realFile, $folderPath . DIRECTORY_SEPARATOR)) continue;
        $size = (int)$entry->getSize();
        if ($size < 1 || $size > 12582912) continue;
        $info = @getimagesize($realFile);
        if (!is_array($info) || !in_array((string)($info['mime'] ?? ''), $allowedMimes, true)) continue;
        $files[] = ['name'=>$name,'path'=>'/images/'.$folder.'/'.$name,'size'=>$size,'width'=>(int)$info[0],'height'=>(int)$info[1]];
    }
    usort($files, static fn(array $a,array $b): int => strcasecmp($a['name'],$b['name']));
    $files = array_slice($files, 0, 250);
    bhvm_out(200, ['ok'=>true,'folder'=>$folder,'files'=>$files]);
} catch (Throwable $error) {
    error_log('Visual Builder media inventory failed: '.$error->getMessage());
    bhvm_out(500, ['ok'=>false,'error'=>'The current deployment media list could not be loaded.']);
}
