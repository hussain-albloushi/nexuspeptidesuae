<script setup>
import { computed, ref } from 'vue';
import { useContentStore } from '../stores/content';
import { allPeptides, categories } from '../data/products';
import ProductGrid from '../components/ProductGrid.vue';

const content = useContentStore();
const catalogue = computed(() => content.catalogue);

// Filter state
const search = ref('');
const category = ref('all');
const sort = ref('price-asc');

// Filtered products
const filteredProducts = computed(() => {
    let list = [...allPeptides];

    // Category filter
    if (category.value !== 'all') {
        list = list.filter(p => p.category === category.value);
    }

    // Search filter
    if (search.value.trim()) {
        const term = search.value.trim().toLowerCase();
        list = list.filter(p =>
            p.name.toLowerCase().includes(term) ||
            p.tags.some(tag => tag.toLowerCase().includes(term))
        );
    }

    // Sort
    switch (sort.value) {
        case 'price-desc':
            return list.sort((a, b) => b.price - a.price);
        case 'name-asc':
            return list.sort((a, b) => a.name.localeCompare(b.name));
        case 'name-desc':
            return list.sort((a, b) => b.name.localeCompare(a.name));
        default: // price-asc
            return list.sort((a, b) => a.price - b.price);
    }
});

</script>

<template>
    <div>
        <!-- White page header, matching the source site (no black bars) -->
        <section class="bg-background py-14 md:py-16">
            <div class="wd-container text-center">
                <h1 class="text-3xl text-ink md:text-4xl">{{ catalogue.catalogueTitle }}</h1>
                <p class="mx-auto mt-4 max-w-3xl text-neutral-500">
                    {{ catalogue.catalogueIntro }}
                </p>
            </div>
        </section>

        <section class="bg-background pb-16 md:pb-20">
            <div class="wd-container">
                <!-- Filters -->
                <div class="mt-6 space-y-4">
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <label class="flex items-center gap-1 text-neutral-500">
                            <span class="font-medium">Category</span>
                            <select v-model="category" class="wd-field !w-40 !py-2 !px-3">
                                <option value="all">All</option>
                                <template v-for="cat in categories" :key="cat.slug">
                                    <option :value="cat.slug">{{ cat.name }}</option>
                                </template>
                            </select>
                        </label>

                        <label class="relative flex-1 min-w-[200px]">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 pointer-events-none">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input
                                v-model="search"
                                placeholder="Search products..."
                                class="wd-field w-full pl-10 py-2 pr-3 rounded-lg text-sm"
                            />
                        </label>

                        <label class="flex items-center gap-1 text-neutral-500">
                            <span class="font-medium">Sort by</span>
                            <select v-model="sort" class="wd-field !w-52 !py-2 !px-3">
                                <option value="price-asc">Price: low to high</option>
                                <option value="price-desc">Price: high to low</option>
                                <option value="name-asc">Name: A to Z</option>
                                <option value="name-desc">Name: Z to A</option>
                            </select>
                        </label>


                    </div>
                </div>

                <template v-if="filteredProducts.length">
                    <div class="mt-16">
                        <ProductGrid :products="filteredProducts" />
                    </div>
                </template>
                <div v-else class="rounded-card border border-black/10 bg-white p-10 text-center">
                    <h2 class="text-2xl">{{ catalogue.emptyTitle }}</h2>
                    <p class="mt-3 text-neutral-500">{{ catalogue.emptyBody }}</p>
                </div>
            </div>
        </section>
    </div>
</template>
