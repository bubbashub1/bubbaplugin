<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__.'/admin-session-guard.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/page-document-validator-v1.php';

function bhpbd_out(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['bh_pages_csrf'])) {
    $_SESSION['bh_pages_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['bh_pages_csrf'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body = [];
if ($method === 'POST') {
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($decoded)) bhpbd_out(400, ['ok'=>false, 'error'=>'Invalid request body.']);
    $body = $decoded;
    if (!hash_equals($csrf, (string)($body['csrf'] ?? ''))) {
        bhpbd_out(403, ['ok'=>false, 'error'=>'Security check failed. Refresh the Page Manager and try again.']);
    }
} elseif ($method !== 'GET') {
    bhpbd_out(405, ['ok'=>false, 'error'=>'Method not allowed.']);
}

try {
    $db = bh_mysql();
    $pagesTable = $db->query("SHOW TABLES LIKE 'bh_pages'")->fetchColumn();
    if (!$pagesTable) bhpbd_out(409, ['ok'=>false, 'error'=>'Complete Page Manager setup before using the visual builder.']);

    $action = (string)($body['action'] ?? '');
    if ($method === 'POST' && $action === 'setup') {
        $db->exec("CREATE TABLE IF NOT EXISTS bh_page_builder_documents (
            page_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            draft_json MEDIUMTEXT NOT NULL,
            published_json MEDIUMTEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_bh_page_builder_documents_page FOREIGN KEY (page_id) REFERENCES bh_pages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS bh_page_builder_revisions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            page_id BIGINT UNSIGNED NOT NULL,
            document_json MEDIUMTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_bh_page_builder_revisions_page_created (page_id, created_at),
            CONSTRAINT fk_bh_page_builder_revisions_page FOREIGN KEY (page_id) REFERENCES bh_pages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        bhpbd_out(200, ['ok'=>true, 'csrf'=>$csrf, 'message'=>'Visual Builder draft storage is ready. Existing page content has not been changed.']);
    }

    $documentsTable = $db->query("SHOW TABLES LIKE 'bh_page_builder_documents'")->fetchColumn();
    $revisionsTable = $db->query("SHOW TABLES LIKE 'bh_page_builder_revisions'")->fetchColumn();
    if (!$documentsTable || !$revisionsTable) {
        if ($method === 'GET') bhpbd_out(200, ['ok'=>true, 'csrf'=>$csrf, 'setup_required'=>true]);
        bhpbd_out(409, ['ok'=>false, 'error'=>'Run the one-time Visual Builder storage setup first.']);
    }

    if ($method === 'GET') {
        $pageId = filter_var($_GET['page_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$pageId || $pageId < 1) bhpbd_out(400, ['ok'=>false, 'error'=>'A valid page_id is required.']);
        $pageQ = $db->prepare("SELECT id, title, slug, status FROM bh_pages WHERE id=? LIMIT 1");
        $pageQ->execute([$pageId]);
        $page = $pageQ->fetch(PDO::FETCH_ASSOC);
        if (!$page) bhpbd_out(404, ['ok'=>false, 'error'=>'Page not found.']);
        $docQ = $db->prepare("SELECT draft_json, updated_at FROM bh_page_builder_documents WHERE page_id=? LIMIT 1");
        $docQ->execute([$pageId]);
        $stored = $docQ->fetch(PDO::FETCH_ASSOC);
        $revisionQ = $db->prepare("SELECT id, created_at FROM bh_page_builder_revisions WHERE page_id=? ORDER BY id DESC LIMIT 30");
        $revisionQ->execute([$pageId]);
        $revisions = $revisionQ->fetchAll(PDO::FETCH_ASSOC);
        $draft = null;
        if ($stored && is_string($stored['draft_json'])) {
            $draft = json_decode($stored['draft_json'], true);
            if (!is_array($draft)) bhpbd_out(500, ['ok'=>false, 'error'=>'The saved builder draft could not be read.']);
        }
        bhpbd_out(200, ['ok'=>true, 'csrf'=>$csrf, 'page'=>$page, 'draft'=>$draft, 'updated_at'=>$stored['updated_at'] ?? null, 'revisions'=>$revisions]);
    }

    if ($action === 'save_draft') {
        $pageId = filter_var($body['page_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$pageId || $pageId < 1) bhpbd_out(400, ['ok'=>false, 'error'=>'A valid page_id is required.']);
        $document = $body['document'] ?? null;
        $validation = bh_validate_page_document_v1($document);
        if (!$validation['ok']) bhpbd_out(422, ['ok'=>false, 'error'=>'The page document failed validation.', 'validation_errors'=>$validation['errors']]);
        $pageQ = $db->prepare("SELECT id, title, slug FROM bh_pages WHERE id=? LIMIT 1");
        $pageQ->execute([$pageId]);
        $page = $pageQ->fetch(PDO::FETCH_ASSOC);
        if (!$page) bhpbd_out(404, ['ok'=>false, 'error'=>'Page not found.']);
        if (!hash_equals((string)$page['slug'], (string)$document['page']['slug'])) {
            bhpbd_out(409, ['ok'=>false, 'error'=>'The builder document URL does not match this Page Manager record.']);
        }
        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $db->beginTransaction();
        $currentQ = $db->prepare("SELECT draft_json FROM bh_page_builder_documents WHERE page_id=? FOR UPDATE");
        $currentQ->execute([$pageId]);
        $current = $currentQ->fetchColumn();
        if (is_string($current) && $current !== '') {
            $revisionQ = $db->prepare("INSERT INTO bh_page_builder_revisions (page_id, document_json) VALUES (?, ?)");
            $revisionQ->execute([$pageId, $current]);
        }
        $saveQ = $db->prepare("INSERT INTO bh_page_builder_documents (page_id, draft_json, published_json) VALUES (?, ?, NULL) ON DUPLICATE KEY UPDATE draft_json=VALUES(draft_json)");
        $saveQ->execute([$pageId, $json]);
        $db->commit();
        bhpbd_out(200, ['ok'=>true, 'csrf'=>$csrf, 'message'=>'Draft saved. The published page and existing Page Manager content are unchanged.']);
    }

    if ($action === 'restore_revision') {
        $pageId = filter_var($body['page_id'] ?? null, FILTER_VALIDATE_INT);
        $revisionId = filter_var($body['revision_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$pageId || $pageId < 1 || !$revisionId || $revisionId < 1) {
            bhpbd_out(400, ['ok'=>false, 'error'=>'A valid page and revision are required.']);
        }
        $db->beginTransaction();
        $revisionQ = $db->prepare("SELECT document_json FROM bh_page_builder_revisions WHERE id=? AND page_id=? LIMIT 1");
        $revisionQ->execute([$revisionId, $pageId]);
        $revisionJson = $revisionQ->fetchColumn();
        if (!is_string($revisionJson)) {
            $db->rollBack();
            bhpbd_out(404, ['ok'=>false, 'error'=>'Revision not found for this page.']);
        }
        $document = json_decode($revisionJson, true);
        $validation = bh_validate_page_document_v1($document);
        if (!$validation['ok']) {
            $db->rollBack();
            bhpbd_out(409, ['ok'=>false, 'error'=>'This revision is no longer valid and cannot be restored.']);
        }
        $currentQ = $db->prepare("SELECT draft_json FROM bh_page_builder_documents WHERE page_id=? FOR UPDATE");
        $currentQ->execute([$pageId]);
        $current = $currentQ->fetchColumn();
        if (is_string($current) && $current !== '') {
            $snapshotQ = $db->prepare("INSERT INTO bh_page_builder_revisions (page_id, document_json) VALUES (?, ?)");
            $snapshotQ->execute([$pageId, $current]);
        }
        $saveQ = $db->prepare("INSERT INTO bh_page_builder_documents (page_id, draft_json, published_json) VALUES (?, ?, NULL) ON DUPLICATE KEY UPDATE draft_json=VALUES(draft_json)");
        $saveQ->execute([$pageId, $revisionJson]);
        $db->commit();
        bhpbd_out(200, ['ok'=>true, 'csrf'=>$csrf, 'message'=>'Previous draft restored. The public page is unchanged.']);
    }

    bhpbd_out(400, ['ok'=>false, 'error'=>'Unknown Visual Builder action.']);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log('Bubba Hub visual builder draft API error: '.$e->getMessage());
    bhpbd_out(500, ['ok'=>false, 'error'=>'Visual Builder draft storage is temporarily unavailable. No published page was changed.']);
}
