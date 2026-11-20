<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__.'/admin-session-guard.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/page-document-validator-v1.php';

function bh_vb_out(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['bh_visual_builder_csrf'])) {
    $_SESSION['bh_visual_builder_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['bh_visual_builder_csrf'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body = [];

if ($method === 'POST') {
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) bh_vb_out(400, ['ok'=>false, 'error'=>'Invalid request body.']);
    if (!hash_equals($csrf, (string)($body['csrf'] ?? ''))) {
        bh_vb_out(403, ['ok'=>false, 'error'=>'Security check failed. Refresh and try again.']);
    }
} elseif ($method !== 'GET') {
    bh_vb_out(405, ['ok'=>false, 'error'=>'Method not allowed.']);
}

try {
    $db = bh_mysql();
    $pagesTable = $db->query("SHOW TABLES LIKE 'bh_pages'")->fetchColumn();
    if (!$pagesTable) bh_vb_out(409, ['ok'=>false, 'error'=>'Complete Page Manager setup before using the visual builder.']);

    $action = (string)($body['action'] ?? '');

    if ($method === 'POST' && $action === 'setup') {
        $db->exec("CREATE TABLE IF NOT EXISTS bh_visual_page_documents (
            page_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            document_json MEDIUMTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_bh_visual_page_doc FOREIGN KEY (page_id) REFERENCES bh_pages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS bh_visual_page_document_revisions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            page_id BIGINT UNSIGNED NOT NULL,
            document_json MEDIUMTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_bh_visual_doc_rev (page_id, created_at),
            CONSTRAINT fk_bh_visual_page_rev FOREIGN KEY (page_id) REFERENCES bh_pages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        bh_vb_out(200, ['ok'=>true, 'csrf'=>$csrf, 'message'=>'Visual Builder draft storage set up. Existing Page Manager content and published pages have not been changed.']);
    }

    $documentsTable = $db->query("SHOW TABLES LIKE 'bh_visual_page_documents'")->fetchColumn();
    $revisionsTable = $db->query("SHOW TABLES LIKE 'bh_visual_page_document_revisions'")->fetchColumn();
    if (!$documentsTable || !$revisionsTable) {
        if ($method === 'GET') bh_vb_out(200, ['ok'=>true, 'csrf'=>$csrf, 'setup_required'=>true]);
        bh_vb_out(409, ['ok'=>false, 'error'=>'Run the one-time Visual Builder storage setup first.']);
    }

    if ($method === 'GET') {
        $pageId = filter_var($_GET['page_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$pageId || $pageId < 1) bh_vb_out(400, ['ok'=>false, 'error'=>'A valid page_id is required.']);

        $pageQ = $db->prepare("SELECT id,title,slug,status FROM bh_pages WHERE id=? LIMIT 1");
        $pageQ->execute([$pageId]);
        $page = $pageQ->fetch(PDO::FETCH_ASSOC);
        if (!$page) bh_vb_out(404, ['ok'=>false, 'error'=>'Page not found.']);

        $docQ = $db->prepare("SELECT document_json,updated_at FROM bh_visual_page_documents WHERE page_id=? LIMIT 1");
        $docQ->execute([$pageId]);
        $row = $docQ->fetch(PDO::FETCH_ASSOC);
        $draft = null;
        if ($row) {
            $draft = json_decode((string)$row['document_json'], true);
            if (!is_array($draft)) bh_vb_out(500, ['ok'=>false, 'error'=>'The saved builder draft could not be read.']);
        }

        $revisionQ = $db->prepare("SELECT id,created_at FROM bh_visual_page_document_revisions WHERE page_id=? ORDER BY id DESC LIMIT 30");
        $revisionQ->execute([$pageId]);
        bh_vb_out(200, [
            'ok'=>true,
            'csrf'=>$csrf,
            'page'=>$page,
            'draft'=>$draft,
            'updated_at'=>$row['updated_at'] ?? null,
            'revisions'=>$revisionQ->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($action === 'save_draft') {
        $pageId = filter_var($body['page_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$pageId || $pageId < 1) bh_vb_out(422, ['ok'=>false, 'error'=>'A valid page ID is required.']);

        $document = $body['document'] ?? null;
        $validation = bh_validate_page_document_v1($document);
        if (!$validation['ok']) {
            bh_vb_out(422, ['ok'=>false, 'error'=>'Document validation failed.', 'details'=>$validation['errors']]);
        }

        $db->beginTransaction();
        $pageQ = $db->prepare("SELECT id,title,slug,status FROM bh_pages WHERE id=? FOR UPDATE");
        $pageQ->execute([$pageId]);
        $page = $pageQ->fetch(PDO::FETCH_ASSOC);
        if (!$page) {
            $db->rollBack();
            bh_vb_out(404, ['ok'=>false, 'error'=>'Page not found.']);
        }
        if ($page['status'] === 'archived') {
            $db->rollBack();
            bh_vb_out(409, ['ok'=>false, 'error'=>'Archived pages cannot be edited in the visual builder.']);
        }

        // Page identity remains controlled by Page Manager, not by canvas payloads.
        $document['page']['title'] = (string)$page['title'];
        $document['page']['slug'] = (string)$page['slug'];
        $validation = bh_validate_page_document_v1($document);
        if (!$validation['ok']) {
            $db->rollBack();
            bh_vb_out(422, ['ok'=>false, 'error'=>'The page record cannot be represented by this builder document.', 'details'=>$validation['errors']]);
        }

        $json = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $oldQ = $db->prepare("SELECT document_json FROM bh_visual_page_documents WHERE page_id=? FOR UPDATE");
        $oldQ->execute([$pageId]);
        $old = $oldQ->fetchColumn();
        if (is_string($old) && $old !== '') {
            $revisionQ = $db->prepare("INSERT INTO bh_visual_page_document_revisions (page_id,document_json) VALUES (?,?)");
            $revisionQ->execute([$pageId, $old]);
        }
        $saveQ = $db->prepare("INSERT INTO bh_visual_page_documents (page_id,document_json) VALUES (?,?) ON DUPLICATE KEY UPDATE document_json=VALUES(document_json),updated_at=NOW()");
        $saveQ->execute([$pageId, $json]);
        $db->commit();

        bh_vb_out(200, ['ok'=>true, 'csrf'=>$csrf, 'message'=>'Draft document saved. The published page and existing Page Manager content are unchanged.', 'document'=>$document]);
    }

    if ($action === 'restore_revision') {
        $pageId = filter_var($body['page_id'] ?? null, FILTER_VALIDATE_INT);
        $revisionId = filter_var($body['revision_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$pageId || $pageId < 1 || !$revisionId || $revisionId < 1) {
            bh_vb_out(422, ['ok'=>false, 'error'=>'A valid page and revision are required.']);
        }

        $db->beginTransaction();
        $pageQ = $db->prepare("SELECT id,title,slug,status FROM bh_pages WHERE id=? FOR UPDATE");
        $pageQ->execute([$pageId]);
        $page = $pageQ->fetch(PDO::FETCH_ASSOC);
        if (!$page || $page['status'] === 'archived') {
            $db->rollBack();
            bh_vb_out(404, ['ok'=>false, 'error'=>'The editable page was not found.']);
        }

        $revisionQ = $db->prepare("SELECT document_json FROM bh_visual_page_document_revisions WHERE id=? AND page_id=? LIMIT 1");
        $revisionQ->execute([$revisionId, $pageId]);
        $revisionJson = $revisionQ->fetchColumn();
        if (!is_string($revisionJson)) {
            $db->rollBack();
            bh_vb_out(404, ['ok'=>false, 'error'=>'Revision not found for this page.']);
        }

        $document = json_decode($revisionJson, true);
        if (!is_array($document)) {
            $db->rollBack();
            bh_vb_out(409, ['ok'=>false, 'error'=>'This revision cannot be read.']);
        }
        $document['page']['title'] = (string)$page['title'];
        $document['page']['slug'] = (string)$page['slug'];
        $validation = bh_validate_page_document_v1($document);
        if (!$validation['ok']) {
            $db->rollBack();
            bh_vb_out(409, ['ok'=>false, 'error'=>'This revision is no longer valid and cannot be restored.']);
        }

        $oldQ = $db->prepare("SELECT document_json FROM bh_visual_page_documents WHERE page_id=? FOR UPDATE");
        $oldQ->execute([$pageId]);
        $old = $oldQ->fetchColumn();
        if (is_string($old) && $old !== '') {
            $snapshotQ = $db->prepare("INSERT INTO bh_visual_page_document_revisions (page_id,document_json) VALUES (?,?)");
            $snapshotQ->execute([$pageId, $old]);
        }
        $saveQ = $db->prepare("INSERT INTO bh_visual_page_documents (page_id,document_json) VALUES (?,?) ON DUPLICATE KEY UPDATE document_json=VALUES(document_json),updated_at=NOW()");
        $saveQ->execute([$pageId, json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
        $db->commit();

        bh_vb_out(200, ['ok'=>true, 'csrf'=>$csrf, 'message'=>'Previous draft restored. The public page is unchanged.', 'document'=>$document]);
    }

    bh_vb_out(400, ['ok'=>false, 'error'=>'Unknown Visual Builder action.']);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log('Visual builder draft API error: '.$e->getMessage());
    bh_vb_out(500, ['ok'=>false, 'error'=>'Visual Builder draft request failed. No published page was changed.']);
}
