<?php
session_start();
require_once __DIR__ . '/github_config.php';
require_once __DIR__ . '/github_helpers.php';

header('Content-Type: application/json');

if (empty($_SESSION['github_token'])) {
    echo json_encode(['success' => false, 'message' => 'Not connected to GitHub.']);
    exit;
}

$repos = [];
$page = 1;

// Paginate through all repos (up to a safety cap of 200)
do {
    $response = ghApiRequest(
        "https://api.github.com/user/repos?per_page=100&page=$page&sort=updated",
        'GET',
        ['Authorization: Bearer ' . $_SESSION['github_token']]
    );

    if ($response['status'] !== 200 || !is_array($response['body'])) break;

    foreach ($response['body'] as $repo) {
        $repos[] = [
            'full_name'      => $repo['full_name'],
            'private'        => $repo['private'],
            'default_branch' => $repo['default_branch'],
        ];
    }

    $page++;
} while (count($response['body']) === 100 && count($repos) < 200);

echo json_encode(['success' => true, 'repos' => $repos]);
