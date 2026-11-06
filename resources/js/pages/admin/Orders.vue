<template>
  <div class="card mb-4">
    <div class="card-header">
      <h3 class="card-title">Orders</h3>
      <div class="card-tools">
        <div class="input-group input-group-sm" style="width:220px">
          <input v-model="q" type="search" class="form-control" placeholder="Search order…" />
          <button class="btn btn-default" type="button"><i class="bi bi-search"></i></button>
        </div>
      </div>
    </div>
    <div class="card-body p-0">
      <table class="table table-striped align-middle mb-0">
        <thead><tr><th>Order #</th><th>Customer</th><th>Status</th><th>Payment</th><th>Date</th><th class="text-end">Total</th></tr></thead>
        <tbody>
          <tr v-if="filtered.length === 0"><td colspan="6" class="text-center text-secondary py-4">No orders found.</td></tr>
          <tr v-for="o in filtered" :key="o.id">
            <td class="fw-semibold">#{{ o.order_number || o.id }}</td>
            <td>{{ o.user?.name || '—' }}</td>
            <td><span class="badge" :class="statusClass(o.status)">{{ o.status }}</span></td>
            <td><span class="badge" :class="o.payment_status === 'paid' ? 'text-bg-success' : 'text-bg-warning'">{{ o.payment_status || 'pending' }}</span></td>
            <td class="text-secondary">{{ (o.created_at || '').split('T')[0] }}</td>
            <td class="text-end fw-semibold">AED {{ o.total_amount }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
const list = ref([]); const q = ref('');
const filtered = computed(() => {
  const t = q.value.toLowerCase();
  if (!t) return list.value;
  return list.value.filter(o => String(o.id).includes(t) || (o.user?.name || '').toLowerCase().includes(t));
});
function statusClass(s) {
  return { pending:'text-bg-secondary', processing:'text-bg-info', shipped:'text-bg-primary', delivered:'text-bg-success', cancelled:'text-bg-danger' }[s] || 'text-bg-secondary';
}
onMounted(() => axios.get('/api/orders').then(r => list.value = r.data || []).catch(() => {}));
</script>
