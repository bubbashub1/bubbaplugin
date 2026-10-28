import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const php = readFileSync('api/admin-import-export.php', 'utf8');
const js = readFileSync('assets/js/admin-import-export.js', 'utf8');
const html = readFileSync('admin/admin-import-export.html', 'utf8');

function check(label, condition) {
  assert.ok(condition, label);
  process.stdout.write('PASS: ' + label + '\n');
}

check('server uses 25-row bounded batches', php.includes('array_slice($rows,$offset,$limit)') && php.includes("'has_more'"));
check('both clients send selected fields', (js.match(/chunk\.append\("update_fields"/g) || []).length === 2);
check('both clients advance batch offset', (js.match(/offset=next/g) || []).length === 2);
check('CSV field selector exists', html.includes('id="activityCsvFieldChoices"'));
check('no default preview checkbox', !html.includes('id="activityCsvDryRun"'));
check('existing activity values update selectively', php.includes('Existing records: update only explicitly supplied nonblank fields.'));
check('shared venue writes are blocked', php.includes('if($sets && $sharedCount===0)'));
check('shared organiser details are preserved', php.includes('if((int)$q->fetchColumn()>1){$validSets=[];$validArgs=[];}'));
check('existing sessions require explicit replacement', php.includes("($_POST['replace_sessions']??'')==='1'"));
check('unscoped session replacements are rejected', php.includes('Session replacement requires activity-scoped sessions'));
check('existing gallery replacement is opt-in', php.includes("($_POST['replace_gallery']??'')==='1'"));
check('multi-venue edits require explicit targeting', php.includes('Multiple venues linked to this activity. Venue changes require a venue-specific import'));
check('CSV headers must be unique', php.includes('CSV headers must be nonempty and unique'));
check('missing venue links do not create blank venue records', php.includes('Existing activity has no linked venue'));
check('legacy shared-venue timetable export is guarded', php.includes('exportSessionsSafe'));
check('row count is capped', php.includes('20,000-row safety limit'));
check('unknown IDs cannot silently create duplicates', php.includes('refusing to create a duplicate'));
console.log('Static importer contracts passed; staging/database integration tests still required.');
