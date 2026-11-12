import { createRouter, createWebHistory } from 'vue-router';
import StoreLayout from './layout/StoreLayout.vue';
import { useAuthStore } from './stores/auth';

const routes = [
    {
        path: '/',
        component: StoreLayout,
        children: [
            { path: '', name: 'home', component: () => import('./pages/Home.vue') },

            // Catalogue
            { path: 'all-peptides', name: 'all-peptides', component: () => import('./pages/AllPeptides.vue') },
            { path: 'peptide-shop', name: 'shop', component: () => import('./pages/Shop.vue') },
            { path: 'peptide-shop/page/:page', name: 'shop-page', component: () => import('./pages/Shop.vue') },
            { path: 'product/:slug', name: 'product', component: () => import('./pages/Product.vue') },
            { path: 'product-category/:slug', name: 'category', component: () => import('./pages/Category.vue') },
            { path: 'product-tag/:slug', name: 'tag', component: () => import('./pages/Category.vue') },

            // Content
            { path: 'about-us', name: 'about', component: () => import('./pages/About.vue') },
            { path: 'help-center', name: 'help-center', component: () => import('./pages/HelpCenter.vue') },
            { path: 'coa', name: 'coa', component: () => import('./pages/Coa.vue') },

            // Commerce
            { path: 'cart', name: 'cart', component: () => import('./pages/Cart.vue') },
            { path: 'checkout', name: 'checkout', component: () => import('./pages/Checkout.vue') },
            { path: 'wishlist', name: 'wishlist', component: () => import('./pages/Wishlist.vue') },
            { path: 'my-account', name: 'account', component: () => import('./pages/Account.vue') },
            { path: 'reset-password', name: 'reset-password', component: () => import('./pages/ResetPassword.vue') },
            { path: 'my-orders', name: 'orders', component: () => import('./pages/Orders.vue') },


            // Legal
            { path: 'terms-conditions', name: 'terms', component: () => import('./pages/Legal.vue'), props: { page: 'terms' } },
            { path: 'privacy-policy', name: 'privacy', component: () => import('./pages/Legal.vue'), props: { page: 'privacy' } },
            { path: 'return-refunds', name: 'returns', component: () => import('./pages/Legal.vue'), props: { page: 'returns' } },
            { path: 'shipping-delivery', name: 'shipping', component: () => import('./pages/Legal.vue'), props: { page: 'shipping' } },
            { path: 'disclaimer', name: 'disclaimer', component: () => import('./pages/Legal.vue'), props: { page: 'disclaimer' } },

            { path: ':pathMatch(.*)*', name: 'not-found', component: () => import('./pages/NotFound.vue') },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition;
        if (to.hash) return { el: to.hash, top: 90 };
        return { top: 0 };
    },
});

// Re-check the session on every navigation (throttled to once a minute inside
// pingSession) so a customer whose account was deleted elsewhere is signed out
// automatically instead of browsing on a revoked token. A 401 lands in the
// global response interceptor, which clears the session and sends them to log in.
router.beforeEach(async () => {
    const auth = useAuthStore();
    await auth.pingSession();

    return true;
});

export default router;
