<?php
/**
 * CSQA portal: documentation
 */

require_once __DIR__ . '/includes/functions.php';

$page_title = 'Documentation';
$active_page = 'about';
$breadcrumb = 'Documentation';
require __DIR__ . '/includes/header.php';

$manifest = csqa_manifest();
$area_names = $manifest ? csqa_area_names($manifest) : [];
?>

<h1>Documentation</h1>
<p class="csqa-lead">
    This site monitors the performance of CryoSat-2 Level-2 products by mapping and summarising
    selected product parameters for every 30-day data take, for each ESA processing baseline.
</p>

<h2>Input Products</h2>
<ul class="csqa-lead">
    <li><strong>L2 GDR-A</strong>: the global Level-2 geophysical data record, containing the
        20 Hz measurements of all acquisition modes (LRM, SAR and SARin).</li>
    <li><strong>L2i</strong>: intermediate Level-2 products, organised by acquisition mode, containing
        additional specialised parameters.</li>
</ul>
<p class="csqa-lead">
    The product baseline is identified by the baseline letter in the product file name
    (ie <code>CS_OFFL_SIR_GDR_2__20260801T002509_20260801T020423_F001.nc</code> is baseline F,
    version 001). Results of each baseline are produced and shown separately. When the same granule
    is available more than once for a baseline, the highest version is used.
</p>
<p class="csqa-lead">
    The <a href="availability.php">Data Availability</a> page shows the number of files of each
    product per acquisition day over the most recent 30 days of data, and the latest data received.
</p>

<h2>Data Takes (Cycles)</h2>
<p class="csqa-lead">
    Results are organised by fixed 30-day data takes of CryoSat-2 rather than by calendar month.
    Cycle 1 starts at 00:00 UTC on 18-Oct-2010, the start of the operational phase, and cycle
    <var>n</var> covers the 30 days from 18-Oct-2010 + 30 &times; (<var>n</var>&minus;1) days.
    Each measurement is assigned to a cycle by its time stamp, so a product spanning a cycle boundary
    contributes to both cycles. Product time stamps can be TAI rather than UTC, so measurements within
    about 37 seconds of a cycle boundary may be assigned to the neighbouring cycle.
    See the <a href="cycles.php">list of cycles</a>.
</p>
<p class="csqa-lead">
    GDR-A products become available about
    <?= $manifest ? h(round(csqa_data_latency_days($manifest))) : 35 ?> days after acquisition,
    so recent cycles are first processed
    with partial data and reprocessed as further products become available. The coverage of each
    cycle (the number of its days covered by the input products) is shown with the results, and
    cycles covering less than <?= (int)round(100 * CSQA_PARTIAL_COVERAGE_FRACTION) ?>% of their
    days are marked as partial.
</p>

<h2>Areas</h2>
<ul class="csqa-lead">
    <li><strong>Global</strong>: all measurements.</li>
    <li><strong>North Polar</strong>: measurements located north of 60&deg;N.</li>
    <li><strong>South Polar</strong>: measurements located south of 60&deg;S.</li>
</ul>
<p class="csqa-lead">
    Measurement locations are taken from each variable's coordinates, which are the nadir location or
    the estimated echo location (point of closest approach, POCA) depending on the variable and
    product baseline.
</p>

<h2>Maps</h2>
<p class="csqa-lead">
    Maps show the parameter at each measurement location of the cycle, with histograms of the values
    and a map of the locations of missing values. To keep map production practical, maps of more
    than 2 million valid measurements show a regular subsample of the measurements along track (the
    fraction plotted is noted on the map); the statistics printed on the maps, and all the
    statistics on this site, use every measurement.
</p>
<p class="csqa-lead">
    Some parameters (ie radar freeboard) also have <strong>gridded maps</strong> of the polar
    regions: the valid measurements of the cycle are gridded into the cells of a polar
    stereographic grid (ie 10 km cells, EPSG:3413 in the north and EPSG:3031 in the south), and a
    statistic of the measurements in each cell (median, mean, maximum, standard deviation or count)
    is mapped. The statistics of a gridded map are those of its cell values.
</p>

<h2>Statistics</h2>
<ul class="csqa-lead">
    <li><strong>Flag parameters</strong> (ie acquisition mode, surface type): the percentage of
        each flag value, calculated from the valid (non-fill) values in the area.</li>
    <li><strong>Physical parameters</strong> (ie backscatter): the mean, median, standard deviation
        (population), root mean square (RMS), minimum and maximum of the valid values in the area.</li>
    <li><strong>Crossovers</strong>: single cycle crossover height differences (ascending minus
        descending) over the Antarctic and Greenland ice sheets, per acquisition mode and
        retracker. The number of crossovers, and statistics of the differences within 5 m (one crossover per pair of passes), with
        maps of the mean difference within 20 km of each 10 km grid cell.</li>
    <li><strong>Quality flags</strong>: the percentage of records with each bit set, per acquisition
        mode and per mode over a surface type of the surface type mask (LRM over ice, land and
        ocean, SARin over ice and land, SAR over ocean), as in the CryoSat-2 quality reports.</li>
    <li>For every selection the number of measurements (<em>Records</em>) and the number of valid
        values (<em>Valid</em>) are also given.</li>
</ul>
<p class="csqa-lead">
    Statistics of all cycles are shown as trends on each parameter page and can be downloaded as CSV
    files.
</p>

<?php if ($manifest): ?>
<h2>Monitored Parameters</h2>
<div class="table-responsive">
<table class="table table-sm table-bordered csqa-table align-middle">
    <thead>
    <tr><th>Parameter</th><th>Product</th><th>Variables</th><th>Type</th><th>Selections</th></tr>
    </thead>
    <tbody>
    <?php foreach ($manifest['parameters'] as $param): ?>
        <tr>
            <td><a href="<?= h(csqa_url('parameter.php', ['p' => $param['id']])) ?>"><?= h($param['long_name']) ?></a></td>
            <td><?= h($param['source']) ?></td>
            <td><code><?= h(implode(', ', array_column($param['variants'], 'variable'))) ?></code></td>
            <td><?= $param['type'] === 'flag' ? 'Flag' : 'Physical' . ($param['units'] ? ' (' . h($param['units']) . ')' : '') ?></td>
            <td>
                <?= h(implode(', ', array_map(fn($a) => $area_names[$a] ?? $a, $param['areas']))) ?>
                <?php if (count($param['variants']) > 1): ?>
                    <br>per <?= h(strtolower($param['variant_label'])) ?>: <?= h(implode(', ', array_column($param['variants'], 'name'))) ?>
                <?php endif; ?>
                <?php if ($param['modes']): ?><br>per acquisition mode<?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="csqa-muted small">
    Results generated <?= h(csqa_date($manifest['generated_at'], 'd-M-Y H:i')) ?> UTC by the
    CSQA processing software version <?= h($manifest['software_version']) ?>.
</p>
<?php endif; ?>

<h2>Further Information</h2>
<ul class="csqa-lead">
    <li><a href="https://earth.esa.int/eogateway/missions/cryosat">ESA CryoSat mission pages</a></li>
    <li><a href="https://earth.esa.int/eogateway/missions/cryosat/data/data-unavailability-periods">CryoSat data unavailability periods</a></li>
</ul>

<?php require __DIR__ . '/includes/footer.php'; ?>
