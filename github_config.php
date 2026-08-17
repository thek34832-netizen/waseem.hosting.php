<?php
/**
 * GitHub Integration Config
 * -------------------------------------------------------------
 * 1. Go to: https://github.com/settings/developers -> "New OAuth App"
 * 2. Homepage URL              : your live site URL (e.g. https://yourdomain.com)
 * 3. Authorization callback URL: https://yourdomain.com/github_callback.php
 * 4. Copy the "Client ID" and generate a "Client Secret", paste them below.
 * 5. Change WEBHOOK_SECRET to any random string of your own — it is used
 *    to verify that update pings really come from GitHub.
 * -------------------------------------------------------------
 */

define('GITHUB_CLIENT_ID', 'Ov23li51iQjXnBUK6jya');
define('GITHUB_CLIENT_SECRET', '15964dbd5241945fcf4fd1b45fdb5a112fe979e1');
define('WEBHOOK_SECRET', 'change-this-to-a-long-random-string');

// Auto-detect the base URL of this installation (no need to hardcode a domain)
function ghBaseUrl() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $dir  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $protocol . '://' . $host . $dir;
}

define('GITHUB_REDIRECT_URI', ghBaseUrl() . '/github_callback.php');
define('GITHUB_SCOPES', 'repo admin:repo_hook read:user');
