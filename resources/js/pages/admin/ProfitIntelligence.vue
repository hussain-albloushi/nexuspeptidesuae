<template>
    <div class="ai-page">
        <AiPeriodBar
            v-model:period="period" v-model:start="start" v-model:end="end"
            :period-label="periodLabel" :generated-at="pnlData.analysis_generated_at"
            @refresh="loadAll"
        />

        <div v-if="error" class="alert alert-danger d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle"></i>
            <span>{{ error }}</span>
            <button class="btn btn-sm btn-outline-danger ms-auto" type="button" @click="loadAll">Retry</button>
        </div>

        <div v-if="loading && !pnlData.pnl" class="text-center py-5 text-secondary">
            <span class="spinner-border spinner-border-sm me-2"></span>Calculating profitability…
        </div>

        <template v-else-if="pnlData.pnl">
            <section class="card ai-summary-card mb-4">
                <div class="card-body d-flex gap-3 align-items-start">
                    <span class="ai-summary-icon"><i class="bi bi-stars"></i></span>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h3 class="card-title mb-0">AI profitability analysis</h3>
                            <span class="badge" :class="pnlData.summary_source === 'ai' ? 'text-bg-primary' : 'text-bg-secondary'">
                                {{ pnlData.summary_source === 'ai' ? 'AI interpreted' : 'Computed briefing' }}
                            </span>
                            <small class="text-secondary ms-auto">{{ periodLabel }}</small>
                        </div>
                        <p class="mb-0 ai-summary-text">{{ pnlData.summary }}</p>
                    </div>
                </div>
            </section>

            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-lg-4 col-sm-6">
                    <AiKpiCard label="Gross profit" :value="pnl.gross_profit" :previous="previousPnl.gross_profit ?? null" :delta="grossProfitDelta" :delta-percent="grossProfitDeltaPercent" unit="AED" note="Net sales after discounts minus COGS." />
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6">
                    <AiKpiCard label="Gross margin" :value="pnl.gross_profit_margin" unit="%" :decimals="1" note="Gross profit ÷ net sales (ex VAT)." />
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6">
                    <AiKpiCard label="Net sales" :value="pnl.net_sales_ex_vat" unit="AED" note="Gross revenue minus discounts. VAT is informational only." />
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6">
                    <AiKpiCard label="Refunds" :value="pnl.refunds" unit="AED" note="Manual refunds plus approved return requests." />
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6">
                    <AiKpiCard label="Recorded expenses" :value="pnl.recorded_expenses" unit="AED" note="Expenses recorded in the period." />
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6">
                    <AiKpiCard
                        label="Net operating profit"
                        :value="pnl.expense_data_complete ? pnl.net_operating_profit : null"
                        unit="AED"
                        :note="pnl.expense_data_complete ? 'Gross profit minus recorded expenses and refunds.' : 'Not shown: expense data is incomplete.'"
                    />
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-6">
                    <section class="card h-100">
                        <div class="card-header"><h3 class="card-title mb-0">Profit &amp; loss statement</h3></div>
                        <div class="card-body">
                            <ul class="ai-pnl-list">
                                <li><span>Gross revenue</span><strong>{{ money(pnl.gross_revenue) }}</strong></li>
                                <li><span>Discounts</span><strong class="text-danger">− {{ money(pnl.discounts) }}</strong></li>
                                <li class="ai-pnl-total"><span>Net sales (ex VAT)</span><strong>{{ money(pnl.net_sales_ex_vat) }}</strong></li>
                                <li><span>VAT collected <i class="bi bi-info-circle text-secondary" title="Reported for reference only; never added to revenue."></i></span><strong>{{ money(pnl.tax_collected) }}</strong></li>
                                <li><span>Cost of goods sold</span><strong class="text-danger">− {{ money(pnl.cost_of_goods_sold) }}</strong></li>
                                <li class="ai-pnl-total"><span>Gross profit</span><strong>{{ money(pnl.gross_profit) }}</strong></li>
                                <li><span>Shipping income</span><strong>{{ money(pnl.shipping_income) }}</strong></li>
                                <li><span>Shipping expenses <i class="bi bi-info-circle text-secondary" title="Included in recorded expenses; shown for reference so it is never double-counted."></i></span><strong>{{ money(pnl.shipping_expenses) }}</strong></li>
                                <li><span>Recorded other expenses</span><strong class="text-danger">− {{ money(pnl.recorded_expenses) }}</strong></li>
                                <li><span>Refunds</span><strong class="text-danger">− {{ money(pnl.refunds) }}</strong></li>
                                <li v-if="pnl.expense_data_complete" class="ai-pnl-total"><span>Net operating profit</span><strong>{{ money(pnl.net_operating_profit) }}</strong></li>
                            </ul>
                            <div v-if="!pnl.expense_data_complete" class="alert alert-warning small mt-3 mb-0">
                                <i class="bi bi-exclamation-circle me-1"></i>{{ pnl.note }}
                            </div>
                        </div>
                    </section>
                </div>
                <div class="col-lg-6">
                    <section class="card h-100">
                        <div class="card-header"><h3 class="card-title mb-0">Gross profit by category</h3></div>
                        <div class="card-body ai-chart-body"><canvas ref="categoryCanvas" role="img" aria-label="Gross profit by category"></canvas></div>
                    </section>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-7">
                    <section class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">Product profitability</h3>
                            <small class="text-secondary">Sorted by gross profit</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Net sales</th><th class="text-end">COGS</th><th class="text-end">Gross profit</th><th class="text-end">Margin</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in productsData.products" :key="row.product_id">
                                        <td>{{ row.product }}</td>
                                        <td class="text-end">{{ number(row.units_sold) }}</td>
                                        <td class="text-end">{{ money(row.net_sales) }}</td>
                                        <td class="text-end">{{ money(row.cogs) }}</td>
                                        <td class="text-end fw-semibold">{{ money(row.gross_profit) }}</td>
                                        <td class="text-end"><span class="badge" :class="marginBadge(row.gross_profit_margin)">{{ row.gross_profit_margin === null ? '—' : percent(row.gross_profit_margin) }}</span></td>
                                    </tr>
                                    <tr v-if="!productsData.products?.length"><td colspan="6" class="text-center text-secondary py-4">No product profitability in this period.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
                <div class="col-lg-5">
                    <section class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">Category margins</h3>
                            <small class="text-secondary">Revenue vs profit</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Category</th><th class="text-end">Revenue</th><th class="text-end">Gross profit</th><th class="text-end">Margin</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in categoriesData.categories" :key="row.category">
                                        <td>{{ row.category }}</td>
                                        <td class="text-end">{{ money(row.revenue) }}</td>
                                        <td class="text-end">{{ money(row.gross_profit) }}</td>
                                        <td class="text-end">{{ row.gross_profit_margin === null ? '—' : percent(row.gross_profit_margin) }}</td>
                                    </tr>
                                    <tr v-if="!categoriesData.categories?.length"><td colspan="4" class="text-center text-secondary py-4">No category data in this period.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>

            <section class="card">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h3 class="card-title mb-0">Discount impact</h3>
                    <span class="badge text-bg-warning">{{ percent(discountsData.discount_share_of_gross_percent) }} of gross revenue</span>
                    <strong class="ms-auto">{{ money(discountsData.total_discounts) }} in discounts</strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Net sales</th><th class="text-end">Discount applied</th><th class="text-end">Gross profit</th><th class="text-end">Margin</th></tr></thead>
                        <tbody>
                            <tr v-for="row in discountsData.by_product" :key="row.product">
                                <td>{{ row.product }}</td>
                                <td class="text-end">{{ number(row.units_sold) }}</td>
                                <td class="text-end">{{ money(row.net_sales) }}</td>
                                <td class="text-end text-warning fw-semibold">{{ money(row.discount_applied) }}</td>
                                <td class="text-end">{{ money(row.gross_profit) }}</td>
                                <td class="text-end">{{ row.margin_percent === null ? '—' : percent(row.margin_percent) }}</td>
                            </tr>
                            <tr v-if="!discountsData.by_product?.length"><td colspan="6" class="text-center text-secondary py-4">No discounted product sales in this period.</td></tr>
                        </tbody>
                    </table>
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
import { chartTheme, destroyCharts, formatDate, money, number, percent, periodQuery } from './ai.js';

