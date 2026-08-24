// assets/js/charts.js
import Chart from 'chart.js/auto';
import ChartDataLabels from 'chartjs-plugin-datalabels';

function prettyDate(s) {
    // make it parseable as ISO (add 'T' and assume UTC)
    const d = new Date(s.replace(' ', 'T') + 'Z');
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        timeZone: 'UTC',
    }).format(d);
}

// Helper to safely parse JSON from <script> tags
const parseChartData = (id) => {
    const el = document.getElementById(id);
    if (!el) return null;
    try {
        return JSON.parse(el.textContent);
    } catch (e) {
        console.error(`Invalid JSON in ${id}`, e);
        return null;
    }
};

document.addEventListener('DOMContentLoaded', () => {
    //  CSS root color variables
    const root = getComputedStyle(document.documentElement);
    const primaryColor = root.getPropertyValue('--color-primary').trim();
    const secondaryColor = root.getPropertyValue('--color-secondary').trim();
    const accentColor = root.getPropertyValue('--color-accent').trim();
    const warningColor = root.getPropertyValue('--color-warning').trim();
    const successColor = root.getPropertyValue('--color-success').trim();

    // === Dashboard – Storage Plans Doughnut Chart ===
    const dashboardPlansData = parseChartData('dashboardStoragePlanData');
    const dashboardPlansCanvas = document.getElementById('admin-dashboard-metric-2');

    if (dashboardPlansData && dashboardPlansCanvas) {
        const labels = Object.keys(dashboardPlansData);
        const values = Object.values(dashboardPlansData);

        new Chart(dashboardPlansCanvas, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [
                    {
                        data: values,
                        backgroundColor: [primaryColor, secondaryColor, accentColor, warningColor, successColor],
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { boxWidth: 20, padding: 15 },
                        align: 'center',
                    },
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold' },
                        formatter: (value) => `${value.toFixed(1)}%`,
                    },
                    title: {
                        display: true,
                        text: 'Storage Plans',
                        font: { size: 16, weight: 'bold' },
                    },
                },
            },
        });
    }

    // === Dashboard – Circular “Active Firms” Chart ===
    const activeFirmsValueElement = document.getElementById('dashboardActiveFirmsValue');
    const activeFirmsCanvas = document.getElementById('admin-dashboard-metric-1');

    if (activeFirmsCanvas && activeFirmsValueElement) {
        const value = parseFloat(activeFirmsValueElement.textContent);
        new Chart(activeFirmsCanvas, {
            type: 'doughnut',
            data: {
                datasets: [
                    {
                        data: [value, 100 - value],
                        backgroundColor: [primaryColor, '#e5e7eb'],
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false },
                    datalabels: { display: false },
                    title: {
                        // color: '#fff',
                        display: true,
                        text: 'Active Firms',
                        font: { size: 16, weight: 'bold' },
                    },
                },
            },
            plugins: [
                {
                    id: 'centerText',
                    beforeDraw: (chart) => {
                        const {
                            ctx,
                            chartArea: { width, height },
                        } = chart;
                        ctx.save();
                        ctx.font = 'bold 20px sans-serif';
                        ctx.fillStyle = '#6a737b';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(value + '%', width / 2, height / 2);
                    },
                },
            ],
        });
    }

    // Revenue Chart (Bar)
    const revenueData = parseChartData('revenueChartData');
    const revenueCanvas = document.getElementById('admin-storage-plan-page-metric-1');

    if (revenueData && revenueCanvas) {
        const labels = Object.keys(revenueData).filter((key) => key !== '_total');
        const revenues = labels.map((label) => revenueData[label].revenue);

        new Chart(revenueCanvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Revenue $',
                        data: revenues,
                        backgroundColor: [primaryColor, secondaryColor, accentColor, warningColor, successColor],
                        borderWidth: 1,
                    },
                ],
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: `Total Revenue: $${revenueData._total.toLocaleString()}`,
                        font: { size: 16 },
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const label = context.label || '';
                                const revenue = revenueData[label].revenue;
                                const percent = revenueData[label].percentage;
                                return `${label}: $${revenue.toFixed(2)} (${percent}%)`;
                            },
                        },
                    },
                    legend: { position: 'bottom' },
                    datalabels: {
                        color: '#fff', // ← make numbers white
                        anchor: 'end', // optional: position label at end of bar
                        align: 'start', // optional: moves label inside bar
                        font: {
                            weight: 'bold',
                        },
                        formatter: (value) => value.toFixed(1),
                    },
                },
                scales: {
                    y: { beginAtZero: true },
                },
            },
        });
    }

    // Storage Usage Chart (Doughnut)
    const usageData = parseChartData('usageChartData');
    const usageCanvas = document.getElementById('admin-storage-plan-page-metric-2');

    if (usageData && usageCanvas) {
        const labels = Object.keys(usageData);
        const values = Object.values(usageData);

        new Chart(usageCanvas, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [
                    {
                        data: values,
                        backgroundColor: [primaryColor, secondaryColor, accentColor, warningColor, successColor],
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold' },
                        formatter: (value) => `${value.toFixed(1)}%`,
                    },
                    title: {
                        display: true,
                        text: 'Storage Plan Usage',
                        font: { size: 16, weight: 'bold' },
                    },
                    legend: {
                        position: 'right',
                        labels: { boxWidth: 20, padding: 15 },
                        align: 'center',
                    },
                },
            },
        });
    }

    // Audit Log Chart (Doughnut)
    const usageDataAuditLog = parseChartData('audit-log-severity-data');
    const usageCanvasAuditLog = document.getElementById('admin-audit-log-view-metric-1');

    if (usageDataAuditLog && usageCanvasAuditLog) {
        const labels = usageDataAuditLog.labels; // ["info","warning","error"]
        const counts = usageDataAuditLog.counts; // [101, 0, 10]
        const percentages = usageDataAuditLog.percentages; // [90.99, 0, 9.01]
        const window = usageDataAuditLog.window;

        new Chart(usageCanvasAuditLog, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [
                    {
                        data: percentages,
                        backgroundColor: [successColor, warningColor, accentColor],
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold' },
                        formatter: (value) => `${value.toFixed(1)}%`,
                    },
                    title: {
                        display: true,
                        text: 'Audit Log Metrics: ' + prettyDate(window.end.date) + ' - ' + prettyDate(window.start.date),
                        font: { size: 16, weight: 'bold' },
                    },
                    legend: {
                        position: 'right',
                        labels: { boxWidth: 20, padding: 15 },
                        align: 'center',
                    },
                },
            },
        });
    }
});
