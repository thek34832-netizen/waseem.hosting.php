<?php
/**
 * GitHub calls this file automatically every time someone pushes to a
 * connected repo. It verifies the request really came from GitHub, then
 * re-downloads and re-extracts that repo so the live site updates itself
 * with zero manual action.
 */
require_once __DIR__ . '/github_config.php';
require_once __DIR__ . '/github_helpers.php';

header('Content-Type: application/json');

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

// Verify the payload was really signed with our webhook secret
$expected = 'sha256=' . hash_hmac('sha256', $payload, WEBHOOK_SECRET);
if (!$signature || !hash_equals($expected, $signature)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid signature.']);
    exit;
}

$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
$data  = json_decode($payload, true);

// Only react to push events; ignore GitHub's initial "ping" test event, etc.
if ($event !== 'push' || !$data || empty($data['repository']['full_name'])) {
    echo json_encode(['success' => true, 'message' => 'Ignored (not a push event).']);
    exit;
}

$repo_full_name = $data['repository']['full_name'];
$pushed_branch  = isset($data['ref']) ? str_replace('refs/heads/', '', $data['ref']) : null;

$deployment = ghFindDeploymentByRepo($repo_full_name);

if (!$deployment) {
    echo json_encode(['success' => false, 'message' => 'No deployment found for this repo.']);
    exit;
}

// Only redeploy if the push was to the branch we actually deployed
if ($pushed_branch && $pushed_branch !== $deployment['branch']) {
    echo json_encode(['success' => true, 'message' => "Push to '$pushed_branch' ignored, deployed branch is '{$deployment['branch']}'."]);
    exit;
}

$result = ghDeployRepoToSite(
    $deployment['owner'],
    $deployment['repo'],
    $deployment['branch'],
    $deployment['custom_url'],
    $deployment['token'] ?? null
);

// Keep the "updated_at" timestamp fresh
if ($result['success']) {
    $deployments = ghGetDeployments();
    if (isset($deployments[$deployment['custom_url']])) {
        $deployments[$deployment['custom_url']]['updated_at'] = date('c');
        ghSaveDeployments($deployments);
    }
}

echo json_encode($result);
