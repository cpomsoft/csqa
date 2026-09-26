<?php
/**
 * CSQA portal: list of 30-day data takes (cycles) and their processing status per baseline
 */

require_once __DIR__ . '/includes/functions.php';

$page_title = 'Data Takes (Cycles)';
$active_page = 'cycles';
$breadcrumb = 'Data Takes';
$manifest = csqa_manifest();

$extra_scripts = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('cycle-finder');
    if (!form) return;
    const start = Date.parse(form.dataset.start + 'T00:00:00Z');
    const lengthMs = Number(form.dataset.length) * 86400000;
    const out = document.getElementById('cycle-finder-result');
    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const fmt = (t) => {
        const d = new Date(t);
        return `${String(d.getUTCDate()).padStart(2, '0')}-${MONTHS[d.getUTCMonth()]}-${d.getUTCFullYear()}`;
    };
    const update = () => {
        const t = Date.parse(form.date.value + 'T00:00:00Z');
        if (Number.isNaN(t)) { out.textContent = ''; return; }
        if (t < start) { out.textContent = 'Before the start of cycle 1'; return; }
        const cycle = Math.floor((t - start) / lengthMs) + 1;
        const cStart = start + (cycle - 1) * lengthMs;
        out.innerHTML = '';
        const link = document.createElement('a');
        link.href = '#cycle-' + cycle;
        link.textContent = 'Cycle ' + cycle;
        out.append(link, ': ' + fmt(cStart) + ' to ' + fmt(cStart + lengthMs - 1));
    };
    form.date.addEventListener('input', update);
    form.addEventListener('submit', (e) => { e.preventDefault(); update(); });
    update();
});
</script>
JS;

require __DIR__ . '/includes/header.php';
?>

<h1>Data Takes (Cycles)</h1>
<p class="csqa-lead">
    Monitoring results are organised by 30-day data takes (cycles) of CryoSat-2, numbered from
    cycle 1 starting at 00:00 UTC on the start of the operational phase. The table lists every cycle,
    the input products found for each product baseline, and the number of days of the cycle they
    cover. Cycles covering less than <?= (int)round(100 * CSQA_PARTIAL_COVERAGE_FRACTION) ?>% of
    their 30 days are marked as partial. Cycles are listed up to the latest that can have data,
    as input products become available about
    <?= $manifest ? h(round(csqa_data_latency_days($manifest))) : 35 ?> days after acquisition.
</p>

<?php if (!$manifest): ?>
    <div class="alert alert-info">No monitoring results have been processed yet.</div>
<?php else:
    $cycle_length = (int)$manifest['cycle_length_days'];
    $mission_start = strtotime($manifest['mission_start_date'] . 'T00:00:00Z');
    $baselines = $manifest['baselines'];
    $by_baseline = [];
    // list cycles up to the latest that can have data (or the latest processed, if later)
    $last_cycle = csqa_latest_available_cycle($manifest);
    foreach ($baselines as $b) {
        foreach ($b['cycles'] as $c) {
            $by_baseline[$b['id']][(int)$c['cycle']] = $c;
            $last_cycle = max($last_cycle, (int)$c['cycle']);
        }
    }
    $first_param = $manifest['parameters'][0]['id'] ?? '';
?>

<form class="csqa-controls mb-3" id="cycle-finder" data-start="<?= h($manifest['mission_start_date']) ?>"
      data-length="<?= $cycle_length ?>">
    <div>
        <label class="form-label" for="finder-date">Find the cycle of a date</label>
        <input class="form-control form-control-sm" type="date" id="finder-date" name="date"
               value="<?= gmdate('Y-m-d') ?>" min="<?= h($manifest['mission_start_date']) ?>">
    </div>
    <div class="pb-1" id="cycle-finder-result" aria-live="polite"></div>
</form>

<div class="table-responsive">
<table class="table table-sm table-bordered csqa-table align-middle">
    <thead>
    <tr>
        <th rowspan="2">Cycle</th>
        <th rowspan="2">Start</th>
        <th rowspan="2">End</th>
        <?php foreach ($baselines as $b): ?>
            <th colspan="2" class="text-center">Baseline-<?= h($b['id']) ?></th>
        <?php endforeach; ?>
    </tr>
    <tr>
        <?php foreach ($baselines as $b): ?>
            <th class="num">Files</th>
            <th>Coverage (days)</th>
        <?php endforeach; ?>
    </tr>
    </thead>
    <tbody>
    <?php for ($cn = $last_cycle; $cn >= 1; $cn--):
        $start = $mission_start + ($cn - 1) * $cycle_length * 86400;
    ?>
        <tr id="cycle-<?= $cn ?>">
            <td><strong><?= $cn ?></strong></td>
            <td class="text-nowrap"><?= gmdate('d-M-Y', $start) ?></td>
            <td class="text-nowrap"><?= gmdate('d-M-Y', $start + $cycle_length * 86400 - 1) ?></td>
            <?php foreach ($baselines as $b):
                $c = $by_baseline[$b['id']][$cn] ?? null;
            ?>
                <?php if (!$c): ?>
                    <td class="num csqa-muted">&ndash;</td><td class="csqa-muted small">not processed</td>
                <?php else:
                    $files = array_sum(array_map(fn($p) => (int)($p['n_files'] ?? 0), $c['products']));
                    $days = csqa_coverage_days($c);
                    $pct = min(100, 100 * $days / $cycle_length);
                ?>
                    <td class="num"><?= number_format($files) ?></td>
                    <td style="min-width: 11rem">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height: 0.5rem" role="progressbar"
                                 aria-label="Coverage" aria-valuenow="<?= round($pct) ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width: <?= round($pct, 1) ?>%; background: var(--csqa-navy)"></div>
                            </div>
                            <a class="small text-nowrap"
                               href="<?= h(csqa_url('parameter.php', ['p' => $first_param, 'b' => $b['id'], 'c' => $cn])) ?>"
                               title="Show the monitoring results of this cycle"><?= number_format($days, 1) ?></a>
                        </div>
                    </td>
                <?php endif; ?>
            <?php endforeach; ?>
        </tr>
    <?php endfor; ?>
    </tbody>
</table>
</div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
