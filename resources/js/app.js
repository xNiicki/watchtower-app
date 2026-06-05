import './bootstrap';
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip);

const lineCfg = (labels, datasets) => ({
    type: 'line',
    data: { labels, datasets },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#71717a', maxTicksLimit: 6 }, grid: { display: false } },
            y: { ticks: { color: '#71717a' }, grid: { color: '#27272a' }, beginAtZero: true },
        },
        elements: { point: { radius: 0 } },
    },
});

// Alpine is provided by Livewire 4. Register the component on alpine:init.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('metricsChart', (initial) => ({
        requestsChart: null,
        latencyChart: null,
        init() {
            this.render(initial);
            // Range changes push fresh data via a Livewire-dispatched browser event.
            window.addEventListener('metrics-updated', (e) => this.render(e.detail.chart));
        },
        render(data) {
            if (!data) return;
            this.requestsChart?.destroy();
            this.latencyChart?.destroy();
            this.requestsChart = new Chart(this.$refs.requests, lineCfg(data.labels, [
                { data: data.requests, borderColor: '#34d399', backgroundColor: 'rgba(52,211,153,.18)', fill: true, borderWidth: 2, tension: 0.3 },
            ]));
            this.latencyChart = new Chart(this.$refs.latency, lineCfg(data.labels, [
                { data: data.latencyAvg, borderColor: '#60a5fa', borderWidth: 2, tension: 0.3 },
                { data: data.latencyMax, borderColor: '#a78bfa', borderWidth: 1, borderDash: [4, 3], tension: 0.3 },
            ]));
        },
    }));
});
