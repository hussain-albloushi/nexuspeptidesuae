<template>
    <div class="ai-page">
        <AiPeriodBar
            v-model:period="period" v-model:start="start" v-model:end="end"
            :period-label="periodLabel"
            @refresh="load"
        />

        <div v-if="error" class="alert alert-danger d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle"></i>
            <span>{{ error }}</span>
            <button class="btn btn-sm btn-outline-danger ms-auto" type="button" @click="load">Retry</button>
        </div>

        <div v-if="loading && !data.revenue" class="text-center py-5 text-secondary">
            <span class="spinner-border spinner-border-sm me-2"></span>Analysing business performance…
        </div>

        <template v-else-if="data.revenue">
            <section class="card ai-summary-card mb-4">
                <div class="card-body d-flex gap-3 align-items-start">
                    <span class="ai-summary-icon"><i class="bi bi-stars"></i></span>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h3 class="card-title mb-0">AI business analysis</h3>
                            <span class="badge" :class="data.summary_source === 'ai' ? 'text-bg-primary' : 'text-bg-secondary'">
                                {{ data.summary_source === 'ai' ? 'AI interpreted' : 'Computed briefing' }}
                            </span>
                            <small class="text-secondary ms-auto">{{ periodLabel }}</small>
                        </div>
                        <p class="mb-0 ai-summary-text">{{ data.summary }}</p>
                    </div>
                </div>
            </section>

            <div class="row g-3 mb-4">
                <div v-for="kpi in kpis" :key="kpi.label" class="col-xl-2 col-lg-4 col-sm-6">
                    <AiKpiCard v-bind="kpi" :decimals="kpi.unit === '%' ? 1 : 2" />
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-7">
                    <section class="card h-100">
                        <div class="card-header"><h3 class="card-title mb-0">Revenue by category</h3></div>
                        <div class="card-body ai-chart-body"><canvas ref="categoryCanvas" role="img" aria-label="Revenue by category"></canvas></div>
                    </section>
                </div>
                <div class="col-lg-5">
                    <section class="card h-100">
                        <div class="card-header"><h3 class="card-title mb-0">Payment method mix</h3></div>
                        <div class="card-body ai-chart-body"><canvas ref="paymentCanvas" role="img" aria-label="Revenue by payment method"></canvas></div>
                    </section>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-7">
                    <section class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">Top products</h3>
                            <small class="text-secondary">By revenue</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th><th class="text-end">Margin</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in data.top_products" :key="row.product">
                                        <td>{{ row.product }}</td>
                                        <td class="text-end">{{ number(row.units) }}</td>
                                        <td class="text-end">{{ money(row.revenue) }}</td>
                                        <td class="text-end">
                                            <span class="badge" :class="marginBadge(row.margin_percent)">{{ row.margin_percent === null ? 'n/a' : percent(row.margin_percent) }}</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!data.top_products?.length"><td colspan="4" class="text-center text-secondary py-4">No product sales in this period.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
                <div class="col-lg-5">
                    <section class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">Top customers</h3>
                            <small class="text-secondary">By revenue</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr><th>Customer</th><th class="text-end">Orders</th><th class="text-end">Revenue</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in data.top_customers" :key="row.email">
                                        <td><div class="fw-semibold">{{ row.name }}</div><small class="text-secondary">{{ row.email }}</small></td>
                                        <td class="text-end">{{ number(row.orders) }}</td>
                                        <td class="text-end">{{ money(row.revenue) }}</td>
                                    </tr>
                                    <tr v-if="!data.top_customers?.length"><td colspan="3" class="text-center text-secondary py-4">No customer orders in this period.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-4">
                    <section class="card h-100">
                        <div class="card-header"><h3 class="card-title mb-0">Orders by status</h3></div>
                        <div class="card-body">
                            <ul class="ai-stat-list">
                                <li v-for="(count, status) in data.orders_by_status" :key="status">
                                    <span class="text-capitalize">{{ titleCase(status) }}</span>
                                    <strong>{{ number(count) }}</strong>
                                </li>
                                <li v-if="!Object.keys(data.orders_by_status || {}).length" class="text-secondary">No orders in this period.</li>
                            </ul>
                        </div>
                    </section>
                </div>
                <div class="col-lg-4">
                    <section class="card h-100">
                        <div class="card-header"><h3 class="card-title mb-0">Discount usage</h3></div>
                        <div class="card-body">
                            <div class="ai-stat-hero">{{ money(data.discounts?.total) }}</div>
                            <p class="text-secondary small mb-3">Total discounts granted</p>
                            <ul class="ai-stat-list">
                                <li><span>Discounted orders</span><strong>{{ number(data.discounts?.discounted_orders) }}</strong></li>
                                <li><span>Orders in period</span><strong>{{ number(data.discounts?.orders) }}</strong></li>
                                <li><span>Repeat purchase rate</span><strong>{{ percent(data.repeat_rate) }}</strong></li>
                            </ul>
                        </div>
                    </section>
                </div>
                <div class="col-lg-4">
                    <section class="card h-100">
                        <div class="card-header"><h3 class="card-title mb-0">Top locations</h3></div>
                        <div class="card-body">
                            <ul class="ai-stat-list">
                                <li v-for="row in data.geographic" :key="row.location">
                                    <span>{{ row.location }}</span>
                                    <strong>{{ number(row.orders) }} orders</strong>
                                </li>
                                <li v-if="!data.geographic?.length" class="text-secondary">No shipping location data recorded for this period.</li>
                            </ul>
                        </div>
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
import {
    CHART_PALETTE, chartTheme, destroyCharts, formatDate, money, number, percent,
    periodQuery, titleCase,
} from './ai.js';

