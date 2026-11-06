<script setup>
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useCartStore } from '../stores/cart';
import { useContentStore } from '../stores/content';
import { useCurrencyStore } from '../stores/currency';
import { swal, swalToast } from '../services/alerts';
import PriceDisplay from '../components/PriceDisplay.vue';

const router = useRouter();
const cart = useCartStore();
const currency = useCurrencyStore();
const content = useContentStore();
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
});

const couponDiscount = ref(0);
const couponApplied = ref(false);
const submitting = ref(false);

const finalTotal = computed(() => Math.max(0, cart.total - couponDiscount.value));

function applyCoupon() {
    const code = form.value.coupon.trim().toUpperCase();
    if (!code) return;

    // Static clone — a small set of demo codes.
    const codes = { RESEARCH10: 0.1, ZENVY20: 0.2, RUO15: 0.15 };
    if (codes[code]) {
        couponDiscount.value = cart.subtotal * codes[code];
        couponApplied.value = true;
        swalToast(`Coupon ${code} applied.`);
    } else {
        couponDiscount.value = 0;
        couponApplied.value = false;
        swalToast('This coupon code is not valid.', 'error');
    }
}

function removeCoupon() {
    form.value.coupon = '';
    couponDiscount.value = 0;
    couponApplied.value = false;
}

async function placeOrder() {
    const f = form.value;
    const required = ['name', 'email', 'phone', 'address', 'zip', 'city', 'country'];
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

    // Simulate the request the static build will later send to Laravel.
    await new Promise((r) => setTimeout(r, 700));

    const orderNumber = `ZV-${Date.now().toString().slice(-7)}`;
    const total = finalTotal.value;

    cart.clear();
    submitting.value = false;
    removeCoupon();

    await swal({
        icon: 'success',
        title: 'Your order has been received!',
        html: `
            <p style="margin:.5rem 0 0">Thank you. Your order number is</p>
            <p style="font-weight:700;margin:.25rem 0 0">${orderNumber}</p>
            <p style="margin:.75rem 0 0">Total: <strong>${currency.format(total)}</strong></p>
        `,
        confirmButtonText: 'Continue shopping',
    });

    router.push('/all-peptides');
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
                            <input id="ccountry" v-model="form.country" type="text" class="wd-field" required />
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
                            <label class="mb-2 block text-sm font-medium text-ink" for="czip">
                                Postcode / ZIP *
                            </label>
                            <input id="czip" v-model="form.zip" type="text" class="wd-field" required />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="ccity">
                                Town / City *
                            </label>
                            <input id="ccity" v-model="form.city" type="text" class="wd-field" required />
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

                    <!-- Payment -->
                    <h2 class="pt-4 text-2xl">Payment</h2>
                    <div class="space-y-3 rounded-card border border-black/10 p-5">
                        <label class="flex cursor-pointer items-center gap-3">
                            <input v-model="form.payment" type="radio" value="cod" class="accent-primary" />
                            <span class="text-sm text-ink">Cash on delivery</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-3">
                            <input v-model="form.payment" type="radio" value="stripe" class="accent-primary" />
                            <span class="text-sm text-ink">Credit / debit card (Stripe)</span>
                        </label>
                        <p class="text-xs text-neutral-400">
                            Payments are processed securely through trusted third-party providers.
                            {{ site.name }} does not store or access payment card information.
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
                <aside class="h-fit rounded-card border border-black/10 p-6">
                    <h2 class="font-widget text-lg text-muted">Your order</h2>

                    <ul class="mt-5 divide-y divide-black/10">
                        <li
                            v-for="line in cart.lines"
                            :key="line.slug"
                            class="flex items-center justify-between gap-3 py-3 text-sm"
                        >
                            <span class="text-ink">
                                {{ line.product.name }}
                                <span class="text-neutral-400">× {{ line.qty }}</span>
                            </span>
                            <span class="shrink-0 font-medium text-ink">
                                <PriceDisplay :value="line.total" />
                            </span>
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

                    <dl class="mt-5 space-y-3 border-t border-black/10 pt-5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-neutral-500">Subtotal</dt>
                            <dd class="font-medium text-ink">
                                <PriceDisplay :value="cart.subtotal" />
                            </dd>
                        </div>
                        <div v-if="couponDiscount" class="flex justify-between text-success">
                            <dt>Coupon discount</dt>
                            <dd>-<PriceDisplay :value="couponDiscount" /></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-neutral-500">Shipping</dt>
                            <dd class="font-medium text-ink">
                                <template v-if="cart.shipping === 0">Free</template>
                                <template v-else><PriceDisplay :value="cart.shipping" /></template>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-neutral-500">{{ currency.settings.vatLabel || 'VAT' }}</dt>
                            <dd class="font-medium text-ink">
                                <PriceDisplay :value="cart.tax" />
                            </dd>
                        </div>
                        <div class="flex justify-between border-t border-black/10 pt-3">
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
