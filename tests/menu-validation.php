<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/menu-validation.php';

function menu_test(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

menu_test(count(bh_menu_default_data()) === 4, 'four existing menu groups are retained');
menu_test(bh_menu_safe_url('directory.html'), 'relative page links are accepted');
menu_test(bh_menu_safe_url('/directory.html?region=Torbay#results'), 'root-relative links with query and fragment are accepted');
menu_test(bh_menu_safe_url('#footer'), 'same-page fragments are accepted');
menu_test(bh_menu_safe_url('mailto:contact@bubbahub.co.uk'), 'email links are accepted');
menu_test(bh_menu_safe_url('https://www.example.com/families'), 'HTTPS external links are accepted');
menu_test(!bh_menu_safe_url('javascript:alert(1)'), 'javascript URLs are rejected');
menu_test(!bh_menu_safe_url('data:text/html,unsafe'), 'data URLs are rejected');
menu_test(!bh_menu_safe_url('http://example.com'), 'unencrypted external URLs are rejected');
menu_test(!bh_menu_safe_url('//example.com/path'), 'protocol-relative URLs are rejected');
menu_test(!bh_menu_safe_url('/images/../private.txt'), 'parent path traversal is rejected');
menu_test(!bh_menu_safe_url("directory.html\\path"), 'backslashes are rejected');
menu_test(!bh_menu_safe_url('https://example.com:444/path'), 'non-standard HTTPS ports are rejected');
menu_test(!bh_menu_safe_url('mailto:not-an-email'), 'invalid email links are rejected');

$stored = bh_menu_clean_items([
    ['label'=>'  Find activities  ','url'=>'directory.html','visible'=>true],
    ['label'=>'Unsafe','url'=>'javascript:alert(1)','visible'=>true],
    ['label'=>'Hidden','url'=>'privacy.html','visible'=>false],
    ['label'=>'','url'=>'account.html'],
]);
menu_test(count($stored) === 2, 'unsafe and incomplete stored items are omitted');
menu_test($stored[0]['label'] === 'Find activities' && $stored[0]['order'] === 1, 'stored labels and order are normalised');
menu_test($stored[1]['visible'] === false, 'stored visibility is preserved');

$valid = bh_menu_default_data();
$checked = bh_menu_validate_payload($valid);
menu_test($checked['ok'] === true, 'default menu data is a valid complete save');
menu_test($checked['menus']['main'][0]['order'] === 1, 'save order is derived from list order');

$invalid = $valid;
$invalid['unexpected'] = [];
menu_test(!bh_menu_validate_payload($invalid)['ok'], 'unknown menu groups are rejected');
$invalid = $valid;
$invalid['main'] = [];
menu_test(!bh_menu_validate_payload($invalid)['ok'], 'empty main navigation is rejected');
$invalid = $valid;
foreach ($invalid['main'] as &$item) $item['visible'] = false;
unset($item);
menu_test(!bh_menu_validate_payload($invalid)['ok'], 'all-hidden main navigation is rejected');
$invalid = $valid;
$invalid['footer_legal'][0]['url'] = 'javascript:alert(1)';
menu_test(!bh_menu_validate_payload($invalid)['ok'], 'unsafe URLs are rejected on save');
$invalid = $valid;
$invalid['main'][0]['extra'] = 'not allowed';
menu_test(!bh_menu_validate_payload($invalid)['ok'], 'unknown item fields are rejected');
$invalid = $valid;
$invalid['main'][0]['label'] = str_repeat('x', 71);
menu_test(!bh_menu_validate_payload($invalid)['ok'], 'long labels are rejected');
$invalid = $valid;
$invalid['main'][0]['visible'] = 'false';
menu_test(!bh_menu_validate_payload($invalid)['ok'], 'non-boolean visibility is rejected');
$invalid = $valid;
$invalid['main'] = array_fill(0, 31, ['label'=>'Link','url'=>'directory.html','visible'=>true]);
menu_test(!bh_menu_validate_payload($invalid)['ok'], 'menu item count is bounded');
$invalid = $valid;
unset($invalid['footer_tools']);
menu_test(!bh_menu_validate_payload($invalid)['ok'], 'all four menu groups are required');

echo "All menu validation tests passed.\n";
