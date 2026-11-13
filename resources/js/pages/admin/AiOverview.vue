<template>
    <div class="ai-page">
        <AiPeriodBar
            v-model:period="period" v-model:start="start" v-model:end="end"
            :period-label="periodLabel" :generated-at="data.analysis_generated_at"
            @refresh="load"
        />

        <div v-if="error" class="alert alert-danger d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle"></i>
            <span>{{ error }}</span>
            <button class="btn btn-sm btn-outline-danger ms-auto" type="button" @click="load">Retry</button>
        </div>

        <div v-if="loading && !data.kpis" class="text-center py-5 text-secondary"><span class="spinner-border spinner-border-sm me-2"></span>Calculating business intelligence…</div>

        <template v-else>
            <section class="card ai-summary-card mb-4">
                <div class="card-body d-flex gap-3 align-items-start">
                    <span class="ai-summary-icon"><i class="bi bi-stars"></i></span>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h3 class="card-title mb-0">AI business summary</h3>
                            <span class="badge" :class="data.summary_source === 'ai' ? 'text-bg-primary' : 'text-bg-secondary'">
                                {{ data.summary_source === 'ai' ? 'AI interpreted' : 'Computed briefing' }}
                            </span>
                            <small class="text-secondary ms-auto">Generated {{ formatDateTime(data.analysis_generated_at) }}</small>
                        </div>
                        <p class="mb-2 ai-summary-text">{{ data.summary }}</p>
                        <div class="d-flex flex-wrap gap-2">
                            <router-link to="/admin/ai-business-chat" class="btn btn-sm btn-primary"><i class="bi bi-chat-dots me-1"></i>Ask the AI assistant</router-link>
                            <router-link to="/admin/ai-recommendations" class="btn btn-sm btn-outline-primary">View recommendations</router-link>
                            <router-link to="/admin/ai-reports" class="btn btn-sm btn-outline-secondary">Generate a report</router-link>
                        </div>
                    </div>
                </div>
            </section>

            <div class="row g-3 mb-2">
                <div v-for="kpi in data.kpis" :key="kpi.label" class="col-xl-3 col-md-4 col-sm-6">
                    <AiKpiCard
                        :label="kpi.label"
                        :value="kpi.value"
                        :previous="kpi.previous"
                        :delta="kpi.delta"
                        :delta-percent="kpi.delta_percent"
                        :unit="kpi.unit"
                        :note="kpi.explanation"
                    />
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <section class="card h-100">
                        <div class="card-header"><h3 class="card-title mb-0">Revenue over time</h3></div>
                        <div class="card-body ai-chart-body"><canvas ref="revenueCanvas" role="img" aria-label="Revenue over time"></canvas></div>
                    </section>
                </div>
                <div class="col-lg-4">
                    <section class="card h-100">
                        <div class="card-header"><h3 class="card-title mb-0">Category performance</h3></div>
                        <div class="card-body ai-chart-body"><canvas ref="categoryCanvas" role="img" aria-label="Revenue by category"></canvas></div>
                    </section>
                </div>
                <div class="col-lg-6">
                    <section class="card">
                        <div class="card-header"><h3 class="card-title mb-0">Orders over time</h3></div>
                        <div class="card-body ai-chart-body"><canvas ref="ordersCanvas" role="img" aria-label="Orders over time"></canvas></div>
                    </section>
                </div>
                <div class="col-lg-6">
                    <section class="card">
                        <div class="card-header"><h3 class="card-title mb-0">Gross profit over time</h3></div>
                        <div class="card-body ai-chart-body"><canvas ref="profitCanvas" role="img" aria-label="Gross profit over time"></canvas></div>
                    </section>
                </div>
                <div class="col-lg-5">
                    <section class="card">
                        <div class="card-header"><h3 class="card-title mb-0">Product sales distribution</h3></div>
                        <div class="card-body ai-chart-body"><canvas ref="productCanvas" role="img" aria-label="Product sales distribution"></canvas></div>
                    </section>
                </div>
                <div class="col-lg-7">
                    <section class="card">
                        <div class="card-header"><h3 class="card-title mb-0">Period comparison</h3></div>
                        <div class="card-body ai-chart-body"><canvas ref="comparisonCanvas" role="img" aria-label="Current vs previous period revenue"></canvas></div>
                    </section>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import Chart from 'chart.js/auto';
import AiPeriodBar from './AiPeriodBar.vue';
import AiKpiCard from './AiKpiCard.vue';
import { formatDate, formatDateTime, money, number, periodQuery } from './ai.js';

const period = ref('30d');
const start = ref('');
const end = ref('');
const loading = ref(true);
const error = ref('');
const data = ref({});

const revenueCanvas = ref(null);
const categoryCanvas = ref(null);
const ordersCanvas = ref(null);
const profitCanvas = ref(null);
const productCanvas = ref(null);
const comparisonCanvas = ref(null);

const charts = [];
let requestId = 0;

const periodLabel = computed(() => {
    if (!data.value.period) return '';
    const { start: s, end: e } = data.value.period;
    return `${formatDate(s)} → ${formatDate(e)}`;
});

function chartColors() {
    const styles = getComputedStyle(document.documentElement);
    return {
        text: styles.getPropertyValue('--admin-muted').trim() || '#6b7280',
        grid: styles.getPropertyValue('--admin-border').trim() || '#e5e7eb',
        primary: styles.getPropertyValue('--admin-primary').trim() || '#009e5a',
        secondary: styles.getPropertyValue('--admin-secondary').trim() || '#0b4f8a',
    };
}

