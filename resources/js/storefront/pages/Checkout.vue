<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useCartStore } from '../stores/cart';
import { useContentStore } from '../stores/content';
import { useCurrencyStore } from '../stores/currency';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';
import { swal, swalToast } from '../services/alerts';
import PriceDisplay from '../components/PriceDisplay.vue';
import ShippingProgress from '../components/ShippingProgress.vue';
import { UAE_CITIES } from '../constants';

const router = useRouter();
const cart = useCartStore();
const currency = useCurrencyStore();
const content = useContentStore();
const auth = useAuthStore();
const site = computed(() => content.site);

const form = ref({
    name: '',
    email: '',
    phone: '',
    address: '',
    zip: '',
    city: '',
    country: 'United Arab Emirates',
    payment: 'cod',
    coupon: '',
    comment: '',
    ageConfirmed: false,
    saveForLater: true,
});

const appliedCoupon = ref(null);
const submitting = ref(false);

// Pre-fill from the details saved on the customer's account, if any.
onMounted(async () => {
    if (!auth.isAuthenticated) {
        return;
    }

    try {
        await auth.refreshUser();
    } catch {
        // Stale token — prefill just keeps the account name/email.
    }

    const saved = auth.user?.saved_address;

    if (saved) {
        Object.assign(form.value, {
            name: saved.name ?? form.value.name,
            email: saved.email ?? form.value.email,
            phone: saved.phone ?? form.value.phone,
            address: saved.address ?? form.value.address,
            zip: saved.zip ?? form.value.zip,
            city: saved.city ?? form.value.city,
            country: saved.country ?? form.value.country,
        });
    } else if (auth.user) {
        form.value.name = auth.user.name ?? '';
        form.value.email = auth.user.email ?? '';
    }
});

const couponDiscount = computed(() => {
    const coupon = appliedCoupon.value;
    if (!coupon) return 0;

    // Stop applying the discount if the cart ever drops below the minimum order.
    if (coupon.minOrderAmount && cart.subtotal < Number(coupon.minOrderAmount)) {
        return 0;
    }

    const subtotal = cart.subtotal;
    let amount = coupon.type === 'percentage'
        ? subtotal * (Number(coupon.value) / 100)
        : Math.min(Number(coupon.value), subtotal);

    if (coupon.maxDiscount != null) {
        amount = Math.min(amount, Number(coupon.maxDiscount));
    }

    return Math.max(0, Math.min(subtotal, Math.round(amount * 100) / 100));
});

const couponApplied = computed(() => Boolean(appliedCoupon.value));

// COD handling fee — added to the total only when cash on delivery is selected.
const codFee = computed(() => (
    form.value.payment === 'cod'
        ? Math.max(0, Number(currency.settings.codFee ?? 0))
        : 0
));

const finalTotal = computed(() => Math.max(0, cart.total - couponDiscount.value) + codFee.value);

function removeCoupon() {
    appliedCoupon.value = null;
    form.value.coupon = '';
}

async function applyCoupon() {
    const code = form.value.coupon.trim();

    if (!code) {
        return;
    }

    try {
        const { data } = await axios.post('/api/coupons/validate', {
            code,
            orderTotal: cart.subtotal,
        });

        if (!data.ok) {
            appliedCoupon.value = null;
            swalToast(data.message || 'This coupon code is not valid.', 'error');
            return;
        }

        appliedCoupon.value = data;
        swalToast(`Coupon ${data.code} applied.`);
    } catch (err) {
        appliedCoupon.value = null;
        const message = err?.response?.data?.message;
        swalToast(message || 'Unable to validate this coupon right now.', 'error');
    }
}

