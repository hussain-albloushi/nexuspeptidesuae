<script setup>
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { products, findBySlug, relatedTo } from '../data/products';
import { useCurrencyStore } from '../stores/currency';

const currency = useCurrencyStore();
import { useCartStore } from '../stores/cart';
import { useWishlistStore } from '../stores/wishlist';
import ProductGrid from '../components/ProductGrid.vue';
import PriceDisplay from '../components/PriceDisplay.vue';
import { swalToast, swal } from '../services/alerts';

const route = useRoute();
const router = useRouter();
const cart = useCartStore();
const wishlist = useWishlistStore();

const qty = ref(1);
const tab = ref('description');
const zoomed = ref(false);

const product = computed(() => findBySlug(route.params.slug));
const related = computed(() => relatedTo(product.value, 8));

const index = computed(() => products.findIndex((p) => p.slug === route.params.slug));
const prev = computed(() => (index.value > 0 ? products[index.value - 1] : null));
const next = computed(() =>
    index.value < products.length - 1 ? products[index.value + 1] : null,
);

// Reset state when navigating between products.
watch(
    () => route.params.slug,
    () => {
        qty.value = 1;
        tab.value = 'description';
        if (!product.value) router.replace('/peptide-shop');
    },
    { immediate: true },
);

const isInStock = computed(() => product.value?.stock === null || product.value?.stock === undefined || Number(product.value.stock) > 0);

function addToCart() {
    if (!product.value || !isInStock.value) return;
    cart.add(product.value.slug, qty.value);
    swalToast(`${product.value.name} added to your cart.`);
}

async function buyNow() {
    if (!product.value || !isInStock.value) return;
    cart.add(product.value.slug, qty.value);
    router.push('/checkout');
}

function toggleWishlist() {
    if (!product.value) return;
    const added = wishlist.toggle(product.value.slug);
    swalToast(
        added ? `${product.value.name} added to wishlist.` : `${product.value.name} removed from wishlist.`,
        added ? 'success' : 'info',
    );
}

function goTo(p) {
    if (p) router.push(`/product/${p.slug}`);
}
</script>

