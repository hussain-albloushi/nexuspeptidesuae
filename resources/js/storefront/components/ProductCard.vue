<script setup>
import { useCartStore } from '../stores/cart';
import { useWishlistStore } from '../stores/wishlist';
import { useCurrencyStore } from '../stores/currency';
import { swalToast } from '../services/alerts';
import PriceDisplay from './PriceDisplay.vue';

const props = defineProps({
    product: { type: Object, required: true },
});

const cart = useCartStore();
const wishlist = useWishlistStore();
const currency = useCurrencyStore();

function addToCart() {
    cart.add(props.product.slug, 1);
    swalToast(`${props.product.name} added to your cart.`);
}

function toggleWishlist() {
    const added = wishlist.toggle(props.product.slug);
    swalToast(
        added ? `${props.product.name} added to wishlist.` : `${props.product.name} removed from wishlist.`,
        added ? 'success' : 'info',
    );
}
</script>

<template>
    <div class="group relative flex flex-col">
        <!-- Badge -->
        <span
            v-if="product.discount"
            class="absolute left-3 top-3 z-10 rounded-full bg-primary px-3 py-1 text-xs font-bold text-white"
        >
            -{{ product.discount }}%
        </span>

        <!-- Wishlist -->
        <button
            type="button"
            class="absolute right-3 top-3 z-10 grid h-9 w-9 place-items-center rounded-full bg-white text-ink shadow-md transition hover:bg-secondary hover:text-white"
            :aria-label="wishlist.has(product.slug) ? 'Remove from wishlist' : 'Add to wishlist'"
            @click="toggleWishlist"
        >
            <i :class="wishlist.has(product.slug) ? 'fa-solid fa-heart' : 'fa-regular fa-heart'"></i>
        </button>

        <!-- Image -->
        <router-link
            :to="`/product/${product.slug}`"
            class="relative block overflow-hidden rounded-card bg-neutral-50"
        >
            <img
                :src="product.image"
                :alt="product.name"
                loading="lazy"
                class="aspect-square w-full object-contain p-5 transition duration-500 group-hover:scale-105"
            />
        </router-link>

        <!-- Meta -->
        <div class="mt-4 flex flex-1 flex-col">
            <router-link
                :to="`/product/${product.slug}`"
                class="font-semibold leading-snug text-ink transition hover:text-secondary"
            >
                {{ product.name }}
            </router-link>

            <p class="mt-2 text-sm text-neutral-500">
                <template v-if="product.discount">
                    <del class="text-neutral-400">
                        <PriceDisplay :value="product.regular" />
                    </del>
                    <ins class="ml-1 font-semibold text-navy">
                        <PriceDisplay :value="product.price" />
                    </ins>
                </template>
                <template v-else>
                    <span class="font-semibold text-navy">
                        <PriceDisplay :value="product.price" />
                    </span>
                </template>
            </p>
            <span class="sr-only">Original price was: <PriceDisplay :value="product.regular" />.</span>

            <button type="button" class="wd-btn mt-4 w-full self-start" @click="addToCart">
                Add to basket
            </button>
        </div>
    </div>
</template>
