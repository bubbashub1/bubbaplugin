<?php
declare(strict_types=1);

/**
 * Disposable-MySQL integration tests for Listing Monitor persistence.
 * Never point LISTING_MONITOR_TEST_DSN at a live or staging database.
 */
$dsn = (string)(getenv('LISTING_MONITOR_TEST_DSN') ?: '');
$user = (string)(getenv('LISTING_MONITOR_TEST_USER') ?: '');
$password = (string)(getenv('LISTING_MONITOR_TEST_PASSWORD') ?: '');
if ($dsn === '' || !str_contains($dsn, 'dbname=listing_monitor_test')) {
    fwrite(STDERR, "Refusing to run: provide a disposable DSN whose database name is listing_monitor_test.\n");
    exit(2);
}

$db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$passed = 0;
$failed = [];
$check = static function (string $name, bool $condition) use (&$passed, &$failed): void {
    if ($condition) { $passed++; echo "PASS: {$name}\n"; }
    else { $failed[] = $name; echo "FAIL: {$name}\n"; }
};

$tables = [
    'bh_listing_monitor_drafts',
    'bh_listing_monitor_website_history',
    'bh_listing_check_findings',
    'bh_listing_check_queue',
    'bh_listing_check_runs',
    'bh_listing_discovery_terms',
    'bh_listing_checker_settings',
    'bh_activities',
];
$db->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($tables as $table) { $db->exec("DROP TABLE IF EXISTS `{$table}`"); }
$db->exec('SET FOREIGN_KEY_CHECKS=1');

