<?php
declare(strict_types=1);

/** Pure rules used by the Listing Monitor worker and its offline tests. */
function bh_lm_is_public_ip(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function bh_lm_resolve_redirect(string $location, string $scheme, string $host, int $port, string $currentPath): string {
    $location = trim($location);
    if ($location === '') {
        throw new RuntimeException('Website redirect did not provide a destination.');
    }
    if (str_starts_with($location, '//')) {
        return $scheme . ':' . $location;
    }
    if (preg_match('~^https?://~i', $location)) {
        return $location;
    }
    $base = $scheme . '://' . $host . (($port !== ($scheme === 'https' ? 443 : 80)) ? ':' . $port : '');
    if (str_starts_with($location, '/')) {
        return $base . $location;
    }
    if (str_starts_with($location, '?')) {
        return $base . $currentPath . $location;
    }
    return $base . rtrim(dirname($currentPath), '/') . '/' . $location;
}

function bh_lm_classify_response(int $status, string $body): array {
    $plain = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plain = preg_replace('/\\s+/u', ' ', trim($plain)) ?? trim($plain);
    $lower = strtolower($plain);

    if ($status === 404 || $status === 410) {
        return ['outcome' => 'possible_closure', 'evidence' => 'Website returned HTTP ' . $status . '. This is a dead-link warning, not proof that the activity has closed.'];
    }
    if ($status >= 200 && $status < 300 && preg_match('/\\b(permanently closed|business has closed|we have closed|ceased trading|no longer trading|closing permanently)\\b/u', $lower, $match)) {
        $pos = (int) (stripos($lower, $match[0]) ?: 0);
        return ['outcome' => 'closure_indicated', 'evidence' => substr($plain, max(0, $pos - 160), 360)];
    }
    if ($status >= 200 && $status < 300 && preg_match('/\\b(temporarily closed|closed for the holidays|seasonal closure|closed today|reopening on|back open on)\\b/u', $lower, $match)) {
        $pos = (int) (stripos($lower, $match[0]) ?: 0);
        return ['outcome' => 'temporary_closure', 'evidence' => substr($plain, max(0, $pos - 160), 360)];
    }
    if ($status >= 200 && $status < 400) {
        return ['outcome' => 'healthy', 'evidence' => 'Website responded successfully (HTTP ' . $status . ').'];
    }
    if ($status >= 500) {
        return ['outcome' => 'unable_to_verify', 'evidence' => 'Website server error HTTP ' . $status . '.'];
    }
    return ['outcome' => 'unable_to_verify', 'evidence' => 'Website returned HTTP ' . $status . '.'];
}
