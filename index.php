<?php
/**
 * CSQA portal: overview page
 */

require_once __DIR__ . '/includes/functions.php';

$page_title = CSQA_SITE_TITLE;
$active_page = 'home';
$breadcrumb = '';
require __DIR__ . '/includes/header.php';

$manifest = csqa_manifest();
?>

<h1>CryoSat-2 Level-2 Performance Monitoring</h1>
<p class="csqa-lead">
    The performance of CryoSat-2 is monitored from the Level-2 products (GDR-A and L2i) produced by
    the ESA Instrument Processing Facility in all SIRAL acquisition modes (LRM, SAR and SARin).
    Maps and statistics of each monitored parameter are produced for every 30-day data take (cycle)
    since the start of the operational phase on 18-Oct-2010, globally and for the north and south
    polar regions, and for each product baseline.
</p>

<?php if (!$manifest): ?>
    <div class="alert alert-info">No monitoring results have been processed yet.</div>
<?php else:
    $cycle_length = (int)$manifest['cycle_length_days'];
    $mission_start = strtotime($manifest['mission_start_date'] . 'T00:00:00Z');
    $today_cycle = csqa_cycle_of_time($manifest, time());
    $latency_days = csqa_data_latency_days($manifest);
    $latest_cycle = csqa_latest_available_cycle($manifest);
    $latest_cycle_start = csqa_cycle_start_time($manifest, $latest_cycle);
    $area_names = csqa_area_names($manifest);
?>

<p class="csqa-muted">
    <i class="fa-regular fa-calendar"></i>
    Today (<?= gmdate('d-M-Y') ?>) is in cycle <?= $today_cycle ?>. GDR-A products become
    available about <?= h(round($latency_days)) ?> days after acquisition, so the latest cycle that
    can have data is cycle <?= $latest_cycle ?> (<?= gmdate('d-M-Y', $latest_cycle_start) ?> to
    <?= gmdate('d-M-Y', $latest_cycle_start + $cycle_length * 86400 - 1) ?>), which is completed as
    further products become available.
</p>

<h2>Latest Processed Cycles</h2>
<div class="row g-3">
    <?php foreach ($manifest['baselines'] as $b):
        $latest = end($b['cycles']);
        $partial = csqa_is_partial($latest, $cycle_length);
    ?>
        <div class="col-sm-6 col-xl-4">
            <div class="csqa-stat-tile">
                <div class="label">Baseline-<?= h($b['id']) ?> latest cycle</div>
                <div class="value"><?= (int)$latest['cycle'] ?></div>
                <div class="small"><?= h(csqa_date($latest['start'])) ?> to <?= h(csqa_cycle_last_day($latest)) ?></div>
                <div class="small mt-1">
                    <span class="badge <?= $partial ? 'badge-partial' : 'badge-complete' ?>">
                        <?= $partial ? 'Partial' : 'Complete' ?></span>
                    <?= number_format(csqa_coverage_days($latest), 1) ?> of <?= $cycle_length ?> days
                    &middot; <?= count($b['cycles']) ?> cycle<?= count($b['cycles']) === 1 ? '' : 's' ?> processed
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h2>Monitored Parameters</h2>
<div class="row g-3">
    <?php foreach ($manifest['parameters'] as $param):
        $baselines = csqa_param_baselines($manifest, $param['id']);
        $thumb = null;
        if ($baselines) {
            $b = $baselines[0];
            foreach ($manifest['baselines'] as $candidate) {
                if (!empty($candidate['default']) && csqa_param_cycles($candidate, $param['id'])) {
                    $b = $candidate;
                    break;
                }
            }
            $param_cycles = csqa_param_cycles($b, $param['id']);
            $latest = end($param_cycles);
            $card_variant = $param['default_variant'] ?? $param['variants'][0]['id'];
            $card_mode = $param['map_modes'][0] ?? ($param['modes'][0] ?? '');
            $card_area = $param['areas'][0];
            $card_suffix = '';
            if (array_key_exists('map_modes', $param) && !$param['map_modes'] && !empty($param['grid'])) {
                // only gridded maps (ie crossovers): the first grid statistic's map
                $card_mode = $param['grid']['modes'][0];
                $card_area = $param['grid']['areas'][0];
                $card_suffix = $param['grid']['statistics'][0]['file_suffix'];
            }
            $file = csqa_plot_filename($param['id'], $card_variant, $card_mode, $card_area,
                $param['image_format'], $card_suffix);
            $thumb = csqa_plot_url($b['id'], (int)$latest['cycle'], $param['id'], $file, false,
                $latest['processed_at'] ?? '');
            $thumb_caption = $area_names[$card_area] . ', Baseline-' . $b['id'] . ', cycle '
                . $latest['cycle'];
        }
    ?>
        <div class="col-md-6 col-xl-4">
            <a class="csqa-param-card" href="<?= h(csqa_url('parameter.php', ['p' => $param['id']])) ?>">
                <?php if ($thumb): ?>
                    <img src="<?= h($thumb) ?>" loading="lazy" width="1020" height="850"
                         alt="<?= h($param['long_name']) ?> map: <?= h($thumb_caption) ?>">
                <?php endif; ?>
                <div class="body">
                    <div class="title"><?= h($param['long_name']) ?></div>
                    <div class="small csqa-muted"><?= $thumb ? h($thumb_caption) : 'not processed yet' ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<h2>About the Monitoring</h2>
<ul class="csqa-lead">
    <li>Results are organised by <a href="cycles.php">30-day data takes (cycles)</a>, numbered from
        cycle 1 starting at 00:00 UTC on <?= h(gmdate('d-M-Y', $mission_start)) ?>.</li>
    <li>Each parameter is mapped and summarised for the areas:
        <?= h(implode(', ', array_values($area_names))) ?>.</li>
    <li>Flag parameters are summarised by the percentage of each flag value, and physical
        parameters by their mean, median and standard deviation.</li>
    <li>See the <a href="about.php">documentation</a> for details of the processing.</li>
</ul>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
