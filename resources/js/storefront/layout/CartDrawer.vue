<script setup>
import { useCartStore } from '../stores/cart';
import { useCurrencyStore } from '../stores/currency';
import { swalToast } from '../services/alerts';
import PriceDisplay from '../components/PriceDisplay.vue';
import ShippingProgress from '../components/ShippingProgress.vue';

defineProps({ open: Boolean });
const emit = defineEmits(['close']);

const cart = useCartStore();
const currency = useCurrencyStore();

function setQty(slug, e) {
    cart.setQty(slug, parseInt(e.target.value, 10) || 1);
}
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="open" class="fixed inset-0 z-50 bg-black/50" @click="emit('close')"></div>
        </Transition>

        <Transition name="slide-right">
            <aside
                v-if="open"
                class="fixed right-0 top-0 z-50 flex h-full w-full max-w-md flex-col bg-white shadow-2xl"
                aria-label="Shopping cart"
            >
                <div class="flex items-center justify-between border-b border-line px-6 py-5">
                    <h2 class="font-widget text-lg text-muted">Shopping cart</h2>
                    <div class="flex items-center gap-4">
                        <span class="text-sm text-neutral-500">
                            {{ cart.count }} {{ cart.count === 1 ? 'item' : 'items' }}
                        </span>
                        <button type="button" aria-label="Close cart" @click="emit('close')">
                            <i class="fa-solid fa-xmark text-xl text-ink"></i>
                        </button>
                    </div>
                </div>

                <!-- Empty -->
                <div v-if="cart.isEmpty" class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
                    <i class="fa-solid fa-bag-shopping text-4xl text-neutral-300"></i>
                    <p class="text-neutral-500">Your cart is currently empty.</p>
                    <router-link to="/peptide-shop" class="wd-btn" @click="emit('close')">
                        Return to shop
                    </router-link>
                </div>

                <!-- Lines -->
                <div v-else class="flex-1 overflow-y-auto px-6 py-4">
                    <ul class="divide-y divide-line">
                        <li v-for="line in cart.lines" :key="line.slug" class="flex gap-4 py-4">
                            <router-link
                                :to="`/product/${line.slug}`"
                                @click="emit('close')"
                                class="shrink-0"
                            >
                                <img
                                    :src="line.product.image"
                                    :alt="line.product.name"
                                    class="h-20 w-20 rounded-card object-contain"
                                />
                            </router-link>

                            <div class="flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <router-link
                                        :to="`/product/${line.slug}`"
                                        class="font-semibold leading-tight text-ink hover:text-secondary"
                                        @click="emit('close')"
                                    >
                                        {{ line.product.name }}
                                    </router-link>
                                    <button
                                        type="button"
                                        aria-label="Remove"
                                        class="text-neutral-400 hover:text-danger"
                                        @click="cart.remove(line.slug)"
                                    >
                                        <i class="fa-solid fa-trash-can text-sm"></i>
                                    </button>
                                </div>

                                <p class="mt-1 text-sm text-neutral-500">
                                    <PriceDisplay :value="line.product.price" />
                                </p>

                                <div class="mt-2 flex items-center gap-3">
                                    <div class="flex items-center rounded-btn border border-line">
                                        <button
                                            type="button"
                                            class="px-3 py-1 text-ink hover:text-secondary"
                                            aria-label="Decrease quantity"
                                            @click="cart.setQty(line.slug, line.qty - 1)"
                                        >
                                            −
                                        </button>
                                        <span class="min-w-6 text-center text-sm">{{ line.qty }}</span>
                                        <button
                                            type="button"
                                            class="px-3 py-1 text-ink hover:text-secondary"
                                            aria-label="Increase quantity"
                                            @click="cart.setQty(line.slug, line.qty + 1)"
                                        >
                                            +
                                        </button>
                                    </div>
                                    <span class="text-sm font-semibold text-navy">
                                        <PriceDisplay :value="line.total" />
                                    </span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Totals -->
                <div v-if="!cart.isEmpty" class="border-t border-line px-6 py-5">
                    <div class="flex items-center justify-between text-sm text-ink">
                        <span>Subtotal</span>
                        <span class="font-semibold text-ink">
                            <PriceDisplay :value="cart.subtotal" />
                        </span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-sm text-ink">
                        <span>Shipping</span><strong><template v-if="cart.shipping === 0">Free</template><PriceDisplay v-else :value="cart.shipping" /></strong>
                    </div>
                    <ShippingProgress />
                    <p class="mt-2 text-xs text-neutral-500">Shipping eligibility is based on the item subtotal.</p>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <router-link
                            to="/cart"
                            class="wd-btn wd-btn-outline"
                            @click="emit('close')"
                        >
                            View cart
                        </router-link>
                        <router-link to="/checkout" class="wd-btn" @click="emit('close')">
                            Checkout
                        </router-link>
                    </div>
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-right-enter-active,
.slide-right-leave-active {
    transition: transform 0.3s ease;
}
.slide-right-enter-from,
.slide-right-leave-to {
    transform: translateX(100%);
}
</style>
