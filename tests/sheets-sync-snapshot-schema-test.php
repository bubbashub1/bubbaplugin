<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/sheets-sync-snapshot-schema.php';
$record = ['environment'=>'staging','activity_id'=>999999,'approved_by'=>1,
    'approved_at'=>'2026-10-21T18:00:00Z','version'=>1,'fields'=>['title'=>'Approved']];
if (!bh_sync_validate_snapshot_metadata($record)['ok']) exit(1);
$cases = [
    array_merge($record,['environment'=>'live']),
    array_merge($record,['approved_by'=>0]),
    array_merge($record,['fields'=>['unexpected'=>'value']]),
    array_merge($record,['fields'=>['title'=>[]]]),
    array_merge($record,['version'=>0]),
];
foreach ($cases as $case) if (bh_sync_validate_snapshot_metadata($case)['ok']) exit(1);
echo "PASS: snapshot metadata provenance validation\n";
