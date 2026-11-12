<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { byPriceAsc } from '../data/products';
import { useContentStore } from '../stores/content';
import ProductGrid from '../components/ProductGrid.vue';

const PER_PAGE = 20;

const content = useContentStore();
const catalogue = computed(() => content.catalogue);

const route = useRoute();
const router = useRouter();

const sort = ref('price-asc');
const perPage = ref(PER_PAGE);
const columns = ref(4);

const page = computed(() => Math.max(1, parseInt(route.params.page, 10) || 1));

const sorted = computed(() => {
    const list = [...byPriceAsc];
    switch (sort.value) {
        case 'price-desc':
            return list.sort((a, b) => b.price - a.price);
        case 'name':
            return list.sort((a, b) => a.name.localeCompare(b.name));
        default:
            return list;
    }
});

const totalPages = computed(() => Math.max(1, Math.ceil(sorted.value.length / perPage.value)));

const visible = computed(() => {
    const start = (page.value - 1) * perPage.value;
    return sorted.value.slice(start, start + perPage.value);
});

const columnClass = computed(
    () =>
        ({
            2: 'grid-cols-1 md:grid-cols-2',
            3: 'grid-cols-2 md:grid-cols-3',
            4: 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
        })[columns.value] || 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
);

function goToPage(n) {
    if (n < 1 || n > totalPages.value || n === page.value) return;
    router.push(n === 1 ? '/peptide-shop' : `/peptide-shop/page/${n}`);
}

// Reset to page 1 when sorting or density changes.
watch([sort, perPage], () => goToPage(1));
</script>

<template>
    <div>
        <section class="wd-page-title py-14 md:py-16">
            <div class="wd-container">
                <nav class="mb-3 text-sm text-white/60">
                    <router-link to="/" class="hover:text-white">Home</router-link>
                    <span class="mx-2">›</span>
                    <span class="text-white">{{ catalogue.shopTitle }}</span>
                </nav>
                <h1 class="text-3xl text-white md:text-4xl">{{ catalogue.shopTitle }}</h1>
            </div>
        </section>

        <section class="wd-container py-12">
            <!-- Toolbar -->
            <div class="mb-9 flex flex-wrap items-center justify-between gap-4 border-b border-line pb-5">
                <p class="text-sm text-neutral-500">
                    Showing
                    <strong class="text-ink">
                        {{ (page - 1) * perPage + 1 }}–{{ Math.min(page * perPage, sorted.length) }}
                    </strong>
                    of {{ sorted.length }} results
                </p>

                <div class="flex flex-wrap items-center gap-4">
                    <label class="flex items-center gap-2 text-sm text-neutral-500">
                        Show
                        <select v-model="perPage" class="wd-field !w-auto !py-2 !px-4">
                            <option :value="9">9</option>
                            <option :value="12">12</option>
                            <option :value="18">18</option>
                            <option :value="24">24</option>
                        </select>
                    </label>

                    <div class="flex items-center gap-1.5">
                        <button
                            v-for="n in [2, 3, 4]"
                            :key="n"
                            type="button"
                            class="grid h-8 w-8 place-items-center rounded-md border text-xs"
                            :class="columns === n ? 'border-primary bg-primary text-white' : 'border-line text-neutral-500'"
                            :aria-label="`${n} columns`"
                            @click="columns = n"
                        >
                            <i class="fa-solid fa-table-cells"></i>
                        </button>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-neutral-500">
                        Sort by
                        <select v-model="sort" class="wd-field !w-auto !py-2 !px-4">
                            <option value="price-asc">Price: low to high</option>
                            <option value="price-desc">Price: high to low</option>
                            <option value="name">Name</option>
                        </select>
                    </label>
                </div>
            </div>

            <ProductGrid v-if="visible.length" :products="visible" :columns="columnClass" />

            <div v-else class="rounded-card border border-line bg-white p-10 text-center">
                <h2 class="text-2xl">{{ catalogue.emptyTitle }}</h2>
                <p class="mt-3 text-neutral-500">{{ catalogue.emptyBody }}</p>
            </div>

            <!-- Pagination -->
            <nav
                v-if="totalPages > 1"
                class="mt-12 flex items-center justify-center gap-2"
                aria-label="Pagination"
            >
                <button
                    type="button"
                    class="grid h-10 w-10 place-items-center rounded-full border border-line text-ink transition hover:border-secondary hover:bg-secondary hover:text-white disabled:opacity-40"
                    :disabled="page === 1"
                    aria-label="Previous page"
                    @click="goToPage(page - 1)"
                >
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>

                <button
                    v-for="n in totalPages"
                    :key="n"
                    type="button"
                    class="grid h-10 w-10 place-items-center rounded-full border text-sm font-medium transition"
                    :class="n === page ? 'border-primary bg-primary text-white' : 'border-line text-ink hover:border-secondary'"
                    @click="goToPage(n)"
                >
                    {{ n }}
                </button>

                <button
                    type="button"
                    class="grid h-10 w-10 place-items-center rounded-full border border-line text-ink transition hover:border-secondary hover:bg-secondary hover:text-white disabled:opacity-40"
                    :disabled="page === totalPages"
                    aria-label="Next page"
                    @click="goToPage(page + 1)"
                >
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </nav>
        </section>
    </div>
</template>
