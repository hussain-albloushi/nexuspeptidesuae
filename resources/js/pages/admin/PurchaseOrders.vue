<template>
  <div class="card mb-4">
    <div class="card-header"><h3 class="card-title">Purchase Orders</h3></div>
    <div class="card-body p-0">
      <table class="table table-striped align-middle mb-0">
        <thead><tr><th>PO #</th><th>Supplier</th><th>Status</th><th>Date</th><th class="text-end">Total</th></tr></thead>
        <tbody>
          <tr v-if="list.length === 0"><td colspan="5" class="text-center text-secondary py-4">No purchase orders found.</td></tr>
          <tr v-for="po in list" :key="po.id">
            <td class="fw-semibold font-monospace">{{ po.po_number }}</td>
            <td>{{ po.supplier?.trading_name || po.supplier?.legal_name || '—' }}</td>
            <td><span class="badge text-bg-info">{{ po.status }}</span></td>
            <td class="text-secondary">{{ (po.created_at || '').split('T')[0] }}</td>
            <td class="text-end fw-semibold">AED {{ po.total_amount || po.total || '0.00' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
const list = ref([]);
onMounted(() => axios.get('/api/purchase-orders').then(r => list.value = r.data || []).catch(() => {}));
</script>
