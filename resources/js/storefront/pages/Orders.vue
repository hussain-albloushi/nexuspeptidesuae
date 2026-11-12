<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { useCurrencyStore } from '../stores/currency';
import { useRoute, useRouter } from 'vue-router';
import { swalToast } from '../services/alerts';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const currency = useCurrencyStore();
const orders = ref([]);
const loading = ref(true);
const error = ref(null);
const returnForm = ref({ orderId: null, reason: '', lines: {}, busy: false, error: '' });

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

// --- Returns -------------------------------------------------------------

/** Quantity of an order item already covered by non-rejected return requests. */
function returnedQty(order, item) {
    let returned = 0;
    for (const request of order.return_requests || []) {
        if (request.status === 'rejected') continue;
        for (const line of request.items || []) {
            if (line.order_item_id === item.id) returned += line.quantity;
        }
    }
    return returned;
}

function returnableQty(order, item) {
    return Math.max(0, item.quantity - returnedQty(order, item));
}

/** Human label for a return request's lines, e.g. "1 × BPC-157 10mg". */
function returnLineLabel(order, request) {
    const names = new Map((order.items || []).map((item) => [item.id, item.name]));
    const lines = (request.items || []).map((line) => `${line.quantity} × ${names.get(line.order_item_id) || 'item'}`);
    return lines.length ? lines.join(', ') : 'whole order';
}

/** Latest in-review item-level return request, if any. */
function pendingReturn(order) {
    return (order.return_requests || []).find((entry) => entry.status === 'requested' && entry.items?.length);
}

/** Latest approved return request, if any. */
function approvedReturn(order) {
    const approved = (order.return_requests || []).filter((entry) => entry.status === 'approved');
    return approved[approved.length - 1];
}

/** Whether the order can receive a new return request right now. */
function canRequestReturn(order) {
    if (pendingReturn(order)) return false;
    if (order.status !== 'delivered') return false;
    if (order.payment_status === 'refunded') return false;

    const window = Number(currency.settings.returnWindowDays ?? 14);
    if (window <= 0) return false;
    const delivered = (order.status_history || []).find((entry) => entry.new_status === 'delivered');
    const deliveredAt = new Date(delivered?.created_at || order.updated_at);
    if (deliveredAt.getTime() + window * 24 * 60 * 60 * 1000 < Date.now()) return false;

    return (order.items || []).some((item) => returnableQty(order, item) > 0);
}

function startReturn(order) {
    returnForm.value = { orderId: order.id, reason: '', lines: {}, busy: false, error: '' };
}

function cancelReturn() {
    returnForm.value = { orderId: null, reason: '', lines: {}, busy: false, error: '' };
}

async function submitReturn(order) {
    if (returnForm.value.busy) return;
    const items = Object.entries(returnForm.value.lines)
        .filter(([, quantity]) => Number(quantity) > 0)
        .map(([orderItemId, quantity]) => ({ order_item_id: Number(orderItemId), quantity: Number(quantity) }));
    if (!items.length) {
        returnForm.value.error = 'Choose at least one item and quantity to return.';
        return;
    }
    returnForm.value.busy = true;
    returnForm.value.error = '';
    try {
        const { data } = await axios.post(`/api/orders/${order.id}/return`, { reason: returnForm.value.reason, items });
        swalToast(data.message || 'Return request received.');
        cancelReturn();
        await fetchOrders();
    } catch (e) {
        returnForm.value.busy = false;
        returnForm.value.error = (e.response?.data?.message) || 'The return request could not be sent. Please try again.';
    }
}
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

                <!-- Return requests -->
                <div v-if="pendingReturn(order) || approvedReturn(order) || canRequestReturn(order)" class="border-t border-black/5 px-6 py-4">
                    <template v-if="pendingReturn(order)">
                        <p class="flex items-center gap-2 text-sm text-amber-700">
                            <i class="fa-solid fa-clock"></i>
                            Return requested ({{ returnLineLabel(order, pendingReturn(order)) }}) — we are reviewing it and will contact you.
                        </p>
                    </template>
                    <template v-else-if="approvedReturn(order)">
                        <p class="flex items-center gap-2 text-sm text-emerald-700">
                            <i class="fa-solid fa-circle-check"></i>
                            Return approved ({{ returnLineLabel(order, approvedReturn(order)) }})
                            <span v-if="approvedReturn(order).refund_amount">— AED {{ money(approvedReturn(order).refund_amount) }} refunded</span>
                        </p>
                    </template>
                    <template v-if="canRequestReturn(order)">
                        <button
                            v-if="returnForm.orderId !== order.id"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border border-black/10 px-5 py-2 text-sm font-semibold text-navy transition hover:bg-black/5"
                            :class="{ 'mt-3': pendingReturn(order) || approvedReturn(order) }"
                            @click="startReturn(order)"
                        >
                            <i class="fa-solid fa-rotate-left"></i>
                            Request return
                        </button>
                        <form v-else class="max-w-xl space-y-3" :class="{ 'mt-3': pendingReturn(order) || approvedReturn(order) }" @submit.prevent="submitReturn(order)">
                            <div v-if="returnForm.error" class="rounded-card bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{{ returnForm.error }}</div>
                            <fieldset class="space-y-2">
                                <legend class="text-sm font-medium text-ink">Choose what you want to send back</legend>
                                <div
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="flex items-center justify-between gap-3 rounded-card border border-black/10 bg-white px-4 py-2"
                                >
                                    <div>
                                        <p class="text-sm font-medium text-ink">{{ item.name }}</p>
                                        <p class="text-xs text-neutral-500">
                                            AED {{ money(item.price) }} · {{ returnableQty(order, item) }} of {{ item.quantity }} returnable
                                        </p>
                                    </div>
                                    <select
                                        v-if="returnableQty(order, item) > 0"
                                        v-model.number="returnForm.lines[item.id]"
                                        class="rounded-full border border-black/10 bg-background px-3 py-1 text-sm text-ink focus:border-primary focus:outline-none"
                                        :aria-label="'Quantity to return for ' + item.name"
                                    >
                                        <option v-for="n in returnableQty(order, item) + 1" :key="n - 1" :value="n - 1">{{ n - 1 }}</option>
                                    </select>
                                    <span v-else class="text-xs text-neutral-400">Fully returned</span>
                                </div>
                            </fieldset>
                            <label class="block text-sm font-medium text-ink" :for="'return-reason-' + order.id">
                                Tell us what went wrong with these items
                            </label>
                            <textarea
                                :id="'return-reason-' + order.id"
                                v-model="returnForm.reason"
                                rows="3"
                                required
                                minlength="10"
                                maxlength="1000"
                                class="w-full rounded-card border border-black/10 bg-background px-4 py-3 text-sm text-ink focus:border-primary focus:outline-none"
                                placeholder="For example: the vial arrived damaged…"
                            ></textarea>
                            <div class="flex items-center gap-3">
                                <button
                                    type="submit"
                                    class="inline-flex items-center rounded-full bg-primary px-6 py-2 text-sm font-semibold text-white transition hover:bg-primary-hover disabled:opacity-50"
                                    :disabled="returnForm.busy"
                                >
                                    {{ returnForm.busy ? 'Sending…' : 'Send return request' }}
                                </button>
                                <button type="button" class="text-sm text-neutral-500 hover:text-ink" @click="cancelReturn">Cancel</button>
                            </div>
                        </form>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
