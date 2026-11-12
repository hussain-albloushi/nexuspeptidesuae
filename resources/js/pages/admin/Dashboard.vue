<template>
  <div class="operations-dashboard apex-dashboard">
    <header class="dashboard-toolbar">
      <div><h1>Dashboard</h1><p>Welcome back. Here's what's happening with your store today.</p></div>
      <div class="dashboard-freshness" aria-live="polite">
      <span class="dashboard-live-dot" :class="{ paused: !autoRefresh || error }"></span>
      <span v-if="loading">Updating dashboard…</span>
      <span v-else-if="data">Updated {{ updatedTime }}</span>
      <span v-else>Waiting for store data</span>
      <label class="dashboard-live-toggle"><input v-model="autoRefresh" type="checkbox" class="form-check-input m-0" /> Auto-refresh every 30s</label>
      <span v-if="data" class="reporting-dates">{{ data.period.start }} — {{ data.period.end }} · {{ data.period.timezone }}</span>
    </div>
    </header>
    <div v-if="error" class="alert alert-warning dashboard-error" role="alert"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>{{ error }} <template v-if="data">The last successful update remains below.</template></span><button type="button" class="dashboard-button" @click="refresh">Retry</button></div>
    <section class="dashboard-performance" aria-label="Store performance">
      <div v-if="!data && loading" class="metrics-grid" aria-label="Loading dashboard" role="status"><div v-for="index in 4" :key="index" class="dashboard-metric placeholder-glow"><span class="placeholder col-6"></span><span class="placeholder col-9 mt-3"></span></div></div>
      <div v-if="data" class="metrics-grid"><router-link v-for="card in metricCards" :key="card.key" :to="card.to" class="dashboard-metric" :class="card.tone" :title="card.note">
            <div class="metric-heading">
              <span class="metric-label">{{ card.label }}</span>
              <span class="metric-icon"><i :class="card.icon" aria-hidden="true"></i></span>
            </div>
            <div class="metric-value">{{ card.money ? money(data.metrics[card.key].value) : number(data.metrics[card.key].value) }}</div>
            <div class="metric-comparison" :class="changeClass(data.metrics[card.key])">
              <i v-if="data.metrics[card.key].change_percent !== null" class="fa-solid"
                 :class="data.metrics[card.key].change_percent >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'" aria-hidden="true"></i>
              {{ comparison(data.metrics[card.key]) }}
            </div>
            <div class="metric-note">{{ card.note }}</div>
            <svg class="metric-sparkline" viewBox="0 0 300 44" preserveAspectRatio="none" aria-hidden="true"><path :d="metricSparkline(card) + ' L300 44 L0 44 Z'" fill="currentColor" opacity=".07" /><path :d="metricSparkline(card)" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" /></svg>
          </router-link></div>
    </section>
    <template v-if="data">
      <div class="apex-overview-grid">
        <section class="dashboard-panel apex-overview">
            <div class="dashboard-panel-heading">
              <div><h3>Overview</h3><p>Sales performance for the selected period</p></div>
              <div class="dashboard-controls">
        <div class="dashboard-periods" role="group" aria-label="Dashboard reporting period">
          <button v-for="period in [7, 30, 90]" :key="period" type="button" :class="{ selected: days === period }"
                  :aria-pressed="days === period" @click="days = period">{{ period }} days</button>
        </div>
        <button class="dashboard-button" type="button" :disabled="loading" @click="refresh">
          <i class="fa-solid fa-arrows-rotate" :class="{ 'dashboard-spin': loading }" aria-hidden="true"></i> Refresh
        </button>
      </div>
            </div>
            <div class="dashboard-panel-body">
              <div class="dashboard-chart"><canvas ref="chartCanvas" role="img" aria-label="Daily order value and paid sales trend"></canvas></div>
              <p v-if="!hasSales" class="text-secondary small text-center mb-0 mt-2">No sales recorded in this period. Your real totals will appear here as orders arrive.</p>
            </div>
          </section>
        <div class="apex-dashboard-rail"><section class="dashboard-panel apex-pipeline-card">
  <div class="dashboard-panel-heading"><div><h3>Order pipeline</h3><p>How your orders are progressing</p></div></div>
  <div class="dashboard-panel-body apex-donut-row">
    <div class="apex-donut" :style="{ background: pipelineGradient }"><div><strong>{{ number(data.metrics.orders.value) }}</strong><span>Orders</span></div></div>
    <div class="apex-donut-legend"><div v-for="status in statusRows" :key="status.status"><span class="legend-dot" :style="{ backgroundColor: statusColor(status.status) }"></span><span>{{ label(status.status) }}</span><strong>{{ number(status.count) }}</strong></div></div>
  </div>
