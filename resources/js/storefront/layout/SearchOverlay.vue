<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { products, findBySlug } from '../data/products';
import { useCurrencyStore } from '../stores/currency';
import PriceDisplay from '../components/PriceDisplay.vue';
import { useCartStore } from '../stores/cart';
import { swalToast } from '../services/alerts';

const currency = useCurrencyStore();

const props = defineProps({
    open: Boolean,
    initialQuery: { type: String, default: '' },
});
const emit = defineEmits(['close']);

const query = ref('');
const input = ref(null);

const results = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (q.length === 0) return [];
    return products
        .filter((p) => p.name.toLowerCase().includes(q) || p.tags.some((t) => t.toLowerCase().includes(q)))
        .slice(0, 10);
});

watch(
    () => props.open,
    async (open) => {
        if (open) {
            query.value = props.initialQuery || '';
            await nextTick();
            input.value?.focus();
        }
    },
);

function select() {
    emit('close');
}

function addToCart(slug) {
    const cart = useCartStore();
    cart.add(slug, 1);
    swalToast('Added to your basket.', 'success');
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="open"
                class="fixed inset-0 z-50 overflow-y-auto bg-white"
                role="dialog"
                aria-modal="true"
                aria-label="Search"
            >
                <div class="wd-container py-8">
                    <div class="flex items-center justify-between">
                        <span class="font-widget text-lg text-muted">Search</span>
                        <button type="button" aria-label="Close search" @click="emit('close')">
                            <i class="fa-solid fa-xmark text-2xl text-ink"></i>
                        </button>
                    </div>

                    <form
                        class="mt-8 flex items-center gap-4 border-b-2 border-line pb-4"
                        @submit.prevent
                    >
                        <i class="fa-solid fa-magnifying-glass text-xl text-ink"></i>
                        <input
                            ref="input"
                            v-model="query"
                            type="search"
                            placeholder="Start typing to see products you are looking for."
                            class="w-full bg-transparent text-lg outline-none placeholder:text-neutral-400 md:text-2xl"
                        />
                    </form>

                    <p
                        v-if="!query.trim()"
                        class="mt-10 text-center text-sm text-neutral-500"
                    >
                        Start typing to see products you are looking for.
                    </p>

                    <ul v-else-if="results.length" class="mt-8 divide-y divide-line">
                        <li v-for="p in results" :key="p.slug">
                            <div class="flex items-center gap-4 py-4 hover:bg-neutral-50">
                                <router-link
                                    :to="`/product/${p.slug}`"
                                    class="flex items-center gap-4 py-4 w-full"
                                >
                                    <img
                                        :src="p.image"
                                        :alt="p.name"
                                        class="h-16 w-16 rounded-card object-contain"
                                    />
                                    <span class="flex-1 font-semibold text-ink">{{ p.name }}</span>
                                    <span class="text-sm font-semibold text-navy">
                                        <PriceDisplay :value="p.price" />
                                    </span>
                                </router-link>
                                <button
                                    type="button"
                                    class="wd-btn wd-btn-outline w-32 h-11 flex items-center justify-center"
                                    @click="addToCart(p.slug)"
                                >
                                    Add to basket
                                </button>
                            </div>
                        </li>
                    </ul>

                    <p v-else class="mt-10 text-center text-sm text-neutral-500">
                        No products found for “{{ query }}”.
                    </p>
                </div>
            </div>
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
</style>