<template>
  <div class="card mb-4">
    <div class="card-header"><h3 class="card-title">Coupons</h3></div>
    <div class="card-body p-0">
      <table class="table table-striped align-middle mb-0">
        <thead><tr><th>Code</th><th>Type</th><th class="text-end">Value</th><th class="text-end">Used</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-if="list.length === 0"><td colspan="5" class="text-center text-secondary py-4">No coupons found.</td></tr>
          <tr v-for="c in list" :key="c.id">
            <td class="fw-semibold font-monospace">{{ c.code }}</td>
            <td>{{ c.type || 'fixed' }}</td>
            <td class="text-end">AED {{ c.value ?? c.discount ?? '0.00' }}</td>
            <td class="text-end text-secondary">{{ c.usage_count ?? 0 }}{{ c.usage_limit ? ' / ' + c.usage_limit : '' }}</td>
            <td><span class="badge" :class="c.is_active !== false ? 'text-bg-success' : 'text-bg-secondary'">{{ c.is_active !== false ? 'Active' : 'Inactive' }}</span></td>
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
onMounted(() => axios.get('/api/coupons').then(r => list.value = r.data || []).catch(() => {}));
</script>
