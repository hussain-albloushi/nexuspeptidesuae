<script setup>
import { useCartStore } from '../stores/cart';
import { useCurrencyStore } from '../stores/currency';
import { swalConfirm, swalToast } from '../services/alerts';
import PriceDisplay from '../components/PriceDisplay.vue';
import ShippingProgress from '../components/ShippingProgress.vue';

const cart = useCartStore();
const currency = useCurrencyStore();

async function remove(slug, name) {
    const ok = await swalConfirm('Remove item?', `Remove ${name} from your cart?`, 'Remove');
    if (ok) {
        cart.remove(slug);
        swalToast('Item removed from your cart.', 'info');
    }
}

async function clearCart() {
    const ok = await swalConfirm('Clear cart?', 'Remove all items from your cart?', 'Clear cart');
    if (ok) {
        cart.clear();
        swalToast('Your cart has been emptied.', 'info');
    }
}

function setQty(slug, e) {
    cart.setQty(slug, parseInt(e.target.value, 10) || 1);
}
</script>

<template>
    <div>
        <section class="bg-background py-14 md:py-16">
            <div class="wd-container">
                <nav class="mb-3 text-sm text-neutral-500">
                    <router-link to="/" class="hover:text-secondary">Home</router-link>
                    <span class="mx-2">›</span>
                    <span class="text-ink">Shopping cart</span>
                </nav>
                <h1 class="text-3xl text-navy md:text-4xl">Shopping cart</h1>
            </div>
        </section>

        <section class="wd-container py-14">
            <!-- Empty -->
            <div v-if="cart.isEmpty" class="mx-auto max-w-2xl py-20 text-center">
                <h2 class="font-heading text-4xl text-navy">Your basket is currently empty.</h2>
                <p class="mt-5 text-sm leading-relaxed text-neutral-500">
                    Before proceed to checkout you must add some products to your shopping cart. You
                    will find a lot of products on our “Catalogue” page.
                </p>
                <router-link
                    to="/peptide-shop"
                    class="mt-10 inline-flex items-center justify-center rounded bg-neutral-200 px-8 py-3 text-sm font-semibold text-ink"
                >
                    Return to shop
                </router-link>
            </div>

            <div v-else class="grid gap-10 lg:grid-cols-3 lg:gap-12">
                <!-- Table -->
                <div class="lg:col-span-2">
                    <div
                        class="grid grid-cols-[1fr_auto] gap-4 border-b border-line pb-4 text-sm font-semibold uppercase tracking-wide text-neutral-500"
                    >
                        <span>Product</span>
                        <span>Total</span>
                    </div>

                    <ul class="divide-y divide-line">
                        <li
                            v-for="line in cart.lines"
                            :key="line.slug"
                            class="grid grid-cols-[1fr_auto] items-center gap-4 py-5"
                        >
                            <div class="flex items-start gap-4">
                                <router-link :to="`/product/${line.slug}`" class="shrink-0">
                                    <img
                                        :src="line.product.image"
                                        :alt="line.product.name"
                                        class="h-20 w-20 rounded-card object-contain"
                                    />
                                </router-link>

                                <div>
                                    <router-link
                                        :to="`/product/${line.slug}`"
                                        class="font-semibold text-ink hover:text-secondary"
                                    >
                                        {{ line.product.name }}
                                    </router-link>
                                    <p class="mt-1 text-sm text-neutral-500">
                                        <PriceDisplay :value="line.product.price" />
                                    </p>

                                    <div class="mt-3 flex items-center gap-3">
                                        <div class="flex items-center rounded-btn border border-line">
                                            <button
                                                type="button"
                                                class="px-3 py-1.5 hover:text-secondary"
                                                aria-label="Decrease"
                                                @click="cart.setQty(line.slug, line.qty - 1)"
                                            >
                                                −
                                            </button>
                                            <input
                                                :value="line.qty"
                                                type="number"
                                                min="1"
                                                class="w-12 bg-transparent text-center text-sm outline-none"
                                                aria-label="Quantity"
                                                @change="setQty(line.slug, $event)"
                                            />
                                            <button
                                                type="button"
                                                class="px-3 py-1.5 hover:text-secondary disabled:opacity-40"
                                                aria-label="Increase"
                                                :disabled="line.product.stock !== null && line.product.stock !== undefined && line.qty >= Number(line.product.stock)"
                                                @click="cart.setQty(line.slug, line.qty + 1)"
                                            >
                                                +
                                            </button>
                                        </div>

                                        <button
                                            type="button"
                                            class="text-neutral-400 transition hover:text-danger"
                                            aria-label="Remove"
                                            @click="remove(line.slug, line.product.name)"
                                        >
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <span class="font-semibold text-ink">
                                <PriceDisplay :value="line.total" />
                            </span>
                        </li>
                    </ul>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <router-link to="/peptide-shop" class="wd-btn wd-btn-outline">
                            Continue shopping
                        </router-link>
                        <button
                            type="button"
                            class="wd-btn wd-btn-outline"
                            @click="clearCart"
                        >
                            Clear cart
                        </button>
                    </div>
                </div>

                <!-- Totals -->
                <aside class="h-fit rounded-card border border-line p-6">
                    <h2 class="font-widget text-lg text-muted">Cart totals</h2>
                    <ShippingProgress />

                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between border-b border-line pb-3">
                            <dt class="text-neutral-500">Subtotal</dt>
                            <dd class="font-semibold text-ink">
                                <PriceDisplay :value="cart.subtotal" />
                            </dd>
                        </div>
                        <div class="flex justify-between border-b border-line pb-3">
                            <dt class="text-neutral-500">Total items</dt>
                            <dd class="font-semibold text-ink">{{ cart.count }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-line pb-3">
                            <dt class="text-neutral-500">Shipping</dt>
                            <dd class="font-semibold text-ink">
                                <template v-if="cart.shipping === 0">Free</template>
                                <template v-else><PriceDisplay :value="cart.shipping" /></template>
                            </dd>
                        </div>
                        <div v-if="currency.settings.vatEnabled" class="flex justify-between border-b border-line pb-3">
                            <dt class="text-neutral-500">{{ currency.settings.vatLabel || 'VAT' }}</dt>
                            <dd class="font-semibold text-ink">
                                <PriceDisplay :value="cart.tax" />
                            </dd>
                        </div>
                        <div class="flex justify-between pt-1">
                            <dt class="text-base font-semibold text-ink">Total</dt>
                            <dd class="text-base font-semibold text-navy">
                                <PriceDisplay :value="cart.total" />
                            </dd>
                        </div>
                    </dl>

                    <router-link to="/checkout" class="wd-btn mt-6 w-full">
                        Proceed to checkout
                        <i class="fa-solid fa-arrow-right"></i>
                    </router-link>
                </aside>
            </div>
        </section>
    </div>
</template>
