<template>
  <form
    ref="root"
    class="navbar-search admin-global-search"
    :class="{ 'mobile-open': mobileOpen }"
    role="search"
    @submit.prevent="submit"
    @keydown.esc.prevent="close"
  >
    <label for="navbar-search-input" class="visually-hidden">Search orders, products, or customers</label>
    <div class="navbar-search-field">
      <input
        id="navbar-search-input"
        ref="input"
        v-model="q"
        type="search"
        class="form-control"
        placeholder="Search anything…"
        autocomplete="off"
        spellcheck="false"
        maxlength="255"
        role="combobox"
        aria-controls="quick-search-results"
        aria-autocomplete="list"
        :aria-expanded="panelOpen ? 'true' : 'false'"
        @focus="panelOpen = true"
        @keydown="onKeydown"
      >
      <span class="navbar-search-submit" aria-hidden="true"><AdminIcon name="search" :size="16" /></span>
      <kbd class="search-shortcut" aria-hidden="true">Ctrl K</kbd>
    </div>

    <div
      v-show="panelOpen"
      id="quick-search-results"
      class="quick-search-panel"
      role="listbox"
      aria-label="Search suggestions"
    >
      <div v-if="loading" class="quick-search-state" role="status">
        <span class="quick-search-spinner" aria-hidden="true"></span> Searching…
      </div>
      <div v-else-if="error" class="quick-search-state quick-search-error" role="alert">
        {{ error }}
        <button type="button" class="btn btn-sm btn-outline-primary mt-2" @click="run">Retry</button>
      </div>
      <div v-else-if="!activeQuery" class="quick-search-state">
        Search records or jump to any section — try an order number, SKU, email, or a page name like “coupons”.
      </div>
      <div v-else-if="!results.length" class="quick-search-state">
        No results for “<strong>{{ activeQuery }}</strong>”.
      </div>
      <template v-else>
        <div v-for="group in groups" :key="group.type" class="quick-search-group">
          <div class="quick-search-group-label">
            {{ group.label }}<span class="quick-search-count">{{ group.items.length }}</span>
          </div>
          <a
            v-for="item in group.items"
            :key="`${group.type}-${item.id}`"
            :href="hrefFor(item)"
            class="quick-search-item"
            role="option"
            :aria-selected="activeIndex === item.index ? 'true' : 'false'"
            :class="{ active: activeIndex === item.index }"
            @mouseenter="activeIndex = item.index"
            @click.prevent="go(item)"
          >
            <span class="quick-search-icon" aria-hidden="true"><AdminIcon :name="iconFor(group.type)" :size="15" /></span>
            <span class="quick-search-text">
              <strong>{{ item.name }}</strong>
              <small>{{ item.detail }}</small>
            </span>
            <span v-if="item.status" class="badge" :class="statusClass(item.status)">{{ item.status }}</span>
          </a>
        </div>
        <router-link :to="{ path: '/admin/search', query: { q: activeQuery } }" class="quick-search-footer" @click="close">
          View all results for “{{ activeQuery }}”
        </router-link>
      </template>
    </div>
  </form>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import { searchQuery, useQuickSearch } from './search.js';

const props = defineProps({ mobileOpen: { type: Boolean, default: false } });
const emit = defineEmits(['update:mobileOpen']);

const route = useRoute();
const router = useRouter();
const root = ref(null);
const input = ref(null);
const panelOpen = ref(false);
const activeIndex = ref(-1);
const { q, results, loading, error, activeQuery, run } = useQuickSearch();

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

/** Results grouped by section, each row carrying its flat keyboard index. */
const groups = computed(() => {
  const grouped = {};
  for (const result of results.value) {
    (grouped[result.type] ??= []).push(result);
  }

  let index = 0;
  return Object.entries(grouped).map(([type, items]) => ({
    type,
    label: sectionMeta[type]?.label ?? type,
    items: items.map((item) => ({ ...item, index: index++ })),
  }));
});

const flat = computed(() => groups.value.flatMap((group) => group.items));

function statusClass(status) {
  return {
    pending: 'text-bg-secondary', processing: 'text-bg-info', shipped: 'text-bg-primary',
    delivered: 'text-bg-success', cancelled: 'text-bg-danger',
    active: 'text-bg-success', draft: 'text-bg-secondary',
  }[String(status).toLowerCase()] || 'text-bg-secondary';
}

function hrefFor(item) {
  return router.resolve(item.link ?? { path: '/admin/search', query: { q: activeQuery.value } }).href;
}

