<?php
session_start();
require_once __DIR__ . '/storage_config.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/github_config.php';
require_once __DIR__ . '/github_helpers.php';

authRequireLogin();

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

// Validate CSRF state
if (empty($code) || empty($state) || !isset($_SESSION['github_oauth_state']) || $state !== $_SESSION['github_oauth_state']) {
    $_SESSION['error'] = 'GitHub connection failed — invalid or expired request. Please try again.';
    header('Location: index.php');
    exit;
}
unset($_SESSION['github_oauth_state']);

// Exchange the temporary code for an access token
$token_response = ghApiRequest(
    'https://github.com/login/oauth/access_token',
    'POST',
    ['Content-Type: application/json'],
    [
        'client_id'     => GITHUB_CLIENT_ID,
        'client_secret' => GITHUB_CLIENT_SECRET,
        'code'          => $code,
        'redirect_uri'  => GITHUB_REDIRECT_URI,
    ]
);

$access_token = $token_response['body']['access_token'] ?? null;

if (!$access_token) {
    $_SESSION['error'] = 'GitHub connection failed — could not get an access token. Check your Client ID/Secret in github_config.php.';
    header('Location: index.php');
    exit;
}

// Fetch the connected GitHub username
$user_response = ghApiRequest(
    'https://api.github.com/user',
    'GET',
    ['Authorization: Bearer ' . $access_token]
);

$_SESSION['github_token']    = $access_token;
$_SESSION['github_username'] = $user_response['body']['login'] ?? 'GitHub User';
$_SESSION['success'] = 'GitHub connected successfully! Select a repository below to deploy it.';

header('Location: index.php');
exit;
