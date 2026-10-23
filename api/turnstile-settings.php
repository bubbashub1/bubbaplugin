<?php
declare(strict_types=1);
/** Read existing Cloudflare Turnstile configuration from WordPress constants or environment. */
function bh_turnstile_setting(string $name): string {
    $aliases = [
        'BUBBAHUB_TURNSTILE_SITE_KEY' => ['BUBBAHUB_TURNSTILE_SITE_KEY','BH_TURNSTILE_SITE_KEY','TURNSTILE_SITE_KEY'],
        'BUBBAHUB_TURNSTILE_SECRET_KEY' => ['BUBBAHUB_TURNSTILE_SECRET_KEY','BH_TURNSTILE_SECRET_KEY','TURNSTILE_SECRET_KEY'],
    ];
    foreach ($aliases[$name] ?? [$name] as $key) {
        if (defined($key)) { $value = trim((string)constant($key)); if ($value !== '') return $value; }
        $value = getenv($key); if (is_string($value) && trim($value) !== '') return trim($value);
    }
    return '';
}