const period = ref('30d');
const start = ref('');
const end = ref('');
const loading = ref(true);
const error = ref('');
const pnlData = ref({});
const productsData = ref({});
const categoriesData = ref({});
const discountsData = ref({});
const categoryCanvas = ref(null);
const charts = [];
let requestId = 0;

const pnl = computed(() => pnlData.value.pnl || {});
const previousPnl = computed(() => pnlData.value.previous_period || {});

const periodLabel = computed(() => {
    if (!pnlData.value.period) return '';
    return `${formatDate(pnlData.value.period.start)} → ${formatDate(pnlData.value.period.end)}`;
});

const grossProfitDelta = computed(() => {
    if (previousPnl.value.gross_profit === undefined || previousPnl.value.gross_profit === null) return null;

    return Number(pnl.value.gross_profit || 0) - Number(previousPnl.value.gross_profit);
});

const grossProfitDeltaPercent = computed(() => {
    const previous = Number(previousPnl.value.gross_profit || 0);
    if (!previous || Math.abs(previous) < 0.000001) return null;

    return (Number(grossProfitDelta.value) / Math.abs(previous)) * 100;
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
    const categories = categoriesData.value.categories || [];
    if (!categoryCanvas.value || !categories.length) return;
    const theme = chartTheme();

    charts.push(new Chart(categoryCanvas.value, {
        type: 'bar',
        data: {
            labels: categories.map((row) => row.category),
            datasets: [
                { label: 'Revenue', data: categories.map((row) => row.revenue), backgroundColor: '#cbd5e1', borderRadius: 4 },
                { label: 'Gross profit', data: categories.map((row) => row.gross_profit), backgroundColor: theme.primary, borderRadius: 4 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: theme.text, boxWidth: 8, usePointStyle: true } },
                tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${money(context.parsed.y)}` } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: theme.text } },
                y: { beginAtZero: true, grid: { color: theme.grid }, ticks: { color: theme.text, callback: (value) => number(value) } },
            },
        },
    }));
}

async function loadAll() {
    const id = ++requestId;
    loading.value = true;
    error.value = '';
    const query = periodQuery(period.value, start.value, end.value);

    const [pnlResult, productsResult, categoriesResult, discountsResult] = await Promise.allSettled([
        axios.get(`/api/ai/profit/pnl?${query}`),
        axios.get(`/api/ai/profit/products?${query}`),
        axios.get(`/api/ai/profit/categories?${query}`),
        axios.get(`/api/ai/profit/discounts-impact?${query}`),
    ]);

    if (id !== requestId) return;

    if (pnlResult.status === 'fulfilled') {
        pnlData.value = pnlResult.value.data || {};
    } else {
        error.value = pnlResult.reason?.response?.data?.message || 'Could not load profitability data.';
    }
    productsData.value = productsResult.status === 'fulfilled' ? (productsResult.value.data || {}) : {};
    categoriesData.value = categoriesResult.status === 'fulfilled' ? (categoriesResult.value.data || {}) : {};
    discountsData.value = discountsResult.status === 'fulfilled' ? (discountsResult.value.data || {}) : {};
    loading.value = false;
    await nextTick();
    renderCharts();
}

watch([period, start, end], loadAll);

onMounted(loadAll);
onBeforeUnmount(() => destroyCharts(charts));
</script>

<style scoped>
.ai-page { max-width: 1600px; }
.ai-summary-card { border-left: 3px solid var(--admin-primary); }
.ai-summary-icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 12px; background: color-mix(in srgb, var(--admin-primary) 10%, var(--admin-surface)); color: var(--admin-primary); font-size: 1.1rem; flex-shrink: 0; }
.ai-summary-text { font-size: .95rem; line-height: 1.6; white-space: pre-wrap; }
.ai-chart-body { height: 340px; position: relative; }
.ai-pnl-list { list-style: none; margin: 0; padding: 0; }
.ai-pnl-list li { display: flex; justify-content: space-between; gap: 1rem; padding: .5rem 0; border-bottom: 1px dashed var(--admin-border); font-size: .875rem; }
.ai-pnl-list li:last-child { border-bottom: 0; }
.ai-pnl-total { font-weight: 700; border-bottom-style: solid !important; }
</style>
