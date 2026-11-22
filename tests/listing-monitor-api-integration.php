<?php
declare(strict_types=1);

/**
 * End-to-end HTTP tests for the admin-only Listing Monitor draft API.
 * Runs only against the disposable listing_monitor_test MySQL database.
 */
$dsn = (string)(getenv('LISTING_MONITOR_TEST_DSN') ?: '');
$user = (string)(getenv('LISTING_MONITOR_TEST_USER') ?: '');
$password = (string)(getenv('LISTING_MONITOR_TEST_PASSWORD') ?: '');
if ($dsn === '' || !str_contains($dsn, 'dbname=listing_monitor_test')) {
    fwrite(STDERR, "Refusing to run: a disposable listing_monitor_test DSN is required.\n");
    exit(2);
}
$root = dirname(__DIR__);
$documentRoot = realpath($root);
$privateConfig = dirname($documentRoot) . '/database-config-' . basename($documentRoot) . '.php';
if (is_file($privateConfig)) {
    fwrite(STDERR, "Refusing to overwrite an existing database config file.\n");
    exit(2);
}
$sessionDir = sys_get_temp_dir() . '/bh-listing-monitor-sessions-' . getmypid();
if (!mkdir($sessionDir, 0700, true) && !is_dir($sessionDir)) {
    throw new RuntimeException('Could not create isolated PHP session directory.');
}
$logFile = $sessionDir . '/server.log';
$port = 18081;
$server = null;
$passed = 0;
$failed = [];
$check = static function (string $name, bool $condition) use (&$passed, &$failed): void {
    if ($condition) { $passed++; echo "PASS: {$name}\n"; }
    else { $failed[] = $name; echo "FAIL: {$name}\n"; }
};

$db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$config = "<?php return " . var_export([
    'db' => ['host' => '127.0.0.1', 'name' => 'listing_monitor_test', 'user' => $user, 'pass' => $password, 'charset' => 'utf8mb4'],
], true) . ";";
file_put_contents($privateConfig, $config);

