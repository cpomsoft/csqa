/* CSQA portal: data availability page - files per acquisition day charts (Plotly) */

(function () {
    'use strict';

    // categorical colours (fixed order, validated for colour vision deficiency), as the trends
    const COLORS = ['#2a78d6', '#eb6834', '#1baf7a'];
    const INK = '#52514e';
    const GRID = '#e4e6ea';
    const MUTED = '#8a8f98';

    function median(values) {
        const v = values.slice().sort((a, b) => a - b);
        const mid = Math.floor(v.length / 2);
        return v.length % 2 ? v[mid] : (v[mid - 1] + v[mid]) / 2;
    }

    function drawChart(el, prod) {
        const dates = prod.daily.map((d) => d.date);
        const multi = prod.series.length > 1;
        const traces = prod.series.map((label, i) => ({
            type: 'bar',
            name: label,
            x: dates,
            y: prod.daily.map((d) => d.files[label] || 0),
            customdata: prod.daily.map((d) => d.hours[label] || 0),
            marker: {color: COLORS[i % COLORS.length], line: {width: 0}},
            hovertemplate: `${multi ? label + ': ' : ''}%{y} files, %{customdata:.1f} h of data<extra></extra>`,
        }));
        const totals = prod.daily.map((d) => Object.values(d.files).reduce((a, b) => a + b, 0));
        const withFiles = totals.filter((t) => t > 0);
        const med = withFiles.length ? median(withFiles) : 0;
        const layout = {
            height: multi ? 350 : 320,
            margin: {l: 56, r: 16, t: 16, b: multi ? 80 : 48},
            paper_bgcolor: '#ffffff',
            plot_bgcolor: '#ffffff',
            font: {family: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', size: 13, color: INK},
            barmode: 'stack',
            bargap: 0.2,
            hovermode: 'x unified',
            hoverlabel: {bgcolor: '#ffffff', bordercolor: GRID, font: {color: '#0b0b0b'}},
            showlegend: multi,
            legend: {orientation: 'h', x: 0, y: -0.25, yanchor: 'top', traceorder: 'normal', font: {color: INK}},
            xaxis: {
                type: 'date', title: {text: 'Acquisition day'}, tickformat: '%d %b', hoverformat: '%d-%b-%Y',
                dtick: 5 * 86400000, gridcolor: GRID, linecolor: GRID, zeroline: false,
            },
            yaxis: {
                title: {text: 'Files per day'}, gridcolor: GRID, linecolor: GRID, zeroline: false,
                rangemode: 'tozero',
            },
            shapes: [],
            annotations: [],
        };
        if (med > 0) {
            // reference: the median number of files on the days with files
            layout.shapes.push({
                type: 'line', xref: 'paper', x0: 0, x1: 1, yref: 'y', y0: med, y1: med,
                line: {color: MUTED, width: 1, dash: 'dash'},
            });
            layout.annotations.push({
                xref: 'paper', x: 1, xanchor: 'right', yref: 'y', y: med, yanchor: 'bottom',
                text: `median ${med} files/day`, showarrow: false, font: {size: 11, color: INK},
            });
        }
        Plotly.react(el, traces, layout, {
            responsive: true, displaylogo: false,
            modeBarButtonsToRemove: ['select2d', 'lasso2d', 'autoScale2d'],
            toImageButtonOptions: {filename: `csqa_${prod.id}_availability`, scale: 2},
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.Plotly) {
            return;
        }
        document.querySelectorAll('.csqa-avail-data').forEach((dataEl) => {
            const el = document.getElementById(dataEl.dataset.target);
            if (el) {
                drawChart(el, JSON.parse(dataEl.textContent));
            }
        });
    });
})();
