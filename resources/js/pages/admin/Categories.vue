<template>
  <div class="card mb-4">
    <div class="card-header"><h3 class="card-title">Categories</h3></div>
    <div class="card-body p-0">
      <table class="table table-striped align-middle mb-0">
        <thead><tr><th>Name</th><th>Slug</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-if="list.length === 0"><td colspan="3" class="text-center text-secondary py-4">No categories found.</td></tr>
          <tr v-for="c in list" :key="c.id">
            <td class="fw-semibold">{{ c.name }}</td>
            <td class="text-secondary">{{ c.slug }}</td>
            <td><span class="badge text-bg-success">{{ c.is_active !== false ? 'Active' : 'Inactive' }}</span></td>
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
onMounted(() => axios.get('/api/categories').then(r => list.value = r.data || []).catch(() => {}));
</script>