try {
    $command = [PHP_BINARY, '-d', 'session.save_path=' . $sessionDir, '-S', '127.0.0.1:' . $port, '-t', $root];
    $server = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['file', $logFile, 'a'],
        2 => ['file', $logFile, 'a'],
    ], $pipes);
    if (!is_resource($server)) { throw new RuntimeException('Could not start the local PHP test server.'); }
    fclose($pipes[0]);

    $ready = false;
    for ($i = 0; $i < 60; $i++) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
        if (is_resource($socket)) { fclose($socket); $ready = true; break; }
        usleep(100000);
    }
    if (!$ready) { throw new RuntimeException('Local PHP test server did not start.'); }

    $seedSession = static function (string $id, int $lastActivity) use ($sessionDir): void {
        ini_set('session.save_path', $sessionDir);
        ini_set('session.use_strict_mode', '0');
        session_name('BUBBAHUB_ADMINSESSID');
        session_id($id);
        session_start();
        $_SESSION['bh_admin_authenticated'] = true;
        $_SESSION['bh_admin_username'] = 'integration-admin';
        $_SESSION['bh_admin_last_activity'] = $lastActivity;
        session_write_close();
    };
    $validSession = 'monitorvalid' . getmypid();
    $expiredSession = 'monitorexpired' . getmypid();
    $seedSession($validSession, time());
    $seedSession($expiredSession, time() - 1900);

    $request = static function (string $query, string $method = 'GET', ?array $body = null, ?string $session = null, array $extraHeaders = []): array {
        $headers = ['Accept: application/json'];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Origin: http://127.0.0.1:18081';
            $headers[] = 'Sec-Fetch-Site: same-origin';
        }
        if ($session !== null) { $headers[] = 'Cookie: BUBBAHUB_ADMINSESSID=' . $session; }
        foreach ($extraHeaders as $extraHeader) {
            $headerName = strtolower(trim(strtok($extraHeader, ':') ?: ''));\n            $headers = array_values(array_filter($headers, static fn(string $header): bool => strtolower(trim(strtok($header, ':') ?: '')) !== $headerName));\n            $headers[] = $extraHeader;\n        }
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ignore_errors' => true,
                'timeout' => 8,
            ],
        ];
        $context = stream_context_create($options);
        $raw = file_get_contents('http://127.0.0.1:18081/api/admin-listing-monitor-drafts.php' . $query, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) { $status = (int)$match[1]; }
        }
        return ['status' => $status, 'json' => json_decode((string)$raw, true), 'raw' => (string)$raw];
    };

    $unauthenticated = $request('?drafts=1');
    $check('API rejects requests without an admin session', $unauthenticated['status'] === 401);
    $expired = $request('?drafts=1', 'GET', null, $expiredSession);
    $check('API rejects an expired 30-minute admin session', $expired['status'] === 401
        && ($expired['json']['error'] ?? '') === 'admin_session_expired');

    $insertActivity = $db->prepare('INSERT INTO bh_activities(title,description,website) VALUES(?,?,?)');
    $insertActivity->execute(['API test activity', 'Original description', 'https://api-test.example.org']);
    $activityId = (int)$db->lastInsertId();
    $insertFinding = $db->prepare("INSERT INTO bh_listing_check_findings(check_type,activity_id,candidate_json,finding_type,confidence,source_url,checked_at,review_status) VALUES('health',?,?,?,70,?,NOW(),'new')");
    $insertFinding->execute([$activityId, json_encode(['title' => 'Suggested title', 'snippet' => 'Official source evidence']), 'api_test_change', 'https://api-test.example.org']);
    $findingId = (int)$db->lastInsertId();

    $list = $request('?drafts=1', 'GET', null, $validSession);
    $csrf = (string)($list['json']['csrf'] ?? '');
    $check('authenticated API returns draft list and CSRF token', $list['status'] === 200 && $csrf !== '');

    $detail = $request('?finding_id=' . $findingId, 'GET', null, $validSession);
    $check('finding review returns current and suggested listing fields', $detail['status'] === 200
        && ($detail['json']['fields']['title']['current'] ?? null) === 'API test activity'
        && ($detail['json']['fields']['title']['suggested'] ?? null) === 'Suggested title');

    $badCsrf = $request('', 'POST', ['action' => 'save_draft', 'csrf' => 'wrong', 'finding_id' => $findingId, 'changes' => ['title' => 'Bad']], $validSession);
    $check('API rejects an invalid CSRF token', $badCsrf['status'] === 403);

    $badOrigin = $request('', 'POST', ['action' => 'save_draft', 'csrf' => $csrf, 'finding_id' => $findingId, 'changes' => ['title' => 'Bad']], $validSession, ['Origin: https://attacker.example']);
    $check('API rejects a cross-origin save request', $badOrigin['status'] === 403);

    $saved = $request('', 'POST', ['action' => 'save_draft', 'csrf' => $csrf, 'finding_id' => $findingId, 'changes' => ['title' => 'First suggestion']], $validSession);
    $draftId = (int)($saved['json']['draft_id'] ?? 0);
    $liveTitle = $db->query("SELECT title FROM bh_activities WHERE id={$activityId}")->fetchColumn();
    $check('saving a suggestion creates a private draft without changing the public listing', $saved['status'] === 200 && $draftId > 0 && $liveTitle === 'API test activity');

    $resaved = $request('', 'POST', ['action' => 'save_draft', 'csrf' => $csrf, 'finding_id' => $findingId, 'changes' => ['title' => 'Revised suggestion']], $validSession);
    $draftCount = (int)$db->query("SELECT COUNT(*) FROM bh_listing_monitor_drafts WHERE finding_id={$findingId} AND status='draft'")->fetchColumn();
    $check('saving again updates the existing draft instead of duplicating it', $resaved['status'] === 200
        && (int)($resaved['json']['draft_id'] ?? 0) === $draftId && $draftCount === 1);

    $draftDetail = $request('?draft_id=' . $draftId, 'GET', null, $validSession);
    $check('saved draft preview returns full activity and proposed changes', $draftDetail['status'] === 200
        && ($draftDetail['json']['draft']['proposed_changes']['title'] ?? '') === 'Revised suggestion'
        && ($draftDetail['json']['activity']['description'] ?? '') === 'Original description');

    $notConfirmed = $request('', 'POST', ['action' => 'approve_draft', 'csrf' => $csrf, 'draft_id' => $draftId, 'confirm' => false], $validSession);
    $check('approval requires explicit confirmation', $notConfirmed['status'] === 422);

    $approved = $request('', 'POST', ['action' => 'approve_draft', 'csrf' => $csrf, 'draft_id' => $draftId, 'confirm' => true], $validSession);
    $audit = $db->query("SELECT admin_username,before_values,approved_values FROM bh_listing_monitor_audit WHERE draft_id={$draftId}")->fetch();
    $check('confirmed approval updates selected field and records named admin audit', $approved['status'] === 200
        && $db->query("SELECT title FROM bh_activities WHERE id={$activityId}")->fetchColumn() === 'Revised suggestion'
        && ($audit['admin_username'] ?? '') === 'integration-admin');

    $insertActivity->execute(['Stale API test activity', 'Original description', 'https://stale-test.example.org']);
    $staleActivityId = (int)$db->lastInsertId();
    $insertFinding->execute([$staleActivityId, json_encode(['title' => 'Stale suggestion']), 'api_stale_test', 'https://stale-test.example.org']);
    $staleFindingId = (int)$db->lastInsertId();
    $staleSaved = $request('', 'POST', ['action' => 'save_draft', 'csrf' => $csrf, 'finding_id' => $staleFindingId, 'changes' => ['title' => 'Must not overwrite']], $validSession);
    $staleDraftId = (int)($staleSaved['json']['draft_id'] ?? 0);
    $db->prepare('UPDATE bh_activities SET title=? WHERE id=?')->execute(['Changed elsewhere', $staleActivityId]);
    $staleApproval = $request('', 'POST', ['action' => 'approve_draft', 'csrf' => $csrf, 'draft_id' => $staleDraftId, 'confirm' => true], $validSession);
    $staleAuditCount = (int)$db->query("SELECT COUNT(*) FROM bh_listing_monitor_audit WHERE draft_id={$staleDraftId}")->fetchColumn();
    $check('API blocks approval when a live field changed after the snapshot', $staleApproval['status'] === 409
        && $db->query("SELECT title FROM bh_activities WHERE id={$staleActivityId}")->fetchColumn() === 'Changed elsewhere'
        && $staleAuditCount === 0);

    $invalidUrl = $request('', 'POST', ['action' => 'save_draft', 'csrf' => $csrf, 'finding_id' => $staleFindingId, 'changes' => ['website' => 'javascript:alert(1)']], $validSession);
    $check('API rejects unsafe website schemes in proposed fields', $invalidUrl['status'] === 422);

    $oversized = $request('', 'POST', null, $validSession, ['Content-Type: application/json', 'Content-Length: 65537']);
    // Send a body large enough to exercise the bounded read, rather than relying only on the declared length.
    $oversizedBody = str_repeat('x', 65537);
    $oversizedContext = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\nCookie: BUBBAHUB_ADMINSESSID={$validSession}\r\nContent-Length: 65537", 'content' => $oversizedBody, 'ignore_errors' => true, 'timeout' => 8]]);
    $oversizedRaw = file_get_contents('http://127.0.0.1:' . $port . '/api/admin-listing-monitor-drafts.php', false, $oversizedContext);
    $oversizedStatus = 0;
    foreach (($http_response_header ?? []) as $header) { if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) { $oversizedStatus = (int)$match[1]; } }
    $check('API rejects oversized JSON bodies', $oversizedStatus === 413);

    echo "\n{$passed} checks passed; " . count($failed) . " failed.\n";
    if ($failed) { exit(1); }
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    @unlink($privateConfig);
    foreach (glob($sessionDir . '/sess_*') ?: [] as $file) { @unlink($file); }
    @unlink($logFile);
    @rmdir($sessionDir);
}
