<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/api/db.php';
$db = bh_mysql();
$sql = file_get_contents(dirname(__DIR__) . '/migrations/claim-invitations-20261030.sql');
if ($sql === false) { fwrite(STDERR, "Migration file missing\n"); exit(1); }
foreach (explode(';', $sql) as $statement) {
    if (trim($statement) !== '') $db->exec($statement);
}
foreach (['bh_claim_invitations', 'bh_listing_claims'] as $table) {
    $check = $db->query("SHOW TABLES LIKE " . $db->quote($table));
    if (!$check->fetchColumn()) { fwrite(STDERR, "Missing table: $table\n"); exit(1); }
    echo "Verified $table\n";
}
