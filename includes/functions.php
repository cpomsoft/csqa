<?php
/**
 * CSQA portal: access to the processed outputs (manifest, cycle statistics, timeseries, plots)
 *
 * Output layout (written by cpom.altimetry.projects.csqa):
 *   manifest.json
 *   baseline_<B>/cycles/cycle_<NNN>/cycle_info.json
 *   baseline_<B>/cycles/cycle_<NNN>/stats/<param>.json
 *   baseline_<B>/cycles/cycle_<NNN>/plots/<param>/[thumbs/]<plot file>
 *   baseline_<B>/timeseries/<param>.csv
 */

require_once __DIR__ . '/../config.php';

const CSQA_ID_RE = '/^[a-z0-9_]+$/';
const CSQA_BASELINE_RE = '/^[A-Z]$/';
const CSQA_CYCLE_RE = '/^[0-9]{1,4}$/';

/** HTML-escape a value */
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** JSON for embedding in a <script> element */
function csqa_json($value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/** A GET parameter if it matches a pattern, else the default */
function csqa_get(string $key, string $pattern, $default = null)
{
    $value = $_GET[$key] ?? null;
    if (is_string($value) && preg_match($pattern, $value)) {
        return $value;
    }
    return $default;
}

/** Read and decode a json file, or null */
function csqa_read_json(string $path): ?array
{
    if (!is_readable($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

/** The portal manifest (null if the processing tools have not produced one yet) */
function csqa_manifest(): ?array
{
    static $manifest = false;
    if ($manifest === false) {
        $manifest = csqa_read_json(CSQA_DATA_DIR . '/manifest.json');
    }
    return $manifest;
}

/** Find an item with a given id in a list of arrays */
function csqa_find(array $items, string $id): ?array
{
    foreach ($items as $item) {
        if (($item['id'] ?? null) === $id) {
            return $item;
        }
    }
    return null;
}

/** id => long_name of the manifest's areas */
function csqa_area_names(array $manifest): array
{
    $names = [];
    foreach ($manifest['areas'] as $area) {
        $names[$area['id']] = $area['long_name'];
    }
    return $names;
}

/** id => label of the manifest's acquisition modes */
function csqa_mode_labels(array $manifest): array
{
    $labels = [];
    foreach ($manifest['modes'] as $mode) {
        $labels[$mode['id']] = $mode['label'];
    }
    return $labels;
}

/** Days after acquisition before input products are available */
function csqa_data_latency_days(array $manifest): float
{
    return (float)($manifest['data_latency_days'] ?? 35);
}

/** Start time (unix) of a cycle */
function csqa_cycle_start_time(array $manifest, int $cycle): int
{
    $mission_start = strtotime($manifest['mission_start_date'] . 'T00:00:00Z');
    return $mission_start + ($cycle - 1) * (int)$manifest['cycle_length_days'] * 86400;
}

/** Cycle containing a time (unix), or 0 before the start of cycle 1 */
function csqa_cycle_of_time(array $manifest, int $time): int
{
    $mission_start = strtotime($manifest['mission_start_date'] . 'T00:00:00Z');
    if ($time < $mission_start) {
        return 0;
    }
    return intdiv($time - $mission_start, (int)$manifest['cycle_length_days'] * 86400) + 1;
}

/** Latest cycle that can have data: the cycle containing (now - data latency) */
function csqa_latest_available_cycle(array $manifest): int
{
    return max(1, csqa_cycle_of_time($manifest, time() - (int)round(csqa_data_latency_days($manifest) * 86400)));
}

/** Cycles of a baseline for which a parameter has been processed (ascending) */
function csqa_param_cycles(array $baseline, string $param_id): array
{
    return array_values(array_filter(
        $baseline['cycles'],
        fn($cycle) => in_array($param_id, $cycle['parameters'], true)
    ));
}

/** Baselines for which a parameter has been processed (newest first) */
function csqa_param_baselines(array $manifest, string $param_id): array
{
    return array_values(array_filter(
        $manifest['baselines'],
        fn($baseline) => count(csqa_param_cycles($baseline, $param_id)) > 0
    ));
}

/** Directory of a cycle's outputs */
function csqa_cycle_dir(string $baseline, int $cycle): string
{
    return sprintf('%s/baseline_%s/cycles/cycle_%03d', CSQA_DATA_DIR, $baseline, $cycle);
}

/** Statistics of a parameter for a cycle */
function csqa_cycle_stats(string $baseline, int $cycle, string $param_id): ?array
{
    return csqa_read_json(csqa_cycle_dir($baseline, $cycle) . "/stats/$param_id.json");
}

/** Path of a parameter's statistics timeseries csv file */
function csqa_timeseries_path(string $baseline, string $param_id): string
{
    return sprintf('%s/baseline_%s/timeseries/%s.csv', CSQA_DATA_DIR, $baseline, $param_id);
}

/**
 * Statistics timeseries of a parameter, as a list of rows (column => value).
 * Numeric columns are converted to numbers, empty values to null.
 */
function csqa_timeseries(string $baseline, string $param_id): array
{
    $path = csqa_timeseries_path($baseline, $param_id);
    if (!is_readable($path) || ($fh = fopen($path, 'r')) === false) {
        return [];
    }
    $columns = fgetcsv($fh, null, ',', '"', '');
    $rows = [];
    while (($values = fgetcsv($fh, null, ',', '"', '')) !== false) {
        if (count($values) !== count($columns)) {
            continue;
        }
        $row = [];
        foreach ($columns as $i => $column) {
            $value = $values[$i];
            if ($value === '') {
                $row[$column] = in_array($column, ['variant', 'mode'], true) ? '' : null;
            } elseif (is_numeric($value) && !in_array($column, ['start_date', 'end_date'], true)) {
                $row[$column] = $value + 0;
            } else {
                $row[$column] = $value;
            }
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

/** Plot file name, as generated by cpom.altimetry.projects.csqa.plotting.plot_filename */
function csqa_plot_filename(string $param_id, string $variant, string $mode, string $area,
                            string $format): string
{
    $parts = array_filter([$param_id, $variant, $mode, $area], fn($part) => $part !== '');
    return implode('_', $parts) . '.' . $format;
}

/** URL of a plot image (served by plot.php) */
function csqa_plot_url(string $baseline, int $cycle, string $param_id, string $file,
                       bool $thumb = false, string $version = ''): string
{
    $query = ['b' => $baseline, 'c' => $cycle, 'p' => $param_id, 'f' => $file];
    if ($thumb) {
        $query['thumb'] = 1;
    }
    if ($version !== '') {
        $query['v'] = substr(md5($version), 0, 8); // cache buster when a cycle is reprocessed
    }
    return 'plot.php?' . http_build_query($query);
}

/** Format an ISO date/time, ie '2026-07-26T00:00:00Z' -> '26-Jul-2026' */
function csqa_date(?string $iso, string $format = 'd-M-Y'): string
{
    if (!$iso) {
        return '';
    }
    return gmdate($format, strtotime($iso));
}

/** Last day of a cycle (its end time is exclusive) */
function csqa_cycle_last_day(array $cycle, string $format = 'd-M-Y'): string
{
    return gmdate($format, strtotime($cycle['end']) - 1);
}

/** Days of a cycle covered by a product's input files */
function csqa_coverage_days(array $cycle, ?string $product = null): float
{
    $products = $cycle['products'] ?? [];
    if ($product !== null) {
        return (float)($products[$product]['coverage_days'] ?? 0.0);
    }
    $days = 0.0;
    foreach ($products as $info) {
        $days = max($days, (float)($info['coverage_days'] ?? 0.0));
    }
    return $days;
}

/** True if a cycle's input data covers less than CSQA_PARTIAL_COVERAGE_FRACTION of it */
function csqa_is_partial(array $cycle, int $cycle_length_days, ?string $product = null): bool
{
    return csqa_coverage_days($cycle, $product) < CSQA_PARTIAL_COVERAGE_FRACTION * $cycle_length_days;
}

/** Format a number for a statistics table */
function csqa_num($value, int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '&ndash;';
    }
    return number_format((float)$value, $decimals);
}

/** URL of a page with query parameters */
function csqa_url(string $page, array $query): string
{
    $query = array_filter($query, fn($value) => $value !== null && $value !== '');
    return $page . ($query ? '?' . http_build_query($query) : '');
}
