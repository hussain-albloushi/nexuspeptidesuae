<script setup>
import { useCartStore } from '../stores/cart';
import { useCurrencyStore } from '../stores/currency';
import PriceDisplay from './PriceDisplay.vue';

const cart = useCartStore();
const currency = useCurrencyStore();
</script>

<template>
    <div
        v-if="!cart.isEmpty && (currency.settings.shippingMode === 'free' || (currency.settings.shippingMode === 'flat' && (cart.freeShippingRemaining !== null || currency.settings.flatRate === 0)))"
        class="mt-4 rounded-lg border border-secondary/20 bg-secondary/5 p-4 text-sm text-ink"
        aria-live="polite"
        aria-atomic="true"
    >
        <p v-if="cart.shipping === 0" class="flex items-center gap-2 font-medium">
            <i class="fa-solid fa-truck-fast text-secondary" aria-hidden="true"></i>
            Your order qualifies for free shipping!
        </p>
        <p v-else>
            Add <strong><PriceDisplay :value="cart.freeShippingRemaining" /></strong> more to get free shipping.
        </p>
        <progress
            v-if="cart.freeShippingRemaining !== null && currency.settings.freeShippingThreshold > 0"
            :value="cart.freeShippingProgress"
            max="100"
            aria-label="Progress toward free shipping"
            class="mt-3 block h-2 w-full overflow-hidden rounded-full accent-secondary"
        ></progress>
    </div>
</template>
