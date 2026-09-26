<?php
/**
 * Download the statistics timeseries (csv) of a parameter for a baseline.
 *
 * download.php?b=<baseline>&p=<parameter>
 */

require_once __DIR__ . '/includes/functions.php';

$baseline = csqa_get('b', CSQA_BASELINE_RE);
$param_id = csqa_get('p', CSQA_ID_RE);
if ($baseline === null || $param_id === null) {
    http_response_code(400);
    exit('Bad request');
}

$path = csqa_timeseries_path($baseline, $param_id);
if (!is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="cryosat2_csqa_baseline_' . $baseline . '_'
    . $param_id . '_statistics.csv"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
