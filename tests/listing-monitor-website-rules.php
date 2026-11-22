<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/listing-monitor-website-rules.php';

$passed = 0;
$failed = [];
$check = static function (string $name, bool $condition) use (&$passed, &$failed): void {
    if ($condition) { $passed++; echo "PASS: {$name}\n"; }
    else { $failed[] = $name; echo "FAIL: {$name}\n"; }
};
$expect = static function (int $status, string $body, string $outcome): bool {
    return bh_lm_classify_response($status, $body)['outcome'] === $outcome;
};

$check('200 ordinary page is healthy', $expect(200, '<html><body>Welcome to our classes</body></html>', 'healthy'));
$check('301 final response is healthy if presented without redirect', $expect(301, '', 'healthy'));
$check('404 is possible closure, not confirmed closure', $expect(404, 'Not found', 'possible_closure'));
$check('410 is possible closure', $expect(410, 'Gone', 'possible_closure'));
$check('explicit permanent closure is indicated', $expect(200, '<p>We have permanently closed our business.</p>', 'closure_indicated'));
$check('ceased trading is closure indication', $expect(200, 'We have ceased trading.', 'closure_indicated'));
$check('temporary closure stays temporary', $expect(200, 'We are temporarily closed.', 'temporary_closure'));
$check('holiday closure stays temporary', $expect(200, 'Closed for the holidays.', 'temporary_closure'));
$check('403 is unable to verify', $expect(403, 'Access denied', 'unable_to_verify'));
$check('429 is unable to verify', $expect(429, 'Rate limited', 'unable_to_verify'));
$check('500 is unable to verify', $expect(500, 'Server error', 'unable_to_verify'));
$check('503 is unable to verify', $expect(503, 'Unavailable', 'unable_to_verify'));
$check('closure words in a 403 do not indicate closure', $expect(403, 'Permanently closed', 'unable_to_verify'));
$evidence = bh_lm_classify_response(200, '<p>We have permanently closed our business.</p>')['evidence'];
$check('closure evidence contains the matching wording', str_contains($evidence, 'permanently closed'));
$check('public IPv4 accepted', bh_lm_is_public_ip('8.8.8.8'));
$check('loopback IPv4 rejected', !bh_lm_is_public_ip('127.0.0.1'));
$check('private IPv4 rejected', !bh_lm_is_public_ip('192.168.1.10'));
$check('link-local IPv4 rejected', !bh_lm_is_public_ip('169.254.1.1'));
$check('IPv6 loopback rejected', !bh_lm_is_public_ip('::1'));
$check('public IPv6 accepted', bh_lm_is_public_ip('2606:4700:4700::1111'));
$check('absolute redirect retained', bh_lm_resolve_redirect('https://example.org/new', 'https', 'example.com', 443, '/old/page') === 'https://example.org/new');
$check('protocol-relative redirect inherits scheme', bh_lm_resolve_redirect('//example.org/new', 'https', 'example.com', 443, '/old/page') === 'https://example.org/new');
$check('root-relative redirect uses same host', bh_lm_resolve_redirect('/new', 'https', 'example.com', 443, '/old/page') === 'https://example.com/new');
$check('relative redirect uses current directory', bh_lm_resolve_redirect('new', 'https', 'example.com', 443, '/old/page') === 'https://example.com/old/new');
$check('query-only redirect preserves path', bh_lm_resolve_redirect('?next=1', 'https', 'example.com', 443, '/old/page') === 'https://example.com/old/page?next=1');
$check('nonstandard port retained for redirect base', bh_lm_resolve_redirect('/new', 'http', 'example.com', 8080, '/old') === 'http://example.com:8080/new');

echo "\n{$passed} checks passed; " . count($failed) . " failed.\n";
if ($failed) { exit(1); }
