<script setup>
import { computed } from 'vue';
import { useWishlistStore } from '../stores/wishlist';
import { useCartStore } from '../stores/cart';
import { findBySlug } from '../data/products';
import { useCurrencyStore } from '../stores/currency';
import PriceDisplay from '../components/PriceDisplay.vue';

const currency = useCurrencyStore();
import ProductGrid from '../components/ProductGrid.vue';
import { swalConfirm, swalToast } from '../services/alerts';

const wishlist = useWishlistStore();
const cart = useCartStore();

const items = computed(() =>
    wishlist.slugs.map((slug) => findBySlug(slug)).filter(Boolean),
);

async function clearWishlist() {
    const ok = await swalConfirm(
        'Clear wishlist?',
        'Remove all products from your wishlist?',
        'Clear wishlist',
    );
    if (ok) {
        wishlist.clear();
        swalToast('Your wishlist has been cleared.', 'info');
    }
}

function remove(product) {
    wishlist.remove(product.slug);
    swalToast(`${product.name} removed from your wishlist.`, 'info');
}

function moveToCart(product) {
    cart.add(product.slug, 1);
    wishlist.remove(product.slug);
    swalToast(`${product.name} moved to your cart.`);
}
</script>

<template>
    <div>
        <section class="bg-background py-14 md:py-16">
            <div class="wd-container">
                <nav class="mb-3 text-sm text-neutral-500">
                    <router-link to="/" class="hover:text-secondary">Home</router-link>
                    <span class="mx-2">›</span>
                    <span class="text-ink">Wishlist</span>
                </nav>
                <h1 class="text-3xl text-navy md:text-4xl">Wishlist</h1>
            </div>
        </section>

        <section class="wd-container py-14 md:py-16">
            <!-- Empty -->
            <div v-if="wishlist.isEmpty" class="mx-auto max-w-2xl text-center">
                <i class="fa-regular fa-heart text-5xl text-neutral-300"></i>
                <h2 class="mt-5 text-2xl text-ink">This wishlist is empty.</h2>
                <p class="mt-4 leading-relaxed text-neutral-500">
                    You don’t have any products in the wishlist yet. You will find a lot of research
                    products on our “Catalogue” page.
                </p>
                <router-link to="/peptide-shop" class="wd-btn mt-7">
                    Return to shop
                </router-link>
            </div>

            <!-- Items -->
            <template v-else>
                <p class="mb-8 text-sm text-neutral-500">
                    Showing {{ items.length }} {{ items.length === 1 ? 'product' : 'products' }}
                </p>

                <div class="grid gap-x-6 gap-y-10 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    <div v-for="product in items" :key="product.slug" class="group relative flex flex-col">
                        <span
                            v-if="product.discount"
                            class="absolute left-3 top-3 z-10 rounded-full bg-primary px-3 py-1 text-xs font-bold text-white"
                        >
                            -{{ product.discount }}%
                        </span>

                        <button
                            type="button"
                            class="absolute right-3 top-3 z-10 grid h-9 w-9 place-items-center rounded-full bg-white text-ink shadow-md transition hover:bg-danger hover:text-white"
                            aria-label="Remove from wishlist"
                            @click="remove(product)"
                        >
                            <i class="fa-solid fa-heart"></i>
                        </button>

                        <router-link
                            :to="`/product/${product.slug}`"
                            class="block overflow-hidden rounded-card bg-neutral-50"
                        >
                            <img
                                :src="product.image"
                                :alt="product.name"
                                loading="lazy"
                                class="aspect-square w-full object-contain p-5 transition duration-500 group-hover:scale-105"
                            />
                        </router-link>

                        <div class="mt-4 flex flex-1 flex-col">
                            <router-link
                                :to="`/product/${product.slug}`"
                                class="font-semibold leading-snug text-ink transition hover:text-secondary"
                            >
                                {{ product.name }}
                            </router-link>

                            <p class="mt-2 text-sm text-neutral-500">
                                <template v-if="product.discount">
                                    <span class="line-through">
                                        Original price was: <PriceDisplay :value="product.regular" />.
                                    </span>
                                    <span class="ml-1 font-medium text-navy">
                                        Current price is: <PriceDisplay :value="product.price" />.
                                    </span>
                                </template>
                                <template v-else>
                                    <span class="font-semibold text-navy">
                                        <PriceDisplay :value="product.price" />
                                    </span>
                                </template>
                            </p>

                            <div class="mt-4 flex gap-2">
                                <button
                                    type="button"
                                    class="wd-btn flex-1 !px-4 !py-3 text-sm"
                                    @click="moveToCart(product)"
                                >
                                    Add to basket
                                </button>
                                <button
                                    type="button"
                                    class="grid h-11 w-11 shrink-0 place-items-center rounded-btn border border-line text-ink transition hover:border-danger hover:text-danger"
                                    aria-label="Remove"
                                    @click="remove(product)"
                                >
                                    <i class="fa-solid fa-trash-can text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-12 flex flex-wrap justify-center gap-3">
                    <router-link to="/peptide-shop" class="wd-btn wd-btn-outline">
                        Continue shopping
                    </router-link>
                    <button
                        type="button"
                        class="wd-btn wd-btn-outline"
                        @click="clearWishlist"
                    >
                        Clear wishlist
                    </button>
                </div>
            </template>
        </section>
    </div>
</template>
