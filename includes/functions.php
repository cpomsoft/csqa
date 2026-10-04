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

/** id => manifest entry of the mode surface selections (ie LRM over ice) */
function csqa_mode_surfaces(array $manifest): array
{
    $selections = [];
    foreach ($manifest['modes'] as $mode) {
        if (!empty($mode['surfaces'])) {
            $selections[$mode['id']] = $mode;
        }
    }
    return $selections;
}

/** id => kind of the manifest's mode selections: 'mode' (and 'all'), 'mode_surface' or 'pass' */
function csqa_mode_kinds(array $manifest): array
{
    $kinds = [];
    foreach ($manifest['modes'] as $mode) {
        // manifests of older processing software have no kind
        $kinds[$mode['id']] = $mode['kind'] ?? (!empty($mode['surfaces']) ? 'mode_surface' : 'mode');
    }
    return $kinds;
}

/** Measurement rate of a parameter's product variables, from their names: '1 Hz' (ie *_01),
 * '20 Hz' (ie *_20_ku) or '' if mixed or unknown */
function csqa_measurement_rate(array $param): string
{
    $rates = [];
    foreach ($param['variants'] as $v) {
        foreach (!empty($v['inputs']) ? $v['inputs'] : [$v['variable']] as $name) {
            $rates[] = preg_match('/_01(_|$)/', $name) ? '1 Hz' : (strpos($name, '_20_') !== false ? '20 Hz' : '');
        }
    }
    $rates = array_unique($rates);
    return count($rates) === 1 ? $rates[0] : '';
}

/** A mode selection as text, ie 'All modes', 'SAR mode', 'LRM Ice', 'Ascending passes' */
function csqa_mode_text(string $mode, array $mode_labels, array $mode_kinds): string
{
    $label = $mode_labels[$mode] ?? $mode;
    return ($mode === 'all' || ($mode_kinds[$mode] ?? 'mode') !== 'mode') ? $label : "$label mode";
}

/** The records of a mode selection, ie ' acquired in SAR mode', ' acquired in LRM mode over
 * the ice surface type' or ' from ascending passes' ('' for all modes) */
function csqa_mode_records_text(string $mode, array $mode_labels, array $mode_surfaces,
                                array $mode_kinds = []): string
{
    if ($mode === '' || $mode === 'all') {
        return '';
    }
    if (($mode_kinds[$mode] ?? '') === 'pass') {
        return ' from ' . strtolower($mode_labels[$mode] ?? $mode);
    }
    if (isset($mode_surfaces[$mode])) {
        $sel = $mode_surfaces[$mode];
        return ' acquired in ' . ($mode_labels[$sel['mode']] ?? $sel['mode']) . ' mode over the '
            . implode(' / ', $sel['surfaces']) . ' surface type';
    }
    return ' acquired in ' . ($mode_labels[$mode] ?? $mode) . ' mode';
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
 * If a variant is given only lines containing it are parsed (the quality flag timeseries has a
 * row per cycle, area, flag bit and mode), so callers must still check each row's variant.
 */
function csqa_timeseries(string $baseline, string $param_id, string $variant = ''): array
{
    $path = csqa_timeseries_path($baseline, $param_id);
    if (!is_readable($path) || ($fh = fopen($path, 'r')) === false) {
        return [];
    }
    $columns = fgetcsv($fh, null, ',', '"', '');
    if (!$columns) {
        fclose($fh);
        return [];
    }
    $rows = [];
    while (($line = fgets($fh)) !== false) {
        if ($variant !== '' && strpos($line, ",$variant,") === false) {
            continue;
        }
        $values = str_getcsv(rtrim($line, "\r\n"), ',', '"', '');
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

/**
 * What a parameter variant is in each acquisition mode, ie
 * 'Ocean CFI retracker (LRM), UCL sea-ice retracker (SAR), UCL margins retracker (SARin)'
 */
function csqa_variant_mode_summary(array $variant, array $modes, array $mode_labels): string
{
    $parts = [];
    foreach ($modes as $mode) {
        if (!array_key_exists($mode, $variant['mode_descriptions'] ?? [])) {
            continue;
        }
        $desc = $variant['mode_descriptions'][$mode];
        $parts[] = ($desc ?? 'N/A') . ' (' . ($mode_labels[$mode] ?? $mode) . ')';
    }
    return implode(', ', $parts);
}

/** Plot file name, as generated by cpom.altimetry.projects.csqa.plotting.plot_filename */
function csqa_plot_filename(string $param_id, string $variant, string $mode, string $area,
                            string $format, string $scale_suffix = ''): string
{
    $parts = array_filter([$param_id, $variant, $mode, $area, $scale_suffix], fn($part) => $part !== '');
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

/** Format a number with only the decimals it needs (up to 3), ie 4300 -> '4,300', -1.2 -> '-1.2' */
function csqa_num_compact($value): string
{
    $text = number_format((float)$value, 3);
    // strpos rather than str_contains: the production server runs PHP 7.4
    return strpos($text, '.') !== false ? rtrim(rtrim($text, '0'), '.') : $text;
}

/** URL of a page with query parameters */
function csqa_url(string $page, array $query): string
{
    $query = array_filter($query, fn($value) => $value !== null && $value !== '');
    return $page . ($query ? '?' . http_build_query($query) : '');
}
