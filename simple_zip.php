<?php
/**
 * Minimal pure-PHP ZIP reader — a fallback for servers where the
 * `zip` PHP extension isn't compiled in (this happens on some Railway/
 * Nixpacks PHP builds). Reads the ZIP's central directory and inflates
 * each entry itself, so ZIP upload / GitHub deploy still work even
 * without ext-zip installed.
 *
 * Only supports the two compression methods ZIP normally uses for
 * text/code archives: 0 (stored) and 8 (deflate) — which covers
 * GitHub's zipballs and virtually every ZIP a code editor/OS creates.
 *
 * Returns an array of ['name' => string, 'data' => string, 'is_dir' => bool]
 * or false if the file isn't a readable ZIP.
 */
function simpleZipExtractAll($zip_path) {
    $content = file_get_contents($zip_path);
    if ($content === false || strlen($content) < 22) return false;

    // Find the End Of Central Directory record (search from the end,
    // since an optional comment field can follow it)
    $eocd_sig = "\x50\x4b\x05\x06";
    $eocd_pos = strrpos($content, $eocd_sig);
    if ($eocd_pos === false) return false;

    $eocd = substr($content, $eocd_pos, 22);
    $entry_count   = unpack('v', substr($eocd, 10, 2))[1];
    $cd_offset     = unpack('V', substr($eocd, 16, 4))[1];

    $entries = [];
    $offset = $cd_offset;
    $cd_sig = "\x50\x4b\x01\x02";

    for ($i = 0; $i < $entry_count; $i++) {
        if (substr($content, $offset, 4) !== $cd_sig) break;

        $method          = unpack('v', substr($content, $offset + 10, 2))[1];
        $compressed_size = unpack('V', substr($content, $offset + 20, 4))[1];
        $name_len        = unpack('v', substr($content, $offset + 28, 2))[1];
        $extra_len       = unpack('v', substr($content, $offset + 30, 2))[1];
        $comment_len     = unpack('v', substr($content, $offset + 32, 2))[1];
        $local_offset    = unpack('V', substr($content, $offset + 42, 4))[1];
        $name            = substr($content, $offset + 46, $name_len);

        $entries[] = [
            'name' => $name,
            'method' => $method,
            'compressed_size' => $compressed_size,
            'local_offset' => $local_offset,
        ];

        $offset += 46 + $name_len + $extra_len + $comment_len;
    }

    $results = [];
    $local_sig = "\x50\x4b\x03\x04";

    foreach ($entries as $entry) {
        $is_dir = substr($entry['name'], -1) === '/';
        if ($is_dir) {
            $results[] = ['name' => $entry['name'], 'data' => '', 'is_dir' => true];
            continue;
        }

        $lo = $entry['local_offset'];
        if (substr($content, $lo, 4) !== $local_sig) continue;

        $lname_len  = unpack('v', substr($content, $lo + 26, 2))[1];
        $lextra_len = unpack('v', substr($content, $lo + 28, 2))[1];
        $data_start = $lo + 30 + $lname_len + $lextra_len;

        $raw = substr($content, $data_start, $entry['compressed_size']);

        if ($entry['method'] === 0) {
            $data = $raw;
        } elseif ($entry['method'] === 8) {
            $data = @gzinflate($raw);
            if ($data === false) continue;
        } else {
            continue; // unsupported method (rare for code repos)
        }

        $results[] = ['name' => $entry['name'], 'data' => $data, 'is_dir' => false];
    }

    return $results;
}
