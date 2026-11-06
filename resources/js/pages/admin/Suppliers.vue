<template>
  <div class="card mb-4">
    <div class="card-header"><h3 class="card-title">Suppliers</h3></div>
    <div class="card-body p-0">
      <table class="table table-striped align-middle mb-0">
        <thead><tr><th>Supplier</th><th>Contact</th><th>Status</th><th>Products</th></tr></thead>
        <tbody>
          <tr v-if="list.length === 0"><td colspan="4" class="text-center text-secondary py-4">No suppliers found.</td></tr>
          <tr v-for="s in list" :key="s.id">
            <td class="fw-semibold">{{ s.trading_name || s.legal_name }}</td>
            <td class="text-secondary">{{ s.email || s.phone || '—' }}</td>
            <td><span class="badge text-bg-success">{{ s.status || 'Active' }}</span></td>
            <td>{{ s.products_count ?? '—' }}</td>
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
onMounted(() => axios.get('/api/suppliers').then(r => list.value = r.data || []).catch(() => {}));
</script>
