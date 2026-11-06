<script setup>
import { computed } from 'vue';
import { useContentStore } from '../stores/content';
import { allPeptides } from '../data/products';
import ProductGrid from '../components/ProductGrid.vue';

const content = useContentStore();
const catalogue = computed(() => content.catalogue);
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
                <template v-if="allPeptides.length">
                    <p class="mb-8 text-sm text-neutral-500">
                        Showing all {{ allPeptides.length }} results
                    </p>
                    <ProductGrid :products="allPeptides" />
                </template>
                <div v-else class="rounded-card border border-black/10 bg-white p-10 text-center">
                    <h2 class="text-2xl">{{ catalogue.emptyTitle }}</h2>
                    <p class="mt-3 text-neutral-500">{{ catalogue.emptyBody }}</p>
                </div>
            </div>
        </section>
    </div>
</template>
