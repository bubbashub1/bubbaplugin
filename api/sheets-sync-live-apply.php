<?php
declare(strict_types=1);
/**
 * Phase 1: permanently locked live writer entrypoint.
 * Deliberately contains no database write statements.
 * DO NOT turn this into an updater without separate review and audited schema.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhLockedWriterReply(int $status,array $body):never {
 http_response_code($status);
 echo json_encode($body+['activity_writes_performed'=>0,'mode'=>'locked_no_writes'],JSON_INVALID_UTF8_SUBSTITUTE);
 exit;
}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='bubbahub.co.uk')
 bhLockedWriterReply(403,['ok'=>false,'error'=>'live_host_required']);
require_once __DIR__.'/admin-session-guard.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')
 bhLockedWriterReply(405,['ok'=>false,'error'=>'post_required']);
$settings=dirname(__DIR__,2).'/bh-sheets-sync-secret.php';
if(is_file($settings)&&is_readable($settings))require_once $settings;
// A separate, code-level release gate. Enabling an environment flag alone cannot
// activate activity writes, even accidentally.
bhLockedWriterReply(423,[
 'ok'=>false,
 'error'=>'live_writer_not_implemented',
 'live_writes_enabled'=>getenv('BH_SHEETS_SYNC_LIVE_WRITES')==='enabled',
 'next_step'=>'Audit change log schema, implement transactional checks, and test on staging before permitting writes.'
]);