</section><section class="dashboard-panel apex-attention-card" aria-labelledby="attention-title">
  <div class="dashboard-panel-heading"><div><h3 id="attention-title">Needs attention</h3><p>Live store priorities, across all dates</p></div></div>
  <div class="apex-priorities"><router-link v-for="item in attentionCards" :key="item.key" :to="item.to" class="apex-priority" :class="item.tone">
    <div><span>{{ item.label }}</span><strong>{{ number(data.attention[item.key]) }}</strong></div>
    <div class="apex-priority-track"><span :style="{ width: attentionWidth(data.attention[item.key]) + '%' }"></span></div>
  </router-link></div>
</section></div>
      </div>
      <div class="apex-lower-grid"><section class="dashboard-panel">
            <div class="dashboard-panel-heading">
              <div><h3>Recent orders</h3><p>Latest transactions from your store</p></div>
              <router-link to="/admin/orders" class="dashboard-text-link">View all <i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i></router-link>
            </div>
            <div class="table-responsive">
              <table class="table align-middle dashboard-table mb-0">
                <thead><tr><th scope="col">Customer</th><th scope="col">Order ID</th><th scope="col">Status</th><th scope="col">Payment</th><th scope="col" class="text-end">Total</th></tr></thead>
                <tbody>
                  <tr v-if="!data.recent_orders.length"><td colspan="5" class="dashboard-empty">No orders yet. New orders will appear here.</td></tr>
                  <tr v-for="order in data.recent_orders.slice(0, 6)" :key="order.id">
                    <td><div class="apex-customer-cell"><span class="customer-avatar">{{ order.user?.name?.charAt(0).toUpperCase() || '?' }}</span><div><strong>{{ order.user?.name || 'Customer unavailable' }}</strong><small>{{ date(order.created_at) }}</small></div></div></td><td><router-link :to="{ path: '/admin/orders', query: { q: order.order_number || String(order.id) } }" class="dashboard-order-link">#{{ order.order_number || order.id }}</router-link></td>
                    <td><span class="dashboard-status" :style="{ '--status-color': statusColor(order.status) }">{{ label(order.status) }}</span></td>
                    <td><span class="payment-label" :class="{ paid: order.payment_status === 'paid' }">{{ label(order.payment_status || 'pending') }}</span></td>
                    <td class="text-end fw-semibold text-nowrap">{{ money(order.total_amount) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section><section class="dashboard-panel">
            <div class="dashboard-panel-heading"><div><h3>Recent customers</h3><p>The latest customer registrations.</p></div><router-link to="/admin/customers" class="dashboard-text-link">Customers <i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i></router-link></div>
            <div class="dashboard-panel-body">
              <div v-if="!data.recent_customers.length" class="dashboard-empty">No customers have registered yet.</div>
              <router-link v-for="customer in data.recent_customers" :key="customer.id"
                           :to="{ path: '/admin/customers', query: { q: customer.email } }" class="customer-item">
                <span class="customer-avatar">{{ customer.name?.charAt(0).toUpperCase() || '?' }}</span>
                <div class="min-width-zero"><strong>{{ customer.name }}</strong><span>{{ customer.email }}</span></div>
                <small class="text-secondary ms-auto text-nowrap">{{ date(customer.created_at) }}</small>
              </router-link>
            </div>
          </section></div>
      <div class="apex-bottom-grid"><section class="dashboard-panel">
            <div class="dashboard-panel-heading"><div><h3><i class="fa-solid fa-box-open panel-icon" aria-hidden="true"></i> Stock watch</h3><p>Active products that need replenishing.</p></div><router-link to="/admin/inventory" class="dashboard-text-link">Inventory <i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i></router-link></div>
            <div class="stock-list">
              <div v-if="!data.stock_alerts.length" class="dashboard-empty"><i class="fa-solid fa-circle-check text-success fs-3 d-block mb-2" aria-hidden="true"></i>No active products need restocking.</div>
              <router-link v-for="product in data.stock_alerts" :key="product.id" :to="{ path: '/admin/inventory', query: { status: product.stock_quantity <= 0 ? 'out_of_stock' : 'low_stock' } }" class="stock-item">
                <div class="min-width-zero"><strong>{{ product.name }}</strong><span>{{ product.sku }} · Alert at {{ product.low_stock_threshold }}</span></div>
                <span class="stock-quantity" :class="{ empty: product.stock_quantity <= 0 }">{{ product.stock_quantity <= 0 ? 'Out' : product.stock_quantity + ' left' }}</span>
              </router-link>
            </div>
          <router-link to="/admin/inventory" class="panel-footer-link">Manage inventory <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></router-link></section><section class="dashboard-panel">
            <div class="dashboard-panel-heading"><div><h3><i class="fa-solid fa-trophy panel-icon" aria-hidden="true"></i> Top products</h3><p>Ranked by units ordered in the selected period.</p></div><router-link to="/admin/products" class="dashboard-text-link">Catalog <i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i></router-link></div>
            <div class="table-responsive">
              <table class="table align-middle dashboard-table mb-0"><thead><tr><th scope="col">Product</th><th scope="col" class="text-end">Units</th><th scope="col" class="text-end">Item sales</th></tr></thead>
                <tbody><tr v-if="!data.top_products.length"><td colspan="3" class="dashboard-empty">No product sales in this period.</td></tr>
                  <tr v-for="(product, index) in data.top_products" :key="product.product_id"><td><span class="product-rank">{{ index + 1 }}</span>{{ product.name }}</td><td class="text-end fw-semibold">{{ number(product.units) }}</td><td class="text-end text-nowrap">{{ money(product.sales) }}</td></tr>
                </tbody>
              </table>
            </div>
          </section></div>
    </template>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Chart from 'chart.js/auto';
import { useDashboard } from './dashboard';
import { formatSiteDate } from '../../siteTime.js';

const { days, data, loading, error, autoRefresh, refresh, start } = useDashboard();
const chartCanvas = ref(null);
let chart;
const metricCards = [
  { key: 'order_value', label: 'Order value', money: true, icon: 'fa-solid fa-bag-shopping', tone: 'tone-blue', to: '/admin/orders', note: 'Non-cancelled orders, including tax & shipping' },
  { key: 'paid_sales', label: 'Paid sales', money: true, icon: 'fa-solid fa-credit-card', tone: 'tone-green', to: '/admin/accounting', note: 'Orders marked as paid; cancelled excluded' },
  { key: 'orders', label: 'Orders received', icon: 'fa-solid fa-receipt', tone: 'tone-purple', to: '/admin/orders', note: 'All orders placed in this period' },
  { key: 'customers', label: 'New customers', icon: 'fa-solid fa-users', tone: 'tone-orange', to: '/admin/customers', note: 'Customer accounts created in this period' },
];
const attentionCards = [
  { key: 'out_of_stock', label: 'Out of stock', tone: 'alert-danger', icon: 'fa-solid fa-box', to: { path: '/admin/inventory', query: { status: 'out_of_stock' } } },
  { key: 'low_stock', label: 'Running low', tone: 'alert-warning', icon: 'fa-solid fa-triangle-exclamation', to: { path: '/admin/inventory', query: { status: 'low_stock' } } },
  { key: 'failed_payments', label: 'Failed payments', tone: 'alert-danger', icon: 'fa-solid fa-credit-card', to: '/admin/orders' },
  { key: 'to_process', label: 'To fulfill', tone: '', icon: 'fa-solid fa-box-open', to: '/admin/orders' },
  { key: 'awaiting_payment', label: 'Awaiting payment', tone: 'alert-warning', icon: 'fa-solid fa-wallet', to: '/admin/orders' },
];
const money = (value) => new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED', maximumFractionDigits: 2 }).format(Number(value || 0));
const number = (value) => new Intl.NumberFormat('en', { maximumFractionDigits: 0 }).format(Number(value || 0));
const label = (value) => String(value).replace(/_/g, ' ').replace(/^./, (letter) => letter.toUpperCase());
const date = (value) => formatSiteDate(value, { day: 'numeric', month: 'short' }, '—');
const updatedTime = computed(() => data.value ? formatSiteDate(data.value.generated_at, { hour: '2-digit', minute: '2-digit', second: '2-digit' }, '') : '');
const hasSales = computed(() => data.value?.trend.some((point) => point.booked > 0 || point.paid > 0));
const statusColor = (status) => ({ pending: '#f59e0b', processing: '#008e4c', shipped: '#1686e5', delivered: '#00a6a8', cancelled: '#ef4444' }[status] || 'var(--admin-muted)');
const statusRows = computed(() => {
  const counts = new Map((data.value?.order_statuses || []).map((entry) => [entry.status, entry.count]));
  const statuses = [...new Set(['pending', 'processing', 'shipped', 'delivered', 'cancelled', ...counts.keys()])];
  const total = data.value?.metrics.orders.value || 0;
  return statuses.map((status) => ({ status, count: counts.get(status) || 0, percent: total ? (counts.get(status) || 0) / total * 100 : 0 }));
});

const pipelineGradient = computed(() => {
  let offset = 0;
  const segments = statusRows.value.filter((row) => row.count > 0).map((row) => {
    const start = offset;
    offset += row.percent;
    return statusColor(row.status) + ' ' + start + '% ' + offset + '%';
  });
  return segments.length ? 'conic-gradient(' + segments.join(', ') + ')' : 'var(--admin-border)';
});
function metricSparkline(card) {
  const values = card.money
    ? (data.value?.trend || []).map((point) => Number(card.key === 'paid_sales' ? point.paid : point.booked))
    : [Number(data.value?.metrics[card.key].previous || 0), Number(data.value?.metrics[card.key].value || 0)];
  if (!values.length) return 'M0 42 L300 42';
  const maximum = Math.max(1, ...values);
  return values.map((value, index) => (index ? 'L' : 'M') + (index / Math.max(1, values.length - 1) * 300).toFixed(1) + ' ' + (40 - value / maximum * 34).toFixed(1)).join(' ');
}
function attentionWidth(value) {
  return Number(value || 0) / Math.max(1, ...Object.values(data.value?.attention || {}).map(Number)) * 100;
}

function comparison(metric) {
  if (metric.change_percent === null) return metric.value > 0 ? 'New activity vs previous period' : 'No activity in either period';
  return `${Math.abs(metric.change_percent)}% ${metric.change_percent >= 0 ? 'up' : 'down'} vs previous period`;
}
function changeClass(metric) {
  return metric.change_percent === null || metric.change_percent === 0 ? 'text-secondary' : metric.change_percent > 0 ? 'text-success' : 'text-danger';
}

watch(data, async (snapshot) => {
  await nextTick();
  if (!snapshot || !chartCanvas.value) return;
  const colors = getComputedStyle(chartCanvas.value);
  const textColor = colors.getPropertyValue('--dashboard-muted').trim();
  const gridColor = colors.getPropertyValue('--dashboard-border').trim();
  const labels = snapshot.trend.map((point) => formatSiteDate(point.date, { day: 'numeric', month: 'short' }, ''));
  const datasets = [
    { label: 'Order value', data: snapshot.trend.map((point) => point.booked), borderColor: colors.getPropertyValue('--dashboard-accent').trim(), backgroundColor: 'rgba(11,79,138,.08)', fill: true, tension: .3, pointRadius: snapshot.trend.length > 30 ? 0 : 2 },
    { label: 'Paid sales', data: snapshot.trend.map((point) => point.paid), borderColor: colors.getPropertyValue('--admin-secondary').trim(), borderDash: [5, 4], tension: .3, pointRadius: 0 },
  ];
  const options = {
    responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' },
    plugins: {
      legend: { position: 'bottom', labels: { color: textColor, font: { size: 14 }, usePointStyle: true, boxWidth: 7, padding: 24 } },
      tooltip: { titleFont: { size: 14 }, bodyFont: { size: 14 }, callbacks: { label: (context) => context.dataset.label + ': ' + money(context.parsed.y) } },
    },
    scales: {
      x: { grid: { display: false }, ticks: { color: textColor, font: { size: 13 }, maxTicksLimit: 8 } },
      y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, font: { size: 13 }, callback: (value) => number(value) }, title: { display: true, text: 'AED', color: textColor, font: { size: 14 } } },
    },
  };
  if (chart) {
    chart.data = { labels, datasets };
    chart.options = options;
    chart.update('none');
  } else {
    chart = new Chart(chartCanvas.value, {
      type: 'line', data: { labels, datasets },
      options,
    });
  }
});
onMounted(start);
onBeforeUnmount(() => chart?.destroy());
</script>

