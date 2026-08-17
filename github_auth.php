<?php
session_start();
require_once __DIR__ . '/github_config.php';

// Disconnect flow
if (isset($_GET['disconnect'])) {
    unset($_SESSION['github_token'], $_SESSION['github_username']);
    header('Location: index.php');
    exit;
}

// CSRF protection token for the OAuth round-trip
$state = bin2hex(random_bytes(16));
$_SESSION['github_oauth_state'] = $state;

$params = http_build_query([
    'client_id'    => GITHUB_CLIENT_ID,
    'redirect_uri' => GITHUB_REDIRECT_URI,
    'scope'        => GITHUB_SCOPES,
    'state'        => $state,
    'allow_signup' => 'true',
]);

header('Location: https://github.com/login/oauth/authorize?' . $params);
exit;
