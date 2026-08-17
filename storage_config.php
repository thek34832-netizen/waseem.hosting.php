<?php
/**
 * Storage path config.
 * ---------------------------------------------------------------
 * On normal shared hosting this changes nothing — sites/ and data/
 * stay right next to your PHP files, exactly as before.
 *
 * On Railway (or any host with a mounted volume), set an env var
 * pointing at the volume's mount path and everything written by
 * this app (uploaded sites + GitHub deployment records) survives
 * every redeploy instead of being wiped when the container rebuilds:
 *
 *   Railway dashboard -> your service -> Variables -> add:
 *     RAILWAY_VOLUME_MOUNT_PATH = /data   (or whatever you set the volume mount to)
 *
 * (Railway actually injects RAILWAY_VOLUME_MOUNT_PATH automatically
 * once you attach a volume to the service — you usually don't need
 * to set it yourself. STORAGE_PATH is a manual override you can use
 * on any other host that gives you persistent disk space.)
 * ---------------------------------------------------------------
 */

if (!defined('STORAGE_ROOT')) {
    $volume_path = getenv('RAILWAY_VOLUME_MOUNT_PATH') ?: getenv('STORAGE_PATH');

    if ($volume_path) {
        define('STORAGE_ROOT', rtrim($volume_path, '/'));
    } else {
        define('STORAGE_ROOT', __DIR__);
    }

    define('SITES_DIR', STORAGE_ROOT . '/sites');
    define('DATA_DIR', STORAGE_ROOT . '/data');

    if (!is_dir(SITES_DIR)) @mkdir(SITES_DIR, 0777, true);
    if (!is_dir(DATA_DIR))  @mkdir(DATA_DIR, 0777, true);
}

/**
 * Given a list of HTML file paths found in a deployed site's folder,
 * pick which one to actually serve as the homepage. index.html/index.htm
 * always wins if present — otherwise falls back to the first file found
 * (alphabetical order), so a site never accidentally serves something
 * like a Google/Bing verification file instead of the real homepage.
 */
function pickMainHtmlFile($html_files) {
    if (empty($html_files)) return null;

    foreach ($html_files as $f) {
        if (strtolower(basename($f)) === 'index.html') return $f;
    }
    foreach ($html_files as $f) {
        if (strtolower(basename($f)) === 'index.htm') return $f;
    }
    return $html_files[0];
}
