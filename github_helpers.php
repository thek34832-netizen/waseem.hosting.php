<?php
/**
 * Shared helpers for the GitHub connect / auto-deploy feature.
 * Deployment records are stored in a flat JSON file (data/deployments.json)
 * since this project doesn't use a database. Paths come from storage_config.php
 * so everything lands on the Railway volume when one is mounted.
 */

require_once __DIR__ . '/storage_config.php';

define('GH_DATA_DIR', DATA_DIR);
define('GH_DEPLOYMENTS_FILE', GH_DATA_DIR . '/deployments.json');

// Make a request to the GitHub API (or the OAuth token endpoint)
function ghApiRequest($url, $method = 'GET', $headers = [], $body = null) {
    $ch = curl_init($url);
    $default_headers = [
        'User-Agent: MR-WASEEM-HOSTING',
        'Accept: application/vnd.github+json',
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($default_headers, $headers));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
    }

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['status' => 0, 'body' => null, 'error' => $error];
    }

    return ['status' => $status, 'body' => json_decode($response, true), 'error' => null];
}

// Download a binary URL (used for the repo zipball) straight to a file
function ghDownloadFile($url, $destination, $token = null) {
    $ch = curl_init($url);
    $fp = fopen($destination, 'w');

    $headers = ['User-Agent: MR-WASEEM-HOSTING'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $ok = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if (!$ok || $status >= 400) {
        @unlink($destination);
        return false;
    }
    return true;
}

// Read the deployments JSON store
function ghGetDeployments() {
    if (!is_dir(GH_DATA_DIR)) {
        mkdir(GH_DATA_DIR, 0777, true);
    }
    if (!file_exists(GH_DEPLOYMENTS_FILE)) {
        return [];
    }
    $content = file_get_contents(GH_DEPLOYMENTS_FILE);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

// Save the deployments JSON store (custom_url => deployment info)
function ghSaveDeployments($deployments) {
    if (!is_dir(GH_DATA_DIR)) {
        mkdir(GH_DATA_DIR, 0777, true);
    }
    file_put_contents(GH_DEPLOYMENTS_FILE, json_encode($deployments, JSON_PRETTY_PRINT));

    // Protect the data folder from direct web access
    $htaccess = GH_DATA_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\nRequire all denied\n");
    }
}

// Find a deployment record by its GitHub "owner/repo" full name
function ghFindDeploymentByRepo($repo_full_name) {
    foreach (ghGetDeployments() as $key => $info) {
        if (isset($info['repo_full_name']) && strcasecmp($info['repo_full_name'], $repo_full_name) === 0) {
            if (!isset($info['custom_url'])) $info['custom_url'] = $key;
            return $info;
        }
    }
    return null;
}

// Recursively delete a directory's contents (keeps the directory itself)
function ghClearDirectory($dir) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            ghClearDirectory($path);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

/**
 * Download a repo's zipball and extract it into the given target directory.
 * GitHub wraps everything in a single top-level folder (repo-branch/) —
 * this strips that wrapper so files land directly in the target dir.
 * Uses ZipArchive when available; otherwise falls back to the pure-PHP
 * reader in simple_zip.php so this still works on servers without ext-zip.
 */
function ghDeployRepoToSite($owner, $repo, $branch, $target_dir, $token = null) {
    $zip_url = "https://api.github.com/repos/$owner/$repo/zipball/$branch";
    $tmp_zip = tempnam(sys_get_temp_dir(), 'ghzip_');

    if (!ghDownloadFile($zip_url, $tmp_zip, $token)) {
        @unlink($tmp_zip);
        return ['success' => false, 'message' => 'Could not download repository. Check the branch name and repo access.'];
    }

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    } else {
        ghClearDirectory($target_dir); // wipe old version before writing the new one
    }

    $has_html = false;

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($tmp_zip) !== true) {
            @unlink($tmp_zip);
            return ['success' => false, 'message' => 'Downloaded file is not a valid ZIP.'];
        }

        // Figure out the auto-generated top-level folder name (first entry)
        $root_prefix = '';
        if ($zip->numFiles > 0) {
            $first = $zip->getNameIndex(0);
            if (strpos($first, '/') !== false) {
                $root_prefix = substr($first, 0, strpos($first, '/') + 1);
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (substr($name, -1) === '/') continue; // skip directory entries

            $relative = $root_prefix && strpos($name, $root_prefix) === 0
                ? substr($name, strlen($root_prefix))
                : $name;

            if ($relative === '' || strpos($relative, '.git/') === 0) continue;

            $dest_path = $target_dir . '/' . $relative;
            $dest_dir  = dirname($dest_path);
            if (!is_dir($dest_dir)) mkdir($dest_dir, 0777, true);

            $content = $zip->getFromIndex($i);
            if ($content !== false) {
                file_put_contents($dest_path, $content);
                $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
                if ($ext === 'html' || $ext === 'htm') $has_html = true;
            }
        }
        $zip->close();
    } else {
        // Fallback: no ext-zip on this server, use the pure-PHP reader
        require_once __DIR__ . '/simple_zip.php';
        $entries = simpleZipExtractAll($tmp_zip);
        if ($entries === false) {
            @unlink($tmp_zip);
            return ['success' => false, 'message' => 'Downloaded file is not a valid ZIP.'];
        }

        $root_prefix = '';
        foreach ($entries as $e) {
            if (strpos($e['name'], '/') !== false) {
                $root_prefix = substr($e['name'], 0, strpos($e['name'], '/') + 1);
                break;
            }
        }

        foreach ($entries as $e) {
            if ($e['is_dir']) continue;

            $relative = $root_prefix && strpos($e['name'], $root_prefix) === 0
                ? substr($e['name'], strlen($root_prefix))
                : $e['name'];

            if ($relative === '' || strpos($relative, '.git/') === 0) continue;

            $dest_path = $target_dir . '/' . $relative;
            $dest_dir  = dirname($dest_path);
            if (!is_dir($dest_dir)) mkdir($dest_dir, 0777, true);

            file_put_contents($dest_path, $e['data']);
            $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
            if ($ext === 'html' || $ext === 'htm') $has_html = true;
        }
    }

    @unlink($tmp_zip);

    if (!$has_html) {
        return ['success' => false, 'message' => 'No HTML file found in this repo/branch.'];
    }

    return ['success' => true, 'message' => 'Repository deployed successfully.'];
}

// Create (or reuse) a push webhook on the given repo so future pushes auto-redeploy
function ghEnsureWebhook($owner, $repo, $token) {
    $hook_url = ghBaseUrl() . '/webhook.php';

    $existing = ghApiRequest(
        "https://api.github.com/repos/$owner/$repo/hooks",
        'GET',
        ['Authorization: Bearer ' . $token]
    );

    if ($existing['status'] === 200 && is_array($existing['body'])) {
        foreach ($existing['body'] as $hook) {
            if (isset($hook['config']['url']) && $hook['config']['url'] === $hook_url) {
                return $hook['id']; // already exists
            }
        }
    }

    $create = ghApiRequest(
        "https://api.github.com/repos/$owner/$repo/hooks",
        'POST',
        ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        [
            'name' => 'web',
            'active' => true,
            'events' => ['push'],
            'config' => [
                'url' => $hook_url,
                'content_type' => 'json',
                'secret' => WEBHOOK_SECRET,
                'insecure_ssl' => '0',
            ],
        ]
    );

    return ($create['status'] === 201 && isset($create['body']['id'])) ? $create['body']['id'] : null;
}
