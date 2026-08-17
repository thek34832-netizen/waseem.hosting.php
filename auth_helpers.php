<?php
/**
 * Authentication helpers. Users are stored in data/users.json since this
 * project doesn't use a database. Passwords are hashed with PHP's
 * built-in password_hash() (bcrypt) — never stored in plain text.
 */

require_once __DIR__ . '/storage_config.php';

define('USERS_FILE', DATA_DIR . '/users.json');

// Owner's branding photo — shown on the login page and as the header avatar
define('OWNER_PHOTO_URL', 'https://raw.githubusercontent.com/waseempathan501/Photo-link-/refs/heads/main/IMG-20260625-WA0003.jpg');

function authGetUsers() {
    if (!file_exists(USERS_FILE)) return [];
    $data = json_decode(file_get_contents(USERS_FILE), true);
    return is_array($data) ? $data : [];
}

function authSaveUsers($users) {
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0777, true);
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));

    $htaccess = DATA_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\nRequire all denied\n");
    }
}

// Only lowercase letters, numbers, dash, underscore — keeps it filesystem-safe
function authCleanUsername($username) {
    $username = strtolower(trim($username));
    $username = preg_replace('/[^a-z0-9_-]/', '', $username);
    return substr($username, 0, 32);
}

function authUserExists($username) {
    $users = authGetUsers();
    return isset($users[$username]);
}

function authRegister($username, $password) {
    $username = authCleanUsername($username);

    if (strlen($username) < 3) {
        return ['success' => false, 'message' => 'Username at least 3 characters (letters, numbers, - and _ only).'];
    }
    if (strlen($password) < 4) {
        return ['success' => false, 'message' => 'Password at least 4 characters.'];
    }

    $users = authGetUsers();
    if (isset($users[$username])) {
        return ['success' => false, 'message' => 'Username already taken.'];
    }

    $users[$username] = [
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('c'),
    ];
    authSaveUsers($users);

    return ['success' => true, 'username' => $username];
}

function authLogin($username, $password) {
    $username = authCleanUsername($username);
    $users = authGetUsers();

    if (!isset($users[$username]) || !password_verify($password, $users[$username]['password_hash'])) {
        return ['success' => false, 'message' => 'Wrong username or password.'];
    }

    return ['success' => true, 'username' => $username];
}

function authCurrentUser() {
    return $_SESSION['username'] ?? null;
}

// Call at the top of any page that requires login
function authRequireLogin() {
    if (!authCurrentUser()) {
        header('Location: login.php');
        exit;
    }
}

// Every logged-in user's sites live in their own subfolder so names never collide
function userSitesDir($username) {
    $dir = SITES_DIR . '/' . $username;
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    return $dir;
}
