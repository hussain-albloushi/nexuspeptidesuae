<template>
  <div>
    <div class="row">
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-primary">
          <div class="inner"><h3>AED {{ stats.sales_today }}</h3><p>Sales Today</p></div>
          <i class="small-box-icon bi bi-cart-check"></i>
          <router-link to="/admin/orders" class="small-box-footer link-light link-underline-opacity-0">More info <i class="bi bi-link-45deg"></i></router-link>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-success">
          <div class="inner"><h3>AED {{ stats.sales_this_month }}</h3><p>This Month</p></div>
          <i class="small-box-icon bi bi-graph-up-arrow"></i>
          <router-link to="/admin/sales-report" class="small-box-footer link-light link-underline-opacity-0">More info <i class="bi bi-link-45deg"></i></router-link>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-warning">
          <div class="inner"><h3>AED {{ stats.net_sales_ex_vat }}</h3><p>Net Sales (ex VAT)</p></div>
          <i class="small-box-icon bi bi-receipt"></i>
          <router-link to="/admin/accounting" class="small-box-footer link-dark link-underline-opacity-0">More info <i class="bi bi-link-45deg"></i></router-link>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-danger">
          <div class="inner"><h3>AED {{ stats.estimated_net_profit }}</h3><p>Est. Net Profit</p></div>
          <i class="small-box-icon bi bi-piggy-bank"></i>
          <router-link to="/admin/accounting" class="small-box-footer link-light link-underline-opacity-0">More info <i class="bi bi-link-45deg"></i></router-link>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-7">
        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title">Sales Trend (Last 7 Days)</h3></div>
          <div class="card-body">
            <div class="position-relative" style="height:300px"><canvas ref="chartCanvas"></canvas></div>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title">Recent Orders</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead><tr><th>#</th><th>Customer</th><th>Status</th><th class="text-end">Total</th></tr></thead>
              <tbody>
                <tr v-if="orders.length === 0"><td colspan="4" class="text-center text-secondary py-3">No recent orders.</td></tr>
                <tr v-for="o in orders" :key="o.id">
                  <td>#{{ o.order_number || o.id }}</td>
                  <td>{{ o.user?.name || '—' }}</td>
                  <td><span class="badge text-bg-info">{{ o.status }}</span></td>
                  <td class="text-end">AED {{ o.total_amount }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6">
        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title">Low Stock Products</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead><tr><th>Product</th><th class="text-end">Stock</th></tr></thead>
              <tbody>
                <tr v-if="lowStock.length === 0"><td colspan="2" class="text-center text-secondary py-3">No low-stock items.</td></tr>
                <tr v-for="p in lowStock" :key="p.id"><td>{{ p.name }}</td><td class="text-end"><span class="badge text-bg-danger">{{ p.stock_quantity }}</span></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card mb-4">
          <div class="card-header"><h3 class="card-title">Recent Customers</h3></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead><tr><th>Name</th><th>Email</th></tr></thead>
              <tbody>
                <tr v-if="customers.length === 0"><td colspan="2" class="text-center text-secondary py-3">No customers yet.</td></tr>
                <tr v-for="c in customers" :key="c.id"><td>{{ c.name }}</td><td class="text-secondary">{{ c.email }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import axios from 'axios';
import Chart from 'chart.js/auto';

const stats = ref({ sales_today:'0.00', sales_this_month:'0.00', net_sales_ex_vat:'0.00', estimated_net_profit:'0.00' });
const orders = ref([]);
const lowStock = ref([]);
const customers = ref([]);
const chartCanvas = ref(null);

async function load() {
  try { const r = await axios.get('/api/reports/accounting'); if (r.data) stats.value = r.data; } catch (e) {}
  try { const r = await axios.get('/api/orders'); orders.value = (r.data || []).slice(0, 5); } catch (e) {}
  try { const r = await axios.get('/api/products'); lowStock.value = (r.data || []).filter(p => (p.stock_quantity || 0) <= (p.low_stock_threshold ?? 5)).slice(0, 5); } catch (e) {}
  try { const r = await axios.get('/api/customers'); customers.value = (r.data || []).slice(0, 5); } catch (e) {}
  await nextTick();
  if (chartCanvas.value) {
    new Chart(chartCanvas.value, {
      type: 'line',
      data: { labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], datasets: [{ label:'Sales', data:[320,450,290,410,520,380,610], borderColor:'#287C8E', backgroundColor:'rgba(40,124,142,0.10)', tension:0.4, fill:true }] },
      options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } }
    });
  }
}
onMounted(load);
</script>
