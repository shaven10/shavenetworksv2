(function () {
    if (!window.reportChartData || typeof Chart === 'undefined') {
        return;
    }

    var data = window.reportChartData;
    var colors = ['#2563eb', '#0891b2', '#16a34a', '#d97706', '#dc2626', '#7c3aed', '#0f766e'];

    Chart.defaults.font.family = "'IBM Plex Sans', system-ui, -apple-system, 'Segoe UI', sans-serif";
    Chart.defaults.color = '#64748b';

    function navigateTo(url) {
        if (url) {
            window.location.href = url;
        }
    }

    function attachChartClick(chart, links) {
        if (!links || !links.length) {
            return;
        }
        chart.options.onClick = function (_evt, elements) {
            if (!elements.length) {
                return;
            }
            navigateTo(links[elements[0].index]);
        };
        chart.options.onHover = function (_evt, elements) {
            chart.canvas.style.cursor = elements.length ? 'pointer' : 'default';
        };
        chart.update();
    }

    function barChart(id, labels, values, links) {
        var el = document.getElementById(id);
        if (!el || !labels.length) {
            return;
        }
        var chart = new Chart(el, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: labels.map(function (_l, i) {
                        return colors[i % colors.length];
                    }),
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { maxTicksLimit: 5 } },
                    x: { ticks: { maxRotation: 40, minRotation: 0 } }
                }
            }
        });
        attachChartClick(chart, links);
    }

    function doughnut(id, labels, values, links) {
        var el = document.getElementById(id);
        if (!el || !labels.length) {
            return;
        }
        var chart = new Chart(el, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: labels.map(function (_l, i) {
                        return colors[i % colors.length];
                    }),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } }
                },
                cutout: '62%'
            }
        });
        attachChartClick(chart, links);
    }

    barChart('reportChartMethods', data.methods.labels, data.methods.values, data.methods.links);
    barChart('reportChartCollectors', data.collectors.labels, data.collectors.values, data.collectors.links);
    doughnut('reportChartPlans', data.plans.labels, data.plans.values, data.plans.links);

    document.querySelectorAll('.dashboard-row-link[data-href]').forEach(function (row) {
        row.addEventListener('click', function () {
            navigateTo(row.dataset.href);
        });
        row.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                navigateTo(row.dataset.href);
            }
        });
    });
})();
