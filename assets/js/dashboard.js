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

    Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    Chart.defaults.color = '#64748b';

    function doughnut(id, labels, values, colorSet) {
        var el = document.getElementById(id);
        if (!el) return;

        new Chart(el, {
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
    }

    function lineChart(id, labels, values) {
        var el = document.getElementById(id);
        if (!el) return;

        new Chart(el, {
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
                    pointRadius: 3
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
    }

    function barChart(id, labels, values) {
        var el = document.getElementById(id);
        if (!el) return;

        new Chart(el, {
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
    }

    doughnut('chartCustomerStatus', data.customerStatus.labels, data.customerStatus.values,
        [colors.green, colors.orange, colors.red]);

    if (data.planSubscribers) {
        barChart('chartPlanSubscribers', data.planSubscribers.labels, data.planSubscribers.values);
    }

    if (data.billStatus) {
        doughnut('chartBillStatus', data.billStatus.labels, data.billStatus.values,
            [colors.green, colors.blue, colors.orange, colors.red]);
    }

    if (data.monthlyCollections) {
        lineChart('chartCollections', data.monthlyCollections.labels, data.monthlyCollections.values);
    }
})();
