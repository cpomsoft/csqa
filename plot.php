<?php
/**
 * Serve a CSQA plot image from the data directory (outside the web root).
 *
 * plot.php?b=<baseline>&c=<cycle>&p=<parameter>&f=<plot file>[&thumb=1]
 * Every component is validated, so no other file can be served.
 */

require_once __DIR__ . '/includes/functions.php';

$baseline = csqa_get('b', CSQA_BASELINE_RE);
$cycle = csqa_get('c', CSQA_CYCLE_RE);
$param_id = csqa_get('p', CSQA_ID_RE);
$file = csqa_get('f', '/^[a-z0-9_]+\.(webp|png|avif)$/');

if ($baseline === null || $cycle === null || $param_id === null || $file === null
    || strpos($file, $param_id . '_') !== 0) {
    http_response_code(400);
    exit('Bad request');
}

$path = csqa_cycle_dir($baseline, (int)$cycle) . "/plots/$param_id/"
    . (empty($_GET['thumb']) ? '' : 'thumbs/') . $file;
if (!is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

$types = ['webp' => 'image/webp', 'png' => 'image/png', 'avif' => 'image/avif'];
$mtime = filemtime($path);
$size = filesize($path);
$etag = '"' . md5($path . $mtime . $size) . '"';

header('Content-Type: ' . $types[pathinfo($file, PATHINFO_EXTENSION)]);
header('Cache-Control: public, max-age=86400');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');

if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag
    || (!isset($_SERVER['HTTP_IF_NONE_MATCH']) && isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
        && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime)) {
    http_response_code(304);
    exit;
}

header('Content-Length: ' . $size);
readfile($path);