<template>
    <div v-if="product" class="wd-container py-8 md:py-12">
        <!-- Breadcrumbs -->
        <nav class="mb-6 text-sm text-neutral-500">
            <router-link to="/" class="hover:text-secondary">Home</router-link>
            <span class="mx-2">›</span>
            <router-link
                :to="`/product-category/${product.category}`"
                class="hover:text-secondary"
            >
                {{ product.categoryLabel }}
            </router-link>
            <span class="mx-2">›</span>
            <span class="text-ink">{{ product.name }}</span>
        </nav>

        <div class="grid gap-10 lg:grid-cols-2 lg:gap-16">
            <!-- Gallery -->
            <div class="relative">
                <span
                    v-if="product.discount"
                    class="absolute left-4 top-4 z-10 rounded-full bg-primary px-3 py-1 text-xs font-bold text-white"
                >
                    -{{ product.discount }}%
                </span>

                <button
                    type="button"
                    class="block w-full overflow-hidden rounded-card bg-neutral-50"
                    aria-label="Click to enlarge"
                    @click="zoomed = true"
                >
                    <img
                        :src="product.image"
                        :alt="product.name"
                        class="aspect-square w-full object-contain p-10"
                    />
                </button>
                <p class="mt-3 text-center text-xs text-neutral-400">Click to enlarge</p>

                <!-- Prev / next -->
                <div class="mt-6 flex items-center justify-between gap-4">
                    <button
                        type="button"
                        class="flex flex-1 items-center gap-3 rounded-card border border-line p-3 text-left transition hover:border-secondary disabled:opacity-40"
                        :disabled="!prev"
                        @click="goTo(prev)"
                    >
                        <i class="fa-solid fa-chevron-left text-xs text-neutral-400"></i>
                        <img
                            v-if="prev"
                            :src="prev.image"
                            :alt="prev.name"
                            class="h-12 w-12 rounded-card object-contain"
                        />
                        <span v-if="prev" class="truncate text-sm text-ink">{{ prev.name }}</span>
                    </button>

                    <button
                        type="button"
                        class="flex flex-1 items-center justify-end gap-3 rounded-card border border-line p-3 text-right transition hover:border-secondary disabled:opacity-40"
                        :disabled="!next"
                        @click="goTo(next)"
                    >
                        <span v-if="next" class="truncate text-sm text-ink">{{ next.name }}</span>
                        <img
                            v-if="next"
                            :src="next.image"
                            :alt="next.name"
                            class="h-12 w-12 rounded-card object-contain"
                        />
                        <i class="fa-solid fa-chevron-right text-xs text-neutral-400"></i>
                    </button>
                </div>
            </div>

            <!-- Summary -->
            <div>
                <h1 class="text-3xl md:text-4xl">{{ product.name }}</h1>

                <div v-if="isInStock" class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span class="text-3xl font-bold text-navy">
                        <PriceDisplay :value="product.price" />
                    </span>
                    <template v-if="product.discount">
                        <span class="text-lg text-neutral-400 line-through" aria-label="Original price">
                            <PriceDisplay :value="product.regular" />
                        </span>
                        <span class="rounded-full bg-danger/10 px-3 py-1 text-sm font-bold text-danger">
                            -{{ product.discount }}%
                        </span>
                        <span class="text-sm font-medium text-success">
                            You save <PriceDisplay :value="product.regular - product.price" />
                        </span>
                        <span class="sr-only">
                            Original price was <PriceDisplay :value="product.regular" />; now
                            <PriceDisplay :value="product.price" />.
                        </span>
                    </template>
                </div>
                <div v-else class="mt-4 text-lg font-semibold text-red-600">Out of stock</div>

                <p class="mt-5 leading-relaxed text-ink" v-html="product.description.intro"></p>

                <!-- Quantity + actions -->
                <div class="mt-8 flex flex-wrap items-center gap-4">
                    <div class="flex items-center rounded-btn border border-line">
                        <button
                            type="button"
                            class="px-4 py-3 text-ink hover:text-secondary"
                            aria-label="Decrease quantity"
                            @click="qty = Math.max(1, qty - 1)"
                        >
                            −
                        </button>
                        <input
                            v-model.number="qty"
                            type="number"
                            min="1"
                            class="w-14 bg-transparent text-center outline-none"
                            aria-label="Quantity"
                        />
                        <button
                            type="button"
                            class="px-4 py-3 text-ink hover:text-secondary disabled:opacity-40"
                            aria-label="Increase quantity"
                            :disabled="product.stock !== null && product.stock !== undefined && qty >= Number(product.stock)"
                            @click="qty = Math.min((product.stock ?? Infinity), qty + 1)"
                        >
                            +
                        </button>
                    </div>

                    <button v-if="isInStock" type="button" class="wd-btn" @click="addToCart">Add to basket</button>
                    <button v-else type="button" class="wd-btn" disabled>Out of stock</button>
                    <button v-if="isInStock" type="button" class="wd-btn wd-btn-outline" @click="buyNow">
                        Buy now
                    </button>
                </div>

                <button
                    type="button"
                    class="mt-5 flex items-center gap-2 text-sm text-neutral-500 transition hover:text-secondary"
                    @click="toggleWishlist"
                >
                    <i :class="wishlist.has(product.slug) ? 'fa-solid fa-heart' : 'fa-regular fa-heart'"></i>
                    {{ wishlist.has(product.slug) ? 'Remove from wishlist' : 'Add to wishlist' }}
                </button>

                <!-- Meta -->
                <dl class="mt-8 space-y-2 border-t border-line pt-6 text-sm">
                    <div class="flex gap-2">
                        <dt class="text-neutral-500">Category:</dt>
                        <dd>
                            <router-link
                                :to="`/product-category/${product.category}`"
                                class="text-navy hover:text-secondary"
                            >
                                {{ product.categoryLabel }}
                            </router-link>
                        </dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-neutral-500">Tag:</dt>
                        <dd class="flex flex-wrap gap-2">
                            <router-link
                                v-for="t in product.tags"
                                :key="t"
                                :to="`/product-tag/${t.toLowerCase().replace(/\s+/g, '-')}`"
                                class="text-navy hover:text-secondary"
                            >
                                {{ t }}
                            </router-link>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mt-16">
            <div class="flex gap-8 border-b border-line">
                <button
                    type="button"
                    class="pb-4 text-sm font-semibold uppercase tracking-wide transition"
                    :class="tab === 'description' ? 'border-b-2 border-primary text-ink' : 'text-neutral-400'"
                    @click="tab = 'description'"
                >
                    Description
                </button>
                <button
                    type="button"
                    class="pb-4 text-sm font-semibold uppercase tracking-wide transition"
                    :class="tab === 'reviews' ? 'border-b-2 border-primary text-ink' : 'text-neutral-400'"
                    @click="tab = 'reviews'"
                >
                    Reviews (0)
                </button>
            </div>

            <!-- Description -->
            <div v-if="tab === 'description'" class="max-w-4xl py-8">
                <h2 class="text-2xl">{{ product.description.heading }}</h2>
                <h3 class="mt-2 font-widget text-lg text-muted">
                    {{ product.description.subheading }}
                </h3>

                <div
                    class="mt-5 space-y-4 leading-relaxed text-ink"
                    v-html="product.description.intro"
                ></div>
                <p class="mt-4 leading-relaxed text-ink">
                    <!-- Research description hidden for consumer-facing site. Product details will be updated when new descriptions are provided. -->
                </p>

                <h4 class="mt-8 font-semibold text-ink">Product Features</h4>
                <ul class="mt-3 space-y-2">
                    <li
                        v-for="f in product.description.features"
                        :key="f"
                        class="flex items-start gap-2.5 text-ink"
                    >
                        <i class="fa-solid fa-circle-check mt-1.5 text-xs text-success"></i>
                        {{ f }}
                    </li>
                </ul>

                <h4 class="mt-8 font-semibold text-ink">Storage</h4>
                <p class="mt-2 text-ink">{{ product.description.storage }}</p>

                <h4 class="mt-8 font-semibold text-ink">Package Includes</h4>
                <p class="mt-2 text-ink">{{ product.description.package }}</p>

                <hr class="my-8 border-line" />

                <h4 class="font-semibold text-ink">{{ product.description.noticeTitle }}</h4>
                <p class="mt-2 font-semibold text-ink">{{ product.description.notice }}</p>
                <div
                    class="mt-2 leading-relaxed text-ink"
                    v-html="product.description.noticeBody"
                ></div>
            </div>

            <!-- Reviews -->
            <div v-else class="max-w-3xl py-8">
                <h2 class="text-2xl">Reviews</h2>
                <p class="mt-4 text-neutral-500">There are no reviews yet.</p>
                <p class="mt-6 text-ink">
                    Be the first to review “{{ product.name }}”
                </p>

                <form class="mt-6 space-y-5" @submit.prevent="swalToast('Thank you — your review has been submitted.')">
                    <p class="text-sm text-neutral-500">
                        Your email address will not be published. Required fields are marked *
                    </p>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-ink">Your rating *</label>
                        <div class="flex gap-2 text-lg text-neutral-300">
                            <i
                                v-for="n in 5"
                                :key="n"
                                class="fa-regular fa-star cursor-pointer hover:text-secondary"
                            ></i>
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-ink" for="review">Your review *</label>
                        <textarea id="review" rows="5" class="wd-field" required></textarea>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="name">Name *</label>
                            <input id="name" type="text" class="wd-field" required />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="email">Email *</label>
                            <input id="email" type="email" class="wd-field" required />
                        </div>
                    </div>

                    <button type="submit" class="wd-btn">Submit</button>
                </form>
            </div>
        </div>

        <!-- Related -->
        <section class="mt-14 border-t border-line pt-14">
            <h2 class="mb-8 text-2xl">Related products</h2>
            <ProductGrid :products="related" />
        </section>

        <!-- Lightbox -->
        <Teleport to="body">
            <Transition name="fade">
                <div
                    v-if="zoomed"
                    class="fixed inset-0 z-50 grid place-items-center bg-black/85 p-6"
                    @click="zoomed = false"
                >
                    <button
                        type="button"
                        class="absolute right-6 top-6 text-2xl text-white"
                        aria-label="Close"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <img
                        :src="product.image"
                        :alt="product.name"
                        class="max-h-[85vh] max-w-[90vw] rounded-card bg-white object-contain p-6"
                    />
                </div>
            </Transition>
        </Teleport>
    </div>

    <div v-else class="wd-container py-24 text-center text-neutral-500">
        Loading…
    </div>
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
</style>
