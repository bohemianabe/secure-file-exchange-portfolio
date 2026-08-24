// assets/js/charts.js
import Chart from 'chart.js/auto';
import ChartDataLabels from 'chartjs-plugin-datalabels';

const chartCanvas = document.getElementById('storage-chart');

if (chartCanvas) {
    const usedPercent = Number(chartCanvas.dataset.usedPercent);
    // const usedPercent = 90.6;
    const segments = 40;
    const filledSegments = Math.round((usedPercent / 100) * segments);

    const data = Array.from({ length: segments }, (_, i) => 1);

    let usedColor = '#3f73d8'; // Blue (default)

    if (usedPercent >= 90) {
        usedColor = '#dc3545'; // Red
    } else if (usedPercent >= 70) {
        usedColor = '#ffc107'; // Yellow (Bootstrap warning)
    }

    // Build your Chart.js graph...
    new Chart(document.getElementById('storage-chart'), {
        type: 'doughnut',
        data: {
            datasets: [
                {
                    data: data,
                    backgroundColor: data.map((_, i) => (i < filledSegments ? usedColor : '#f1f3f6')),
                    borderColor: '#fff',
                    borderWidth: 4,
                    spacing: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            rotation: -135,
            circumference: 270,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false },
            },
        },
    });
}
