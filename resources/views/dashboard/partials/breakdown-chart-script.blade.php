{{-- expects `isDark` and `accent` already declared in the including page's script scope --}}
const grid = isDark ? '#2c2c2a' : '#e1e0d9';
const ink = isDark ? '#c3c2b7' : '#52514e';

const breakdowns = @json($breakdowns);
document.querySelectorAll('.viz-breakdown').forEach(function (canvas) {
    const dimension = canvas.dataset.dimension;
    const entries = Object.entries(breakdowns[dimension] || {});
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: entries.map(function (e) { return e[0]; }),
            datasets: [{
                data: entries.map(function (e) { return e[1]; }),
                backgroundColor: accent,
                borderRadius: 4,
                maxBarThickness: 18,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { beginAtZero: true, grid: { color: grid }, ticks: { color: ink, precision: 0 } },
                // autoSkip false — Chart.js drops middle category labels on a short canvas like
                // this (~28px/bar) if it judges them too cramped to fit; with only a handful of
                // categories there's always room, so force every label to render
                y: { grid: { display: false }, ticks: { color: ink, autoSkip: false } },
            },
            plugins: {
                legend: { display: false },
                // xAlign 'left' grows the tooltip box to the right of the cursor instead of
                // centering on it — otherwise it can render directly over the y-axis category
                // labels on the left edge
                tooltip: { xAlign: 'left', yAlign: 'center' },
            },
        },
    });
});
