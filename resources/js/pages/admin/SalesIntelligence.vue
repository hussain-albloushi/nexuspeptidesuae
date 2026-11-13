<template>
    <div class="ai-page">
        <AiPeriodBar
            v-model:period="period" v-model:start="start" v-model:end="end"
            :period-label="periodLabel"
            @refresh="loadAll"
        />

        <div v-if="error" class="alert alert-danger d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle"></i>
            <span>{{ error }}</span>
            <button class="btn btn-sm btn-outline-danger ms-auto" type="button" @click="loadAll">Retry</button>
        </div>

        <div v-if="loading && !trendData.series" class="text-center py-5 text-secondary">
            <span class="spinner-border spinner-border-sm me-2"></span>Detecting sales trends…
        </div>

        <template v-else>
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-lg-4 col-sm-6">
                    <AiKpiCard label="Revenue change" :value="trendData.trend?.revenue_delta_percent" unit="%" :decimals="1" note="Versus the prior comparable period." />
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6">
                    <div class="card ai-kpi-card h-100"><div class="card-body">
                        <span class="ai-kpi-label">Trend direction</span>
                        <div class="ai-kpi-value text-capitalize">{{ trendData.trend?.direction || '—' }}</div>
                        <p class="ai-kpi-note small text-secondary mb-0">Slope {{ number(trendData.trend?.slope, 2) }} AED/day · R² {{ number(trendData.trend?.r2, 2) }}</p>
                    </div></div>
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6">
                    <div class="card ai-kpi-card h-100"><div class="card-body">
                        <span class="ai-kpi-label">Anomalies</span>
                        <div class="ai-kpi-value">{{ number(trendData.anomalies?.length || 0) }}</div>
                        <p class="ai-kpi-note small text-secondary mb-0">{{ trendData.anomalies_note || 'Statistical outliers beyond 2σ.' }}</p>
                    </div></div>
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6">
                    <div class="card ai-kpi-card h-100"><div class="card-body">
                        <span class="ai-kpi-label">Next 7 days</span>
                        <div class="ai-kpi-value">{{ forecastData.forecast?.reliable ? money(forecastData.forecast.next_7_days) : '—' }}</div>
                        <p class="ai-kpi-note small text-secondary mb-0">{{ forecastData.forecast?.reliable ? 'Statistical estimate, not a promise.' : 'Not enough history to forecast.' }}</p>
                    </div></div>
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6">
                    <div class="card ai-kpi-card h-100"><div class="card-body">
                        <span class="ai-kpi-label">Next 30 days</span>
                        <div class="ai-kpi-value">{{ forecastData.forecast?.reliable ? money(forecastData.forecast.next_30_days) : '—' }}</div>
                        <p class="ai-kpi-note small text-secondary mb-0">Moving-average estimate from 120 days of history.</p>
                    </div></div>
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6">
                    <div class="card ai-kpi-card h-100"><div class="card-body">
                        <span class="ai-kpi-label">Strongest weekday</span>
                        <div class="ai-kpi-value">{{ trendData.seasonality?.strongest_day?.weekday || '—' }}</div>
                        <p class="ai-kpi-note small text-secondary mb-0">{{ trendData.seasonality?.strongest_day ? money(trendData.seasonality.strongest_day.revenue) + ' revenue' : 'No weekday data yet.' }}</p>
                    </div></div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-8">
                    <section class="card h-100">
                        <div class="card-header d-flex flex-wrap align-items-center gap-2">
                            <h3 class="card-title mb-0">Revenue trend</h3>
                            <select v-model="granularity" class="form-select form-select-sm ms-auto" style="max-width: 160px;" aria-label="Trend granularity">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>
                        <div class="card-body ai-chart-body"><canvas ref="trendCanvas" role="img" aria-label="Revenue trend versus previous period"></canvas></div>
                    </section>
                </div>
                <div class="col-lg-4">
                    <section class="card h-100">
                        <div class="card-header"><h3 class="card-title mb-0">Revenue by weekday</h3></div>
                        <div class="card-body ai-chart-body"><canvas ref="weekdayCanvas" role="img" aria-label="Revenue by weekday"></canvas></div>
                    </section>
                </div>
            </div>

            <div v-if="trendData.anomalies?.length" class="alert alert-warning d-flex gap-2">
                <i class="bi bi-activity"></i>
                <div>
                    <strong>Unusual movement detected</strong>
                    <ul class="mb-0 small">
                        <li v-for="anomaly in trendData.anomalies" :key="anomaly.index">
                            {{ anomaly.note }} Latest window {{ money(anomaly.value) }} · z-score {{ anomaly.z_score }}
                        </li>
                    </ul>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-8">
                    <section class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">Sales breakdown</h3>
                            <small class="text-secondary">Current period</small>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive mb-3">
                                <table class="table table-sm align-middle mb-0">
                                    <thead><tr><th>Category</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead>
                                    <tbody>
                                        <tr v-for="row in breakdown.by_category" :key="row.category">
                                            <td>{{ row.category }}</td><td class="text-end">{{ number(row.units) }}</td><td class="text-end">{{ money(row.revenue) }}</td>
                                        </tr>
                                        <tr v-if="!breakdown.by_category?.length"><td colspan="3" class="text-center text-secondary py-3">No category sales in this period.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th><th class="text-end">Margin</th></tr></thead>
                                    <tbody>
                                        <tr v-for="row in breakdown.by_product" :key="row.product">
                                            <td>{{ row.product }}</td><td class="text-end">{{ number(row.units) }}</td><td class="text-end">{{ money(row.revenue) }}</td><td class="text-end">{{ row.margin_percent === null ? '—' : percent(row.margin_percent) }}</td>
                                        </tr>
                                        <tr v-if="!breakdown.by_product?.length"><td colspan="4" class="text-center text-secondary py-3">No product sales in this period.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>
                <div class="col-lg-4">
                    <section class="card h-100">
                        <div class="card-header"><h3 class="card-title mb-0">Orders &amp; discounts</h3></div>
                        <div class="card-body">
                            <ul class="ai-stat-list mb-3">
                                <li v-for="(count, status) in breakdown.by_status" :key="status"><span class="text-capitalize">{{ titleCase(status) }}</span><strong>{{ number(count) }}</strong></li>
                                <li v-if="!Object.keys(breakdown.by_status || {}).length" class="text-secondary">No orders in this period.</li>
                            </ul>
                            <ul class="ai-stat-list">
                                <li><span>Discounted orders</span><strong>{{ number(breakdown.discount_usage?.discounted_orders) }}</strong></li>
                                <li><span>Total discounts</span><strong>{{ money(breakdown.discount_usage?.discounts) }}</strong></li>
                                <li><span>Orders in period</span><strong>{{ number(breakdown.discount_usage?.orders) }}</strong></li>
                            </ul>
                        </div>
                    </section>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-6">
                    <section class="card h-100">
                        <div class="card-header"><h3 class="card-title mb-0">Payment methods</h3></div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Method</th><th class="text-end">Orders</th><th class="text-end">Revenue</th><th class="text-end">Discounts</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in breakdown.by_payment_method" :key="row.method">
                                        <td class="text-capitalize">{{ titleCase(row.method) }}</td><td class="text-end">{{ number(row.orders) }}</td><td class="text-end">{{ money(row.revenue) }}</td><td class="text-end">{{ money(row.discounts) }}</td>
                                    </tr>
                                    <tr v-if="!breakdown.by_payment_method?.length"><td colspan="4" class="text-center text-secondary py-3">No payment data in this period.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
                <div class="col-lg-6">
                    <section class="card h-100">
                        <div class="card-header"><h3 class="card-title mb-0">Top customers</h3></div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Customer</th><th class="text-end">Orders</th><th class="text-end">Revenue</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in breakdown.by_customer" :key="row.email">
                                        <td><div>{{ row.customer }}</div><small class="text-secondary">{{ row.email }}</small></td><td class="text-end">{{ number(row.orders) }}</td><td class="text-end">{{ money(row.revenue) }}</td>
                                    </tr>
                                    <tr v-if="!breakdown.by_customer?.length"><td colspan="3" class="text-center text-secondary py-3">No customer orders in this period.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>

            <section class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Demand forecast</h3></div>
                <div class="card-body">
                    <div v-if="!forecastData.forecast?.reliable" class="alert alert-secondary mb-0">
                        {{ forecastData.forecast?.note || 'Not enough historical data to forecast.' }}
                    </div>
                    <template v-else>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="ai-detail-stat"><span>Next 7 days</span><strong>{{ money(forecastData.forecast.next_7_days) }}</strong><small class="text-secondary">± {{ money(forecastData.forecast.confidence_half_width_value) }}</small></div></div>
                            <div class="col-md-4"><div class="ai-detail-stat"><span>Next 30 days</span><strong>{{ money(forecastData.forecast.next_30_days) }}</strong><small class="text-secondary">± {{ money(forecastData.forecast.confidence_half_width_thirty_days) }}</small></div></div>
                            <div class="col-md-4"><div class="ai-detail-stat"><span>History used</span><strong>{{ number(forecastData.history_days) }} days</strong><small class="text-secondary">{{ titleCase(forecastData.forecast.method) }}</small></div></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Product</th><th class="text-end">Units in history</th><th class="text-end">Projected 14 days</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in forecastData.product_demand" :key="row.product">
                                        <td>{{ row.product }}</td><td class="text-end">{{ number(row.units_in_history) }}</td><td class="text-end">{{ number(row.projected_14d_units, 1) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </section>

            <section class="card">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h3 class="card-title mb-0">Compare two periods</h3>
                    <small class="text-secondary ms-auto">Both ranges are inclusive.</small>
                </div>
                <div class="card-body">
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-3"><label class="form-label small text-secondary" for="compare-a-start">Period A from</label><input id="compare-a-start" v-model="compareForm.start_a" type="date" class="form-control form-control-sm" /></div>
                        <div class="col-md-3"><label class="form-label small text-secondary" for="compare-a-end">Period A to</label><input id="compare-a-end" v-model="compareForm.end_a" type="date" class="form-control form-control-sm" /></div>
                        <div class="col-md-3"><label class="form-label small text-secondary" for="compare-b-start">Period B from</label><input id="compare-b-start" v-model="compareForm.start_b" type="date" class="form-control form-control-sm" /></div>
                        <div class="col-md-3"><label class="form-label small text-secondary" for="compare-b-end">Period B to</label><input id="compare-b-end" v-model="compareForm.end_b" type="date" class="form-control form-control-sm" /></div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" :disabled="!canCompare || comparing" @click="comparePeriods">
                        <span v-if="comparing" class="spinner-border spinner-border-sm me-1"></span><i v-else class="bi bi-arrow-left-right me-1"></i>Compare periods
                    </button>

                    <div v-if="comparison" class="mt-4">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><div class="ai-detail-stat"><span>Revenue change</span><strong>{{ money(comparison.revenue_delta) }}</strong><small :class="trendMeta(comparison.revenue_delta_percent).class">{{ signedPercent(comparison.revenue_delta_percent) }}</small></div></div>
                            <div class="col-md-3"><div class="ai-detail-stat"><span>Orders change</span><strong>{{ signedNumber(comparison.orders_delta) }}</strong><small class="text-secondary">Period A minus B</small></div></div>
                            <div class="col-md-3"><div class="ai-detail-stat"><span>Units change</span><strong>{{ signedNumber(comparison.units_delta) }}</strong><small class="text-secondary">Period A minus B</small></div></div>
                            <div class="col-md-3"><div class="ai-detail-stat"><span>AOV change</span><strong>{{ signedMoney(comparison.aov_delta) }}</strong><small class="text-secondary">Period A minus B</small></div></div>
                        </div>
                        <div class="row g-3">
                            <div v-for="group in ['top_gainers', 'top_losers']" :key="group" class="col-md-6">
                                <h4 class="h6 text-uppercase text-secondary">{{ group === 'top_gainers' ? 'Top gainers' : 'Top losers' }}</h4>
                                <ul class="ai-stat-list">
                                    <li v-for="row in comparison.contributors?.[group] || []" :key="row.product">
                                        <span>{{ row.product }}</span>
                                        <strong :class="row.delta >= 0 ? 'text-success' : 'text-danger'">{{ signedMoney(row.delta) }}</strong>
                                    </li>
                                    <li v-if="!comparison.contributors?.[group]?.length" class="text-secondary">No movement.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import Chart from 'chart.js/auto';
import AiPeriodBar from './AiPeriodBar.vue';
import AiKpiCard from './AiKpiCard.vue';
import {
    CHART_PALETTE, chartTheme, destroyCharts, formatDate, money, number, percent,
    periodQuery, signedMoney, signedNumber, signedPercent, titleCase, trendMeta,
} from './ai.js';

const period = ref('30d');
const start = ref('');
const end = ref('');
const granularity = ref('daily');

const loading = ref(true);
const error = ref('');
const trendData = ref({});
const breakdown = ref({});
const forecastData = ref({});
const comparison = ref(null);
const comparing = ref(false);

const compareForm = ref({ start_a: '', end_a: '', start_b: '', end_b: '' });

const trendCanvas = ref(null);
const weekdayCanvas = ref(null);
const charts = [];
let requestId = 0;

const periodLabel = computed(() => {
    if (!trendData.value.period) return '';
    return `${formatDate(trendData.value.period.start)} → ${formatDate(trendData.value.period.end)}`;
});

const canCompare = computed(() => Object.values(compareForm.value).every(Boolean));

const weekdayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

function renderCharts() {
    destroyCharts(charts);
    const theme = chartTheme();
    const trend = trendData.value;
    if (!trend.series) return;

    if (trendCanvas.value) {
        const labels = trend.series.map((row) => row.period);
        charts.push(new Chart(trendCanvas.value, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'Current period', data: trend.series.map((row) => row.revenue), borderColor: theme.primary, backgroundColor: `${theme.primary}1a`, fill: true, tension: .3, pointRadius: 0 },
                    { label: 'Previous period', data: (trend.previous_series || []).map((row) => row.revenue), borderColor: theme.secondary, borderDash: [5, 4], tension: .3, pointRadius: 0 },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { position: 'bottom', labels: { color: theme.text, boxWidth: 8, usePointStyle: true } },
                    tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${money(context.parsed.y)}` } },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: theme.text, maxTicksLimit: 10 } },
                    y: { beginAtZero: true, grid: { color: theme.grid }, ticks: { color: theme.text, callback: (value) => number(value) } },
                },
            },
        }));
    }

    const weekdays = [...(trend.seasonality?.by_weekday || [])].sort(
        (a, b) => weekdayOrder.indexOf(a.weekday) - weekdayOrder.indexOf(b.weekday),
    );
    if (weekdayCanvas.value && weekdays.length) {
        charts.push(new Chart(weekdayCanvas.value, {
            type: 'bar',
            data: {
                labels: weekdays.map((row) => row.weekday.slice(0, 3)),
                datasets: [{ label: 'Revenue', data: weekdays.map((row) => row.revenue), backgroundColor: CHART_PALETTE, borderRadius: 4 }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => money(context.parsed.y) } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: theme.text } },
                    y: { beginAtZero: true, grid: { color: theme.grid }, ticks: { color: theme.text, callback: (value) => number(value) } },
                },
            },
        }));
    }
}

async function loadAll() {
    const id = ++requestId;
    loading.value = true;
    error.value = '';
    const query = periodQuery(period.value, start.value, end.value);

    const [trendResult, breakdownResult, forecastResult] = await Promise.allSettled([
        axios.get(`/api/ai/sales/trend?${query}&granularity=${granularity.value}`),
        axios.get(`/api/ai/sales/breakdown?${query}`),
        axios.get(`/api/ai/sales/forecast?${query}`),
    ]);

    if (id !== requestId) return;

    if (trendResult.status === 'fulfilled') {
        trendData.value = trendResult.value.data || {};
    } else {
        error.value = trendResult.reason?.response?.data?.message || 'Could not load the sales trend.';
    }
    breakdown.value = breakdownResult.status === 'fulfilled' ? (breakdownResult.value.data || {}) : {};
    forecastData.value = forecastResult.status === 'fulfilled' ? (forecastResult.value.data || {}) : {};
    loading.value = false;
    await nextTick();
    renderCharts();
}

async function loadTrend() {
    const id = ++requestId;
    error.value = '';
    try {
        const { data } = await axios.get(`/api/ai/sales/trend?${periodQuery(period.value, start.value, end.value)}&granularity=${granularity.value}`);
        if (id !== requestId) return;
        trendData.value = data || {};
        await nextTick();
        renderCharts();
    } catch (e) {
        if (id !== requestId) return;
        error.value = e?.response?.data?.message || 'Could not load the sales trend.';
    }
}

async function comparePeriods() {
    if (!canCompare.value) return;
    comparing.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/api/ai/sales/compare', { params: compareForm.value });
        comparison.value = data;
    } catch (e) {
        error.value = e?.response?.data?.message || 'Could not compare the two periods.';
    } finally {
        comparing.value = false;
    }
}

watch(granularity, loadTrend);
watch([period, start, end], loadAll);

onMounted(loadAll);
onBeforeUnmount(() => destroyCharts(charts));
</script>

<style scoped>
.ai-page { max-width: 1600px; }
.ai-kpi-card .card-body { display: flex; flex-direction: column; gap: .35rem; }
.ai-kpi-label { font-size: .72rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--admin-muted); }
.ai-kpi-value { font-size: 1.35rem; font-weight: 700; letter-spacing: -.02em; }
.ai-kpi-note { margin-top: auto !important; line-height: 1.45; }
.ai-chart-body { height: 320px; position: relative; }
.ai-stat-list { list-style: none; margin: 0; padding: 0; }
.ai-stat-list li { display: flex; justify-content: space-between; gap: 1rem; padding: .55rem 0; border-bottom: 1px solid var(--admin-border); font-size: .875rem; }
.ai-stat-list li:last-child { border-bottom: 0; }
.ai-detail-stat { display: flex; flex-direction: column; gap: .2rem; padding: .75rem; border: 1px solid var(--admin-border); border-radius: 8px; height: 100%; }
.ai-detail-stat span { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: var(--admin-muted); }
.ai-detail-stat strong { font-size: 1rem; }
</style>
