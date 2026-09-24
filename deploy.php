<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

/*
 * Only allow deployment requests originating from the Bubba Hub beta site.
 * GitHub credentials remain on the server and are never sent to the browser.
 */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

$allowed =
    ($origin === 'https://www.bubbahub.co.uk') ||
    str_starts_with($referer, 'https://www.bubbahub.co.uk/beta/');

if (!$allowed) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Request not authorised']);
    exit;
}

/*
 * GitHub credentials are stored outside public_html.
 */
$configFile = dirname(dirname(__DIR__)) . '/github-deploy-config.php';

if (!is_file($configFile)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Deployment is not configured']);
    exit;
}

$config = require $configFile;
$githubToken = (string)($config['github_token'] ?? '');

if ($githubToken === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'GitHub deployment token is missing']);
    exit;
}

/*
 * Trigger the GitHub Actions deployment.
 */
$ch = curl_init(
    'https://api.github.com/repos/bubbashub1/bubbaplugin/actions/workflows/deploy.yml/dispatches'
);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'ref' => 'main'
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/vnd.github+json',
        'Authorization: Bearer ' . $githubToken,
        'Content-Type: application/json',
        'User-Agent: BubbaHub-Admin-Deploy',
        'X-GitHub-Api-Version: 2022-11-28'
    ],
    CURLOPT_TIMEOUT => 20
]);

$response = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

if ($response === false || $status < 200 || $status >= 300) {
    http_response_code(502);

    echo json_encode([
        'ok' => false,
        'error' => 'GitHub deployment request failed',
        'details' => $error !== '' ? $error : 'HTTP ' . $status
    ]);

    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Deployment started'
]);
