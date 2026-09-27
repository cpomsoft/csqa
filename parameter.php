<?php
/**
 * CSQA portal: maps, statistics and statistics trends of a monitored parameter.
 *
 * Query parameters (all optional except p):
 *   p  parameter id          b  baseline          c  cycle number
 *   v  variant (retracker)   m  acquisition mode  a  area id or 'all'
 */

require_once __DIR__ . '/includes/functions.php';

$manifest = csqa_manifest();
$param = null;
if ($manifest) {
    $param_id = csqa_get('p', CSQA_ID_RE, $manifest['parameters'][0]['id'] ?? '');
    $param = csqa_find($manifest['parameters'], $param_id);
}

if (!$param) {
    http_response_code($manifest ? 404 : 503);
    $page_title = 'Parameter not available';
    $breadcrumb = 'Parameter';
    require __DIR__ . '/includes/header.php';
    echo '<h1>Parameter not available</h1><p class="csqa-lead">'
        . ($manifest ? 'The requested parameter is not monitored.'
            : 'No monitoring results have been processed yet.') . '</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$area_names = csqa_area_names($manifest);
$mode_labels = csqa_mode_labels($manifest);
$cycle_length = (int)$manifest['cycle_length_days'];
$image_format = $param['image_format'] ?? $manifest['image_format'];
$is_flag = $param['type'] === 'flag';

// ---- selection -----------------------------------------------------------------------------

$baselines = csqa_param_baselines($manifest, $param['id']);
$baseline = null;
$cycles = [];
$cycle = null;
if ($baselines) {
    $default_baseline = $baselines[0]['id'];
    foreach ($baselines as $b) {
        if (!empty($b['default'])) {
            $default_baseline = $b['id'];
            break;
        }
    }
    $baseline = csqa_find($baselines, csqa_get('b', CSQA_BASELINE_RE, $default_baseline))
        ?? csqa_find($baselines, $default_baseline);
    $cycles = csqa_param_cycles($baseline, $param['id']);
    $cycle_numbers = array_column($cycles, 'cycle');
    $requested_cycle = (int)csqa_get('c', CSQA_CYCLE_RE, '0');
    $cycle_index = array_search($requested_cycle, $cycle_numbers, true);
    if ($cycle_index === false) {
        $cycle_index = count($cycles) - 1; // latest cycle
    }
    $cycle = $cycles[$cycle_index];
}

$variant_ids = array_column($param['variants'], 'id');
$default_variant = in_array($param['default_variant'] ?? '', $variant_ids, true)
    ? $param['default_variant'] : $variant_ids[0];
$variant = csqa_get('v', '/^[a-z0-9]*$/', $default_variant);
if (!in_array($variant, $variant_ids, true)) {
    $variant = $default_variant;
}
// bit flag parameters: each variant is a bit of a flag word (values Not set / Set)
$is_bit_flag = !empty($param['bit_flag']);
$modes = $param['modes'] ?: [''];
$mode = csqa_get('m', '/^[a-z]*$/', $modes[0]);
if (!in_array($mode, $modes, true)) {
    $mode = $modes[0];
}
// modes for which maps are produced (statistics are produced for every mode)
$map_modes = $param['map_modes'] ?? $modes;
$areas = $param['areas'];
$area = csqa_get('a', CSQA_ID_RE, 'all');
if ($area !== 'all' && !in_array($area, $areas, true)) {
    $area = 'all';
}

// colour scales of the maps (the first is the default), each with its own set of maps
$scales = $param['colour_scales'] ?? [];
$scale = $scales[0] ?? null;
$requested_scale = csqa_get('s', '/^[a-z0-9]+$/');
foreach ($scales as $sc) {
    if ($sc['id'] === $requested_scale) {
        $scale = $sc;
    }
}
$scale_suffix = $scale['file_suffix'] ?? '';
$scale_label = count($scales) > 1 ? ' (' . $scale['name'] . ' colour scale)' : '';

$selection = [
    'p' => $param['id'],
    'b' => $baseline['id'] ?? null,
    'c' => $cycle['cycle'] ?? null,
    'v' => count($variant_ids) > 1 ? $variant : null,
    'm' => $mode !== '' ? $mode : null,
    'a' => $area !== 'all' ? $area : null,
    's' => $scale_suffix !== '' ? $scale['id'] : null,
];

/** URL of this page with some selections changed */
function selection_url(array $selection, array $changes): string
{
    return csqa_url('parameter.php', array_merge($selection, $changes));
}

// ---- statistics of the selected cycle ------------------------------------------------------

$stats_rows = [];
$cycle_stats = null;
if ($cycle) {
    $cycle_stats = csqa_cycle_stats($baseline['id'], (int)$cycle['cycle'], $param['id']);
    foreach ($cycle_stats['rows'] ?? [] as $row) {
        if ($row['variant'] === $variant && $row['mode'] === $mode) {
            $stats_rows[$row['area']] = $row;
        }
    }
}

// ---- statistics timeseries of every baseline for the trend chart ---------------------------

$trend_data = [];
foreach ($baselines as $b) {
    $rows = array_values(array_filter(
        csqa_timeseries($b['id'], $param['id']),
        fn($row) => $row['variant'] === $variant && $row['mode'] === $mode
    ));
    if ($rows) {
        $trend_data[$b['id']] = $rows;
    }
}

$current_variant = csqa_find($param['variants'], $variant);
$variant_name = $current_variant['name'];
$variable_name = $current_variant['variable'];

// what each variant is in each acquisition mode (ie the retracker used), if configured
$described_modes = array_values(array_filter($param['modes'], fn($m) => $m !== 'all'));
$has_mode_descriptions = count($variant_ids) > 1 && $described_modes
    && array_filter(array_map(fn($v) => $v['mode_descriptions'] ?? [], $param['variants']));
$mode_described = $has_mode_descriptions && $mode !== '' && $mode !== 'all'
    && array_key_exists($mode, $current_variant['mode_descriptions'] ?? []);
$mode_description = $mode_described ? $current_variant['mode_descriptions'][$mode] : null;

$selection_label = $param['long_name']
    . (count($variant_ids) > 1 ? ": $variant_name" . ($mode_description ? " ($mode_description)" : '') : '')
    . ($mode !== '' ? ', ' . ($mode === 'all' ? $mode_labels['all'] : $mode_labels[$mode] . ' mode') : '');

$page_title = $param['long_name'];
$active_page = $param['id'];
$breadcrumb = $param['long_name'];
$extra_head = '<script src="' . h(CSQA_PLOTLY_JS) . '" defer></script>'
    . '<script src="assets/js/parameter.js?v=7" defer></script>';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
        <h1><?= h($param['long_name']) ?> Monitoring</h1>
        <div class="csqa-muted small mb-2">
            Source: <?= h($manifest['products'][$param['source']] ?? $param['source']) ?>
            <?php $variables = array_values(array_unique(array_column($param['variants'], 'variable'))); ?>
            &middot; Variable<?= count($variables) > 1 ? 's' : '' ?>: <?= h(implode(', ', $variables)) ?>
        </div>
    </div>
</div>
<p class="csqa-lead"><?= h($param['description']) ?></p>

<?php if (!$cycle): ?>
    <div class="alert alert-info">No cycles have been processed for this parameter yet.</div>
<?php else: ?>

<!-- selection controls ------------------------------------------------------------------ -->
<form class="csqa-controls" method="get" action="parameter.php" id="csqa-selection">
    <input type="hidden" name="p" value="<?= h($param['id']) ?>">
    <?php if ($area !== 'all'): ?><input type="hidden" name="a" value="<?= h($area) ?>"><?php endif; ?>

    <div>
        <label class="form-label" for="sel-baseline">Baseline</label>
        <select class="form-select form-select-sm" id="sel-baseline" name="b" onchange="this.form.submit()">
            <?php foreach ($baselines as $b): ?>
                <option value="<?= h($b['id']) ?>" <?= $b['id'] === $baseline['id'] ? 'selected' : '' ?>>
                    <?= h($b['id']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label class="form-label" for="sel-cycle">Cycle (30-day data take)</label>
        <div class="input-group input-group-sm">
            <?php $prev = $cycles[$cycle_index - 1] ?? null; $next = $cycles[$cycle_index + 1] ?? null; ?>
            <a class="btn btn-outline-csqa <?= $prev ? '' : 'disabled' ?>" title="Previous cycle"
               aria-label="Previous cycle"
               href="<?= $prev ? h(selection_url($selection, ['c' => $prev['cycle']])) : '#' ?>">
                <i class="fa-solid fa-chevron-left"></i></a>
            <select class="form-select csqa-cycle-select" id="sel-cycle" name="c" onchange="this.form.submit()">
                <?php foreach (array_reverse($cycles) as $c): ?>
                    <option value="<?= (int)$c['cycle'] ?>" <?= $c['cycle'] === $cycle['cycle'] ? 'selected' : '' ?>>
                        <?= (int)$c['cycle'] ?>: <?= h(csqa_date($c['start'])) ?> to <?= h(csqa_cycle_last_day($c)) ?>
                        <?= csqa_is_partial($c, $cycle_length, $param['source']) ? ' (partial)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a class="btn btn-outline-csqa <?= $next ? '' : 'disabled' ?>" title="Next cycle"
               aria-label="Next cycle"
               href="<?= $next ? h(selection_url($selection, ['c' => $next['cycle']])) : '#' ?>">
                <i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </div>

    <?php if (count($variant_ids) > 6): ?>
        <div>
            <label class="form-label" for="sel-variant"><?= h($param['variant_label']) ?></label>
            <select class="form-select form-select-sm" id="sel-variant" name="v" onchange="this.form.submit()">
                <?php foreach ($param['variants'] as $v): ?>
                    <option value="<?= h($v['id']) ?>" <?= $v['id'] === $variant ? 'selected' : '' ?>>
                        <?= h($v['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php elseif (count($variant_ids) > 1): ?>
        <div>
            <span class="form-label"><?= h($param['variant_label']) ?></span>
            <div class="btn-group btn-group-sm" role="group" aria-label="<?= h($param['variant_label']) ?>">
                <?php foreach ($param['variants'] as $v): ?>
                    <input type="radio" class="btn-check" name="v" id="v-<?= h($v['id']) ?>" value="<?= h($v['id']) ?>"
                           autocomplete="off" <?= $v['id'] === $variant ? 'checked' : '' ?> onchange="this.form.submit()">
                    <label class="btn btn-outline-csqa" for="v-<?= h($v['id']) ?>"
                           title="<?= h($v['variable'] . ($has_mode_descriptions
                               ? ': ' . csqa_variant_mode_summary($v, $described_modes, $mode_labels) : '')) ?>"><?= h($v['name']) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($param['modes']): ?>
        <div>
            <span class="form-label">Acquisition mode</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Acquisition mode">
                <?php foreach ($param['modes'] as $m): ?>
                    <input type="radio" class="btn-check" name="m" id="m-<?= h($m) ?>" value="<?= h($m) ?>"
                           autocomplete="off" <?= $m === $mode ? 'checked' : '' ?> onchange="this.form.submit()">
                    <label class="btn btn-outline-csqa" for="m-<?= h($m) ?>"><?= h($m === 'all' ? 'All' : $mode_labels[$m]) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div>
        <span class="form-label">Area</span>
        <div class="btn-group btn-group-sm" role="group" aria-label="Area">
            <?php foreach (array_merge(['all'], $areas) as $a): ?>
                <a class="btn btn-outline-csqa <?= $a === $area ? 'active' : '' ?>"
                   <?= $a === $area ? 'aria-current="true"' : '' ?>
                   href="<?= h(selection_url($selection, ['a' => $a === 'all' ? null : $a])) ?>">
                    <?= h($a === 'all' ? 'All areas' : $area_names[$a]) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if (count($scales) > 1): ?>
        <div>
            <span class="form-label">Colour scale</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Colour scale">
                <?php foreach ($scales as $sc):
                    $range_text = $sc['range'] ? csqa_num_compact($sc['range'][0]) . ' to ' . csqa_num_compact($sc['range'][1])
                        . ($param['units'] ? ' ' . $param['units'] : '') . (!empty($sc['log']) ? ', log' : '') : '';
                ?>
                    <input type="radio" class="btn-check" name="s" id="s-<?= h($sc['id']) ?>" value="<?= h($sc['id']) ?>"
                           autocomplete="off" <?= $sc['id'] === $scale['id'] ? 'checked' : '' ?> onchange="this.form.submit()">
                    <label class="btn btn-outline-csqa" for="s-<?= h($sc['id']) ?>"
                           title="<?= h($range_text) ?>"><?= h($sc['name']) ?>
                        <span class="csqa-scale-range">[<?= h($range_text) ?>]</span></label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <noscript><button type="submit" class="btn btn-sm btn-primary">Show</button></noscript>
</form>

<?php if ($has_mode_descriptions): ?>
<!-- what each variant (ie retracker) is in each acquisition mode ---------------------------- -->
<div class="table-responsive mt-2">
<table class="table table-sm table-bordered csqa-table csqa-variant-table w-auto">
    <caption><?= h($param['variant_label']) ?>s by acquisition mode</caption>
    <thead>
    <tr>
        <th><?= h($param['variant_label']) ?></th>
        <?php foreach ($described_modes as $m): ?><th><?= h($mode_labels[$m]) ?></th><?php endforeach; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($param['variants'] as $v): $v_selected = $v['id'] === $variant; ?>
        <tr class="<?= $v_selected ? 'selected' : '' ?>">
            <td class="text-nowrap">
                <a href="<?= h(selection_url($selection, ['v' => $v['id']])) ?>"
                   <?= $v_selected ? 'aria-current="true"' : '' ?>><?= h($v['name']) ?></a>
                <span class="csqa-muted small"><?= h($v['variable']) ?></span>
            </td>
            <?php foreach ($described_modes as $m):
                $desc = $v['mode_descriptions'][$m] ?? null;
                $known = array_key_exists($m, $v['mode_descriptions'] ?? []);
                $current = $v_selected && ($mode === $m || $mode === 'all');
            ?>
                <td class="<?= $current ? 'current' : '' ?>">
                    <?php if ($desc !== null): ?><?= h($desc) ?>
                    <?php elseif ($known): ?><span class="csqa-muted">N/A (not used)</span>
                    <?php else: ?><span class="csqa-muted">&ndash;</span><?php endif; ?>
                </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php
$coverage = csqa_coverage_days($cycle, $param['source']);
$partial = csqa_is_partial($cycle, $cycle_length, $param['source']);
$n_files = $cycle['products'][$param['source']]['n_files'] ?? null;
?>
<p class="csqa-cycle-info">
    <strong>Baseline-<?= h($baseline['id']) ?>, cycle <?= (int)$cycle['cycle'] ?>:</strong>
    <?= h(csqa_date($cycle['start'])) ?> to <?= h(csqa_cycle_last_day($cycle)) ?>
    &middot; <?= $n_files !== null ? number_format($n_files) . ' ' . h($param['source']) . ' files' : '' ?>
    &middot; data covers <?= number_format($coverage, 1) ?> of <?= $cycle_length ?> days
    <span class="badge <?= $partial ? 'badge-partial' : 'badge-complete' ?> ms-1">
        <?= $partial ? 'Partial cycle' : 'Complete cycle' ?></span>
    <?php if ($cycle_stats && !empty($cycle_stats['processed_at'])): ?>
        <span class="csqa-muted small ms-2">processed <?= h(csqa_date($cycle_stats['processed_at'], 'd-M-Y H:i')) ?> UTC</span>
    <?php endif; ?>
</p>

<!-- maps -------------------------------------------------------------------------------- -->
<h2><?= h($selection_label) ?>: Maps<?= h($scale_label) ?></h2>
<div class="row g-3">
    <?php foreach ($area === 'all' ? $areas : [$area] as $a):
        $row = $stats_rows[$a] ?? null;
        $file = csqa_plot_filename($param['id'], $variant, $mode, $a, $image_format, $scale_suffix);
        $has_map = $row && ($scale_suffix === '' ? !empty($row['plot']) : !empty($row['extra_plots'][$scale['id']]));
        $full_url = csqa_plot_url($baseline['id'], (int)$cycle['cycle'], $param['id'], $file,
            false, $cycle_stats['processed_at'] ?? '');
        $caption = "$selection_label$scale_label. " . $area_names[$a] . ', Baseline-' . $baseline['id']
            . ', cycle ' . $cycle['cycle'];
    ?>
        <div class="<?= $area === 'all' ? 'col-md-6 col-xl-4' : 'col-12 csqa-plot-single' ?>">
            <div class="csqa-plot-card">
                <div class="card-header">
                    <span><?= h($area_names[$a]) ?></span>
                    <?php if ($area === 'all'): ?>
                        <a class="small fw-normal" href="<?= h(selection_url($selection, ['a' => $a])) ?>">
                            Single map <i class="fa-solid fa-up-right-and-down-left-from-center"></i></a>
                    <?php endif; ?>
                </div>
                <?php if ($has_map): ?>
                    <img src="<?= h($full_url) ?>" data-caption="<?= h($caption) ?>"
                         class="csqa-zoom" alt="Map of <?= h($caption) ?>" width="1020" height="850">
                <?php else: ?>
                    <div class="csqa-no-data">
                        <i class="fa-regular fa-map fa-2x"></i>
                        <div><strong>No map</strong></div>
                        <div><?php if ($row === null): ?>Not processed for this selection
                            <?php elseif ($mode_described && $mode_description === null): ?>
                                <?= h($variant_name) ?> is not used in <?= h($mode_labels[$mode]) ?> mode
                            <?php elseif (!in_array($mode, $map_modes, true)): ?>
                                Maps are only produced for
                                <?= h(implode(', ', array_map(fn($m) => $mode_labels[$m] ?? $m, $map_modes))) ?>
                            <?php elseif ($is_bit_flag && ($row['n_valid'] ?? 0) > 0 && ($row['counts']['set'] ?? 0) == 0): ?>
                                <?= h($variant_name) ?>: never set in this selection
                            <?php elseif (($row['n_valid'] ?? 0) > 0): ?>
                                Map not produced<?= $scale_suffix !== '' ? ' for the ' . h($scale['name']) . ' colour scale' : '' ?>
                            <?php else: ?>No valid <?= h($variable_name) ?> values in this selection
                            <?php endif; ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($is_bit_flag && $cycle_stats):
    // % of records with each bit set, per mode, in one area
    $bits_area = $area === 'all' ? $areas[0] : $area;
    $bits_pct = [];
    foreach ($cycle_stats['rows'] as $r) {
        if ($r['area'] === $bits_area) {
            $bits_pct[$r['variant']][$r['mode']] = $r['pct']['set'] ?? null;
        }
    }
?>
<!-- every bit of the flag word ------------------------------------------------------------ -->
<h2><?= h($param['long_name']) ?>: % of Records with each <?= h($param['variant_label']) ?> Set,
    Cycle <?= (int)$cycle['cycle'] ?>, <?= h($area_names[$bits_area]) ?></h2>
<div class="table-responsive">
<table class="table table-sm table-bordered csqa-table align-middle w-auto">
    <thead>
    <tr>
        <th><?= h($param['variant_label']) ?></th>
        <th>Product flag</th>
        <?php foreach ($modes as $m): ?><th class="num"><?= h($m === 'all' ? $mode_labels['all'] : $mode_labels[$m]) ?></th><?php endforeach; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($param['variants'] as $v): ?>
        <tr class="<?= $v['id'] === $variant ? 'selected' : '' ?>">
            <td class="text-nowrap"><a href="<?= h(selection_url($selection, ['v' => $v['id']])) ?>"
                <?= $v['id'] === $variant ? 'aria-current="true"' : '' ?>><?= h($v['name']) ?></a></td>
            <td class="csqa-muted small"><?= h($v['bit_name'] ?? '') ?></td>
            <?php foreach ($modes as $m): $pct = $bits_pct[$v['id']][$m] ?? null; ?>
                <td class="num <?= ($pct === null || $pct == 0) ? 'csqa-muted' : '' ?>">
                    <?= $pct === null ? '&ndash;' : ($pct == 0 ? '0' : csqa_num($pct)) ?></td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="csqa-muted small mt-1">
    Percentage of the valid records in each acquisition mode with the bit set. Select a bit to show
    its maps, statistics and trends<?= $area === 'all' ? ', and an area above to show this table for it' : '' ?>.
</p>
<?php endif; ?>

<!-- statistics of the cycle ------------------------------------------------------------- -->
<h2><?= h($selection_label) ?>: Cycle <?= (int)$cycle['cycle'] ?> Statistics</h2>
<div class="table-responsive">
<table class="table table-sm table-bordered csqa-table align-middle">
    <thead>
    <tr>
        <th>Area</th>
        <th class="num">Records</th>
        <th class="num">Valid</th>
        <?php if ($is_flag): ?>
            <?php foreach ($param['flags'] as $flag): ?>
                <th class="num"><span class="csqa-swatch" style="background:<?= h($flag['color'] ?? '#888') ?>"></span><?= h($flag['name']) ?> %</th>
            <?php endforeach; ?>
            <th class="num" title="Valid values that are not a defined flag value">Other</th>
        <?php else: ?>
            <?php $u = $param['units'] ? ' (' . h($param['units']) . ')' : ''; ?>
            <th class="num">Mean<?= $u ?></th>
            <th class="num">Median<?= $u ?></th>
            <th class="num">Std Dev<?= $u ?></th>
            <th class="num">Min<?= $u ?></th>
            <th class="num">Max<?= $u ?></th>
        <?php endif; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($areas as $a): $row = $stats_rows[$a] ?? null; ?>
        <tr class="<?= $a === $area ? 'selected' : '' ?>">
            <td><?= h($area_names[$a]) ?></td>
            <?php if (!$row): ?>
                <td colspan="<?= $is_flag ? count($param['flags']) + 3 : 7 ?>" class="csqa-muted">not processed</td>
            <?php else: ?>
                <td class="num"><?= number_format($row['n_records']) ?></td>
                <td class="num"><?= number_format($row['n_valid']) ?>
                    <?php if ($row['n_records'] > 0): ?>
                        <span class="csqa-muted small">(<?= number_format(100 * $row['n_valid'] / $row['n_records'], 1) ?>%)</span>
                    <?php endif; ?></td>
                <?php if ($is_flag): ?>
                    <?php foreach ($param['flags'] as $flag): ?>
                        <td class="num"><?= csqa_num($row['pct'][$flag['key']] ?? null) ?></td>
                    <?php endforeach; ?>
                    <td class="num"><?= number_format($row['n_other'] ?? 0) ?></td>
                <?php else: ?>
                    <?php foreach (['mean', 'median', 'std', 'min', 'max'] as $stat): ?>
                        <td class="num"><?= csqa_num($row[$stat] ?? null) ?></td>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="csqa-muted small mt-1">
    Statistics use every 20 Hz measurement of the cycle within each area<?= $mode !== '' && $mode !== 'all' ? ' acquired in ' . h($mode_labels[$mode]) . ' mode' : '' ?>.
    <?= $is_flag ? 'Flag percentages are of the valid (non-fill) values.' : 'Std Dev is the population standard deviation.' ?>
</p>

<!-- statistics trends ------------------------------------------------------------------- -->
<h2><?= h($selection_label) ?>: Trends</h2>
<div class="csqa-trend-controls" id="csqa-trend-controls">
    <?php if ($is_flag): ?>
        <label>Area
            <select class="form-select form-select-sm d-inline-block w-auto ms-1" id="trend-area">
                <?php foreach ($areas as $a): ?>
                    <option value="<?= h($a) ?>" <?= $a === ($area === 'all' ? $areas[0] : $area) ? 'selected' : '' ?>><?= h($area_names[$a]) ?></option>
                <?php endforeach; ?>
            </select></label>
    <?php else: ?>
        <label>Statistic
            <select class="form-select form-select-sm d-inline-block w-auto ms-1" id="trend-stat">
                <option value="mean">Mean</option>
                <option value="median">Median</option>
                <option value="std">Std Dev</option>
                <option value="min">Min</option>
                <option value="max">Max</option>
                <option value="n_valid">Number of valid values</option>
                <option value="pct_valid">% valid values</option>
            </select></label>
    <?php endif; ?>
    <?php if (count($trend_data) > 1): ?>
        <span>Baselines:</span>
        <?php foreach (array_keys($trend_data) as $bid): ?>
            <div class="form-check form-check-inline mb-0">
                <input class="form-check-input trend-baseline" type="checkbox" id="trend-b-<?= h($bid) ?>"
                       value="<?= h($bid) ?>" <?= $bid === $baseline['id'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="trend-b-<?= h($bid) ?>"><?= h($bid) ?></label>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <a class="ms-auto small" href="<?= h(csqa_url('download.php', ['b' => $baseline['id'], 'p' => $param['id']])) ?>">
        <i class="fa-solid fa-download"></i> Baseline-<?= h($baseline['id']) ?> statistics (CSV)</a>
</div>
<div id="csqa-trend" class="csqa-trend" role="img"
     aria-label="Statistics of <?= h($selection_label) ?> per cycle"></div>
<p class="csqa-muted small">Click a point to show that cycle.</p>

<!-- all cycles -------------------------------------------------------------------------- -->
<h2><?= h($selection_label) ?>: All Cycles from Baseline-<?= h($baseline['id']) ?><?= h($scale_label) ?></h2>
<div class="table-responsive">
<table class="table table-sm table-bordered csqa-table align-middle">
    <thead>
    <tr>
        <th>Cycle</th>
        <th>Timespan</th>
        <th class="num">Coverage (days)</th>
        <?php foreach ($areas as $a): ?><th><?= h($area_names[$a]) ?></th><?php endforeach; ?>
    </tr>
    </thead>
    <tbody>
    <?php
    $n_valid_by_cycle = [];
    $pct_set_by_cycle = [];
    foreach ($trend_data[$baseline['id']] ?? [] as $trow) {
        $n_valid_by_cycle[$trow['cycle']][$trow['area']] = $trow['n_valid'];
        $pct_set_by_cycle[$trow['cycle']][$trow['area']] = $trow['pct_set'] ?? null;
    }
    foreach (array_reverse($cycles) as $c):
        $cn = (int)$c['cycle'];
    ?>
        <tr class="<?= $cn === (int)$cycle['cycle'] ? 'selected' : '' ?>">
            <td><a href="<?= h(selection_url($selection, ['c' => $cn])) ?>"><strong><?= $cn ?></strong></a></td>
            <td class="text-nowrap"><?= h(csqa_date($c['start'])) ?> to <?= h(csqa_cycle_last_day($c)) ?></td>
            <td class="num"><?= number_format(csqa_coverage_days($c, $param['source']), 1) ?>
                <?php if (csqa_is_partial($c, $cycle_length, $param['source'])): ?>
                    <span class="badge badge-partial">partial</span><?php endif; ?></td>
            <?php foreach ($areas as $a):
                $file = csqa_plot_filename($param['id'], $variant, $mode, $a, $image_format, $scale_suffix);
                $has_plot = in_array($mode, $map_modes, true) && ($n_valid_by_cycle[$cn][$a] ?? 0) > 0
                    && (!$is_bit_flag || ($pct_set_by_cycle[$cn][$a] ?? 0) > 0);
            ?>
                <td>
                    <?php if ($has_plot): ?>
                        <a href="<?= h(selection_url($selection, ['c' => $cn, 'a' => $a])) ?>">
                            <img class="csqa-thumb" loading="lazy" width="150" height="125"
                                 src="<?= h(csqa_plot_url($baseline['id'], $cn, $param['id'], $file, true, $c['processed_at'] ?? '')) ?>"
                                 alt="<?= h($area_names[$a]) ?> map, cycle <?= $cn ?>"
                                 onerror="this.parentElement.replaceWith(Object.assign(document.createElement('span'),
                                     {className: 'csqa-thumb-empty', textContent: 'map not available'}))"></a>
                    <?php elseif (!in_array($mode, $map_modes, true)): ?>
                        <span class="csqa-thumb-empty">maps for
                            <?= h(implode(', ', array_map(fn($m) => $mode_labels[$m] ?? $m, $map_modes))) ?> only</span>
                    <?php elseif ($is_bit_flag && ($n_valid_by_cycle[$cn][$a] ?? 0) > 0): ?>
                        <span class="csqa-thumb-empty">never set</span>
                    <?php else: ?>
                        <span class="csqa-thumb-empty">no valid data</span>
                    <?php endif; ?>
                </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- full size map viewer -->
<div class="modal fade" id="csqa-map-modal" tabindex="-1" aria-labelledby="csqa-map-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6" id="csqa-map-modal-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-2 text-center">
                <img class="csqa-modal-img" src="" alt="">
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="csqa-trend-data"><?= csqa_json([
    'param' => [
        'id' => $param['id'],
        'type' => $param['type'],
        'units' => $param['units'],
        'flags' => $is_bit_flag
            ? array_values(array_filter($param['flags'], fn($f) => (int)$f['value'] === 1))
            : $param['flags'],
        'label' => $selection_label,
    ],
    'areas' => array_map(fn($a) => ['id' => $a, 'name' => $area_names[$a]], $areas),
    'baseline' => $baseline['id'],
    'cycle' => (int)$cycle['cycle'],
    'series' => $trend_data,
    'cycleUrl' => selection_url($selection, ['b' => '__B__', 'c' => '__C__']),
]) ?></script>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
