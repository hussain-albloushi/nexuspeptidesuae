<template>
  <div class="app-wrapper apex-admin-shell">
    <!-- Header -->
    <nav class="app-header navbar navbar-expand bg-body">
      <div class="container-fluid">
        <button type="button" class="apex-mobile-menu" data-lte-toggle="sidebar" aria-label="Toggle navigation"><AdminIcon name="menu" /></button>

        <QuickSearch v-model:mobile-open="mobileSearchOpen" />

        <ul class="navbar-nav ms-auto apex-header-actions">
          <li class="nav-item apex-mobile-search"><button type="button" class="nav-link" aria-label="Search admin panel" :aria-expanded="mobileSearchOpen" @click="mobileSearchOpen = !mobileSearchOpen"><AdminIcon name="search" /></button></li>
          <li class="nav-item apex-header-cta"><router-link to="/admin/orders" class="apex-header-button"><AdminIcon name="orders" :size="15" /> Manage orders</router-link></li>


          <li class="nav-item dropdown">
            <a class="nav-link" data-bs-toggle="dropdown" href="#" aria-label="Color mode">
              <AdminIcon name="moon" />
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><button type="button" class="dropdown-item" data-bs-theme-value="light"><i class="fa-solid fa-sun me-2" aria-hidden="true"></i> Light</button></li>
              <li><button type="button" class="dropdown-item" data-bs-theme-value="dark"><i class="fa-solid fa-moon me-2" aria-hidden="true"></i> Dark</button></li>
              <li><button type="button" class="dropdown-item" data-bs-theme-value="auto"><i class="fa-solid fa-circle-half-stroke me-2" aria-hidden="true"></i> Auto</button></li>
            </ul>
          </li>

          <li class="nav-item"><router-link to="/admin/settings" class="nav-link apex-settings-link" aria-label="Settings"><AdminIcon name="palette" /></router-link></li>

          <li class="nav-item dropdown">
            <button type="button" class="nav-link position-relative" data-bs-toggle="dropdown"
                    :aria-label="`Notifications: ${activeAlerts.length} alert categories`" aria-expanded="false" @click="refreshNotifications">
              <AdminIcon name="bell" />
              <span v-if="activeAlerts.length" class="navbar-badge badge text-bg-danger">{{ activeAlerts.length }}</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end admin-notifications">
              <div class="px-3 py-3 border-bottom"><strong>Needs attention</strong><div class="small text-secondary">Live store alerts · {{ activeAlerts.length }} categories</div></div>
              <div v-if="notificationError" class="px-3 py-3" role="alert">
                <p class="mb-2">Alerts could not be refreshed. {{ notificationData ? 'Showing the last update.' : '' }}</p>
                <button class="btn btn-outline-primary btn-sm" type="button" @click="refreshNotifications">Retry</button>
              </div>
              <div v-if="!notificationData && notificationLoading" class="px-3 py-3" role="status">Loading alerts…</div>
              <div v-else-if="notificationData && !activeAlerts.length" class="px-3 py-4 text-secondary">You're all caught up. No items need attention.</div>
              <router-link v-for="alert in activeAlerts" :key="alert.key" :to="alert.to" class="dropdown-item notification-item">
                <i :class="alert.icon" class="fs-5" :style="{ color: alert.color }" aria-hidden="true"></i>
                <div><strong>{{ notificationData.attention[alert.key] }} {{ alert.label }}</strong><small class="d-block text-secondary">{{ alert.note }}</small></div>
                <i class="fa-solid fa-chevron-right ms-auto" aria-hidden="true"></i>
              </router-link>
              <router-link to="/admin" class="dropdown-item text-center py-3 border-top">View store overview</router-link>
            </div>
          </li>

          <li class="nav-item dropdown user-menu">
            <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-label="Account menu">
              <span class="admin-avatar" aria-hidden="true">{{ adminUser?.name?.charAt(0).toUpperCase() || 'A' }}</span>
              <span class="d-none d-xl-inline">{{ adminUser?.name || 'Administrator' }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
              <li class="user-header text-bg-primary">
                <p>{{ adminUser?.name || 'Administrator' }} <small>{{ adminUser?.email || 'NexusPeptides' }}</small></p>
              </li>
              <li class="user-footer">
                <router-link to="/admin" class="btn btn-outline-secondary">Dashboard</router-link>
                <a href="#" @click.prevent="logout" class="btn btn-outline-danger float-end">Sign out</a>
              </li>
            </ul>
          </li>
        </ul>
      </div>
    </nav>

    <!-- Sidebar -->
    <aside class="app-sidebar">
      <button type="button" class="sidebar-edge-toggle" data-lte-toggle="sidebar" aria-label="Collapse navigation"><AdminIcon name="chevron" :size="14" /></button>
      <div class="sidebar-brand">
        <router-link to="/admin" class="brand-link">
          <span class="apex-brand-mark"><AdminIcon name="bolt" :size="20" /></span>
          <span class="brand-identity"><span class="brand-text">NexusPeptides</span><small>DASHBOARD</small></span>
        </router-link>
      </div>

      <div class="sidebar-wrapper">
        <nav class="apex-navigation" aria-label="Main navigation">
          <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" data-accordion="false" id="navigation">
            <li class="nav-header">OVERVIEW</li>
            <li class="nav-item">
              <router-link to="/admin" class="nav-link" :class="{ active: $route.path === '/admin' }"><AdminIcon name="dashboard" class="nav-icon" /><p>Dashboard</p></router-link>
            </li>
            <li class="nav-header">COMMERCE</li>
            <li class="nav-item">
              <router-link to="/admin/orders" class="nav-link" :class="{ active: $route.path.startsWith('/admin/orders') }"><AdminIcon name="orders" class="nav-icon" /><p>Orders</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/customers" class="nav-link" :class="{ active: $route.path === '/admin/customers' }"><AdminIcon name="users" class="nav-icon" /><p>Customers</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/coupons" class="nav-link" :class="{ active: $route.path === '/admin/coupons' }"><AdminIcon name="ticket" class="nav-icon" /><p>Coupons</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/products" class="nav-link" :class="{ active: $route.path.startsWith('/admin/products') }"><AdminIcon name="box" class="nav-icon" /><p>Products</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/categories" class="nav-link" :class="{ active: $route.path === '/admin/categories' }"><AdminIcon name="tags" class="nav-icon" /><p>Categories</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/inventory" class="nav-link" :class="{ active: $route.path === '/admin/inventory' }"><AdminIcon name="inventory" class="nav-icon" /><p>Inventory</p></router-link>
            </li>

            <li class="nav-header">SUPPLY CHAIN</li>
            <li class="nav-item">
              <router-link to="/admin/purchase-orders" class="nav-link" :class="{ active: $route.path.startsWith('/admin/purchase-orders') }"><AdminIcon name="document" class="nav-icon" /><p>Purchase Orders</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/suppliers" class="nav-link" :class="{ active: $route.path === '/admin/suppliers' }"><AdminIcon name="truck" class="nav-icon" /><p>Suppliers</p></router-link>
            </li>

            <li class="nav-header">FINANCE</li>
            <li class="nav-item">
              <router-link to="/admin/accounting" class="nav-link" :class="{ active: $route.path === '/admin/accounting' }"><AdminIcon name="billing" class="nav-icon" /><p>Accounting</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/sales-report" class="nav-link" :class="{ active: $route.path === '/admin/sales-report' }"><AdminIcon name="chart" class="nav-icon" /><p>Sales Report</p></router-link>
            </li>
            <li class="nav-header">SYSTEM</li>
            <li class="nav-item">
              <router-link to="/admin/settings" class="nav-link" :class="{ active: $route.path === '/admin/settings' }"><AdminIcon name="settings" class="nav-icon" /><p>Settings</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/contents" class="nav-link" :class="{ active: $route.path === '/admin/contents' }"><AdminIcon name="document" class="nav-icon" /><p>Website Content</p></router-link>
            </li>
            <li class="nav-item">
              <a href="/" class="nav-link"><AdminIcon name="store" class="nav-icon" /><p>Storefront</p></a>
            </li>
          </ul>
        </nav>
      </div>
      <div class="sidebar-account">
        <span class="sidebar-avatar">{{ adminUser?.name?.charAt(0).toUpperCase() || 'A' }}</span>
        <div><strong>{{ adminUser?.name || 'Administrator' }}</strong><small>Admin</small></div>
        <button type="button" aria-label="Sign out" @click="logout"><AdminIcon name="logout" :size="17" /></button>
      </div>
    </aside>

    <!-- Main -->
    <main class="app-main">
      <div v-if="!['/admin', '/admin/products', '/admin/orders', '/admin/customers'].includes(route.path)" class="app-content-header">
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-6"><h1 class="mb-0 fs-3">{{ pageTitle }}</h1></div>
            <div class="col-sm-6">
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb float-sm-end">
                  <li class="breadcrumb-item"><router-link to="/admin">Home</router-link></li>
                  <li class="breadcrumb-item active" aria-current="page">{{ pageTitle }}</li>
                </ol>
              </nav>
            </div>
          </div>
        </div>
      </div>
      <div class="app-content">
        <div class="container-fluid"><router-view /></div>
      </div>
    </main>

    <footer class="app-footer">
      <div class="float-end d-none d-sm-inline">NexusPeptides Admin</div>
      <strong>© 2026 NexusPeptides.</strong> All rights reserved.
    </footer>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AdminIcon from './AdminIcon.vue';
import QuickSearch from './QuickSearch.vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useDashboard } from './dashboard';

const route = useRoute();
const adminUser = ref(null);
const mobileSearchOpen = ref(false);
const { data: notificationData, loading: notificationLoading, error: notificationError, refresh: refreshNotifications, start: startNotifications } = useDashboard('/api/dashboard/notifications');
const alertTypes = [
  { key: 'out_of_stock', label: 'products out of stock', icon: 'fa-solid fa-box', color: 'var(--admin-danger)', note: 'Replenish unavailable products', to: { path: '/admin/inventory', query: { status: 'out_of_stock' } } },
  { key: 'low_stock', label: 'products running low', icon: 'fa-solid fa-triangle-exclamation', color: 'var(--admin-warning)', note: 'Stock has reached its alert threshold', to: { path: '/admin/inventory', query: { status: 'low_stock' } } },
  { key: 'failed_payments', label: 'failed payments', icon: 'fa-solid fa-credit-card', color: 'var(--admin-danger)', note: 'Review payment issues in Orders', to: '/admin/orders' },
  { key: 'to_process', label: 'orders awaiting fulfillment', icon: 'fa-solid fa-box-open', color: 'var(--admin-primary)', note: 'Review pending and processing orders', to: '/admin/orders' },
  { key: 'awaiting_payment', label: 'orders awaiting payment', icon: 'fa-solid fa-wallet', color: 'var(--admin-warning)', note: 'Review outstanding payments in Orders', to: '/admin/orders' },
];
const activeAlerts = computed(() => alertTypes.filter((alert) => notificationData.value?.attention[alert.key] > 0));
onMounted(startNotifications);
watch(() => route.path, () => { void refreshNotifications(); });

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/me');
    adminUser.value = data;
  } catch {
    adminUser.value = null;
  }
});