function destroyCharts() {
    while (charts.length) {
        charts.pop()?.destroy();
    }
}

function renderCharts() {
    destroyCharts();
    const chartsData = data.value.charts;
    if (!chartsData) return;

    const colors = chartColors();
    const moneyAxis = (value) => number(value);
    const baseLine = (labels, series, label, color, fill = false) => ({
        type: 'line',
        data: {
            labels,
            datasets: [{ label, data: series, borderColor: color, backgroundColor: fill ? `${color}1a` : color, fill, tension: 0.3, pointRadius: 0 }],
        },
        options: {
            responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' },
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => `${label}: ${money(ctx.parsed.y)}` } } },
            scales: {
                x: { grid: { display: false }, ticks: { color: colors.text, maxTicksLimit: 8 } },
                y: { beginAtZero: true, grid: { color: colors.grid }, ticks: { color: colors.text, callback: moneyAxis } },
            },
        },
    });

    if (revenueCanvas.value) {
        charts.push(new Chart(revenueCanvas.value, baseLine(chartsData.revenue_over_time.labels, chartsData.revenue_over_time.series, 'Revenue', colors.primary, true)));
    }

    if (ordersCanvas.value) {
        charts.push(new Chart(ordersCanvas.value, {
            type: 'bar',
            data: { labels: chartsData.orders_over_time.labels, datasets: [{ label: 'Orders', data: chartsData.orders_over_time.series, backgroundColor: colors.secondary, borderRadius: 4 }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: colors.text, maxTicksLimit: 8 } },
                    y: { beginAtZero: true, grid: { color: colors.grid }, ticks: { color: colors.text, precision: 0 } },
                },
            },
        }));
    }

    if (profitCanvas.value) {
        charts.push(new Chart(profitCanvas.value, baseLine(chartsData.profit_over_time.labels, chartsData.profit_over_time.series, 'Gross profit', colors.secondary, true)));
    }

    if (categoryCanvas.value) {
        charts.push(new Chart(categoryCanvas.value, {
            type: 'bar',
            data: { labels: chartsData.category_performance.labels, datasets: [{ label: 'Revenue', data: chartsData.category_performance.series, backgroundColor: colors.primary, borderRadius: 4 }] },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => money(ctx.parsed.x) } } },
                scales: {
                    x: { beginAtZero: true, grid: { color: colors.grid }, ticks: { color: colors.text, callback: moneyAxis } },
                    y: { grid: { display: false }, ticks: { color: colors.text } },
                },
            },
        }));
    }

    if (productCanvas.value) {
        const palette = ['#009e5a', '#0b4f8a', '#c27803', '#b42318', '#6b7280', '#8b5cf6', '#0e7490', '#9333ea'];
        charts.push(new Chart(productCanvas.value, {
            type: 'doughnut',
            data: {
                labels: chartsData.product_sales.labels,
                datasets: [{ data: chartsData.product_sales.series, backgroundColor: palette, borderWidth: 0 }],
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '58%',
                plugins: { legend: { position: 'bottom', labels: { color: colors.text, boxWidth: 8, usePointStyle: true } }, tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${money(ctx.parsed)}` } } },
            },
        }));
    }

    if (comparisonCanvas.value) {
        const rows = chartsData.period_comparison || [];
        charts.push(new Chart(comparisonCanvas.value, {
            type: 'bar',
            data: {
                labels: rows.map((row) => row.period),
                datasets: [
                    { label: 'Current period', data: rows.map((row) => row.current), backgroundColor: colors.primary, borderRadius: 3 },
                    { label: 'Previous period', data: rows.map((row) => row.previous), backgroundColor: '#cbd5e1', borderRadius: 3 },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: colors.text, usePointStyle: true, boxWidth: 8 } }, tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${money(ctx.parsed.y)}` } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: colors.text, maxTicksLimit: 10 } },
                    y: { beginAtZero: true, grid: { color: colors.grid }, ticks: { color: colors.text, callback: moneyAxis } },
                },
            },
        }));
    }
}

async function load() {
    const id = ++requestId;
    loading.value = true;
    error.value = '';
    try {
        const { data: response } = await axios.get(`/api/ai/overview?${periodQuery(period.value, start.value, end.value)}`);
        if (id !== requestId) return;
        data.value = response || {};
        await nextTick();
        renderCharts();
    } catch (e) {
        if (id !== requestId) return;
        error.value = e?.response?.data?.message || 'Could not load the AI overview. Please try again.';
    } finally {
        if (id === requestId) loading.value = false;
    }
}

onMounted(load);
watch([period, start, end], load);
onBeforeUnmount(destroyCharts);
</script>

<style scoped>
.ai-page { max-width: 1600px; }
.ai-summary-card { border-left: 3px solid var(--admin-primary); }
.ai-summary-icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 12px; background: color-mix(in srgb, var(--admin-primary) 10%, var(--admin-surface)); color: var(--admin-primary); font-size: 1.1rem; flex-shrink: 0; }
.ai-summary-text { font-size: .95rem; line-height: 1.6; white-space: pre-wrap; }
.ai-chart-body { height: 300px; position: relative; }
</style>
