<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_name('BUBBAHUBSESSID'); session_start(); }
function franchise_reply(int $status, array $payload): void { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_SLASHES); exit; }
function franchise_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS bh_franchises (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL UNIQUE, website VARCHAR(500) DEFAULT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS bh_franchise_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, franchise_id BIGINT UNSIGNED NOT NULL, organiser_id BIGINT UNSIGNED NOT NULL, status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending', message VARCHAR(1000) DEFAULT NULL, reviewed_at DATETIME DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_franchise_request(franchise_id,organiser_id), INDEX idx_franchise_status(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function franchise_admin(): bool {
    return !empty($_SESSION['bh_admin_id']) || !empty($_SESSION['admin_id']) || !empty($_SESSION['is_admin']);
}
try {
    $db=bh_mysql(); franchise_schema($db);
    $action=(string)($_GET['action'] ?? '');
    if ($action==='brands') {
        $rows=$db->query("SELECT f.id,f.name,f.slug,f.website,COUNT(CASE WHEN r.status='approved' THEN 1 END) AS organiser_count FROM bh_franchises f LEFT JOIN bh_franchise_requests r ON r.franchise_id=f.id GROUP BY f.id ORDER BY f.name")->fetchAll();
        franchise_reply(200,['ok'=>true,'brands'=>$rows]);
    }
    $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
    $input=json_decode((string)file_get_contents('php://input'),true);
    if (!is_array($input)) $input=[];
    if (franchise_admin()) {
        if ($action==='requests' && $method==='GET') {
            $rows=$db->query("SELECT r.*,f.name AS franchise_name,o.organisation_name AS organiser_name FROM bh_franchise_requests r JOIN bh_franchises f ON f.id=r.franchise_id JOIN bh_organisers o ON o.id=r.organiser_id ORDER BY (r.status='pending') DESC,r.created_at DESC")->fetchAll();
            franchise_reply(200,['ok'=>true,'requests'=>$rows]);
        }
        if ($action==='create_brand' && $method==='POST') {
            $name=trim((string)($input['name']??''));$slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$name),'-'));
            if (mb_strlen($name)<2 || mb_strlen($name)>190 || !$slug) franchise_reply(422,['ok'=>false,'error'=>'invalid_name']);
            $stmt=$db->prepare("INSERT INTO bh_franchises(name,slug,website) VALUES(?,?,?)");
            $stmt->execute([$name,$slug,filter_var($input['website']??'',FILTER_VALIDATE_URL) ? $input['website'] : null]);
            franchise_reply(201,['ok'=>true,'id'=>(int)$db->lastInsertId()]);
        }
        if ($action==='review' && $method==='POST') {
            $id=(int)($input['id']??0);$status=(string)($input['status']??'');
            if ($id<1 || !in_array($status,['approved','rejected'],true)) franchise_reply(422,['ok'=>false,'error'=>'invalid_review']);
            $stmt=$db->prepare("UPDATE bh_franchise_requests SET status=?,reviewed_at=NOW() WHERE id=? AND status='pending'");
            $stmt->execute([$status,$id]); if (!$stmt->rowCount()) franchise_reply(404,['ok'=>false,'error'=>'pending_request_not_found']);
            franchise_reply(200,['ok'=>true]);
        }
    }
    $userId=(int)($_SESSION['user_id']??$_SESSION['bh_user_id']??0);
    if (!$userId) franchise_reply(401,['ok'=>false,'error'=>'login_required']);
    $cols=$db->query("SHOW COLUMNS FROM bh_organisers")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('user_id',$cols,true)) {
        $stmt=$db->prepare("SELECT id FROM bh_organisers WHERE user_id=? LIMIT 1");$stmt->execute([$userId]);
    } else {
        $stmt=$db->prepare("SELECT o.id FROM bh_organisers o JOIN bh_users u ON u.email=o.email WHERE u.id=? LIMIT 1");$stmt->execute([$userId]);
    }
    $oid=(int)$stmt->fetchColumn();
    if (!$oid) franchise_reply(403,['ok'=>false,'error'=>'organiser_required']);
    if ($action==='mine' && $method==='GET') {
        $stmt=$db->prepare("SELECT r.id,r.status,r.message,r.created_at,f.name AS franchise_name FROM bh_franchise_requests r JOIN bh_franchises f ON f.id=r.franchise_id WHERE r.organiser_id=? ORDER BY r.created_at DESC");
        $stmt->execute([$oid]);franchise_reply(200,['ok'=>true,'requests'=>$stmt->fetchAll()]);
    }
    if ($action==='request' && $method==='POST') {
        $fid=(int)($input['franchise_id']??0);$message=trim((string)($input['message']??''));
        if ($fid<1 || mb_strlen($message)>1000) franchise_reply(422,['ok'=>false,'error'=>'invalid_request']);
        $stmt=$db->prepare("SELECT id FROM bh_franchises WHERE id=?");$stmt->execute([$fid]);
        if (!$stmt->fetchColumn()) franchise_reply(404,['ok'=>false,'error'=>'brand_not_found']);
        $stmt=$db->prepare("INSERT INTO bh_franchise_requests(franchise_id,organiser_id,status,message) VALUES(?,?,'pending',?) ON DUPLICATE KEY UPDATE status=IF(status='rejected','pending',status),message=IF(status='rejected',VALUES(message),message),reviewed_at=IF(status='rejected',NULL,reviewed_at)");
        $stmt->execute([$fid,$oid,$message]);franchise_reply(200,['ok'=>true,'status'=>'submitted_or_existing']);
    }
    franchise_reply(400,['ok'=>false,'error'=>'unknown_action']);
} catch (Throwable $e) { error_log('Franchise linking: '.$e->getMessage()); franchise_reply(500,['ok'=>false,'error'=>'server_error']); }
