const fs = require('node:fs');
const assert = require('node:assert/strict');

const html = fs.readFileSync('admin/admin-site-manager.html', 'utf8');

assert.ok(html.includes('href="/admin/admin-visual-builder.html"'), 'Site Manager should link to the existing Visual Builder');
assert.ok(html.includes('href="/admin/admin-navigation.html"'), 'Site Manager should link to the existing Navigation Manager');
assert.ok(html.includes('Menu saves apply to the database of the deployment you are using'), 'The menu editor must explain that saves affect the current deployment');
assert.ok(html.includes('Visual Builder changes are isolated drafts'), 'The Visual Builder draft boundary must be visible in Site Manager');
assert.ok(html.includes('upload and delete are not enabled'), 'The Site Manager must not imply that media uploads or deletion are available');
assert.ok(!html.includes('href="/library/"'), 'Do not link the staging Site Manager to the File Manager that writes to GitHub main');

console.log('Site Manager entry-point and safety-boundary checks passed.');