async function placeOrder() {
    const f = form.value;
    const required = ['name', 'email', 'phone', 'address', 'city', 'country'];
    const missing = required.filter((k) => !String(f[k]).trim());

    if (cart.isEmpty) {
        swalToast('Your shopping cart is empty.', 'error');
        return;
    }
    if (missing.length) {
        swalToast('Please complete all required fields.', 'error');
        return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email)) {
        swalToast('Please enter a valid email address.', 'error');
        return;
    }

    if (currency.settings.ageConfirmation && !f.ageConfirmed) {
        swalToast('You must confirm you are 18+ to place an order.', 'error');
        return;
    }

    submitting.value = true;

    const shippingAddress = {
        name: f.name,
        email: f.email,
        phone: f.phone,
        address: f.address,
        zip: f.zip || '',
        city: f.city,
        country: f.country,
    };

    try {
        const { data: order } = await axios.post('/api/orders', {
            items: cart.lines.map((line) => ({ slug: line.product.slug, qty: line.qty })),
            shipping_address: shippingAddress,
            payment_method: f.payment,
            coupon_code: appliedCoupon.value ? appliedCoupon.value.code : null,
            notes: f.comment || null,
        });

        cart.clear();
        removeCoupon();

        // Remember the delivery details for the next checkout (best effort).
        if (f.saveForLater && auth.isAuthenticated) {
            try {
                await auth.saveAddress(shippingAddress);
            } catch {
                // Non-fatal — the order has already been placed.
            }
        }

        if (order.checkout_url) {
            // Stripe Checkout (hosted redirect). The cart is already cleared;
            // the webhook records the payment once the customer pays.
            window.location.assign(order.checkout_url);
            return;
        }

        const demoNote = order.dummy_payment
            ? '<p style="margin:.75rem 0 0;font-size:.8rem;color:#64748b">Demo mode: card payment simulated — no card was charged.</p>'
            : '';

        await swal({
            icon: 'success',
            title: 'Your order has been received!',
            html: `
                <p style="margin:.5rem 0 0">Thank you. Your order number is</p>
                <p style="font-weight:700;margin:.25rem 0 0">${order.order_number}</p>
                <p style="margin:.75rem 0 0">Total: <strong>${currency.format(order.total_amount)}</strong></p>
                ${demoNote}
            `,
            confirmButtonText: 'View my orders',
        });

        router.push('/my-orders');
    } catch (err) {
        const status = err?.response?.status;
        const message = err?.response?.data?.message;

        if (status === 401) {
            // The global axios interceptor already cleared the session and redirected.
            return;
        } else if (status === 422 && message) {
            swalToast(message, 'error');
        } else if (status === 422 && err?.response?.data?.errors) {
            const first = Object.values(err.response.data.errors)[0];
            swalToast(Array.isArray(first) ? first[0] : 'Please review your order details.', 'error');
        } else {
            swalToast('We could not place your order. Please try again.', 'error');
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div>
        <section class="bg-background py-14 md:py-16">
            <div class="wd-container">
                <nav class="mb-3 text-sm text-neutral-500">
                    <router-link to="/" class="hover:text-secondary">Home</router-link>
                    <span class="mx-2">›</span>
                    <span class="text-ink">Checkout</span>
                </nav>
                <h1 class="text-3xl text-navy md:text-4xl">Checkout</h1>
            </div>
        </section>

        <section class="wd-container py-14">
            <div v-if="cart.isEmpty" class="mx-auto max-w-2xl py-20 text-center">
                <h2 class="font-heading text-4xl text-navy">Your basket is currently empty.</h2>
                <p class="mt-5 text-sm leading-relaxed text-neutral-500">
                    Before proceed to checkout you must add some products to your shopping cart. You
                    will find a lot of research products on our “Catalogue” page.
                </p>
                <router-link
                    to="/peptide-shop"
                    class="mt-10 inline-flex items-center justify-center rounded bg-neutral-200 px-8 py-3 text-sm font-semibold text-ink"
                >
                    Return to shop
                </router-link>
            </div>

            <div v-else class="grid gap-10 lg:grid-cols-3 lg:gap-12">
                <!-- Billing form -->
                <form class="space-y-5 lg:col-span-2" @submit.prevent="placeOrder">
                    <h2 class="text-2xl">Billing details</h2>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="cname">
                                Full name *
                            </label>
                            <input id="cname" v-model="form.name" type="text" class="wd-field" required />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="cemail">
                                Email address *
                            </label>
                            <input id="cemail" v-model="form.email" type="email" class="wd-field" required />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="cphone">
                                Phone *
                            </label>
                            <input id="cphone" v-model="form.phone" type="tel" class="wd-field" required />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="ccountry">
                                Country *
                            </label>
                            <input
                                id="ccountry"
                                v-model="form.country"
                                type="text"
                                class="wd-field cursor-not-allowed opacity-70"
                                disabled
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-ink" for="caddress">
                                Street address *
                            </label>
                            <input
                                id="caddress"
                                v-model="form.address"
                                type="text"
                                class="wd-field"
                                placeholder="House number and street name"
                                required
                            />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="ccity">
                                Town / City *
                            </label>
                            <select
                                id="ccity"
                                v-model="form.city"
                                class="wd-field !w-full py-2 px-3 rounded-md text-sm bg-white"
                                required
                            >
                                <option value="" disabled>Select your city…</option>
                                <option v-for="city in UAE_CITIES" :key="city" :value="city">
                                    {{ city }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="czip">
                                Zip / postal code
                            </label>
                            <input id="czip" v-model="form.zip" type="text" class="wd-field" placeholder="Optional" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-ink" for="ccomment">
                                Order notes (optional)
                            </label>
                            <textarea
                                id="ccomment"
                                v-model="form.comment"
                                rows="4"
                                class="wd-field"
                                placeholder="Notes about your order…"
                            ></textarea>
                        </div>
                    </div>

                    <div v-if="auth.isAuthenticated">
                        <label class="flex cursor-pointer items-center gap-3">
                            <input
                                v-model="form.saveForLater"
                                type="checkbox"
                                class="accent-primary"
                            />
                            <span class="text-sm text-ink">
                                Save these details to my account for next time.
                            </span>
                        </label>
                    </div>

                    <!-- Payment -->
                    <h2 class="pt-4 text-2xl">Payment</h2>
                    <div class="space-y-3 rounded-card border border-line p-5">
                        <label class="flex cursor-pointer items-center gap-3">
                            <input v-model="form.payment" type="radio" value="cod" class="accent-primary" />
                            <span class="text-sm text-ink">Cash on delivery</span>
                            <span v-if="codFee > 0" class="text-sm font-semibold text-ink">
                                <PriceDisplay :value="codFee" sign="+" />
                            </span>
                        </label>
                        <p v-if="codFee > 0" class="pl-7 text-xs text-neutral-400">
                            A cash-on-delivery fee of <PriceDisplay :value="codFee" /> will be added to your order total.
                        </p>
                        <label class="flex cursor-pointer items-center gap-3">
                            <input v-model="form.payment" type="radio" value="stripe" class="accent-primary" />
                            <span class="text-sm text-ink">Credit / debit card</span>
                        </label>
                        <p v-if="form.payment === 'cod'" class="text-xs text-neutral-400">
                            Payment is collected on delivery.
                        </p>
                        <p v-else class="text-xs text-neutral-400">
                            Pay securely with your credit or debit card via Stripe.
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="flex cursor-pointer items-center gap-3">
                            <input
                                v-model="form.ageConfirmed"
                                type="checkbox"
                                class="accent-primary"
                            />
                            <span class="text-sm text-ink">
                                I confirm that I am 18 years of age or older.
                            </span>
                        </label>
                        <p v-if="currency.settings.ageConfirmation && !form.ageConfirmed" class="mt-1 text-xs text-neutral-400">
                            You must confirm you are 18+ to place an order.
                        </p>
                    </div>

                    <button type="submit" class="wd-btn w-full sm:w-auto" :disabled="submitting">
                        <i v-if="submitting" class="fa-solid fa-circle-notch fa-spin"></i>
                        {{ submitting ? 'Placing order…' : 'Place order' }}
                    </button>
                </form>

                <!-- Order summary -->
                <aside class="h-fit rounded-card border border-line p-6">
                    <h2 class="font-widget text-lg text-muted">Your order</h2>

                    <ul class="mt-5 divide-y divide-line">
                        <li
                            v-for="line in cart.lines"
                            :key="line.slug"
                            class="py-3 text-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <span class="leading-tight text-ink">{{ line.product.name }}</span>
                                <button
                                    type="button"
                                    class="shrink-0 text-neutral-400 transition hover:text-danger"
                                    aria-label="Remove item"
                                    @click="cart.remove(line.slug)"
                                >
                                    <i class="fa-solid fa-trash-can text-sm"></i>
                                </button>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <div class="flex items-center rounded-btn border border-line">
                                    <button
                                        type="button"
                                        class="px-2 py-1 text-ink transition hover:text-secondary"
                                        aria-label="Decrease quantity"
                                        @click="cart.setQty(line.slug, line.qty - 1)"
                                    >
                                        −
                                    </button>
                                    <span class="min-w-6 text-center text-sm">{{ line.qty }}</span>
                                    <button
                                        type="button"
                                        class="px-2 py-1 text-ink transition hover:text-secondary"
                                        aria-label="Increase quantity"
                                        @click="cart.setQty(line.slug, line.qty + 1)"
                                    >
                                        +
                                    </button>
                                </div>
                                <span class="shrink-0 font-medium text-ink">
                                    <PriceDisplay :value="line.total" />
                                </span>
                            </div>
                        </li>
                    </ul>

                    <!-- Coupon -->
                    <form
                        class="mt-5 flex gap-2"
                        @submit.prevent="applyCoupon"
                    >
                        <input
                            v-model="form.coupon"
                            type="text"
                            placeholder="Coupon code"
                            class="wd-field !py-3 text-sm"
                            :disabled="couponApplied"
                        />
                        <button
                            v-if="!couponApplied"
                            type="submit"
                            class="wd-btn shrink-0 !px-5 !py-3 text-sm"
                        >
                            Apply
                        </button>
                        <button
                            v-else
                            type="button"
                            class="wd-btn wd-btn-outline shrink-0 !px-5 !py-3 text-sm"
                            @click="removeCoupon"
                        >
                            Remove
                        </button>
                    </form>

                    <ShippingProgress />
                    <dl class="mt-5 space-y-3 border-t border-line pt-5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-neutral-500">Subtotal</dt>
                            <dd class="font-medium text-ink">
                                <PriceDisplay :value="cart.subtotal" />
                            </dd>
                        </div>
                        <div v-if="couponDiscount" class="flex justify-between text-success">
                            <dt>Coupon discount</dt>
                            <dd><PriceDisplay :value="-couponDiscount" /></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-neutral-500">Total items</dt>
                            <dd class="font-semibold text-ink">{{ cart.count }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-neutral-500">Shipping</dt>
                            <dd class="font-medium text-ink">
                                <template v-if="cart.shipping === 0">Free</template>
                                <template v-else><PriceDisplay :value="cart.shipping" /></template>
                            </dd>
                        </div>
                        <div v-if="codFee > 0" class="flex justify-between">
                            <dt class="text-neutral-500">Cash on delivery</dt>
                            <dd class="font-medium text-ink"><PriceDisplay :value="codFee" sign="+" /></dd>
                        </div>
                        <div v-if="currency.settings.vatEnabled" class="flex justify-between">
                            <dt class="text-neutral-500">{{ currency.settings.vatLabel || 'VAT' }}</dt>
                            <dd class="font-medium text-ink">
                                <PriceDisplay :value="cart.tax" />
                            </dd>
                        </div>
                        <div class="flex justify-between border-t border-line pt-3">
                            <dt class="text-base font-semibold text-ink">Total</dt>
                            <dd class="text-base font-semibold text-navy">
                                <PriceDisplay :value="finalTotal" />
                            </dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </section>
    </div>
</template>
