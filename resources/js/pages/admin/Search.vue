<template>
  <div class="card mb-4">
    <div class="card-header"><h3 class="card-title">Search Results</h3></div>
    <div class="card-body">
      <form class="input-group mb-3" role="search" @submit.prevent="search">
        <label for="admin-search-query" class="visually-hidden">Search orders, products, or customers</label>
        <input id="admin-search-query" v-model="q" type="search" class="form-control"
               placeholder="Search orders, products, or customers…" maxlength="255" autocomplete="off" spellcheck="false" />
        <button class="btn btn-outline-secondary" type="submit">Search</button>
      </form>
      <p class="form-text">
        Find orders by number or customer, products by name or SKU, customers by name, email, or phone —
        plus coupons, suppliers, purchase orders, categories, and expenses. Section names like
        “settings” or “inventory” link straight to that page.
      </p>

      <div aria-live="polite">
        <div v-if="loading" class="text-center py-4">
          <div class="spinner-border spinner-border-sm text-primary"></div> Searching…
        </div>
        <div v-else-if="error" class="alert alert-danger d-flex justify-content-between align-items-center" role="alert">
          <span>{{ error }}</span>
          <button type="button" class="btn btn-sm btn-outline-danger" @click="search">Retry</button>
        </div>
        <div v-else-if="!activeQuery" class="text-secondary text-center py-4">
          Enter a search term to find orders, products, or customers.
        </div>
        <div v-else-if="results.length === 0" class="text-center py-4">
          <p class="text-secondary mb-1">No results found for "<strong>{{ activeQuery }}</strong>".</p>
          <p class="text-secondary small mb-0">Check the spelling, or try an order number, SKU, or customer email.</p>
        </div>
        <div v-else>
          <p class="text-secondary small">
            {{ results.length }} {{ results.length === 1 ? 'result' : 'results' }} for
            “<strong>{{ activeQuery }}</strong>”
          </p>

          <section v-for="group in groups" :key="group.type" class="mb-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="text-primary mb-0 d-flex align-items-center gap-2">
                <AdminIcon :name="iconFor(group.type)" :size="15" />
                {{ group.label }}
                <span class="badge text-bg-light">{{ group.items.length }}</span>
              </h6>
              <router-link
                v-if="group.type !== 'sections'"
                :to="viewAllTarget(group)"
                class="small text-decoration-none"
              >
                View all <i class="fa-solid fa-chevron-right ms-1" aria-hidden="true"></i>
              </router-link>
            </div>
            <div class="list-group">
              <router-link v-for="result in group.items" :key="`${group.type}-${result.id}`"
                           :to="result.link ?? viewAllTarget(group)"
                           class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                <span class="search-result-icon" aria-hidden="true"><AdminIcon :name="iconFor(group.type)" :size="16" /></span>
                <span class="flex-grow-1 min-w-0">
                  <span class="d-block fw-semibold text-truncate">{{ result.name }}</span>
                  <span class="d-block text-secondary fs-7 text-truncate">{{ result.detail }}</span>
                </span>
                <span v-if="result.status" class="badge" :class="statusClass(result.status)">{{ result.status }}</span>
                <i class="fa-solid fa-chevron-right text-secondary small" aria-hidden="true"></i>
              </router-link>
            </div>
          </section>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import { useAdminSearch } from './search';

const route = useRoute();
const router = useRouter();
const { q, results, loading, error, activeQuery, search } = useAdminSearch(route, router);

const sectionMeta = {
  sections: { label: 'Jump to', icon: 'bolt' },
  orders: { label: 'Orders', icon: 'orders' },
  products: { label: 'Products', icon: 'box' },
  customers: { label: 'Customers', icon: 'users' },
  coupons: { label: 'Coupons', icon: 'ticket' },
  suppliers: { label: 'Suppliers', icon: 'truck' },
  'purchase-orders': { label: 'Purchase Orders', icon: 'document' },
  categories: { label: 'Categories', icon: 'tags' },
  expenses: { label: 'Expenses', icon: 'billing' },
};
const iconFor = (type) => sectionMeta[type]?.icon ?? 'search';

/** The admin page each result type opens — Expenses live under Accounting. */
const sectionPage = {
  orders: '/admin/orders',
  products: '/admin/products',
  customers: '/admin/customers',
  coupons: '/admin/coupons',
  suppliers: '/admin/suppliers',
  'purchase-orders': '/admin/purchase-orders',
  categories: '/admin/categories',
  expenses: '/admin/accounting',
};

function viewAllTarget(group) {
  return { path: sectionPage[group.type] ?? '/admin', query: { q: activeQuery.value } };
}

function statusClass(status) {
  return {
    pending: 'text-bg-secondary', processing: 'text-bg-info', shipped: 'text-bg-primary',
    delivered: 'text-bg-success', cancelled: 'text-bg-danger',
    active: 'text-bg-success', draft: 'text-bg-secondary',
  }[String(status).toLowerCase()] || 'text-bg-secondary';
}

const groups = computed(() => {
  const grouped = {};
  for (const result of results.value) {
    (grouped[result.type] ??= []).push(result);
  }
  return Object.entries(grouped).map(([type, items]) => ({
    type, label: sectionMeta[type]?.label ?? type, items,
  }));
});
</script>

<style scoped>
.search-result-icon {
  display: grid; place-items: center; width: 30px; height: 30px; flex-shrink: 0;
  border-radius: 8px; background: var(--admin-inset); color: var(--admin-primary);
}
.min-w-0 { min-width: 0; }
.list-group-item .badge { flex-shrink: 0; }
</style>
