<?php
declare(strict_types=1);
/** Admin-only, read-only comparison for the first live Sheets baseline. */
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');
if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'bubbahub.co.uk') { http_response_code(403); exit('Live host required'); }
require_once __DIR__.'/admin-session-guard.php';
require_once __DIR__.'/sheets-sync-baseline-guard.php';
$privateSettings = dirname(__DIR__, 2).'/bh-sheets-sync-secret.php';
if (is_file($privateSettings) && is_readable($privateSettings)) require_once $privateSettings;
function bhReviewEscape($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
$id=955;
$fields=['title','price_from','age_range'];
$error=null;
$website=[];
$sheet=[];
$received=[];
$existing=false;
try {
    require_once __DIR__.'/db.php';
    $db=bh_mysql();
    $q=$db->prepare('SELECT a.id,a.title,a.price_from,a.age_range,a.status,a.leader_id,a.created_by,o.email AS organiser_email FROM bh_activities a LEFT JOIN bh_organisers o ON o.id=a.organiser_id WHERE a.id=?');
    $q->execute([$id]);
    $website=$q->fetch(PDO::FETCH_ASSOC) ?: [];
    $q=$db->prepare('SELECT field_name,sheet_value,received_at_utc FROM bh_sheets_sync_live_sheet_snapshots WHERE activity_id=? AND field_name IN (\'title\',\'price_from\',\'age_range\')');
    $q->execute([$id]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) { $sheet[$r['field_name']]=$r['sheet_value']; $received[$r['field_name']]=$r['received_at_utc']; }
    $q=$db->prepare("SELECT COUNT(*) FROM bh_sheets_sync_live_baselines WHERE environment='live' AND activity_id=?");
    $q->execute([$id]); $existing=(int)$q->fetchColumn()>0;
} catch (Throwable $e) { error_log('Baseline review unavailable: '.$e->getMessage()); $error='Could not load the live comparison. No changes made.'; }
$eligible= !$error && $website && $website['status']==='published' && $website['leader_id']===null && $website['created_by']===null && !$existing;
if ($eligible && trim((string)($website['organiser_email']??''))!=='') {
    try {
        $owner=$db->prepare("SELECT 1 FROM bh_users WHERE role='leader' AND status='active' AND LOWER(TRIM(email))=LOWER(TRIM(?)) LIMIT 1");
        $owner->execute([$website['organiser_email']]);
        if ($owner->fetchColumn()) $eligible=false;
    } catch (Throwable $e) { $eligible=false; error_log('Baseline ownership preview failed: '.$e->getMessage()); }
}
$allMatch=$eligible;
foreach ($fields as $f) {
    if (!isset($sheet[$f]) || trim((string)($website[$f]??''))==='' || !bh_sync_field_equal($f,(string)$website[$f],(string)$sheet[$f])) $allMatch=false;
    if (!isset($received[$f]) || strtotime($received[$f].' UTC') < time()-86400) $allMatch=false;
}
$enabled=getenv('BH_SHEETS_LIVE_BASELINE_APPROVALS')==='enabled';
$ready=$enabled && $allMatch;
?>
<!doctype html><html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sheets baseline review · Bubba Hub</title>
<style>body{margin:0;background:#f5faf6;color:#193d2c;font:16px/1.5 system-ui,sans-serif}main{max-width:880px;margin:32px auto;padding:24px}h1{margin-bottom:8px}.panel{background:white;border:1px solid #d6e7d9;border-radius:18px;padding:24px;margin:20px 0;overflow:auto}table{border-collapse:collapse;width:100%;min-width:580px}th,td{padding:12px;text-align:left;border-bottom:1px solid #e4ece6}th{background:#eef7f0}.muted{color:#546d5e}button{border:0;border-radius:25px;padding:13px 24px;background:#16583c;color:white;font-weight:750;cursor:pointer}button:disabled{background:#a0b5a6;cursor:not-allowed}a{color:#16583c}#result{white-space:pre-wrap}</style></head><body><main>
<a href="/admin.html">← Back to Admin</a>
<h1>Google Sheets baseline review</h1><p class="muted">Activity 955 · Review only. Approving stores a trusted baseline; it does not update the activity listing.</p>
<section class="panel"><h2>Website vs Google Sheet</h2>
<?php if ($error): ?><p role="alert"><?=bhReviewEscape($error)?></p><?php else: ?>
<table><thead><tr><th>Field</th><th>Website</th><th>Google Sheet</th><th>Check</th></tr></thead><tbody>
<?php foreach ($fields as $f):
$match=isset($sheet[$f]) && bh_sync_field_equal($f,(string)($website[$f]??''),(string)$sheet[$f]);
$fresh=isset($received[$f]) && strtotime($received[$f].' UTC')>=time()-86400;
?><tr><th scope="row"><?=bhReviewEscape($f)?></th><td><?=bhReviewEscape($website[$f]??'')?></td><td><?=bhReviewEscape($sheet[$f]??'Missing')?></td><td><?= $match && $fresh ? 'Matches · fresh' : ($match ? 'Snapshot expired' : 'Review required') ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?></section>
<section class="panel"><h2>Approval</h2>
<p id="status"><?= $existing ? 'A baseline already exists for this activity.' : ($ready ? 'All three fields match a recent Sheet snapshot. Ready for explicit approval.' : 'Not ready: confirm the listing is unowned, the snapshots are less than 24 hours old, and approvals are enabled.') ?></p>
<p><label><input id="confirm" type="checkbox" <?= $ready ? '' : 'disabled' ?>> I have reviewed all three values and approve capturing the current website values as the baseline for activity 955.</label></p>
<button id="approve" type="button" disabled>Approve baseline for activity 955</button>
<p id="result" role="status" aria-live="polite"></p>
<p class="muted">Automatic live activity updates remain disabled. Never paste your CSRF token into chat.</p>
</section></main>
<script>
const ready=<?= $ready ? 'true' : 'false' ?>;
const expected=<?=json_encode(['955'=>array_intersect_key($website,array_flip($fields))],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?>;
for(const field of ['title','price_from','age_range']) expected['955'][field]=String(expected['955'][field]??'');
const checkbox=document.getElementById('confirm'), button=document.getElementById('approve'), result=document.getElementById('result');
checkbox.addEventListener('change',()=>button.disabled=!ready||!checkbox.checked);
button.addEventListener('click',async()=>{
 if(!ready||!checkbox.checked||!window.confirm('Approve the current website values for activity 955? This creates a baseline record only.'))return;
 button.disabled=true;result.textContent='Submitting approval…';
 try {
   const endpoint='/api/sheets-sync-live-baseline-approve.php';
   const get=await fetch(endpoint,{credentials:'same-origin',cache:'no-store'});
   const state=await get.json();
   if(!get.ok||!state.ok||!state.enabled||!state.csrf_token)throw Error(state.error||'Approval endpoint unavailable');
   const response=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({confirmed:true,activity_ids:[955],fields:['title','price_from','age_range'],expected_values:expected,csrf_token:state.csrf_token})});
   const data=await response.json();
   if(!response.ok||!data.ok)throw Error(data.error||'Approval not completed');
   result.textContent='Baseline approved for activity 955. Activity writes performed: '+data.activity_writes_performed+'. Refresh this page to verify.';
   checkbox.disabled=true;
 }catch(e){result.textContent='Approval not completed: '+e.message+'. No retry was attempted.';}
});
</script></body></html>