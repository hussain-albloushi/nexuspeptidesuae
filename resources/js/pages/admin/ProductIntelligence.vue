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

        <div class="row g-3 mb-4">
            <div v-for="card in summaryCards" :key="card.label" class="col-xl-2 col-lg-4 col-sm-6">
                <div class="card ai-kpi-card h-100">
                    <div class="card-body">
                        <span class="ai-kpi-label">{{ card.label }}</span>
                        <div class="ai-kpi-value">{{ number(card.value) }}</div>
                        <p class="ai-kpi-note small text-secondary mb-0">{{ card.note }}</p>
                    </div>
                </div>
            </div>
        </div>

        <section class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <div class="input-group" style="max-width: 320px;">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input v-model="search" type="search" class="form-control" placeholder="Search name, SKU or category" aria-label="Search products" />
                    </div>
                    <input v-model="category" type="text" class="form-control" style="max-width: 190px;" placeholder="Category" aria-label="Filter by category" />
                    <select v-model="classFilter" class="form-select" style="max-width: 230px;" aria-label="Filter by classification">
                        <option value="">All classifications</option>
                        <option v-for="(label, key) in classLabels" :key="key" :value="key">{{ label }} ({{ (classification[key] || []).length }})</option>
                    </select>
                    <button type="button" class="btn btn-outline-secondary" @click="resetFilters">
                        <i class="bi bi-x-circle me-1"></i>Clear
                    </button>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <span v-if="selected.length" class="small text-secondary">{{ selected.length }}/6 selected</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" :disabled="selected.length < 2" @click="compare">
                            <i class="bi bi-columns-gap me-1"></i>Compare products
                        </button>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button
                        v-for="(label, key) in classLabels"
                        :key="key"
                        type="button"
                        class="btn btn-sm"
                        :class="classFilter === key ? 'btn-primary' : 'btn-outline-secondary'"
                        @click="classFilter = classFilter === key ? '' : key"
                    >
                        {{ label }} <span class="badge text-bg-light ms-1">{{ (classification[key] || []).length }}</span>
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 34px;"></th>
                                <th>Product</th>
                                <th>Category</th>
                                <th class="text-end">Units</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Gross profit</th>
                                <th class="text-end">Margin</th>
                                <th class="text-end">Stock</th>
                                <th>Classification</th>
                                <th style="width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="product in data.data" :key="product.id">
                                <td>
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        :value="product.id"
                                        :checked="selected.includes(product.id)"
                                        :disabled="!selected.includes(product.id) && selected.length >= 6"
                                        :aria-label="`Select ${product.name} for comparison`"
                                        @change="toggleSelected(product.id)"
                                    />
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ product.name }}</div>
                                    <small class="text-secondary">{{ product.sku || 'No SKU' }}</small>
                                </td>
                                <td>{{ product.category }}</td>
                                <td class="text-end">{{ number(product.units_sold) }}</td>
                                <td class="text-end">{{ money(product.revenue) }}</td>
                                <td class="text-end">{{ money(product.gross_profit) }}</td>
                                <td class="text-end">{{ product.gross_profit_margin === null ? '—' : percent(product.gross_profit_margin) }}</td>
                                <td class="text-end">
                                    <span :class="product.stock_quantity <= 0 ? 'text-danger fw-semibold' : (product.stock_quantity <= product.low_stock_threshold ? 'text-warning fw-semibold' : '')">
                                        {{ number(product.stock_quantity) }}
                                    </span>
                                </td>
                                <td>
                                    <span v-for="cls in product.classes" :key="cls" class="badge me-1 mb-1" :class="classBadges[cls] || 'text-bg-secondary'">
                                        {{ classLabels[cls] || cls }}
                                    </span>
                                    <span v-if="!product.classes?.length" class="text-secondary small">—</span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" :aria-label="`View ${product.name}`" @click="openDetail(product.id)">
                                        <i class="bi bi-arrow-up-right"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="loading"><td colspan="10" class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading products…</td></tr>
                            <tr v-else-if="!data.data?.length"><td colspan="10" class="text-center text-secondary py-4">No products match these filters.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                    <small class="text-secondary">
                        Showing {{ data.data?.length || 0 }} of {{ number(meta.total) }} products
                        <span class="ms-2">· Margin warning threshold {{ percent(thresholds.ai_profit_margin_warning_threshold, 0) }}</span>
                    </small>
                    <div class="btn-group" role="group" aria-label="Pagination">
                        <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="meta.page <= 1" @click="goPage(meta.page - 1)">Previous</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary disabled">Page {{ meta.page }} of {{ totalPages }}</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="meta.page >= totalPages" @click="goPage(meta.page + 1)">Next</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Product detail -->
        <div v-if="detail" class="ai-modal-backdrop" @click.self="detail = null">
            <div class="card ai-modal" role="dialog" aria-modal="true" aria-labelledby="product-detail-title">
                <div class="card-header d-flex align-items-center gap-2">
                    <h3 id="product-detail-title" class="card-title mb-0">{{ detail.product.name }}</h3>
                    <span class="badge text-bg-light">{{ detail.product.sku || 'No SKU' }}</span>
                    <button type="button" class="btn-close ms-auto" aria-label="Close" @click="detail = null"></button>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-4"><div class="ai-detail-stat"><span>Revenue</span><strong>{{ money(detail.product.revenue) }}</strong></div></div>
                        <div class="col-6 col-md-4"><div class="ai-detail-stat"><span>Units sold</span><strong>{{ number(detail.product.units_sold) }}</strong></div></div>
                        <div class="col-6 col-md-4"><div class="ai-detail-stat"><span>Gross profit</span><strong>{{ money(detail.product.gross_profit) }}</strong></div></div>
                        <div class="col-6 col-md-4"><div class="ai-detail-stat"><span>Margin</span><strong>{{ detail.product.gross_profit_margin === null ? '—' : percent(detail.product.gross_profit_margin) }}</strong></div></div>
                        <div class="col-6 col-md-4"><div class="ai-detail-stat"><span>Momentum</span><strong :class="momentumClass">{{ detail.product.momentum_percent === null ? 'No prior sales' : signedPercent(detail.product.momentum_percent) }}</strong></div></div>
                        <div class="col-6 col-md-4"><div class="ai-detail-stat"><span>Returns in period</span><strong>{{ number(detail.product.returns_in_period) }}</strong></div></div>
                        <div class="col-6 col-md-4"><div class="ai-detail-stat"><span>Stock on hand</span><strong>{{ number(detail.product.stock_quantity) }}</strong></div></div>
                        <div class="col-6 col-md-4"><div class="ai-detail-stat"><span>Low-stock threshold</span><strong>{{ number(detail.product.low_stock_threshold) }}</strong></div></div>
                        <div class="col-6 col-md-4"><div class="ai-detail-stat"><span>Orders containing</span><strong>{{ number(detail.product.order_count) }}</strong></div></div>
                    </div>

                    <div v-if="detail.product.classes?.length" class="d-flex flex-wrap gap-2 mt-3">
                        <span v-for="cls in detail.product.classes" :key="cls" class="badge" :class="classBadges[cls] || 'text-bg-secondary'">
                            {{ classLabels[cls] || cls }}
                        </span>
                    </div>

                    <div v-if="detail.previous_period" class="alert alert-secondary small mt-3 mb-0">
                        Prior period: {{ number(detail.previous_period.units_sold) }} units · {{ money(detail.previous_period.revenue) }} revenue
                        ({{ detail.previous_period.gross_profit_margin === null ? 'n/a' : percent(detail.previous_period.gross_profit_margin) }} margin).
                    </div>
                    <div v-else class="alert alert-secondary small mt-3 mb-0">
                        No sales for this product in the previous comparable period.
                    </div>
                </div>
            </div>
        </div>

        <!-- Comparison -->
        <div v-if="comparison" class="ai-modal-backdrop" @click.self="comparison = null">
            <div class="card ai-modal ai-modal-wide" role="dialog" aria-modal="true" aria-labelledby="compare-title">
                <div class="card-header d-flex align-items-center">
                    <h3 id="compare-title" class="card-title mb-0">Product comparison</h3>
                    <button type="button" class="btn-close ms-auto" aria-label="Close" @click="comparison = null"></button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th><th class="text-end">Gross profit</th><th class="text-end">Margin</th><th class="text-end">Stock</th></tr></thead>
                        <tbody>
                            <tr v-for="row in comparison.comparison" :key="row.id">
                                <td><div class="fw-semibold">{{ row.name }}</div><small class="text-secondary">{{ row.sku }}</small></td>
                                <td class="text-end">{{ number(row.units_sold) }}</td>
                                <td class="text-end">{{ money(row.revenue) }}</td>
                                <td class="text-end">{{ money(row.gross_profit) }}</td>
                                <td class="text-end">{{ row.gross_profit_margin === null ? '—' : percent(row.gross_profit_margin) }}</td>
                                <td class="text-end">{{ number(row.stock_quantity) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import AiPeriodBar from './AiPeriodBar.vue';
import {
    CLASS_BADGES, CLASS_LABELS, formatDate, money, number, percent, periodQuery,
    signedPercent, trendMeta,
} from './ai.js';

const period = ref('30d');
const start = ref('');
const end = ref('');
const search = ref('');
const category = ref('');
const classFilter = ref('');

const loading = ref(true);
const error = ref('');
const data = ref({});
const meta = ref({ page: 1, per_page: 25, total: 0 });
const thresholds = ref({});
const classification = ref({});
const detail = ref(null);
const comparison = ref(null);
const selected = ref([]);

const classLabels = CLASS_LABELS;
const classBadges = CLASS_BADGES;

let requestId = 0;
let searchTimer = null;

const periodLabel = computed(() => {
    if (!data.value.period) return '';
    return `${formatDate(data.value.period.start)} → ${formatDate(data.value.period.end)}`;
});

const totalPages = computed(() => Math.max(1, Math.ceil((meta.value.total || 0) / (meta.value.per_page || 25))));

const summaryCards = computed(() => {
    const summary = data.value.summary || {};
    return [
        { label: 'Total products', value: summary.total_products, note: 'Products in the catalog.' },
        { label: 'Active', value: summary.active, note: 'Currently visible and sellable.' },
        { label: 'No sales', value: summary.no_sales, note: 'No units sold in this period.' },
        { label: 'Restock needed', value: summary.restock_needed, note: 'At or below stock threshold.' },
        { label: 'Low profit', value: summary.low_profit, note: 'Below the margin warning threshold.' },
        { label: 'High profit', value: summary.high_profit, note: 'At or above the margin threshold.' },
    ];
});

const momentumClass = computed(() => {
    const value = detail.value?.product?.momentum_percent;
    if (value === null || value === undefined) return 'text-secondary';

    return trendMeta(value).class;
});

function toggleSelected(id) {
    const index = selected.value.indexOf(id);
    if (index >= 0) {
        selected.value.splice(index, 1);
    } else if (selected.value.length < 6) {
        selected.value.push(id);
    }
}

function resetFilters() {
    search.value = '';
    category.value = '';
    classFilter.value = '';
    meta.value.page = 1;
    load();
}

async function load() {
    const id = ++requestId;
    loading.value = true;
    error.value = '';
    const params = new URLSearchParams(periodQuery(period.value, start.value, end.value));
    if (search.value.trim()) params.set('search', search.value.trim());
    if (category.value.trim()) params.set('category', category.value.trim());
    if (classFilter.value) params.set('class', classFilter.value);
    params.set('page', String(meta.value.page));
    params.set('per_page', String(meta.value.per_page));

    try {
        const { data: response } = await axios.get(`/api/ai/products?${params.toString()}`);
        if (id !== requestId) return;
        data.value = response || {};
        meta.value = response?.meta || { page: 1, per_page: 25, total: 0 };
        thresholds.value = response?.thresholds || {};
        classification.value = response?.classification || {};
    } catch (e) {
        if (id !== requestId) return;
        error.value = e?.response?.data?.message || 'Could not load product intelligence. Please try again.';
    } finally {
        if (id === requestId) loading.value = false;
    }
}

function goPage(page) {
    if (page < 1 || page > totalPages.value) return;
    meta.value.page = page;
    load();
}

async function openDetail(id) {
    try {
        const { data: response } = await axios.get(`/api/ai/products/${id}?${periodQuery(period.value, start.value, end.value)}`);
        detail.value = response;
    } catch (e) {
        error.value = e?.response?.data?.message || 'Could not load product details.';
    }
}

async function compare() {
    if (selected.value.length < 2) return;
    try {
        const payload = { ids: selected.value, ...(period.value === 'custom' && start.value && end.value
            ? { start: start.value, end: end.value }
            : { period: period.value }) };
        const { data: response } = await axios.post('/api/ai/products/compare', payload);
        comparison.value = response;
    } catch (e) {
        error.value = e?.response?.data?.message || 'Could not compare the selected products.';
    }
}

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { meta.value.page = 1; load(); }, 350);
});
watch(category, () => { meta.value.page = 1; load(); });
watch(classFilter, () => { meta.value.page = 1; load(); });
watch([period, start, end], () => { meta.value.page = 1; load(); });

onMounted(load);
onBeforeUnmount(() => clearTimeout(searchTimer));
</script>

<style scoped>
.ai-page { max-width: 1600px; }
.ai-kpi-card .card-body { display: flex; flex-direction: column; gap: .35rem; }
.ai-kpi-label { font-size: .72rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--admin-muted); }
.ai-kpi-value { font-size: 1.35rem; font-weight: 700; letter-spacing: -.02em; }
.ai-kpi-note { margin-top: auto !important; line-height: 1.45; }
.ai-modal-backdrop { position: fixed; inset: 0; z-index: 1080; background: rgba(9, 14, 17, .55); display: flex; align-items: flex-start; justify-content: center; padding: 6vh 16px 16px; overflow-y: auto; }
.ai-modal { width: min(720px, 100%); box-shadow: 0 24px 60px rgba(0, 0, 0, .28); }
.ai-modal-wide { width: min(1020px, 100%); }
.ai-detail-stat { display: flex; flex-direction: column; gap: .2rem; padding: .75rem; border: 1px solid var(--admin-border); border-radius: 8px; height: 100%; }
.ai-detail-stat span { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: var(--admin-muted); }
.ai-detail-stat strong { font-size: 1rem; }
</style>
