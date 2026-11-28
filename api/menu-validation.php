<?php
declare(strict_types=1);

/** Shared, side-effect-free menu defaults and validation. */
function bh_menu_default_data(): array {
    $groups = [
        'main' => [
            ['label'=>'Find activities','url'=>'directory.html'],
            ['label'=>'My Hub','url'=>'my-hub.html'],
            ['label'=>'Support & Guidance','url'=>'help-support.html'],
            ['label'=>'Leader Hub','url'=>'leader.html'],
            ['label'=>'Account','url'=>'account.html'],
        ],
        'footer_main' => [
            ['label'=>'Find activities','url'=>'directory.html'],
            ['label'=>'My Hub','url'=>'my-hub.html'],
            ['label'=>'Support & Guidance','url'=>'help-support.html'],
            ['label'=>'Leader Hub','url'=>'leader.html'],
            ['label'=>'Account','url'=>'account.html'],
        ],
        'footer_tools' => [
            ['label'=>'Events','url'=>'events.html'],
            ['label'=>'Venues','url'=>'venues.html'],
            ['label'=>'Planner','url'=>'planner.html'],
            ['label'=>'Calendar','url'=>'calendar.html'],
            ['label'=>'Admin','url'=>'admin/admin.html'],
        ],
        'footer_legal' => [
            ['label'=>'Privacy','url'=>'privacy.html'],
            ['label'=>'Terms & Conditions','url'=>'terms.html'],
            ['label'=>'Contact Bubba Hub','url'=>'mailto:contact@bubbahub.co.uk'],
        ],
    ];
    foreach ($groups as &$items) {
        foreach ($items as $index => &$item) {
            $item['visible'] = true;
            $item['order'] = $index + 1;
        }
        unset($item);
    }
    unset($items);
    return $groups;
}

function bh_menu_clean_label(mixed $value): ?string {
    if (!is_string($value)) return null;
    $label = trim((string)preg_replace('/[\x00-\x1F\x7F]/', '', $value));
    if ($label === '' || mb_strlen($label, 'UTF-8') > 70) return null;
    return $label;
}

/** Only local paths/fragments, HTTPS links, and mailto links are accepted. */
function bh_menu_safe_url(mixed $value): bool {
    if (!is_string($value)) return false;
    $url = trim($value);
    if ($url === '' || strlen($url) > 500) return false;
    if (preg_match('/[\x00-\x20\x7F\\\\]/', $url)) return false;
    if (str_starts_with($url, '//')) return false;

    if (preg_match('/^mailto:/i', $url)) {
        $address = substr($url, 7);
        return $address !== '' &&
            !str_contains($address, '?') &&
            !str_contains($address, '#') &&
            filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }

    $parts = parse_url($url);
    if ($parts === false) return false;
    if (isset($parts['scheme'])) {
        if (strtolower((string)$parts['scheme']) !== 'https') return false;
        if (empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return false;
        if (isset($parts['port']) && (int)$parts['port'] !== 443) return false;
        $host = (string)$parts['host'];
        return filter_var($host, FILTER_VALIDATE_IP) !== false ||
            filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    if (isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) return false;
    $path = (string)($parts['path'] ?? '');
    if (preg_match('~(?:^|/)\.\.(?:/|$)~', $path)) return false;
    return preg_match('/^[A-Za-z0-9._~!$&\'()*+,;=:@%\/?#-]+$/D', $url) === 1;
}

/** Normalise legacy stored items for safe public rendering. */
function bh_menu_clean_items(mixed $items): array {
    if (!is_array($items)) return [];
    $clean = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $label = bh_menu_clean_label($item['label'] ?? null);
        $url = $item['url'] ?? null;
        if ($label === null || !bh_menu_safe_url($url)) continue;
        $clean[] = [
            'label' => $label,
            'url' => trim((string)$url),
            'visible' => ($item['visible'] ?? true) !== false,
            'order' => count($clean) + 1,
        ];
        if (count($clean) >= 30) break;
    }
    return $clean;
}

/** Validate a complete admin save payload without silently dropping bad rows. */
function bh_menu_validate_payload(mixed $menus): array {
    $keys = array_keys(bh_menu_default_data());
    if (!is_array($menus)) return ['ok'=>false, 'error'=>'Menu data must be an object.'];
    if (array_diff(array_keys($menus), $keys)) return ['ok'=>false, 'error'=>'Unknown menu group.'];

    $clean = [];
    foreach ($keys as $key) {
        if (!array_key_exists($key, $menus) || !is_array($menus[$key]) || !array_is_list($menus[$key])) {
            return ['ok'=>false, 'error'=>'Each menu group must be supplied as a list.'];
        }
        if (count($menus[$key]) > 30) return ['ok'=>false, 'error'=>'Each menu can contain at most 30 items.'];

        $clean[$key] = [];
        foreach ($menus[$key] as $item) {
            if (!is_array($item) || array_diff(array_keys($item), ['label','url','visible','order'])) {
                return ['ok'=>false, 'error'=>'A menu item contains unsupported fields.'];
            }
            $label = bh_menu_clean_label($item['label'] ?? null);
            $url = $item['url'] ?? null;
            $visible = $item['visible'] ?? true;
            if ($label === null) return ['ok'=>false, 'error'=>'Every menu item needs a label of 1–70 characters.'];
            if (!bh_menu_safe_url($url)) return ['ok'=>false, 'error'=>'Use a valid local link, HTTPS address or email link.'];
            if (!is_bool($visible)) return ['ok'=>false, 'error'=>'Menu visibility must be true or false.'];
            $clean[$key][] = [
                'label' => $label,
                'url' => trim((string)$url),
                'visible' => $visible,
                'order' => count($clean[$key]) + 1,
            ];
        }
    }

    $visibleMain = array_filter($clean['main'], static fn(array $item): bool => $item['visible']);
    if (!$visibleMain) return ['ok'=>false, 'error'=>'Keep at least one main navigation link visible.'];
    return ['ok'=>true, 'menus'=>$clean];
}
