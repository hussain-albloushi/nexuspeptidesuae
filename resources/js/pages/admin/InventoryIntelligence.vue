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

        <div class="row g-3 mb-4">
            <div class="col-xl-2 col-lg-4 col-sm-6">
                <AiKpiCard label="Stock value" :value="valuation.stock_value" unit="AED" note="Valued at product cost. Missing costs are excluded, never guessed." />
            </div>
            <div class="col-xl-2 col-lg-4 col-sm-6">
                <AiKpiCard label="Units on hand" :value="valuation.units_on_hand" note="Across all products with stock tracked." />
            </div>
            <div class="col-xl-2 col-lg-4 col-sm-6">
                <AiKpiCard label="Out of stock" :value="alerts.out_of_stock?.length || 0" note="Active products at zero or negative stock." />
            </div>
            <div class="col-xl-2 col-lg-4 col-sm-6">
                <AiKpiCard label="Low stock" :value="alerts.low_stock?.length || 0" note="At or below their low-stock threshold." />
            </div>
            <div class="col-xl-2 col-lg-4 col-sm-6">
                <AiKpiCard label="Overstocked" :value="alerts.overstocked?.length || 0" note="High stock with very low sales velocity." />
            </div>
            <div class="col-xl-2 col-lg-4 col-sm-6">
                <AiKpiCard label="Reorder suggestions" :value="alerts.recommended_reorders?.length || 0" note="Advisory only — no purchase order is created automatically." />
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-4">
                <section class="card h-100 ai-alert-card ai-alert-danger">
                    <div class="card-header d-flex justify-content-between align-items-center"><h3 class="card-title mb-0">Out of stock</h3><span class="badge text-bg-danger">{{ alerts.out_of_stock?.length || 0 }}</span></div>
                    <div class="card-body">
                        <ul class="ai-stat-list">
                            <li v-for="row in alerts.out_of_stock" :key="row.product_id"><span>{{ row.name }}<small class="d-block text-secondary">{{ row.sku }}</small></span><strong class="text-danger">{{ number(row.stock_quantity) }}</strong></li>
                            <li v-if="!alerts.out_of_stock?.length" class="text-secondary">Nothing is out of stock.</li>
                        </ul>
                    </div>
                </section>
            </div>
            <div class="col-lg-4">
                <section class="card h-100 ai-alert-card ai-alert-warning">
                    <div class="card-header d-flex justify-content-between align-items-center"><h3 class="card-title mb-0">Low stock</h3><span class="badge text-bg-warning">{{ alerts.low_stock?.length || 0 }}</span></div>
                    <div class="card-body">
                        <ul class="ai-stat-list">
                            <li v-for="row in alerts.low_stock" :key="row.product_id"><span>{{ row.name }}<small class="d-block text-secondary">{{ row.sku }}</small></span><strong class="text-warning">{{ number(row.stock_quantity) }}</strong></li>
                            <li v-if="!alerts.low_stock?.length" class="text-secondary">No products are running low.</li>
                        </ul>
                    </div>
                </section>
            </div>
            <div class="col-lg-4">
                <section class="card h-100 ai-alert-card">
                    <div class="card-header d-flex justify-content-between align-items-center"><h3 class="card-title mb-0">Overstocked</h3><span class="badge text-bg-secondary">{{ alerts.overstocked?.length || 0 }}</span></div>
                    <div class="card-body">
                        <ul class="ai-stat-list">
                            <li v-for="row in alerts.overstocked" :key="row.product_id"><span>{{ row.name }}<small class="d-block text-secondary">{{ number(row.units_in_period) }} units sold</small></span><strong>{{ number(row.stock_quantity) }}</strong></li>
                            <li v-if="!alerts.overstocked?.length" class="text-secondary">No overstock detected.</li>
                        </ul>
                    </div>
                </section>
            </div>
        </div>

        <section class="card mb-3">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <h3 class="card-title mb-0">Recommended reorders</h3>
                <span class="badge text-bg-info ms-auto"><i class="bi bi-info-circle me-1"></i>Advisory only</span>
            </div>
            <div class="card-body">
                <p class="small text-secondary">These quantities are suggestions computed from stock levels. Creating a purchase order remains a manual decision — this module never raises one for you.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Product</th><th>SKU</th><th class="text-end">On hand</th><th class="text-end">Suggested quantity</th><th>Reason</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="row in alerts.recommended_reorders" :key="row.product_id">
                                <td>{{ row.name }}</td>
                                <td>{{ row.sku || '—' }}</td>
                                <td class="text-end">{{ number(row.stock_quantity) }}</td>
                                <td class="text-end fw-semibold">{{ number(row.suggested_quantity) }}</td>
                                <td class="small text-secondary">{{ row.reason }}</td>
                                <td class="text-end"><router-link to="/admin/purchase-orders" class="btn btn-sm btn-outline-primary">Create PO manually</router-link></td>
                            </tr>
                            <tr v-if="!alerts.recommended_reorders?.length"><td colspan="6" class="text-center text-secondary py-4">No reorder suggestions — stock levels look healthy.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div class="row g-3 mb-3">
            <div class="col-lg-5">
                <section class="card h-100">
                    <div class="card-header"><h3 class="card-title mb-0">Lowest days of inventory</h3></div>
                    <div class="card-body ai-chart-body"><canvas ref="inventoryCanvas" role="img" aria-label="Products with the fewest days of inventory"></canvas></div>
                </section>
            </div>
            <div class="col-lg-7">
                <section class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">Stock &amp; velocity</h3>
                        <small class="text-secondary">Total value {{ money(stockData.total_stock_value) }}</small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive ai-table-scroll">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr><th>Product</th><th class="text-end">Stock</th><th class="text-end">Sold</th><th class="text-end">Daily</th><th class="text-end">Days left</th><th class="text-end">Value</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in stockData.products" :key="row.id">
                                        <td><div>{{ row.name }}</div><small class="text-secondary">{{ row.sku || 'No SKU' }}</small></td>
                                        <td class="text-end">{{ number(row.stock_quantity) }}</td>
                                        <td class="text-end">{{ number(row.units_sold_in_period) }}</td>
                                        <td class="text-end">{{ number(row.average_daily_sales, 2) }}</td>
                                        <td class="text-end">
                                            <span v-if="row.days_of_inventory === null" class="text-secondary">No sales</span>
                                            <span v-else :class="daysClass(row.days_of_inventory)">{{ number(row.days_of_inventory, 1) }}</span>
                                        </td>
                                        <td class="text-end">{{ money(row.stock_value) }}</td>
                                    </tr>
                                    <tr v-if="!stockData.products?.length"><td colspan="6" class="text-center text-secondary py-4">No active products with stock data.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <section class="card">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <h3 class="card-title mb-0">Recent inventory movement</h3>
                <small class="text-secondary ms-auto">Latest 50 transactions</small>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Product</th><th>Type</th><th class="text-end">Before</th><th class="text-end">After</th><th class="text-end">Change</th><th>Reason</th><th>When</th></tr></thead>
                    <tbody>
                        <tr v-for="row in movementData.movement" :key="row.id">
                            <td>{{ row.product }}<small class="d-block text-secondary">{{ row.sku }}</small></td>
                            <td class="text-capitalize">{{ titleCase(row.type) }}</td>
                            <td class="text-end">{{ number(row.quantity_before) }}</td>
                            <td class="text-end">{{ number(row.quantity_after) }}</td>
                            <td class="text-end" :class="row.delta >= 0 ? 'text-success' : 'text-danger'">{{ signedNumber(row.delta) }}</td>
                            <td class="small text-secondary">{{ row.reason || '—' }}</td>
                            <td class="small text-secondary">{{ formatDateTime(row.created_at) }}</td>
                        </tr>
                        <tr v-if="!movementData.movement?.length"><td colspan="7" class="text-center text-secondary py-4">No inventory movements recorded yet.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-secondary">{{ valuation.note }}</div>
        </section>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import Chart from 'chart.js/auto';
