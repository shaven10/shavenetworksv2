(function () {
    if (!window.dashboardData || typeof Chart === 'undefined') {
        return;
    }

    var data = window.dashboardData;
    var colors = {
        green: '#16a34a',
        blue: '#2563eb',
        orange: '#d97706',
        red: '#dc2626',
        cyan: '#0891b2',
        gray: '#94a3b8'
    };

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
            var canvas = chart.canvas;
            canvas.style.cursor = elements.length ? 'pointer' : 'default';
        };

        chart.update();
    }

    function doughnut(id, labels, values, colorSet, links) {
        var el = document.getElementById(id);
        if (!el) return;

        var chart = new Chart(el, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colorSet,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } }
                },
                cutout: '65%'
            }
        });

        attachChartClick(chart, links);
    }

    function lineChart(id, labels, values, links) {
        var el = document.getElementById(id);
        if (!el) return;

        var chart = new Chart(el, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Collections',
                    data: values,
                    borderColor: colors.cyan,
                    backgroundColor: 'rgba(8, 145, 178, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { maxTicksLimit: 5 } },
                    x: { ticks: { maxTicksLimit: 6 } }
                }
            }
        });

        attachChartClick(chart, links);
    }

    function barChart(id, labels, values, links) {
        var el = document.getElementById(id);
        if (!el) return;

        var chart = new Chart(el, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Active subscribers',
                    data: values,
                    backgroundColor: colors.blue,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, maxTicksLimit: 5 } },
                    x: { ticks: { maxRotation: 45, minRotation: 0 } }
                }
            }
        });

        attachChartClick(chart, links);
    }

    doughnut(
        'chartCustomerStatus',
        data.customerStatus.labels,
        data.customerStatus.values,
        [colors.green, colors.orange, colors.red],
        data.customerStatus.links
    );

    if (data.planSubscribers) {
        barChart(
            'chartPlanSubscribers',
            data.planSubscribers.labels,
            data.planSubscribers.values,
            data.planSubscribers.links
        );
    }

    if (data.billStatus) {
        doughnut(
            'chartBillStatus',
            data.billStatus.labels,
            data.billStatus.values,
            [colors.green, colors.blue, colors.orange, colors.red],
            data.billStatus.links
        );
    }

    if (data.monthlyCollections) {
        lineChart(
            'chartCollections',
            data.monthlyCollections.labels,
            data.monthlyCollections.values,
            data.monthlyCollections.links
        );
    }

    if (data.paymentMethods && data.paymentMethods.labels.length) {
        barChart(
            'chartPaymentMethods',
            data.paymentMethods.labels,
            data.paymentMethods.values,
            data.paymentMethods.links
        );
    }

    if (data.installations) {
        lineChart(
            'chartInstallations',
            data.installations.labels,
            data.installations.values,
            data.installations.links
        );
    }

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
