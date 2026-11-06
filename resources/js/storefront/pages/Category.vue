<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { products, categories, byPriceAsc } from '../data/products';
import ProductGrid from '../components/ProductGrid.vue';

const route = useRoute();

const isTag = computed(() => route.name === 'tag');

const category = computed(() =>
    categories.find((c) => c.slug === route.params.slug) || null,
);

const title = computed(() => {
    if (isTag.value) return String(route.params.slug).replace(/-/g, ' ');
    return category.value?.name || 'Products';
});

const description = computed(() => {
    if (isTag.value) return '';
    return (
        category.value?.description ||
        'Explore a curated selection of high-purity peptides, quality-controlled and batch-traceable for consistent performance.'
    );
});

const list = computed(() => {
    const slug = String(route.params.slug);
    const pool = byPriceAsc;
    if (isTag.value) {
        return pool.filter((p) => p.tags.some((t) => t.toLowerCase().replace(/\s+/g, '-') === slug));
    }
    return pool.filter((p) => p.category === slug);
});
</script>

<template>
    <div>
        <section class="bg-background py-14 md:py-16">
            <div class="wd-container">
                <nav class="mb-3 text-sm text-neutral-500">
                    <router-link to="/" class="hover:text-secondary">Home</router-link>
                    <span class="mx-2">›</span>
                    <span class="text-ink capitalize">{{ title }}</span>
                </nav>
                <h1 class="text-3xl capitalize text-navy md:text-4xl">{{ title }}</h1>
            </div>
        </section>

        <section class="wd-container py-12">
            <p class="mb-8 text-sm text-neutral-500">
                Showing all {{ list.length }} {{ list.length === 1 ? 'result' : 'results' }}
            </p>

            <p v-if="!list.length" class="rounded-card bg-neutral-50 py-20 text-center text-neutral-500">
                No products found in this category.
            </p>

            <ProductGrid v-else :products="list" />
        </section>
    </div>
</template>
