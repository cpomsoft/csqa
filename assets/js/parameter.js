/* CSQA portal: parameter page - statistics trend chart (Plotly) and full size map viewer */

(function () {
    'use strict';

    // categorical colours of the areas (fixed order, validated for colour vision deficiency)
    const AREA_COLORS = ['#2a78d6', '#eb6834', '#1baf7a'];
    const INK = '#52514e';
    const GRID = '#e4e6ea';
    const STAT_LABELS = {
        mean: 'Mean', median: 'Median', std: 'Std Dev', rms: 'RMS', min: 'Min', max: 'Max',
        n_valid: 'Number of valid values', pct_valid: '% valid values',
        n_cells: 'Number of grid cells with data', n_records: 'Number of measurements gridded',
    };
    // statistics that are numbers of values (no units, integer format)
    const COUNT_STATS = ['n_valid', 'n_cells', 'n_records'];
    // line styles used to tell baselines apart when several are shown
    const BASELINE_DASH = ['solid', 'dash', 'dot', 'dashdot'];

    function setupMapViewer() {
        const modalEl = document.getElementById('csqa-map-modal');
        if (!modalEl || !window.bootstrap) {
            return;
        }
        const modal = new bootstrap.Modal(modalEl);
        const modalImg = modalEl.querySelector('.csqa-modal-img');
        const modalTitle = modalEl.querySelector('.modal-title');
        document.querySelectorAll('img.csqa-zoom').forEach((img) => {
            img.tabIndex = 0;
            const open = () => {
                modalImg.src = img.dataset.full || img.src;
                modalImg.alt = img.alt;
                modalTitle.textContent = img.dataset.caption || '';
                modal.show();
            };
            img.addEventListener('click', open);
            img.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    open();
                }
            });
        });
    }

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    // ie '2026-07-26' -> '26-Jul-2026' (as the dates shown by the PHP pages)
    function fmtDate(isoDate) {
        const d = new Date(isoDate + 'T00:00:00Z');
        return `${String(d.getUTCDate()).padStart(2, '0')}-${MONTHS[d.getUTCMonth()]}-${d.getUTCFullYear()}`;
    }

    function lastDay(isoDate) {
        const d = new Date(isoDate + 'T00:00:00Z');
        d.setUTCDate(d.getUTCDate() - 1);
        return d.toISOString().slice(0, 10);
    }

    function statValue(row, stat) {
        if (stat === 'pct_valid') {
            return row.n_records > 0 ? 100 * row.n_valid / row.n_records : null;
        }
        return row[stat];
    }

    // selected baselines, the page's baseline first (drawn solid when comparing baselines)
    function selectedBaselines(data) {
        const boxes = document.querySelectorAll('.trend-baseline');
        if (!boxes.length) {
            return [data.baseline];
        }
        const selected = Array.from(boxes).filter((b) => b.checked).map((b) => b.value);
        return selected.sort((a, b) => (b === data.baseline) - (a === data.baseline));
    }

    // x, y and hover data of a series (rows sorted by cycle), with a null point inserted where
    // cycles are missing so the line breaks across the gap instead of joining its neighbours
    function seriesWithGaps(rows, yValue, bid) {
        const series = {x: [], y: [], customdata: []};
        rows.forEach((r, i) => {
            if (i > 0 && r.cycle - rows[i - 1].cycle > 1) {
                series.x.push(rows[i - 1].cycle + 1);
                series.y.push(null);
                series.customdata.push(null);
            }
            series.x.push(r.cycle);
            series.y.push(yValue(r));
            series.customdata.push([r.cycle, fmtDate(r.start_date), fmtDate(lastDay(r.end_date)), bid]);
        });
        return series;
    }

    // markers for every point when few cycles are shown, otherwise only for points with no
    // neighbour on either side (a line alone would not show them)
    function markerStyle(y, color, allPoints) {
        const isNull = (v) => v === null || v === undefined;
        const shown = y.map((v, i) => !isNull(v) && (allPoints || (isNull(y[i - 1]) && isNull(y[i + 1]))));
        return {
            color: color,
            size: shown.map((on) => (on ? 8 : 0)),
            line: {color: '#fff', width: shown.map((on) => (on ? 2 : 0))},
        };
    }

    function buildTraces(data) {
        const param = data.param;
        const baselines = selectedBaselines(data);
        const multi = baselines.length > 1;
        const traces = [];
        // markers on every point only when few enough cycles are shown to keep lines readable
        const cycles = new Set(baselines.flatMap((bid) => (data.series[bid] || []).map((r) => r.cycle)));
        const allMarkers = cycles.size <= 60;

        baselines.forEach((bid, iBaseline) => {
            const rows = data.series[bid] || [];
            const dash = BASELINE_DASH[iBaseline % BASELINE_DASH.length];
            const suffix = multi ? ` (${bid})` : '';

            if (param.type === 'flag') {
                const area = document.getElementById('trend-area').value;
                const areaRows = rows.filter((r) => r.area === area);
                param.flags.forEach((flag) => {
                    const series = seriesWithGaps(areaRows, (r) => r['pct_' + flag.key], bid);
                    traces.push({
                        ...series,
                        name: flag.name + suffix,
                        mode: 'lines+markers',
                        line: {color: flag.color, width: 2, dash: dash},
                        marker: markerStyle(series.y, flag.color, allMarkers),
                        hovertemplate: `${flag.name}${suffix}: %{y:.2f}%<extra></extra>`,
                    });
                });
            } else {
                const stat = document.getElementById('trend-stat').value;
                data.areas.forEach((area, i) => {
                    const areaRows = rows.filter((r) => r.area === area.id && statValue(r, stat) !== null);
                    const color = AREA_COLORS[i % AREA_COLORS.length];
                    const isCount = COUNT_STATS.includes(stat);
                    const unit = stat === 'pct_valid' ? '%' : (isCount || !param.units ? '' : ` ${param.units}`);
                    const valueFmt = isCount ? '%{y:,}' : '%{y:.3f}';
                    const series = seriesWithGaps(areaRows, (r) => statValue(r, stat), bid);
                    traces.push({
                        ...series,
                        name: area.name + suffix,
                        mode: 'lines+markers',
                        line: {color: color, width: 2, dash: dash},
                        marker: markerStyle(series.y, color, allMarkers),
                        hovertemplate: `${area.name}${suffix}: ${valueFmt}${unit}<extra></extra>`,
                    });
                });
            }
        });
        return traces;
    }

    function yTitle(data) {
        if (data.param.type === 'flag') {
            return '% of valid values';
        }
        const stat = document.getElementById('trend-stat').value;
        if (COUNT_STATS.includes(stat)) {
            return STAT_LABELS[stat];
        }
        if (stat === 'pct_valid') {
            return STAT_LABELS[stat] + ' (%)';
        }
        return STAT_LABELS[stat] + (data.param.units ? ` (${data.param.units})` : '');
    }

    function drawTrend(data) {
        const el = document.getElementById('csqa-trend');
        const traces = buildTraces(data);
        const layout = {
            height: 480,
            margin: {l: 70, r: 20, t: 30, b: 40},
            paper_bgcolor: '#ffffff',
            plot_bgcolor: '#ffffff',
            font: {family: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', size: 13, color: INK},
            hovermode: 'x unified',
            hoverlabel: {bgcolor: '#ffffff', bordercolor: GRID, font: {color: '#0b0b0b'}},
            legend: {orientation: 'h', x: 0, y: -0.18, yanchor: 'top', font: {color: INK}},
            xaxis: {
                title: {text: 'Cycle (30-day data take)'},
                gridcolor: GRID, linecolor: GRID, zeroline: false, tickformat: 'd',
                hoverformat: 'd', rangemode: 'normal',
            },
            yaxis: {
                title: {text: yTitle(data)}, gridcolor: GRID, linecolor: GRID, zeroline: false,
                rangemode: data.param.type === 'flag' ? 'tozero' : 'normal',
            },
            shapes: [{
                type: 'line', xref: 'x', yref: 'paper', x0: data.cycle, x1: data.cycle, y0: 0, y1: 1,
                line: {color: '#9aa3ad', width: 1},
            }],
            annotations: [{
                xref: 'x', yref: 'paper', x: data.cycle, y: 1, yanchor: 'bottom', showarrow: false,
                text: `cycle ${data.cycle}`, font: {size: 11, color: INK},
            }],
        };
        const xs = traces.flatMap((t) => t.x);
        if (xs.length) {
            const xMin = Math.min(...xs);
            const xMax = Math.max(...xs);
            // integer cycle ticks; pad the range so few cycles are not drawn at the plot edges
            layout.xaxis.dtick = Math.max(1, Math.ceil((xMax - xMin) / 12));
            if (new Set(xs).size <= 2) {
                layout.xaxis.range = [xMin - 3, xMax + 3];
            }
        }
        // invisible first trace giving the dates of every shown cycle in the hover label
        const dates = new Map();
        traces.forEach((t) => t.x.forEach((x, i) => {
            if (!dates.has(x) && t.y[i] !== null && t.y[i] !== undefined) {
                dates.set(x, [t.customdata[i][1], t.customdata[i][2], t.y[i]]);
            }
        }));
        if (dates.size) {
            const xDates = Array.from(dates.keys()).sort((a, b) => a - b);
            traces.unshift({
                x: xDates,
                y: xDates.map((x) => dates.get(x)[2]),
                customdata: xDates.map((x) => dates.get(x)),
                mode: 'markers',
                marker: {opacity: 0, size: 1},
                showlegend: false,
                hovertemplate: '%{customdata[0]} to %{customdata[1]}<extra></extra>',
            });
        }
        Plotly.react(el, traces, layout, {
            responsive: true, displaylogo: false,
            modeBarButtonsToRemove: ['select2d', 'lasso2d', 'autoScale2d'],
            toImageButtonOptions: {filename: `csqa_${data.param.id}_trend`, scale: 2},
        });

        if (!el.dataset.clickBound) {
            el.dataset.clickBound = '1';
            el.on('plotly_click', (event) => {
                const point = (event.points || []).find((p) => p.customdata && p.customdata.length > 3);
                if (!point) {
                    return;
                }
                const url = data.cycleUrl
                    .replace('__B__', encodeURIComponent(point.customdata[3]))
                    .replace('__C__', encodeURIComponent(point.customdata[0]));
                window.location.href = url;
            });
        }
    }

    function setupTrend() {
        const dataEl = document.getElementById('csqa-trend-data');
        if (!dataEl || !window.Plotly) {
            return;
        }
        const data = JSON.parse(dataEl.textContent);
        const redraw = () => drawTrend(data);
        document.querySelectorAll('#csqa-trend-controls select, #csqa-trend-controls input')
            .forEach((control) => control.addEventListener('change', redraw));
        redraw();
    }

    document.addEventListener('DOMContentLoaded', () => {
        setupMapViewer();
        setupTrend();
    });
})();
