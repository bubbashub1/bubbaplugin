<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/admin-session-guard.php';

function bh_vb_media_out(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$directory = dirname(__DIR__) . '/images/uploads/visual-builder';
$metadataPath = $directory . '/.media-library.json';
$metadata = [];
if (is_file($metadataPath)) {
    $decoded = json_decode((string)@file_get_contents($metadataPath), true);
    if (is_array($decoded)) $metadata = $decoded;
}

$method = (string)($_SERVER['REQUEST_METHOD'] ?? '');
if ($method === 'GET') {
    $items = [];
    if (is_dir($directory)) {
        $files = glob($directory . '/*');
        if (is_array($files)) foreach ($files as $path) {
            if (!is_file($path)) continue;
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) continue;
            $info = @getimagesize($path);
            if (!$info) continue;
            $filename = basename($path);
            $details = is_array($metadata[$filename] ?? null) ? $metadata[$filename] : [];
            $items[] = [
                'name' => $filename,
                'src' => '/images/uploads/visual-builder/' . rawurlencode($filename),
                'title' => (string)($details['title'] ?? ''),
                'folder' => (string)($details['folder'] ?? ''),
                'width' => (int)$info[0],
                'height' => (int)$info[1],
                'bytes' => (int)(filesize($path) ?: 0),
                'modified' => (int)(filemtime($path) ?: 0),
            ];
        }
    }
    usort($items, static fn(array $a, array $b): int => $b['modified'] <=> $a['modified']);
    $query = strtolower(trim((string)($_GET['q'] ?? '')));
    $folder = trim((string)($_GET['folder'] ?? ''));
    if ($query !== '') $items = array_values(array_filter($items, static fn(array $item): bool =>
        str_contains(strtolower($item['name'] . ' ' . $item['title']), $query)));
    if ($folder !== '') $items = array_values(array_filter($items, static fn(array $item): bool => $item['folder'] === $folder));
    $folders = array_values(array_unique(array_filter(array_map(static fn(array $item): string => $item['folder'], $items))));
    sort($folders, SORT_NATURAL | SORT_FLAG_CASE);
    $total = count($items);
    bh_vb_media_out(200, ['ok' => true, 'items' => array_slice($items, 0, 120), 'total' => $total, 'folders' => $folders]);
}
if ($method !== 'POST') {
    header('Allow: GET, POST');
    bh_vb_media_out(405, ['ok' => false, 'error' => 'Use GET to browse or POST to upload and organise images.']);
}

$expectedToken = (string)($_SESSION['bh_visual_builder_csrf'] ?? '');
$providedToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    bh_vb_media_out(403, ['ok' => false, 'error' => 'Security check failed. Refresh the Visual Builder and try again.']);
}

$action = (string)($_POST['action'] ?? 'upload');
if ($action === 'metadata') {
    $filename = basename((string)($_POST['name'] ?? ''));
    $path = $directory . '/' . $filename;
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($filename === '' || !in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) || !is_file($path) || !@getimagesize($path)) {
        bh_vb_media_out(404, ['ok' => false, 'error' => 'That library image could not be found.']);
    }
    $title = trim((string)($_POST['title'] ?? ''));
    $folder = trim((string)($_POST['folder'] ?? ''));
    if (mb_strlen($title) > 120 || mb_strlen($folder) > 40 || preg_match('/[\\x00-\\x1F\\x7F]/u', $title . $folder)) {
        bh_vb_media_out(422, ['ok' => false, 'error' => 'Titles must be 120 characters or fewer and folder names 40 characters or fewer.']);
    }
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        bh_vb_media_out(503, ['ok' => false, 'error' => 'Image storage is not available.']);
    }
    $metadata[$filename] = ['title' => $title, 'folder' => $folder];
    $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || @file_put_contents($metadataPath, $json, LOCK_EX) === false) {
        bh_vb_media_out(503, ['ok' => false, 'error' => 'Image details could not be saved.']);
    }
    bh_vb_media_out(200, ['ok' => true, 'title' => $title, 'folder' => $folder]);
}
if ($action !== 'upload') bh_vb_media_out(422, ['ok' => false, 'error' => 'Unknown media action.']);

$maxBytes = 8 * 1024 * 1024;
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBytes + 65536) bh_vb_media_out(413, ['ok' => false, 'error' => 'Images must be 8 MB or smaller.']);
if (!isset($_FILES['image']) || !is_array($_FILES['image'])) bh_vb_media_out(422, ['ok' => false, 'error' => 'Choose an image from your device first.']);
$file = $_FILES['image'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) bh_vb_media_out(422, ['ok' => false, 'error' => 'The image could not be uploaded. Please try again.']);
if ((int)$file['size'] < 1 || (int)$file['size'] > $maxBytes) bh_vb_media_out(413, ['ok' => false, 'error' => 'Images must be between 1 byte and 8 MB.']);
$tmp = (string)$file['tmp_name'];
$imageInfo = @getimagesize($tmp);
if (!$imageInfo || (int)$imageInfo[0] < 1 || (int)$imageInfo[1] < 1 || (int)$imageInfo[0] > 8000 || (int)$imageInfo[1] > 8000) bh_vb_media_out(422, ['ok' => false, 'error' => 'This file is not a supported image or its dimensions are too large.']);
$extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
$imageType = (int)($imageInfo[2] ?? 0);
if (!isset($extensions[$imageType])) bh_vb_media_out(422, ['ok' => false, 'error' => 'Use a JPG, PNG, WebP or GIF image. SVG and other file types are not accepted.']);
if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) bh_vb_media_out(503, ['ok' => false, 'error' => 'Image storage is not available. Please contact the site administrator.']);
$denyFile = $directory . '/.htaccess';
if (!is_file($denyFile)) @file_put_contents($denyFile, "Options -Indexes\\n<FilesMatch \"\\\\.(php[0-9]?|phtml|phar|cgi|pl|py|sh)$\">\\nRequire all denied\\n</FilesMatch>\\n");
$indexFile = $directory . '/index.html';
if (!is_file($indexFile)) @file_put_contents($indexFile, '');
$filename = bin2hex(random_bytes(16)) . '.' . $extensions[$imageType];
$destination = $directory . '/' . $filename;
if (!@move_uploaded_file($tmp, $destination)) bh_vb_media_out(503, ['ok' => false, 'error' => 'The image could not be saved. Please try again.']);
@chmod($destination, 0644);
bh_vb_media_out(200, ['ok' => true, 'src' => '/images/uploads/visual-builder/' . $filename, 'width' => (int)$imageInfo[0], 'height' => (int)$imageInfo[1], 'message' => 'Image uploaded successfully.']);
