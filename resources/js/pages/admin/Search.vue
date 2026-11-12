<template>
  <div class="card mb-4">
    <div class="card-header"><h3 class="card-title">Search Results</h3></div>
    <div class="card-body">
      <form class="input-group mb-4" role="search" @submit.prevent="search">
        <label for="admin-search-query" class="visually-hidden">Search orders, products, or customers</label>
        <input id="admin-search-query" v-model="q" type="search" class="form-control"
               placeholder="Search orders, products, or customers…" maxlength="255" />
        <button class="btn btn-outline-secondary" type="submit">Search</button>
      </form>

      <div aria-live="polite">
        <div v-if="loading" class="text-center py-4">
          <div class="spinner-border spinner-border-sm text-primary"></div> Searching…
        </div>
        <div v-else-if="error" class="alert alert-danger" role="alert">{{ error }}</div>
        <div v-else-if="!activeQuery" class="text-secondary text-center py-4">
          Enter a search term to find orders, products, or customers.
        </div>
        <div v-else-if="results.length === 0" class="text-secondary text-center py-4">
          No results found for "<strong>{{ activeQuery }}</strong>".
        </div>
        <div v-else>
          <div v-for="(items, section) in resultsBySection" :key="section" class="mb-4">
            <h6 class="text-primary mb-2">{{ sectionLabel(section) }} ({{ items.length }})</h6>
            <div class="list-group">
              <router-link v-for="result in items" :key="result.id"
                           :to="{ path: `/admin/${section}`, query: { q: activeQuery } }"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-semibold">{{ result.name }}</div>
                  <div class="text-secondary fs-7">{{ result.detail }}</div>
                </div>
                <span v-if="result.status" class="badge" :class="statusClass(result.status)">{{ result.status }}</span>
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAdminSearch } from './search';

const { q, results, loading, error, activeQuery, search } = useAdminSearch(useRoute(), useRouter());

function statusClass(status) {
  return { pending: 'text-bg-secondary', processing: 'text-bg-info', shipped: 'text-bg-primary', delivered: 'text-bg-success', cancelled: 'text-bg-danger', active: 'text-bg-success', draft: 'text-bg-secondary' }[status.toLowerCase()] || 'text-bg-secondary';
}

function sectionLabel(section) {
  return { orders: 'Orders', products: 'Products', customers: 'Customers' }[section];
}

const resultsBySection = computed(() => {
  const grouped = {};
  for (const result of results.value) {
    (grouped[result.type] ??= []).push(result);
  }
  return grouped;
});
</script>