async function go(item) {
  close();
  input.value?.blur();
  await router.push(item.link ?? { path: '/admin/search', query: { q: activeQuery.value } });
}

async function submit() {
  const query = searchQuery(q.value);
  close();
  input.value?.blur();
  await router.push({ path: '/admin/search', query: query ? { q: query } : {} });
}

function onKeydown(event) {
  if (event.key === 'Enter' && activeIndex.value >= 0 && flat.value[activeIndex.value]) {
    event.preventDefault();
    void go(flat.value[activeIndex.value]);
    return;
  }

  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault();
    if (!flat.value.length) return;
    panelOpen.value = true;
    const last = flat.value.length - 1;
    const delta = event.key === 'ArrowDown' ? 1 : -1;
    activeIndex.value = activeIndex.value === -1
      ? (delta === 1 ? 0 : last)
      : (activeIndex.value + delta + flat.value.length) % flat.value.length;
  }
}

function close() {
  panelOpen.value = false;
  activeIndex.value = -1;
  if (props.mobileOpen) emit('update:mobileOpen', false);
}

/** Any click outside the search box dismisses the panel. */
function onDocumentMousedown(event) {
  if (panelOpen.value && root.value && !root.value.contains(event.target)) close();
}

function onWindowKeydown(event) {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault();
    if (window.matchMedia('(max-width: 767px)').matches) {
      emit('update:mobileOpen', true);
      return;
    }
    panelOpen.value = true;
    void nextTick(() => {
      input.value?.focus();
      input.value?.select();
    });
  }
}

watch(q, () => { activeIndex.value = -1; });

watch(() => props.mobileOpen, async (open) => {
  if (!open) return;
  panelOpen.value = true;
  await nextTick();
  input.value?.focus();
});

watch(() => route.fullPath, close);

onMounted(() => {
  document.addEventListener('mousedown', onDocumentMousedown);
  window.addEventListener('keydown', onWindowKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocumentMousedown);
  window.removeEventListener('keydown', onWindowKeydown);
});
</script>

<style scoped>
.quick-search-panel {
  position: absolute; top: calc(100% + 8px); left: 0; width: min(420px, calc(100vw - 2rem));
  background: var(--admin-surface); border: 1px solid var(--admin-border); border-radius: 12px;
  box-shadow: 0 12px 32px #0000001a; max-height: min(480px, 70vh); overflow-y: auto; z-index: 20; padding: 6px;
}
.quick-search-state { padding: 14px 12px; font-size: 13px; color: var(--admin-muted); }
.quick-search-state .btn { display: inline-flex; }
.quick-search-error { color: var(--admin-danger, #b91c1c); }
.quick-search-spinner {
  display: inline-block; width: 12px; height: 12px; margin-right: 6px; vertical-align: -1px;
  border: 2px solid var(--admin-border); border-top-color: var(--admin-primary); border-radius: 50%;
  animation: quick-search-spin .7s linear infinite;
}
@keyframes quick-search-spin { to { transform: rotate(360deg); } }

.quick-search-group-label {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 10px 4px; color: var(--admin-muted); font-size: 10px; font-weight: 600;
  letter-spacing: .08em; text-transform: uppercase;
}
.quick-search-count { font-weight: 500; }
.quick-search-item {
  display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 9px;
  color: var(--admin-text); text-decoration: none; cursor: pointer; font-size: 13px;
}
.quick-search-item:hover, .quick-search-item.active { background: var(--admin-inset); }
.quick-search-item strong { display: block; font-size: 13px; font-weight: 600; line-height: 1.3; }
.quick-search-item small { display: block; color: var(--admin-muted); font-size: 11.5px; line-height: 1.35; }
.quick-search-icon {
  display: grid; place-items: center; width: 28px; height: 28px; flex-shrink: 0;
  border-radius: 8px; background: var(--admin-inset); color: var(--admin-primary);
}
.quick-search-item .badge { margin-left: auto; flex-shrink: 0; }
.quick-search-text { min-width: 0; flex: 1; }
.quick-search-text strong, .quick-search-text small {
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px;
}
.quick-search-footer {
  display: block; padding: 10px 12px; margin-top: 4px; border-top: 1px solid var(--admin-border);
  color: var(--admin-primary); font-size: 12.5px; font-weight: 600; text-align: center; text-decoration: none;
  border-radius: 0 0 10px 10px;
}
.quick-search-footer:hover { background: var(--admin-inset); color: var(--admin-primary); }
</style>
