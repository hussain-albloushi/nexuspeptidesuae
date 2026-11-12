<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { useRoute, useRouter } from 'vue-router';
import { swalToast } from '../services/alerts';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const orders = ref([]);
const loading = ref(true);
const error = ref(null);

// Status badge styling matches AdminLTE + Tailwind theme
function statusBadge(status) {
    const map = {
        pending:      'bg-secondary/15 text-secondary',
        processing:   'bg-primary/15 text-primary',
        shipped:      'bg-purple-600/15 text-purple-700',
        delivered:    'bg-emerald-500/15 text-emerald-700',
        cancelled:    'bg-danger/15 text-danger',
    };
    return map[status] || 'bg-neutral-200 text-neutral-700';
}

// Format currency consistent with storefront (AED)
function money(value) {
    return new Intl.NumberFormat('en-AE', { minimumFractionDigits: 2 }).format(value);
}

// Date helper
function dateStr(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('en-AE', {
        year: 'numeric', month: 'short', day: 'numeric'
    });
}

async function fetchOrders() {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await axios.get('/api/my-orders');
        orders.value = data || [];
    } catch (e) {
        orders.value = [];
        if (e.response && e.response.status === 401) {
            // The global axios interceptor already cleared the session and sent the customer to log in.
            return;
        } else {
            error.value = 'We could not load your orders. Please try again.';
        }
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    // Customer returning from Stripe Checkout — acknowledge the payment.
    if (route.query.payment === 'success') {
        swalToast('Payment received — thank you! Your order is now marked as paid.');
        const query = { ...route.query };
        delete query.payment;
        router.replace({ query });
    }

    fetchOrders();
});
</script>

<template>
    <div class="wd-container py-14 md:py-16">
        <!-- Breadcrumb -->
        <nav class="mb-6 text-sm text-neutral-500">
            <router-link to="/" class="hover:text-secondary">Home</router-link>
            <span class="mx-2">&rsaquo;</span>
            <router-link to="/my-account" class="hover:text-secondary">My account</router-link>
            <span class="mx-2">&rsaquo;</span>
            <span class="text-ink">My orders</span>
        </nav>

        <!-- Page heading -->
        <h1 class="font-heading text-3xl text-navy md:text-4xl">My orders</h1>

        <!-- Loading state -->
        <div v-if="loading" class="mt-12 flex items-center justify-center gap-3 text-neutral-500">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-primary"></i>
            <span>Loading your orders…</span>
        </div>

        <!-- Auth error / redirect -->
        <div
            v-else-if="error && !auth.isAuthenticated"
            class="mt-12 max-w-xl rounded-card border border-black/10 bg-background p-8 text-center"
        >
            <i class="fa-solid fa-lock text-3xl text-neutral-300"></i>
            <p class="mt-4 text-sm leading-relaxed text-neutral-500">{{ error }}</p>
            <router-link
                to="/my-account"
                class="mt-6 inline-flex items-center rounded-full bg-primary px-8 py-3 text-sm font-semibold text-white transition hover:bg-primary-hover"
            >
                Log in to view orders
            </router-link>
        </div>

        <!-- Load failed while logged in -->
        <div
            v-else-if="error"
            class="mt-12 max-w-xl rounded-card border border-black/10 bg-background p-8 text-center"
        >
            <i class="fa-solid fa-triangle-exclamation text-3xl text-neutral-300"></i>
            <p class="mt-4 text-sm leading-relaxed text-neutral-500">{{ error }}</p>
            <button
                type="button"
                class="mt-6 inline-flex items-center rounded-full bg-primary px-8 py-3 text-sm font-semibold text-white transition hover:bg-primary-hover"
                @click="fetchOrders"
            >
                Try again
            </button>
        </div>

        <!-- No orders -->
        <div
            v-else-if="orders.length === 0 && !loading && !error"
            class="mt-12 mx-auto max-w-xl py-20 text-center"
        >
            <i class="fa-solid fa-box-open text-4xl text-neutral-200"></i>
            <h2 class="mt-6 font-heading text-2xl text-navy">No orders yet</h2>
            <p class="mt-3 text-sm leading-relaxed text-neutral-500">
                You haven't placed any orders with us yet. Browse our catalogue and add products to your cart to get started.
            </p>
            <router-link
                to="/peptide-shop"
                class="mt-8 inline-flex items-center rounded-full bg-primary px-8 py-3 text-sm font-semibold text-white transition hover:bg-primary-hover"
            >
                Go to shop
            </router-link>
        </div>

        <!-- Order list -->
        <div v-else class="mt-10 space-y-6">
            <h2 class="font-heading text-xl text-navy">Order history</h2>

            <div
                v-for="order in orders"
                :key="order.id"
                class="rounded-card border border-black/10 bg-background"
            >
                <!-- Order header -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-black/5 px-6 py-4">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-neutral-400">Order #{{ order.order_number ?? order.id }}</p>
                        <p class="mt-1 text-xs text-neutral-500">{{ dateStr(order.created_at) }}</p>
                    </div>
                    <div class="flex items-center gap-4 text-right">
                        <div class="text-right">
                            <p class="text-xs uppercase tracking-widest text-neutral-400">Total</p>
                            <p class="mt-1 text-sm font-semibold text-navy">AED {{ money(order.total_amount) }}</p>
                        </div>
                        <span :class="[statusBadge(order.status), 'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold capitalize']">
                            <span class="w-1.5 h-1.5 rounded-full bg-current opacity-60"></span>
                            {{ order.status }}
                        </span>
                    </div>
                </div>

                <!-- Order items -->
                <div class="px-6 py-4">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wider text-neutral-400">
                                <th class="pb-3 font-medium">Product</th>
                                <th class="pb-3 font-medium text-center">Qty</th>
                                <th class="pb-3 font-medium text-right">Price</th>
                                <th class="pb-3 font-medium text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-black/5">
                            <tr v-for="item in order.items" :key="item.id">
                                <td class="py-3 pr-4 align-top">{{ item.name }}</td>
                                <td class="py-3 text-center text-neutral-600">{{ item.quantity }}</td>
                                <td class="py-3 text-right text-neutral-600">AED {{ money(item.price) }}</td>
                                <td class="py-3 text-right font-medium text-ink">AED {{ money(item.total_price) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer with order meta -->
                <div class="flex flex-wrap gap-x-8 gap-y-2 border-t border-black/5 bg-white/60 px-6 py-3 text-xs text-neutral-500">
                    <span v-if="order.payment_status">Payment: <strong class="text-ink capitalize">{{ order.payment_status }}</strong></span>
                    <span v-if="order.shipping_status">Shipping: <strong class="text-ink capitalize">{{ order.shipping_status }}</strong></span>
                    <span v-if="order.tracking_number">Tracking: <strong class="text-ink">{{ order.tracking_number }}</strong></span>
                    <span v-if="order.notes"><strong>Notes:</strong> {{ order.notes }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
