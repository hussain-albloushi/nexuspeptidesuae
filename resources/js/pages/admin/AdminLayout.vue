<template>
  <div class="app-wrapper">
    <!-- Header -->
    <nav class="app-header navbar navbar-expand bg-body">
      <div class="container-fluid">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Toggle sidebar"><i class="bi bi-list"></i></a>
          </li>
          <li class="nav-item d-none d-md-block">
            <router-link to="/admin" class="nav-link"><i class="bi bi-grid-1x2 me-1"></i> Dashboard</router-link>
          </li>
          <li class="nav-item d-none d-md-block">
            <a href="/" class="nav-link"><i class="bi bi-shop me-1"></i> Storefront</a>
          </li>
        </ul>

        <form class="navbar-search d-none d-xl-block ms-3" role="search" @submit.prevent>
          <label for="navbar-search-input" class="visually-hidden">Search</label>
          <div class="navbar-search-field">
            <input type="search" id="navbar-search-input" class="form-control" placeholder="Search…" autocomplete="off" />
            <button class="navbar-search-submit" type="submit" aria-label="Submit search"><i class="bi bi-search" aria-hidden="true"></i></button>
          </div>
        </form>

        <ul class="navbar-nav ms-auto">
          <li class="nav-item dropdown">
            <a class="nav-link" data-bs-toggle="dropdown" href="#" aria-label="Notifications"><i class="bi bi-bell-fill"></i><span class="navbar-badge badge text-bg-warning">3</span></a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
              <span class="dropdown-item dropdown-header">3 Notifications</span>
              <div class="dropdown-divider"></div>
              <a href="#" class="dropdown-item"><i class="bi bi-envelope me-2"></i> New order received <span class="float-end text-secondary fs-7">3 mins</span></a>
              <div class="dropdown-divider"></div>
              <a href="#" class="dropdown-item"><i class="bi bi-people-fill me-2"></i> New customer registered <span class="float-end text-secondary fs-7">12 mins</span></a>
              <div class="dropdown-divider"></div>
              <a href="#" class="dropdown-item dropdown-footer">See All Notifications</a>
            </div>
          </li>

          <li class="nav-item dropdown">
            <a class="nav-link" data-bs-toggle="dropdown" href="#" aria-label="Color mode">
              <i class="bi bi-circle-half"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><button type="button" class="dropdown-item" data-bs-theme-value="light"><i class="bi bi-sun-fill me-2"></i> Light</button></li>
              <li><button type="button" class="dropdown-item" data-bs-theme-value="dark"><i class="bi bi-moon-fill me-2"></i> Dark</button></li>
              <li><button type="button" class="dropdown-item" data-bs-theme-value="auto"><i class="bi bi-circle-half me-2"></i> Auto</button></li>
            </ul>
          </li>

          <li class="nav-item dropdown user-menu">
            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
              <span class="d-none d-xl-inline">Admin</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
              <li class="user-header text-bg-primary">
                <p>Admin <small>NexusPeptides</small></p>
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
    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
      <div class="sidebar-brand">
        <router-link to="/admin" class="brand-link">
          <i class="bi bi-capsule brand-image opacity-75 shadow fs-4"></i>
          <span class="brand-text fw-light">NexusPeptides</span>
        </router-link>
      </div>

      <div class="sidebar-search" role="search">
        <label for="sidebar-search-input" class="visually-hidden">Filter menu</label>
        <input type="search" id="sidebar-search-input" class="form-control form-control-sm" placeholder="Filter menu…" autocomplete="off" data-lte-toggle="sidebar-search" data-lte-target="#navigation" />
        <p class="fs-7 text-secondary mt-2 mb-0" data-lte-search-empty role="status" hidden>No matching pages.</p>
      </div>

      <div class="sidebar-wrapper">
        <nav class="mt-2" aria-label="Main navigation">
          <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" data-accordion="false" id="navigation">
            <li class="nav-item">
              <router-link to="/admin" class="nav-link" :class="{ active: $route.path === '/admin' }"><i class="nav-icon bi bi-speedometer2"></i><p>Dashboard</p></router-link>
            </li>
            <li class="nav-header">SALES</li>
            <li class="nav-item">
              <router-link to="/admin/orders" class="nav-link" :class="{ active: $route.path.startsWith('/admin/orders') }"><i class="nav-icon bi bi-receipt"></i><p>Orders</p></router-link>
            </li>
            <li class="nav-header">CATALOG</li>
            <li class="nav-item">
              <router-link to="/admin/products" class="nav-link" :class="{ active: $route.path.startsWith('/admin/products') }"><i class="nav-icon bi bi-box-seam-fill"></i><p>Products</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/categories" class="nav-link" :class="{ active: $route.path === '/admin/categories' }"><i class="nav-icon bi bi-tags-fill"></i><p>Categories</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/inventory" class="nav-link" :class="{ active: $route.path === '/admin/inventory' }"><i class="nav-icon bi bi-clipboard-data"></i><p>Inventory</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/customers" class="nav-link" :class="{ active: $route.path === '/admin/customers' }"><i class="nav-icon bi bi-people-fill"></i><p>Customers</p></router-link>
            </li>
            <li class="nav-header">PROCUREMENT</li>
            <li class="nav-item">
              <router-link to="/admin/purchase-orders" class="nav-link" :class="{ active: $route.path.startsWith('/admin/purchase-orders') }"><i class="nav-icon bi bi-file-earmark-text"></i><p>Purchase Orders</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/suppliers" class="nav-link" :class="{ active: $route.path === '/admin/suppliers' }"><i class="nav-icon bi bi-truck"></i><p>Suppliers</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/coupons" class="nav-link" :class="{ active: $route.path === '/admin/coupons' }"><i class="nav-icon bi bi-ticket-perforated"></i><p>Coupons</p></router-link>
            </li>
            <li class="nav-header">FINANCE</li>
            <li class="nav-item">
              <router-link to="/admin/accounting" class="nav-link" :class="{ active: $route.path === '/admin/accounting' }"><i class="nav-icon bi bi-cash-stack"></i><p>Accounting</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/sales-report" class="nav-link" :class="{ active: $route.path === '/admin/sales-report' }"><i class="nav-icon bi bi-graph-up"></i><p>Sales Report</p></router-link>
            </li>
            <li class="nav-header">SYSTEM</li>
            <li class="nav-item">
              <router-link to="/admin/settings" class="nav-link" :class="{ active: $route.path === '/admin/settings' }"><i class="nav-icon bi bi-gear-fill"></i><p>Settings</p></router-link>
            </li>
            <li class="nav-item">
              <router-link to="/admin/contents" class="nav-link" :class="{ active: $route.path === '/admin/contents' }"><i class="nav-icon bi bi-file-richtext"></i><p>Website Content</p></router-link>
            </li>
            <li class="nav-item">
              <a href="/" class="nav-link"><i class="nav-icon bi bi-shop"></i><p>Storefront</p></a>
            </li>
          </ul>
        </nav>
      </div>
    </aside>

    <!-- Main -->
    <main class="app-main">
      <div class="app-content-header">
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
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
const route = useRoute();
const titles = {
  '/admin': 'Dashboard', '/admin/orders': 'Orders', '/admin/products': 'Products',
  '/admin/categories': 'Categories', '/admin/inventory': 'Inventory', '/admin/customers': 'Customers',
  '/admin/purchase-orders': 'Purchase Orders', '/admin/suppliers': 'Suppliers',
  '/admin/coupons': 'Coupons', '/admin/accounting': 'Accounting',
  '/admin/sales-report': 'Sales Report', '/admin/settings': 'Settings', '/admin/contents': 'Website Content',
};
const pageTitle = computed(() => {
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
