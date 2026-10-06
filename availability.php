<?php
/**
 * CSQA portal: availability of the most recent input products (GDR-A, L2i): the number of
 * product files per acquisition day over the most recent 30 days of data, and the latest data
 * received. From availability.json, updated with the portal index after each processing run.
 */

require_once __DIR__ . '/includes/functions.php';

$page_title = 'Data Availability';
$active_page = 'availability';
$breadcrumb = 'Data Availability';
$manifest = csqa_manifest();
$availability = csqa_availability();
$extra_head = '<script src="' . h(CSQA_PLOTLY_JS) . '" defer></script>'
    . '<script src="assets/js/availability.js?v=2" defer></script>';
require __DIR__ . '/includes/header.php';

$latency_days = $manifest ? csqa_data_latency_days($manifest) : 35;
?>

<h1>Input Data Availability</h1>
<p class="csqa-lead">
    Availability of the most recent <?= (int)($availability['days'] ?? 30) ?> days of each input
    product in the monitoring archive, from the ESA CryoSat science server. Note that GDR-A
    products are produced about 30 days after acquisition (once the precise orbits are
    available), and there may be a further delay of a few days before the L2 processing is
    completed, so the monitoring uses data up to about <?= h(round($latency_days)) ?> days old.
</p>

<?php if (!$availability): ?>
    <div class="alert alert-info">The availability of the input products has not been produced yet.</div>
<?php else: ?>
    <p class="csqa-muted small">
        <i class="fa-regular fa-clock"></i> Updated <?= h(csqa_date($availability['generated_at'], 'd-M-Y H:i')) ?> UTC
    </p>

    <?php foreach ($availability['products'] as $n => $prod):
        $latest = $prod['latest'];
        $daily = $prod['daily'];
        $totals = array_map(fn($d) => array_sum($d['files']), $daily);
        $empty_days = count(array_filter($totals, fn($t) => $t == 0));
    ?>
        <section class="mb-4">
            <h2 id="<?= h(strtolower($prod['id'])) ?>"><?= h($prod['long_name']) ?></h2>
            <?php if (!$latest): ?>
                <div class="alert alert-warning">No <?= h($prod['id']) ?> files found in the archive in the last
                    120 days.</div>
            <?php else: $age = csqa_days_ago($latest['stop']); ?>
                <div class="row g-3 mb-3">
                    <div class="col-6 col-xl-3">
                        <div class="csqa-stat-tile">
                            <div class="label">Latest <?= h($prod['id']) ?> data</div>
                            <div class="value"><?= h(csqa_date($latest['stop'])) ?></div>
                            <div class="small">+<?= $age ?> day<?= $age === 1 ? '' : 's' ?> old</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="csqa-stat-tile">
                            <div class="label">Latest file received</div>
                            <div class="value"><?= $latest['received'] ? h(csqa_date($latest['received'])) : '&ndash;' ?></div>
                            <div class="small"><?= $latest['received'] ? h(csqa_date($latest['received'], 'H:i')) . ' UTC' : '' ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="csqa-stat-tile">
                            <div class="label">Files in <?= count($daily) ?> days</div>
                            <div class="value"><?= number_format(array_sum($totals)) ?></div>
                            <div class="small"><?= h(csqa_date($prod['window']['start'])) ?> to <?= h(csqa_date($prod['window']['end'])) ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="csqa-stat-tile">
                            <div class="label">Days without files</div>
                            <div class="value"><?= $empty_days ?></div>
                            <div class="small">of <?= count($daily) ?> days</div>
                        </div>
                    </div>
                </div>

                <div class="csqa-avail-chart" id="avail-chart-<?= $n ?>" role="img"
                     aria-label="<?= h($prod['long_name']) ?> files per acquisition day, <?= h(csqa_date($prod['window']['start'])) ?> to <?= h(csqa_date($prod['window']['end'])) ?>"></div>
                <script type="application/json" class="csqa-avail-data" data-target="avail-chart-<?= $n ?>"><?= csqa_json($prod) ?></script>

                <details class="mt-2">
                    <summary class="small">Files and hours of data per day</summary>
                    <div class="table-responsive mt-2">
                    <table class="table table-sm table-bordered csqa-table align-middle w-auto">
                        <thead>
                        <tr>
                            <th>Acquisition day</th>
                            <?php foreach ($prod['series'] as $s): ?>
                                <th class="num"><?= h($s) ?> files</th>
                            <?php endforeach; ?>
                            <th class="num">Hours of data</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_reverse($daily) as $d): ?>
                            <tr class="<?= array_sum($d['files']) == 0 ? 'csqa-muted' : '' ?>">
                                <td class="text-nowrap"><?= h(csqa_date($d['date'] . 'T00:00:00Z')) ?></td>
                                <?php foreach ($prod['series'] as $s): ?>
                                    <td class="num"><?= (int)($d['files'][$s] ?? 0) ?></td>
                                <?php endforeach; ?>
                                <td class="num"><?= number_format(array_sum($d['hours']), 1) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </details>
                <p class="csqa-muted small mt-1">
                    Latest file: <?= h($latest['file']) ?>. Files are counted on the day their data starts
                    (the highest version of each product granule).
                </p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