<style scoped>
.operations-dashboard {
  --dashboard-accent: var(--admin-link);
  --dashboard-accent-soft: color-mix(in srgb, var(--admin-primary) 9%, var(--admin-surface));
  --dashboard-muted: var(--admin-muted);
  --dashboard-ink: var(--admin-text);
  --dashboard-border: var(--admin-border);
  --dashboard-surface: var(--admin-surface);
  --dashboard-inset: var(--admin-inset);
  --dashboard-success: var(--admin-success-text);
  --dashboard-warning: var(--admin-warning-text);
  --dashboard-danger: var(--admin-danger-text);
  max-width: 1600px;
  margin: 0 auto;
  padding: 2rem 1rem 3rem;
  font-family: 'Source Sans 3', 'Open Sans', system-ui, sans-serif;
  color: var(--dashboard-ink);
  font-size: 1rem;
  line-height: 1.55;
}

.dashboard-toolbar, .dashboard-controls, .dashboard-shortcuts, .dashboard-section-heading,
.attention-heading, .dashboard-panel-heading, .metric-heading, .dashboard-freshness {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}
.dashboard-toolbar { flex-wrap: wrap; margin-bottom: 1.5rem; }
.dashboard-eyebrow { display: flex; align-items: center; gap: .65rem; color: var(--dashboard-accent); font-size: .8rem; font-weight: 700; letter-spacing: .12em; }
.overview-mark { width: 30px; height: 30px; display: grid; place-items: center; background: var(--dashboard-accent-soft); border-radius: 9px; font-size: .95rem; }
.dashboard-toolbar h1 { font-size: clamp(2rem, 3vw, 2.6rem); font-weight: 700; letter-spacing: -.035em; margin: .55rem 0 .3rem; }
.dashboard-toolbar p { color: var(--dashboard-muted); margin: 0; font-size: 1.05rem; }
.dashboard-controls { flex-wrap: wrap; gap: .75rem; }
.dashboard-periods { display: flex; gap: .25rem; padding: .3rem; background: var(--dashboard-surface); border: 1px solid var(--dashboard-border); border-radius: 12px; }
.dashboard-periods button { padding: .55rem .85rem; min-height: 42px; border: 0; background: transparent; border-radius: 8px; color: var(--dashboard-muted); font: inherit; font-weight: 600; }
.dashboard-periods button.selected { background: var(--dashboard-accent-soft); color: var(--dashboard-accent); }
.dashboard-periods button:hover { color: var(--dashboard-accent); }
.dashboard-button { display: inline-flex; gap: .6rem; align-items: center; justify-content: center; min-height: 48px; padding: .65rem 1rem; border: 1px solid var(--dashboard-border); border-radius: 11px; background: var(--dashboard-surface); color: var(--dashboard-ink); font: inherit; font-weight: 600; }
.dashboard-button:hover { border-color: var(--dashboard-accent); color: var(--dashboard-accent); }
.dashboard-button:disabled { opacity: .6; cursor: wait; }
.dashboard-shortcuts { justify-content: flex-start; flex-wrap: wrap; gap: .65rem; margin-bottom: 1.5rem; }
.shortcut-label { color: var(--dashboard-muted); font-size: .875rem; font-weight: 600; margin-right: .25rem; }
.dashboard-shortcuts a { display: inline-flex; align-items: center; gap: .65rem; min-height: 42px; padding: .5rem .9rem; color: var(--dashboard-ink); border: 1px solid var(--dashboard-border); border-radius: 9px; text-decoration: none; background: var(--dashboard-surface); font-size: .95rem; font-weight: 600; }
.dashboard-shortcuts a > i:first-child { color: var(--dashboard-accent); }
.dashboard-shortcuts a > i:last-child { color: var(--dashboard-muted); font-size: .8rem; }
.dashboard-shortcuts a:hover { color: var(--dashboard-accent); border-color: var(--dashboard-accent); background: var(--dashboard-accent-soft); }
.dashboard-error { display: flex; align-items: center; flex-wrap: wrap; gap: 1rem; }
.dashboard-error .dashboard-button { margin-left: auto; }
.dashboard-attention { background: var(--dashboard-surface); border: 1px solid var(--dashboard-border); border-radius: 16px; overflow: hidden; margin-bottom: 1.75rem; }
.attention-heading { flex-wrap: wrap; padding: 1rem 1.25rem; border-bottom: 1px solid var(--dashboard-border); }
.attention-heading h2 { display: flex; align-items: center; gap: .6rem; font-size: 1rem; font-weight: 700; margin: 0; }
.attention-heading h2 i { color: var(--dashboard-accent); }
.attention-heading > span { color: var(--dashboard-muted); font-size: .875rem; }
.attention-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); }
.attention-item { --alert-color: var(--dashboard-accent); display: flex; align-items: center; gap: .75rem; padding: 1.25rem 1rem; text-decoration: none; color: var(--dashboard-ink); border-right: 1px solid var(--dashboard-border); }
.attention-item:last-child { border-right: 0; }
.attention-item:hover { background: var(--dashboard-inset); color: var(--dashboard-ink); }
.attention-icon { display: grid; place-items: center; width: 34px; height: 34px; flex-shrink: 0; background: var(--dashboard-inset); border-radius: 10px; color: var(--dashboard-muted); }
.attention-item.has-alert .attention-icon { color: var(--alert-color); background: color-mix(in srgb, var(--alert-color) 10%, var(--dashboard-surface)); }
.attention-item.has-alert strong { color: var(--alert-color); }
.attention-item.alert-warning { --alert-color: var(--dashboard-warning); }
.attention-item.alert-danger { --alert-color: var(--dashboard-danger); }
.attention-item strong { display: block; font-size: 1.55rem; line-height: 1.15; font-weight: 700; font-variant-numeric: tabular-nums; }
.attention-item div > span { display: block; font-size: .875rem; color: var(--dashboard-muted); margin-top: .3rem; }
.attention-arrow { margin-left: auto; font-size: .7rem; color: var(--dashboard-muted); }
.dashboard-section-heading { flex-wrap: wrap; margin-bottom: 1rem; }
.dashboard-section-heading h2 { font-size: 1.2rem; font-weight: 700; letter-spacing: -.015em; margin: 0; }
.dashboard-section-heading > span { color: var(--dashboard-muted); font-size: .875rem; }
.metrics-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.25rem; }
.dashboard-metric { --metric-color: var(--dashboard-accent); display: block; padding: 1.4rem; border: 1px solid var(--dashboard-border); border-radius: 16px; background: var(--dashboard-surface); text-decoration: none; color: var(--dashboard-ink); min-width: 0; box-shadow: 0 3px 12px #182d401f; transition: border-color .15s, transform .15s; }
.dashboard-metric:hover { transform: translateY(-2px); color: var(--dashboard-ink); border-color: var(--metric-color); }
.tone-blue { --metric-color: var(--dashboard-accent); }
.tone-green { --metric-color: var(--admin-secondary); }
.tone-purple { --metric-color: var(--admin-primary); }
.tone-orange { --metric-color: var(--admin-secondary); }
.metric-heading { gap: .5rem; }
.metric-label { font-size: .95rem; font-weight: 600; color: var(--dashboard-muted); }
.metric-icon { width: 36px; height: 36px; display: grid; place-items: center; flex-shrink: 0; border-radius: 11px; color: var(--metric-color); background: color-mix(in srgb, var(--metric-color) 10%, var(--dashboard-surface)); font-size: 1.15rem; }
.metric-value { font-size: clamp(1.6rem, 2.15vw, 2.3rem); font-weight: 700; line-height: 1.25; letter-spacing: -.035em; margin: 1rem 0 .6rem; overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
.metric-comparison { font-size: .875rem; font-weight: 600; }
.operations-dashboard .text-success { color: var(--dashboard-success) !important; }
.operations-dashboard .text-danger { color: var(--dashboard-danger) !important; }
.operations-dashboard .text-secondary { color: var(--dashboard-muted) !important; }
.metric-note { color: var(--dashboard-muted); font-size: .875rem; padding-top: .7rem; margin-top: .8rem; border-top: 1px solid var(--dashboard-border); }
.dashboard-freshness { justify-content: flex-start; flex-wrap: wrap; gap: .6rem; margin: 0 0 1.5rem; color: var(--dashboard-muted); font-size: .875rem; }
.dashboard-performance { margin-bottom: 2rem; }
.dashboard-live-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--dashboard-success); box-shadow: 0 0 0 3px color-mix(in srgb, var(--dashboard-success) 12%, transparent); }
.dashboard-live-dot.paused { background: var(--dashboard-muted); box-shadow: none; }
.dashboard-live-toggle { display: flex; align-items: center; gap: .5rem; cursor: pointer; margin-left: .5rem; }
.dashboard-live-toggle input:checked { background-color: var(--dashboard-accent); border-color: var(--dashboard-accent); }
.reporting-dates { margin-left: auto; }
.dashboard-grid { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(300px, 1fr); gap: 1.5rem; margin-bottom: 2rem; align-items: start; }
.dashboard-grid-equal { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.dashboard-panel { min-width: 0; background: var(--dashboard-surface); border: 1px solid var(--dashboard-border); border-radius: 16px; overflow: hidden; box-shadow: 0 3px 12px #182d4008; }
.dashboard-panel-heading { padding: 1.4rem; align-items: flex-start; flex-wrap: wrap; border-bottom: 1px solid var(--dashboard-border); }
.dashboard-panel-heading h3 { display: flex; align-items: center; gap: .6rem; font-size: 1.125rem; font-weight: 700; margin: 0; letter-spacing: -.015em; }
.panel-icon { color: var(--dashboard-accent); font-size: 1.1rem; }
.dashboard-panel-heading p { color: var(--dashboard-muted); font-size: .875rem; margin: .4rem 0 0; }
.dashboard-text-link { display: inline-flex; align-items: center; gap: .3rem; color: var(--dashboard-accent); font-size: .875rem; font-weight: 600; text-decoration: none; white-space: nowrap; padding: .15rem 0; }
.dashboard-text-link:hover { color: var(--dashboard-accent); text-decoration: underline; }
.dashboard-panel-body { padding: 1.4rem; }
.dashboard-chart { position: relative; height: 320px; }
.dashboard-chart + p { color: var(--dashboard-muted); font-size: .875rem; }
.pipeline-item { margin-bottom: 1.5rem; }
.pipeline-item:last-child { margin-bottom: 0; }
.pipeline-item .small { font-size: .95rem; }
.pipeline-track { height: 7px; background: var(--dashboard-inset); border-radius: 10px; overflow: hidden; }
.pipeline-track div { height: 100%; border-radius: inherit; transition: width .3s; }
.panel-footer-link { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.4rem; background: var(--dashboard-inset); border-top: 1px solid var(--dashboard-border); color: var(--dashboard-accent); font-weight: 600; font-size: .95rem; text-decoration: none; }
.panel-footer-link:hover { color: var(--dashboard-accent); text-decoration: underline; }
.dashboard-table { --bs-table-bg: var(--dashboard-surface); --bs-table-color: var(--dashboard-ink); --bs-table-border-color: var(--dashboard-border); }
.dashboard-table th { font-size: .8rem; text-transform: uppercase; letter-spacing: .065em; color: var(--dashboard-muted); background: var(--dashboard-inset); font-weight: 600; padding: .9rem 1.25rem; white-space: nowrap; }
.dashboard-table td { font-size: .95rem; padding: 1.1rem 1.25rem; }
.dashboard-table tbody tr:last-child td { border-bottom: 0; }
.dashboard-table tbody tr:hover td { background: var(--dashboard-inset); }
.dashboard-table .small { font-size: .875rem; margin-top: .25rem; }
.dashboard-order-link { color: var(--dashboard-ink); font-weight: 600; text-decoration: none; }
.dashboard-order-link:hover { color: var(--dashboard-accent); text-decoration: underline; }
.dashboard-status { display: inline-flex; align-items: center; gap: .4rem; font-size: .875rem; font-weight: 600; color: var(--dashboard-ink); white-space: nowrap; }
.dashboard-status::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--status-color); }
.payment-label { display: inline-block; color: var(--dashboard-warning); background: color-mix(in srgb, var(--dashboard-warning) 9%, var(--dashboard-surface)); padding: .25rem .55rem; border-radius: 6px; font-size: .875rem; white-space: nowrap; }
.payment-label.paid { color: var(--dashboard-success); background: color-mix(in srgb, var(--dashboard-success) 9%, var(--dashboard-surface)); }
.dashboard-empty { text-align: center; padding: 2.5rem 1.25rem !important; color: var(--dashboard-muted); font-size: .95rem !important; }
.stock-list, .customer-list { padding: 0 1.4rem; }
.stock-item, .customer-item { display: flex; align-items: center; gap: .8rem; padding: 1.1rem 0; border-bottom: 1px solid var(--dashboard-border); color: var(--dashboard-ink); text-decoration: none; }
.stock-item:last-child, .customer-item:last-child { border-bottom: 0; }
.stock-item strong, .customer-item strong { display: block; font-weight: 600; font-size: 1rem; overflow-wrap: anywhere; }
.stock-item div > span, .customer-item div > span { display: block; color: var(--dashboard-muted); font-size: .875rem; margin-top: .25rem; overflow-wrap: anywhere; }
.stock-item:hover strong, .customer-item:hover strong { color: var(--dashboard-accent); text-decoration: underline; }
.stock-quantity { flex-shrink: 0; margin-left: auto; color: var(--dashboard-warning); background: color-mix(in srgb, var(--dashboard-warning) 10%, var(--dashboard-surface)); font-size: .875rem; font-weight: 600; padding: .3rem .6rem; border-radius: 7px; white-space: nowrap; }
.stock-quantity.empty { color: var(--dashboard-danger); background: color-mix(in srgb, var(--dashboard-danger) 10%, var(--dashboard-surface)); }
.product-rank { display: inline-grid; place-items: center; width: 28px; height: 28px; border-radius: 8px; color: var(--dashboard-muted); background: var(--dashboard-inset); font-size: .875rem; margin-right: .6rem; }
.customer-avatar { width: 38px; height: 38px; flex-shrink: 0; display: grid; place-items: center; border-radius: 12px; background: var(--dashboard-accent-soft); color: var(--dashboard-accent); font-size: 1rem; font-weight: 700; }
.customer-item small { font-size: .875rem; }
.min-width-zero { min-width: 0; }
.operations-dashboard a:focus-visible, .operations-dashboard button:focus-visible, .operations-dashboard input:focus-visible { outline: 3px solid var(--dashboard-accent); outline-offset: 3px; }
.dashboard-spin { display: inline-block; animation: dashboard-spin 1s linear infinite; }
@keyframes dashboard-spin { to { transform: rotate(360deg); } }
@media (max-width: 1399px) {
  .metrics-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .attention-item { flex-wrap: wrap; gap: .6rem; }
  .attention-item > div { flex-basis: 100%; order: 2; }
}
@media (max-width: 1199px) {
  .dashboard-grid, .dashboard-grid-equal { grid-template-columns: minmax(0, 1fr); }
  .stock-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 1.5rem; }
  .stock-list .dashboard-empty { grid-column: 1 / -1; }
}
@media (max-width: 767px) {
  .operations-dashboard { padding: 1.25rem .25rem 2rem; }
  .dashboard-toolbar, .dashboard-controls { align-items: flex-start; }
  .dashboard-controls { width: 100%; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .5rem; }
  .dashboard-periods { justify-content: space-between; }
  .dashboard-periods button { padding: .5rem .65rem; font-size: .875rem; }
  .dashboard-button { padding: .65rem .75rem; font-size: .9rem; }
  .dashboard-shortcuts { gap: .5rem; }
  .dashboard-shortcuts a { gap: .5rem; padding: .5rem .75rem; font-size: .875rem; }
  .dashboard-shortcuts a > i:last-child { display: none; }
  .shortcut-label { flex-basis: 100%; }
  .attention-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .attention-item { flex-wrap: nowrap; border-bottom: 1px solid var(--dashboard-border); }
  .attention-item > div { flex-basis: auto; order: 0; }
  .attention-item:nth-child(even) { border-right: 0; }
  .attention-item:last-child { grid-column: 1 / -1; border-bottom: 0; }
  .attention-arrow { display: none; }
  .dashboard-metric { padding: 1.15rem; }
  .metrics-grid { gap: .85rem; }
  .metric-heading { align-items: flex-start; }
  .metric-value { font-size: 1.85rem; }
  .stock-list { grid-template-columns: minmax(0, 1fr); }
  .reporting-dates { flex-basis: 100%; margin-left: 0; }
  .dashboard-section-heading > span { display: none; }
  .dashboard-chart { height: 270px; }
}
@media (max-width: 479px) {
  .metrics-grid { grid-template-columns: minmax(0, 1fr); }
  .attention-item { padding: 1rem .75rem; gap: .5rem; }
  .attention-icon { width: 28px; height: 28px; }
  .dashboard-table th, .dashboard-table td { padding: 1rem; }
  .customer-item { flex-wrap: wrap; }
}
@media (prefers-reduced-motion: reduce) {
  .dashboard-spin { animation: none; }
  .dashboard-metric, .pipeline-track div { transition: none; }
  .dashboard-metric:hover { transform: none; }
}
</style>
