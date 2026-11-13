import { createRouter, createWebHistory } from 'vue-router';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/admin', component: () => import('./pages/admin/AdminLayout.vue'), meta: { requiresAuth: true }, children: [
            { path: '', name: 'admin', component: () => import('./pages/admin/Dashboard.vue') },
            { path: 'orders', name: 'admin-orders', component: () => import('./pages/admin/Orders.vue') },
            { path: 'products', name: 'admin-products', component: () => import('./pages/admin/Products.vue') },
            { path: 'categories', name: 'admin-categories', component: () => import('./pages/admin/Categories.vue') },
            { path: 'inventory', name: 'admin-inventory', component: () => import('./pages/admin/Inventory.vue') },
            { path: 'customers', name: 'admin-customers', component: () => import('./pages/admin/Customers.vue') },
            { path: 'purchase-orders', name: 'admin-purchase-orders', component: () => import('./pages/admin/PurchaseOrders.vue') },
            { path: 'suppliers', name: 'admin-suppliers', component: () => import('./pages/admin/Suppliers.vue') },
            { path: 'coupons', name: 'admin-coupons', component: () => import('./pages/admin/Coupons.vue') },
            { path: 'accounting', name: 'admin-accounting', component: () => import('./pages/admin/Accounting.vue') },
            { path: 'sales-report', name: 'admin-sales-report', component: () => import('./pages/admin/SalesReport.vue') },
            { path: 'settings', name: 'admin-settings', component: () => import('./pages/admin/Settings.vue') },
            { path: 'account', name: 'admin-account', component: () => import('./pages/admin/AccountSettings.vue') },
            { path: 'email-templates', name: 'admin-email-templates', component: () => import('./pages/admin/EmailTemplates.vue') },
            { path: 'contents', name: 'admin-contents', component: () => import('./pages/admin/Contents.vue') },
            { path: 'search', name: 'admin-search', component: () => import('./pages/admin/Search.vue') },

            { path: 'ai-overview', name: 'admin-ai-overview', component: () => import('./pages/admin/AiOverview.vue') },
            { path: 'ai-business-analytics', name: 'admin-ai-business-analytics', component: () => import('./pages/admin/BusinessAnalytics.vue') },
            { path: 'ai-product-intelligence', name: 'admin-ai-product-intelligence', component: () => import('./pages/admin/ProductIntelligence.vue') },
            { path: 'ai-sales-intelligence', name: 'admin-ai-sales-intelligence', component: () => import('./pages/admin/SalesIntelligence.vue') },
            { path: 'ai-profit-intelligence', name: 'admin-ai-profit-intelligence', component: () => import('./pages/admin/ProfitIntelligence.vue') },
            { path: 'ai-inventory-intelligence', name: 'admin-ai-inventory-intelligence', component: () => import('./pages/admin/InventoryIntelligence.vue') },
            { path: 'ai-recommendations', name: 'admin-ai-recommendations', component: () => import('./pages/admin/AiRecommendations.vue') },
            { path: 'ai-business-chat', name: 'admin-ai-business-chat', component: () => import('./pages/admin/AiBusinessChat.vue') },
            { path: 'ai-reports', name: 'admin-ai-reports', component: () => import('./pages/admin/AiReports.vue') },
            { path: 'ai-settings', name: 'admin-ai-settings', component: () => import('./pages/admin/AiSettings.vue') },

            // Wrong admin URLs land on a themed 404 instead of redirecting silently.
            { path: ':pathMatch(.*)*', name: 'admin-not-found', component: () => import('./pages/admin/NotFound.vue') },
        ]},
        { path: '/login', name: 'login', component: () => import('./pages/Login.vue') },
        { path: '/:pathMatch(.*)*', redirect: '/admin' },
    ],
});

router.beforeEach((to) => {
    const token = localStorage.getItem('token');
    const user = JSON.parse(localStorage.getItem('user') || 'null');
    const isAdmin = token && user?.role === 'admin';

    if (to.meta.requiresAuth && !isAdmin) {
        localStorage.removeItem('token');
        return '/login';
    }
    if (to.path === '/login' && isAdmin) {
        return '/admin';
    }
});

export default router;