const period = ref('30d');
const start = ref('');
const end = ref('');
const loading = ref(true);
const error = ref('');
const data = ref({});

const categoryCanvas = ref(null);
const paymentCanvas = ref(null);
const charts = [];
let requestId = 0;

const periodLabel = computed(() => {
    if (!data.value.period) return '';
    return `${formatDate(data.value.period.start)} → ${formatDate(data.value.period.end)}`;
});

function comparison(value, previous, unit) {
    const delta = previous === null || previous === undefined ? null : Number(value) - Number(previous);
    const deltaPercent = previous !== null && previous !== undefined && Math.abs(Number(previous)) > 0.000001
        ? (delta / Math.abs(Number(previous))) * 100
        : null;

    return { value, previous: previous ?? null, delta, deltaPercent, unit };
}

const kpis = computed(() => {
    const d = data.value;
    if (!d.revenue) return [];
    const customers = d.customers || {};

    return [
        { label: 'Revenue', ...comparison(d.revenue.value, d.revenue.previous, 'AED'), note: 'Booked revenue from non-cancelled orders.' },
        { label: 'Orders', ...comparison(d.orders.value, d.orders.previous, ''), note: 'All orders placed, including cancelled.' },
        { label: 'Avg Order Value', ...comparison(d.aov.value, d.aov.previous, 'AED'), note: 'Average value per non-cancelled order.' },
        { label: 'Active Customers', ...comparison(d.active_customers.value, d.active_customers.previous, ''), note: 'Customers with at least one order in the period.' },
        { label: 'Repeat Rate', ...comparison(customers.repeat_rate ?? 0, customers.repeat_rate_previous ?? null, '%'), note: 'Share of active customers ordering more than once.' },
        { label: 'New Customers', ...comparison(customers.new_customers ?? 0, customers.new_customers_previous ?? null, ''), note: 'First-time buyers in the period.' },
    ];
});

function marginBadge(margin) {
    if (margin === null || margin === undefined) return 'text-bg-secondary';
    if (margin >= 30) return 'text-bg-success';
    if (margin >= 15) return 'text-bg-info';
    if (margin >= 0) return 'text-bg-warning';

    return 'text-bg-danger';
}

function renderCharts() {
    destroyCharts(charts);
    const d = data.value;
    if (!d.revenue) return;
    const theme = chartTheme();

    if (categoryCanvas.value && d.top_categories?.length) {
        charts.push(new Chart(categoryCanvas.value, {
            type: 'bar',
            data: {
                labels: d.top_categories.map((row) => row.category),
                datasets: [{ label: 'Revenue', data: d.top_categories.map((row) => row.revenue), backgroundColor: theme.primary, borderRadius: 4 }],
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => money(context.parsed.x) } } },
                scales: {
                    x: { beginAtZero: true, grid: { color: theme.grid }, ticks: { color: theme.text, callback: (value) => number(value) } },
                    y: { grid: { display: false }, ticks: { color: theme.text } },
                },
            },
        }));
    }

    if (paymentCanvas.value && d.payment_methods?.length) {
        charts.push(new Chart(paymentCanvas.value, {
            type: 'doughnut',
            data: {
                labels: d.payment_methods.map((row) => titleCase(row.method)),
                datasets: [{ data: d.payment_methods.map((row) => row.revenue), backgroundColor: CHART_PALETTE, borderWidth: 0 }],
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '58%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: theme.text, boxWidth: 8, usePointStyle: true } },
                    tooltip: { callbacks: { label: (context) => `${context.label}: ${money(context.parsed)}` } },
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
        const { data: response } = await axios.get(`/api/ai/business-analytics?${periodQuery(period.value, start.value, end.value)}`);
        if (id !== requestId) return;
        data.value = response || {};
        await nextTick();
        renderCharts();
    } catch (e) {
        if (id !== requestId) return;
        error.value = e?.response?.data?.message || 'Could not load business analytics. Please try again.';
    } finally {
        if (id === requestId) loading.value = false;
    }
}

onMounted(load);
watch([period, start, end], load);
onBeforeUnmount(() => destroyCharts(charts));
</script>

<style scoped>
.ai-page { max-width: 1600px; }
.ai-summary-card { border-left: 3px solid var(--admin-primary); }
.ai-summary-icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 12px; background: color-mix(in srgb, var(--admin-primary) 10%, var(--admin-surface)); color: var(--admin-primary); font-size: 1.1rem; flex-shrink: 0; }
.ai-summary-text { font-size: .95rem; line-height: 1.6; white-space: pre-wrap; }
.ai-chart-body { height: 300px; position: relative; }
.ai-stat-list { list-style: none; margin: 0; padding: 0; }
.ai-stat-list li { display: flex; justify-content: space-between; gap: 1rem; padding: .55rem 0; border-bottom: 1px solid var(--admin-border); font-size: .875rem; }
.ai-stat-list li:last-child { border-bottom: 0; }
.ai-stat-hero { font-size: 1.6rem; font-weight: 700; letter-spacing: -.03em; }
</style>
