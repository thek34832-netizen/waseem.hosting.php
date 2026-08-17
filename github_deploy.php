<?php
session_start();
require_once __DIR__ . '/github_config.php';
require_once __DIR__ . '/github_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['github_token'])) {
    header('Location: index.php');
    exit;
}

$token           = $_SESSION['github_token'];
$repo_full_name  = trim($_POST['repo_full_name'] ?? '');
$branch          = trim($_POST['branch'] ?? '') ?: 'main';
$custom_url      = trim($_POST['custom_url'] ?? '');

// Reuse the same slug-cleaning rules as the rest of the site
function ghCleanCustomUrl($url) {
    $url = preg_replace('/[^a-z0-9-]/', '', strtolower($url));
    $url = preg_replace('/-+/', '-', $url);
    $url = trim($url, '-');
    return $url ?: 'project-' . rand(1000, 9999);
}

if (empty($repo_full_name) || strpos($repo_full_name, '/') === false) {
    $_SESSION['error'] = 'Please choose a valid repository.';
    header('Location: index.php');
    exit;
}

$custom_url = ghCleanCustomUrl($custom_url);
[$owner, $repo] = explode('/', $repo_full_name, 2);

// Block overwriting a site that isn't already this same GitHub deployment
$deployments = ghGetDeployments();
$existing_plain_file = false;
foreach (['html', 'htm', 'css', 'js'] as $ext) {
    if (file_exists(SITES_DIR . "/$custom_url.$ext")) $existing_plain_file = true;
}
$is_same_repo_redeploy = isset($deployments[$custom_url]) && $deployments[$custom_url]['repo_full_name'] === $repo_full_name;

if (($existing_plain_file || (is_dir(SITES_DIR . "/$custom_url") && !isset($deployments[$custom_url]))) && !$is_same_repo_redeploy) {
    $_SESSION['error'] = 'That project name is already taken. Choose another.';
    header('Location: index.php');
    exit;
}

// Download + extract the repo into sites/{custom_url}/
$result = ghDeployRepoToSite($owner, $repo, $branch, $custom_url, $token);

if (!$result['success']) {
    $_SESSION['error'] = $result['message'];
    header('Location: index.php');
    exit;
}

// Register (or reuse) the push webhook so future commits auto-redeploy
$hook_id = ghEnsureWebhook($owner, $repo, $token);

// Save/update the deployment record
$deployments[$custom_url] = [
    'repo_full_name' => $repo_full_name,
    'branch'         => $branch,
    'owner'          => $owner,
    'repo'           => $repo,
    'token'          => $token, // stored so the webhook can pull private repos later
    'webhook_id'     => $hook_id,
    'deployed_by'    => $_SESSION['github_username'] ?? null,
    'updated_at'     => date('c'),
];
ghSaveDeployments($deployments);

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$website_url = $base_url . '/view.php?site=' . $custom_url;

$_SESSION['success']  = 'Deployed from GitHub! Future pushes to ' . htmlspecialchars($repo_full_name) . ' will auto-update this site.';
$_SESSION['file_url'] = $website_url;
$_SESSION['file_name'] = $repo_full_name;

header('Location: index.php');
exit;
