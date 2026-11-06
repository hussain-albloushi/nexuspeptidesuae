<template>
  <div class="card mb-4">
    <div class="card-header">
      <h3 class="card-title">Products</h3>
      <div class="card-tools">
        <div class="input-group input-group-sm" style="width:220px">
          <input v-model="q" type="search" class="form-control" placeholder="Search product…" />
          <button class="btn btn-default" type="button"><i class="bi bi-search"></i></button>
        </div>
      </div>
    </div>
    <div class="card-body p-0">
      <table class="table table-striped align-middle mb-0">
        <thead><tr><th>Product</th><th>Category</th><th class="text-end">Price</th><th class="text-end">Stock</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-if="filtered.length === 0"><td colspan="5" class="text-center text-secondary py-4">No products found.</td></tr>
          <tr v-for="p in filtered" :key="p.id">
            <td class="fw-semibold">{{ p.name }}</td>
            <td class="text-secondary">{{ p.category?.name || '—' }}</td>
            <td class="text-end">AED {{ p.price }}</td>
            <td class="text-end"><span class="badge" :class="stockBadge(p)">{{ p.stock_quantity ?? 0 }}</span></td>
            <td><span class="badge" :class="p.is_active ? 'text-bg-success' : 'text-bg-secondary'">{{ p.is_active ? 'Active' : 'Draft' }}</span></td>
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
  return t ? list.value.filter(p => p.name?.toLowerCase().includes(t)) : list.value;
});
function stockBadge(p) {
  const qty = p.stock_quantity ?? 0;
  const thr = p.low_stock_threshold ?? 5;
  if (qty <= 0) return 'text-bg-danger';
  if (qty <= thr) return 'text-bg-warning';
  return 'text-bg-success';
}
onMounted(() => axios.get('/api/products').then(r => list.value = r.data || []).catch(() => {}));
</script>