import AiPeriodBar from './AiPeriodBar.vue';
import AiKpiCard from './AiKpiCard.vue';
import {
    chartTheme, destroyCharts, formatDate, formatDateTime, money, number,
    periodQuery, signedNumber, titleCase,
} from './ai.js';

const period = ref('30d');
const start = ref('');
const end = ref('');
const loading = ref(true);
const error = ref('');
const stockData = ref({});
const alerts = ref({});
const movementData = ref({});
const valuation = ref({});
const inventoryCanvas = ref(null);
const charts = [];
let requestId = 0;

const periodLabel = computed(() => {
    if (!stockData.value.period) return '';
    return `${formatDate(stockData.value.period.start)} → ${formatDate(stockData.value.period.end)}`;
});

function daysClass(days) {
    if (days <= 7) return 'text-danger fw-semibold';
    if (days <= 21) return 'text-warning fw-semibold';
    if (days >= 120) return 'text-secondary';

    return '';
}

function renderChart() {
    destroyCharts(charts);
    const products = (stockData.value.products || [])
        .filter((row) => row.days_of_inventory !== null)
        .slice(0, 10);
    if (!inventoryCanvas.value || !products.length) return;
    const theme = chartTheme();

    charts.push(new Chart(inventoryCanvas.value, {
        type: 'bar',
        data: {
            labels: products.map((row) => row.name),
            datasets: [{ label: 'Days of inventory', data: products.map((row) => row.days_of_inventory), backgroundColor: theme.primary, borderRadius: 4 }],
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => `${number(context.parsed.x, 1)} days` } } },
            scales: {
                x: { beginAtZero: true, grid: { color: theme.grid }, ticks: { color: theme.text } },
                y: { grid: { display: false }, ticks: { color: theme.text } },
            },
        },
    }));
}