const titles = {
  '/admin': 'Dashboard', '/admin/orders': 'Orders', '/admin/products': 'Products',
  '/admin/categories': 'Categories', '/admin/inventory': 'Inventory', '/admin/customers': 'Customers',
  '/admin/purchase-orders': 'Purchase Orders', '/admin/suppliers': 'Suppliers',
  '/admin/coupons': 'Coupons', '/admin/accounting': 'Accounting',
  '/admin/sales-report': 'Sales Report', '/admin/settings': 'Settings', '/admin/contents': 'Website Content',
  '/admin/search': 'Search Results',
};

const pageTitle = computed(() => {
  if (route.name === 'admin-not-found') return 'Page not found';
  if (titles[route.path]) return titles[route.path];
  if (route.path.startsWith('/admin/orders')) return 'Orders';
  if (route.path.startsWith('/admin/products')) return 'Products';
  if (route.path.startsWith('/admin/purchase-orders')) return 'Purchase Orders';
  return 'Admin';
});
function logout() {
  axios.post('/api/logout').catch(() => {}).finally(() => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = '/login';
  });
}
</script>

<style scoped>
.apex-admin-shell { --lte-sidebar-width: 260px; font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
.app-main { background: var(--admin-canvas); min-width: 0; }
.app-sidebar {
  background: #090909 !important; color: #d1d5d7; border-right: 1px solid var(--admin-border);
  --lte-sidebar-color: #a4aaad; --lte-sidebar-hover-color: #ebeff1; --lte-sidebar-hover-bg: #161616;
  --lte-sidebar-menu-active-bg: #161616; --lte-sidebar-menu-active-color: #009e5a; --lte-sidebar-header-color: #626b70;
  box-shadow: none !important;
}
.sidebar-brand { height: 64px; min-height: 64px; padding: 0 16px; border-bottom: 1px solid var(--admin-border); justify-content: flex-start; }
.sidebar-brand .brand-link { display: flex; align-items: center; gap: 12px; padding: 0; text-decoration: none; }
.apex-brand-mark { display: grid; place-items: center; width: 32px; height: 32px; background: #009e5a; color: #fff; border-radius: 10px; flex-shrink: 0; }
.brand-identity { display: flex; flex-direction: column; gap: 2px; align-items: flex-start; line-height: 1.2; }
.sidebar-brand .brand-text { color: #f5f6f7; font-size: 14px; font-weight: 650; letter-spacing: -.025em; }
.brand-identity small { color: #697379; font-size: 9px; font-weight: 500; letter-spacing: .08em; }
.sidebar-wrapper { padding: 12px 12px 20px; flex: 1; min-height: 0; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #292929 transparent; }
.sidebar-menu { gap: 2px; }
.sidebar-menu .nav-link { display: flex; align-items: center; gap: 12px; min-height: 36px; padding: 8px 12px; border-radius: 10px; font-size: 13px; font-weight: 500; margin: 0; }
.sidebar-menu .nav-icon { width: 17px; height: 17px; margin: 0; flex-shrink: 0; opacity: .75; }
.sidebar-menu .nav-link p { margin: 0; line-height: 20px; }
.sidebar-menu .nav-link.active { box-shadow: none; font-weight: 600; }
.sidebar-menu .nav-link.active .nav-icon { color: #009e5a; opacity: 1; }
.sidebar-menu .nav-header { padding: 14px 12px 6px; color: #626b70; font-size: 10px; font-weight: 500; letter-spacing: .08em; }
.sidebar-menu .nav-header:first-child { padding-top: 8px; }
.sidebar-edge-toggle { position: absolute; top: 80px; right: -12px; z-index: 5; width: 24px; height: 24px; display: grid; place-items: center; border: 1px solid var(--admin-border); background: #fff; color: #303a40; border-radius: 50%; box-shadow: 0 1px 3px #0000000d; padding: 0; }
.sidebar-edge-toggle svg { transform: rotate(180deg); }
.sidebar-account { display: flex; align-items: center; gap: 12px; padding: 20px; min-height: 77px; border-top: 1px solid var(--admin-border); }
.sidebar-avatar { width: 32px; height: 32px; border-radius: 50%; background: #008e4c; color: #fff; display: grid; place-items: center; flex-shrink: 0; font-size: 11px; font-weight: 600; }
.sidebar-account > div { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.sidebar-account strong { color: #e9edf0; font-size: 12px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 145px; }
.sidebar-account small { font-size: 11px; color: #667176; }
.sidebar-account button { background: transparent; border: 0; color: #737e83; padding: 4px; margin-left: auto; }
.app-header { height: 64px; min-height: 64px; border-bottom: 1px solid var(--admin-border); box-shadow: none; background: color-mix(in srgb, var(--admin-canvas) 80%, transparent) !important; backdrop-filter: blur(20px); position: relative; }
.app-header > .container-fluid { padding-left: 24px; padding-right: 24px; gap: 12px; flex-wrap: nowrap; }
.admin-global-search { position: relative; flex: 0 1 288px; max-width: 288px; margin: 0; }
.admin-global-search .navbar-search-field, .admin-global-search:focus-within .navbar-search-field { width: 100%; }
.admin-global-search .form-control { min-height: 36px; height: 36px; padding: 7px 48px 7px 36px; border-radius: 10px; border: 1px solid var(--admin-border); background: transparent; box-shadow: none; font-size: 13px; }
.admin-global-search .form-control::-webkit-search-cancel-button { display: none; }
.admin-global-search .navbar-search-submit { left: 12px; color: #929fa6; }
.search-shortcut { position: absolute; right: 10px; top: 10px; border: 1px solid var(--admin-border); border-radius: 3px; background: var(--admin-inset); color: var(--admin-muted); padding: 0 3px; font: 9px/14px ui-monospace, monospace; }
.apex-header-actions { align-items: center; gap: 6px; }
.apex-header-actions .nav-link { display: flex; align-items: center; justify-content: center; padding: 6px 8px; width: 32px; height: 32px; color: var(--admin-muted); border-radius: 6px; }
.apex-header-actions .nav-link:hover { background: var(--admin-inset); color: var(--admin-text); }
.apex-header-cta { padding-right: 12px; margin-right: 4px; border-right: 1px solid var(--admin-border); }
.apex-header-button { display: inline-flex; align-items: center; gap: 7px; height: 32px; padding: 0 12px; background: var(--admin-primary); color: #fff; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; white-space: nowrap; }
.apex-header-button:hover { color: #fff; filter: brightness(.95); }
.apex-header-actions .user-menu .nav-link { width: auto; }
.user-menu .nav-link > .d-none { display: none !important; }
.admin-avatar { width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; background: color-mix(in srgb, var(--admin-primary) 10%, var(--admin-surface)); color: var(--admin-primary); font-size: 11px; font-weight: 600; }
.user-menu .dropdown-toggle::after { display: none; }
.navbar-badge { min-width: 7px; width: 7px; height: 7px; padding: 0; font-size: 0; border-radius: 50%; right: 5px; top: 5px; }
.admin-notifications { width: min(400px, calc(100vw - 1.5rem)); max-height: min(650px, 80vh); overflow-y: auto; border-radius: 12px; box-shadow: 0 8px 30px #00000014; }
.notification-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; white-space: normal; border-bottom: 1px solid var(--admin-border); }
.notification-item strong { font-size: 13px; font-weight: 500; }.notification-item small { margin-top: 3px; font-size: 12px; }
.app-content { padding: 0; }.app-content > .container-fluid { padding: 0; }
.app-content-header { padding: 24px 24px 0; }.app-content-header h1 { font-size: 24px !important; font-weight: 600; letter-spacing: -.035em; }.app-content-header .breadcrumb { font-size: 12px; }
.app-footer { display: none; }
.apex-mobile-menu, .apex-mobile-search { display: none; }
:global(.sidebar-collapse .apex-admin-shell .brand-identity), :global(.sidebar-collapse .apex-admin-shell .sidebar-account > div), :global(.sidebar-collapse .apex-admin-shell .sidebar-account button) { display: none; }
:global(.sidebar-collapse .apex-admin-shell .sidebar-edge-toggle svg) { transform: none; }
:global(.sidebar-collapse .apex-admin-shell .sidebar-brand) { padding-left: 20px; }
:global(.sidebar-collapse .apex-admin-shell .sidebar-account) { padding-left: 20px; }
@media (max-width: 991px) {
  .apex-mobile-menu { display: grid; place-items: center; border: 0; background: transparent; color: var(--admin-muted); padding: 4px; width: 28px; height: 32px; }
  .sidebar-edge-toggle { display: none; }
}
@media (max-width: 767px) {
  .app-header > .container-fluid { padding-left: 16px; padding-right: 16px; gap: 8px; }
  .apex-header-cta, .admin-global-search { display: none; }
  .apex-mobile-search { display: list-item; }
  .admin-global-search.mobile-open { display: block; position: absolute; top: 64px; left: 12px; right: 12px; width: auto; max-width: none; margin: 0; padding: 8px; background: var(--admin-surface); border: 1px solid var(--admin-border); border-radius: 10px; box-shadow: 0 8px 24px #00000014; z-index: 10; }
  .search-shortcut { display: none; }
  .admin-global-search .form-control { padding-right: 12px; }
  .apex-header-actions { gap: 2px; }
  .app-content-header { padding: 20px 16px 0; }
}
</style>