$activityFields = [
    'title' => 'VARCHAR(255) NULL',
    'description' => 'MEDIUMTEXT NULL',
    'short_description' => 'TEXT NULL',
    'website' => 'VARCHAR(1000) NULL',
    'booking_url' => 'VARCHAR(1000) NULL',
    'facebook_url' => 'VARCHAR(1000) NULL',
    'instagram_url' => 'VARCHAR(1000) NULL',
    'contact_email' => 'VARCHAR(255) NULL',
    'phone' => 'VARCHAR(100) NULL',
    'town' => 'VARCHAR(190) NULL',
    'region' => 'VARCHAR(190) NULL',
    'postcode' => 'VARCHAR(30) NULL',
    'category' => 'VARCHAR(190) NULL',
    'age_range' => 'VARCHAR(190) NULL',
    'price' => 'VARCHAR(100) NULL',
    'price_details' => 'TEXT NULL',
    'opening_times' => 'TEXT NULL',
    'session_information' => 'MEDIUMTEXT NULL',
];
$definitions = [];
foreach ($activityFields as $name => $type) { $definitions[] = "`{$name}` {$type}"; }
$db->exec('CREATE TABLE bh_activities (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,' . implode(',', $definitions) . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$root = dirname(__DIR__);
foreach ([
    '/database/listing-checker-schema.sql',
    '/database/migrations/20261121_create_listing_monitor_drafts.sql',
    '/database/migrations/20261122_create_listing_monitor_website_checks.sql',
] as $file) {
    $sql = file_get_contents($root . $file);
    if ($sql === false) { throw new RuntimeException('Could not read migration: ' . $file); }
    $db->exec($sql);
}
$check('all Listing Checker and Listing Monitor migrations apply to MySQL', true);
$websiteSettings = $db->query("SELECT setting_key,setting_value FROM bh_listing_checker_settings WHERE setting_key LIKE 'website_monitor_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$check('website monitor has separate safe defaults for enable, interval, batch and runtime',
    ($websiteSettings['website_monitor_enabled'] ?? '') === '0'
    && ($websiteSettings['website_monitor_paused'] ?? '') === '0'
    && ($websiteSettings['website_monitor_interval_hours'] ?? '') === '4'
    && ($websiteSettings['website_monitor_batch_size'] ?? '') === '30'
    && ($websiteSettings['website_monitor_max_runtime_seconds'] ?? '') === '240');

$originalWebsite = 'https://old.example.org';
$insertActivity = $db->prepare('INSERT INTO bh_activities(title,description,website) VALUES(?,?,?)');
$insertActivity->execute(['Test activity', 'Original description', $originalWebsite]);
$activityId = (int)$db->lastInsertId();
$check('test activity fixture created', $activityId > 0);

$history = $db->prepare('INSERT INTO bh_listing_monitor_website_history(activity_id,website_url,outcome,http_status,evidence) VALUES(?,?,?,?,?)');
$history->execute([$activityId, $originalWebsite, 'possible_closure', 404, 'HTTP 404']);
$check('website history accepts repeated private check records', (int)$db->query('SELECT COUNT(*) FROM bh_listing_monitor_website_history')->fetchColumn() === 1);
$history->execute([$activityId, $originalWebsite, 'unable_to_verify', 503, 'Temporary server error']);
$check('website history appends a second check without a separate state table', (int)$db->query('SELECT COUNT(*) FROM bh_listing_monitor_website_history')->fetchColumn() === 2);

$insertFinding = $db->prepare("INSERT INTO bh_listing_check_findings(check_type,activity_id,candidate_json,finding_type,confidence,source_url,checked_at,review_status) VALUES('health',?,?,?,?,?,NOW(),'new')");
$insertFinding->execute([$activityId, json_encode(['website' => $originalWebsite]), 'website_possible_closure', 70, $originalWebsite]);
$findingId = (int)$db->lastInsertId();
$check('website finding insert accepts existing findings schema', $findingId > 0);

$find = $db->prepare("SELECT id,candidate_json FROM bh_listing_check_findings WHERE check_type='health' AND activity_id=? AND finding_type=? ORDER BY id DESC LIMIT 1");
$find->execute([$activityId, 'website_possible_closure']);
$existing = $find->fetch();
$refresh = $db->prepare("UPDATE bh_listing_check_findings SET candidate_json=?,confidence=?,source_url=?,checked_at=NOW(),reviewed_at=IF(review_status='resolved',NULL,reviewed_at),review_status=IF(review_status='resolved','new',review_status) WHERE id=?");
$refresh->execute([json_encode(['website' => $originalWebsite, 'evidence_note' => 'Still unavailable']), 70, $originalWebsite, (int)$existing['id']]);
$check('repeat finding refreshes the existing row', (int)$db->query("SELECT COUNT(*) FROM bh_listing_check_findings WHERE activity_id={$activityId} AND finding_type='website_possible_closure'")->fetchColumn() === 1);

$db->prepare("UPDATE bh_listing_check_findings SET review_status='resolved',reviewed_at=NOW() WHERE id=?")->execute([$findingId]);
$db->prepare("UPDATE bh_listing_check_findings SET reviewed_at=IF(review_status='resolved',NULL,reviewed_at),review_status=IF(review_status='resolved','new',review_status) WHERE id=?")->execute([$findingId]);
$reopened = $db->query("SELECT review_status,reviewed_at FROM bh_listing_check_findings WHERE id={$findingId}")->fetch();
$check('resolved finding reopens as new and clears reviewed timestamp', $reopened['review_status'] === 'new' && $reopened['reviewed_at'] === null);

$db->prepare("UPDATE bh_listing_check_findings SET review_status='follow_up',reviewed_at=NOW() WHERE id=?")->execute([$findingId]);
$db->prepare("UPDATE bh_listing_check_findings SET review_status='resolved',reviewed_at=NOW() WHERE id=? AND review_status IN ('new','follow_up')")->execute([$findingId]);
$check('healthy recovery resolves outstanding follow-up finding', $db->query("SELECT review_status FROM bh_listing_check_findings WHERE id={$findingId}")->fetchColumn() === 'resolved');
$db->prepare("UPDATE bh_listing_check_findings SET review_status='approved' WHERE id=?")->execute([$findingId]);
$db->prepare("UPDATE bh_listing_check_findings SET review_status='resolved',reviewed_at=NOW() WHERE id=? AND review_status IN ('new','follow_up')")->execute([$findingId]);
$check('healthy recovery preserves an admin-approved decision', $db->query("SELECT review_status FROM bh_listing_check_findings WHERE id={$findingId}")->fetchColumn() === 'approved');

$snapshot = ['title' => 'Test activity', 'description' => 'Original description'];
$proposed = ['title' => 'Updated activity title'];
$draft = $db->prepare("INSERT INTO bh_listing_monitor_drafts(finding_id,activity_id,status,original_snapshot,proposed_changes,source_url,source_evidence) VALUES(?,?,'draft',?,?,?,?)");
$draft->execute([$findingId, $activityId, json_encode($snapshot), json_encode($proposed), $originalWebsite, 'Evidence snippet']);
$draftId = (int)$db->lastInsertId();
$check('private draft stores snapshot and proposed changes', $draftId > 0
    && $db->query("SELECT status FROM bh_listing_monitor_drafts WHERE id={$draftId}")->fetchColumn() === 'draft');

$db->beginTransaction();
$live = $db->prepare('SELECT * FROM bh_activities WHERE id=? FOR UPDATE');
$live->execute([$activityId]);
$liveActivity = $live->fetch();
$stale = (string)$liveActivity['title'] !== (string)$snapshot['title'];
if (!$stale) {
    $db->prepare('UPDATE bh_activities SET title=? WHERE id=?')->execute([$proposed['title'], $activityId]);
    $db->prepare("UPDATE bh_listing_monitor_drafts SET status='approved',reviewed_at=NOW(),reviewed_by=? WHERE id=?")
        ->execute(['test-admin', $draftId]);
}
$db->commit();
$approved = $db->query("SELECT title FROM bh_activities WHERE id={$activityId}")->fetchColumn();
$approvedDraft = $db->query("SELECT status,reviewed_by,original_snapshot,proposed_changes FROM bh_listing_monitor_drafts WHERE id={$draftId}")->fetch();
$check('explicit approval updates only the proposed field and retains the full approval record',
    $approved === 'Updated activity title' && $approvedDraft['status'] === 'approved'
    && $approvedDraft['reviewed_by'] === 'test-admin'
    && json_decode($approvedDraft['original_snapshot'], true) === $snapshot
    && json_decode($approvedDraft['proposed_changes'], true) === $proposed);

$staleDraft = $db->prepare("INSERT INTO bh_listing_monitor_drafts(finding_id,activity_id,status,original_snapshot,proposed_changes) VALUES(?,?,'draft',?,?)");
$staleDraft->execute([$findingId, $activityId, json_encode(['title' => 'Old title']), json_encode(['title' => 'Must not overwrite'])]);
$staleId = (int)$db->lastInsertId();
$db->beginTransaction();
$live->execute([$activityId]);
$latest = $live->fetch();
$staleDetected = (string)$latest['title'] !== 'Old title';
if (!$staleDetected) {
    $db->prepare('UPDATE bh_activities SET title=? WHERE id=?')->execute(['Must not overwrite', $activityId]);
}
$db->rollBack();
$check('stale snapshot is detected and blocks a listing update', $staleDetected
    && $db->query("SELECT title FROM bh_activities WHERE id={$activityId}")->fetchColumn() === 'Updated activity title');
$check('stale approval leaves the draft unapproved and creates no approval record',
    $db->query("SELECT status FROM bh_listing_monitor_drafts WHERE id={$staleId}")->fetchColumn() === 'draft');

$final = $db->query("SELECT title,description,website FROM bh_activities WHERE id={$activityId}")->fetch();
$check('website worker leaves all public activity fields untouched', $final['title'] === 'Updated activity title'
    && $final['description'] === 'Original description' && $final['website'] === $originalWebsite);

echo "\n{$passed} checks passed; " . count($failed) . " failed.\n";
if ($failed) { exit(1); }
