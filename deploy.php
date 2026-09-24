<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

if (empty($_SESSION["bh_admin_authenticated"])) { http_response_code(401); echo json_encode(["ok"=>false,"error"=>"Admin login required"]); exit; }

$githubToken = (string)($config['github_token'] ?? '');

if ($githubToken === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'GitHub deployment token is missing']);
    exit;
}

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