async function loadAll() {
    const id = ++requestId;
    loading.value = true;
    error.value = '';
    const query = periodQuery(period.value, start.value, end.value);

    const [stockResult, alertsResult, movementResult, valuationResult] = await Promise.allSettled([
        axios.get(`/api/ai/inventory/stock?${query}`),
        axios.get(`/api/ai/inventory/alerts?${query}`),
        axios.get('/api/ai/inventory/movement?limit=50'),
        axios.get('/api/ai/inventory/valuation'),
    ]);

    if (id !== requestId) return;

    if (stockResult.status === 'fulfilled') {
        stockData.value = stockResult.value.data || {};
    } else {
        error.value = stockResult.reason?.response?.data?.message || 'Could not load inventory intelligence.';
    }
    alerts.value = alertsResult.status === 'fulfilled' ? (alertsResult.value.data || {}) : {};
    movementData.value = movementResult.status === 'fulfilled' ? (movementResult.value.data || {}) : {};
    valuation.value = valuationResult.status === 'fulfilled' ? (valuationResult.value.data || {}) : {};
    loading.value = false;
    await nextTick();
    renderChart();
}

watch([period, start, end], loadAll);

onMounted(loadAll);
onBeforeUnmount(() => destroyCharts(charts));
</script>

<style scoped>
.ai-page { max-width: 1600px; }
.ai-chart-body { height: 340px; position: relative; }
.ai-stat-list { list-style: none; margin: 0; padding: 0; }
.ai-stat-list li { display: flex; justify-content: space-between; gap: 1rem; padding: .55rem 0; border-bottom: 1px solid var(--admin-border); font-size: .875rem; }
.ai-stat-list li:last-child { border-bottom: 0; }
.ai-alert-card { border-top: 3px solid var(--admin-border); }
.ai-alert-danger { border-top-color: var(--admin-danger, #b42318); }
.ai-alert-warning { border-top-color: var(--admin-warning, #c27803); }
.ai-table-scroll { max-height: 340px; overflow-y: auto; }
.ai-table-scroll thead th { position: sticky; top: 0; background: var(--admin-surface); z-index: 1; }
</style>
