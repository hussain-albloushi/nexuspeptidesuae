<template>
  <div class="row">
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-primary"><div class="inner"><h3>AED {{ summary.sales_period ?? '0.00' }}</h3><p>Period Sales</p></div><i class="small-box-icon bi bi-cash-stack"></i></div>
    </div>
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-info"><div class="inner"><h3>AED {{ summary.co_gs ?? '0.00' }}</h3><p>COGS</p></div><i class="small-box-icon bi bi-box-seam"></i></div>
    </div>
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-success"><div class="inner"><h3>AED {{ summary.gross_profit ?? '0.00' }}</h3><p>Gross Profit</p></div><i class="small-box-icon bi bi-graph-up"></i></div>
    </div>
    <div class="col-lg-3 col-6">
      <div class="small-box text-bg-danger"><div class="inner"><h3>AED {{ summary.expenses ?? '0.00' }}</h3><p>Expenses</p></div><i class="small-box-icon bi bi-wallet2"></i></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h3 class="card-title">Expenses</h3></div>
    <div class="card-body p-0">
      <table class="table table-striped align-middle mb-0">
        <thead><tr><th>Expense</th><th>Category</th><th>Date</th><th>VAT</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
          <tr v-if="expenses.length === 0"><td colspan="5" class="text-center text-secondary py-4">No expenses found.</td></tr>
          <tr v-for="e in expenses" :key="e.id">
            <td class="fw-semibold">{{ e.name }}</td>
            <td class="text-secondary">{{ e.category || '—' }}</td>
            <td class="text-secondary">{{ e.date }}</td>
            <td><span class="badge text-bg-secondary">{{ e.vat_status || 'n/a' }}</span></td>
            <td class="text-end fw-semibold">AED {{ e.amount }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
const summary = ref({});
const expenses = ref([]);
onMounted(() => {
  axios.get('/api/reports/accounting').then(r => summary.value = r.data || {}).catch(() => {});
  axios.get('/api/expenses').then(r => expenses.value = r.data || []).catch(() => {});
});
</script>
