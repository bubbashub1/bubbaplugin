<?php
declare(strict_types=1);

/** Pure rules used by the Listing Monitor worker and its offline tests. */
function bh_lm_is_public_ip(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

/** Resolve literal dot segments without changing query strings or fragments. */
function bh_lm_normalize_path(string $path): string {
    $path = $path === '' ? '/' : $path;
    $absolute = str_starts_with($path, '/');
    $trailing = str_ends_with($path, '/');
    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            if ($segments) {
                array_pop($segments);
            }
            continue;
        }
        $segments[] = $segment;
    }
    $normal = ($absolute ? '/' : '') . implode('/', $segments);
    if ($normal === '') {
        $normal = '/';
    }
    if ($trailing && $normal !== '/') {
        $normal .= '/';
    }
    return $normal;
}

function bh_lm_resolve_redirect(string $location, string $scheme, string $host, int $port, string $currentPath): string {
    $location = trim($location);
    if ($location === '') {
        throw new RuntimeException('Website redirect did not provide a destination.');
    }
    if (preg_match('/[\\r\\n\\\\]/', $location)) {
        throw new RuntimeException('Website redirect destination contains invalid characters.');
    }
    if (str_starts_with($location, '//')) {
        return $scheme . ':' . $location;
    }
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $location)) {
        if (!preg_match('~^https?://~i', $location)) {
            throw new RuntimeException('Website redirect uses an unsupported scheme.');
        }
        return $location;
    }
    $base = $scheme . '://' . $host . (($port !== ($scheme === 'https' ? 443 : 80)) ? ':' . $port : '');
    $parts = parse_url($location);
    if ($parts === false) {
        throw new RuntimeException('Website redirect destination is invalid.');
    }
    $suffix = isset($parts['query']) ? '?' . $parts['query'] : '';
    if (isset($parts['fragment'])) {
        $suffix .= '#' . $parts['fragment'];
    }
    if (str_starts_with($location, '?') || str_starts_with($location, '#')) {
        return $base . $currentPath . $suffix;
    }
    $targetPath = (string)($parts['path'] ?? '');
    if (str_starts_with($targetPath, '/')) {
        return $base . bh_lm_normalize_path($targetPath) . $suffix;
    }
    $directory = rtrim(dirname($currentPath), '/');
    return $base . bh_lm_normalize_path(($directory === '' ? '/' : $directory . '/') . $targetPath) . $suffix;
}

/** Return true only when a closure phrase is not explicitly negated nearby. */
function bh_lm_has_positive_closure_phrase(string $text, ?string &$matched = null): bool {
    $patterns = '/\\b(permanently closed|business has closed|we have closed|ceased trading|no longer trading|closing permanently)\\b/u';
    if (!preg_match_all($patterns, $text, $matches, PREG_OFFSET_CAPTURE)) {
        return false;
    }
    foreach ($matches[1] as [$phrase, $offset]) {
        $prefix = substr($text, max(0, $offset - 48), min(48, $offset));
        if (preg_match('/(?:\\bnot\\b|\\bnever\\b|\\bisn\\x27t\\b|\\baren\\x27t\\b|\\bhasn\\x27t\\b|\\bhaven\\x27t\\b)(?:\\s+\\w+){0,2}\\s*$/iu', $prefix)) {
            continue;
        }
        $matched = $phrase;
        return true;
    }
    return false;
}

function bh_lm_classify_response(int $status, string $body): array {
    $plain = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plain = preg_replace('/\\s+/u', ' ', trim($plain)) ?? trim($plain);
    $lower = strtolower($plain);

    if ($status === 404 || $status === 410) {
        return ['outcome' => 'possible_closure', 'evidence' => 'Website returned HTTP ' . $status . '. This is a dead-link warning, not proof that the activity has closed.'];
    }
    $closurePhrase = null;
    if ($status >= 200 && $status < 300 && bh_lm_has_positive_closure_phrase($lower, $closurePhrase)) {
        $pos = (int) (stripos($lower, $closurePhrase) ?: 0);
        return ['outcome' => 'closure_indicated', 'evidence' => substr($plain, max(0, $pos - 160), 360)];
    }
    if ($status >= 200 && $status < 300 && preg_match('/\\b(temporarily closed|closed for the holidays|seasonal closure|closed today|reopening on|back open on)\\b/u', $lower, $match)) {
        $pos = (int) (stripos($lower, $match[0]) ?: 0);
        return ['outcome' => 'temporary_closure', 'evidence' => substr($plain, max(0, $pos - 160), 360)];
    }
    if ($status >= 200 && $status < 300) {
        return ['outcome' => 'healthy', 'evidence' => 'Website responded successfully (HTTP ' . $status . ').'];
    }
    if ($status >= 500) {
        return ['outcome' => 'unable_to_verify', 'evidence' => 'Website server error HTTP ' . $status . '.'];
    }
    return ['outcome' => 'unable_to_verify', 'evidence' => 'Website returned HTTP ' . $status . '.'];
}
