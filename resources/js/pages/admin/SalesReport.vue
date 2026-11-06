<template>
  <div class="row">
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-primary"><div class="inner"><h3>AED {{ report.sales_period ?? '0.00' }}</h3><p>Period Sales</p></div><i class="small-box-icon bi bi-graph-up"></i></div>
    </div>
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-info"><div class="inner"><h3>AED {{ report.co_gs ?? '0.00' }}</h3><p>COGS</p></div><i class="small-box-icon bi bi-box-seam"></i></div>
    </div>
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-success"><div class="inner"><h3>AED {{ report.gross_profit ?? '0.00' }}</h3><p>Gross Profit</p></div><i class="small-box-icon bi bi-cash"></i></div>
    </div>
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-warning"><div class="inner"><h3>AED {{ report.vat_collected ?? '0.00' }}</h3><p>VAT Collected</p></div><i class="small-box-icon bi bi-receipt"></i></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h3 class="card-title">Sales Breakdown</h3></div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tbody>
          <tr><td>Gross Sales</td><td class="text-end">AED {{ report.gross_sales ?? '0.00' }}</td></tr>
          <tr><td>Discounts</td><td class="text-end">AED {{ report.discounts ?? '0.00' }}</td></tr>
          <tr><td>Net Sales (ex VAT)</td><td class="text-end">AED {{ report.net_sales_ex_vat ?? '0.00' }}</td></tr>
          <tr><td>COGS</td><td class="text-end">AED {{ report.co_gs ?? '0.00' }}</td></tr>
          <tr><td>Gross Profit</td><td class="text-end">AED {{ report.gross_profit ?? '0.00' }}</td></tr>
          <tr><td>Expenses</td><td class="text-end">AED {{ report.expenses ?? '0.00' }}</td></tr>
          <tr class="fw-bold"><td>Estimated Net Profit</td><td class="text-end">AED {{ report.estimated_net_profit ?? '0.00' }}</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
const report = ref({});
onMounted(() => axios.get('/api/reports/accounting').then(r => report.value = r.data || {}).catch(() => {}));
</script>
