<?php
declare(strict_types=1);

/**
 * Read Turnstile settings without bootstrapping WordPress.
 *
 * The public config endpoint and native API files run outside WordPress, so
 * wp-config.php has not necessarily been executed and its putenv()/define()
 * values are not available through getenv()/defined(). Read only the two
 * explicitly requested settings from the config file; never include or eval it.
 */
function bh_turnstile_setting(string $name): string {
    $aliases = [
        'BUBBAHUB_TURNSTILE_SITE_KEY' => ['BUBBAHUB_TURNSTILE_SITE_KEY','BH_TURNSTILE_SITE_KEY','TURNSTILE_SITE_KEY'],
        'BUBBAHUB_TURNSTILE_SECRET_KEY' => ['BUBBAHUB_TURNSTILE_SECRET_KEY','BH_TURNSTILE_SECRET_KEY','TURNSTILE_SECRET_KEY'],
    ];
    $keys = $aliases[$name] ?? [$name];

    // Prefer values already loaded into this PHP process.
    foreach ($keys as $key) {
        if (defined($key)) {
            $value = trim((string)constant($key));
            if ($value !== '') return $value;
        }
        $value = getenv($key);
        if (is_string($value) && trim($value) !== '') return trim($value);
    }

    // Standalone /api endpoints do not load WordPress, so inspect wp-config.php
    // as text. Never execute the file and never return its contents to callers.
    $candidates = array_unique(array_filter([
        !empty($_SERVER['DOCUMENT_ROOT']) ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/wp-config.php' : '',
        dirname(__DIR__) . '/wp-config.php',
        dirname(__DIR__, 2) . '/wp-config.php',
    ]));

    foreach ($candidates as $file) {
        if (!is_file($file) || !is_readable($file)) continue;
        $source = @file_get_contents($file);
        if (!is_string($source) || $source === '') continue;

        foreach ($keys as $key) {
            // Supports the putenv('NAME=value') form used in wp-config.php.
            if (preg_match('~^[ \\t]*putenv\\s*\\(\\s*["\\x27]' . preg_quote($key, '~') . '=([^"\\x27]*)["\\x27]\\s*\\)\\s*;~m', $source, $match)) {
                $value = trim(stripcslashes($match[1]));
                if ($value !== '') return $value;
            }

            // Also supports define('NAME', 'value') configuration.
            if (preg_match('~^[ \\t]*define\\s*\\(\\s*["\\x27]' . preg_quote($key, '~') . '["\\x27]\\s*,\\s*["\\x27](.*?)["\\x27]\\s*\\)\\s*;~m', $source, $match)) {
                $value = trim(stripcslashes($match[1]));
                if ($value !== '') return $value;
            }
        }
    }

    return '';
}
