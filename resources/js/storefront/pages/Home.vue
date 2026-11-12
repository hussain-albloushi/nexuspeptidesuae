<script setup>
import { computed, ref } from 'vue';
import { useContentStore } from '../stores/content';
import { allPeptides, categories } from '../data/products';
import StandardsSection from '../components/StandardsSection.vue';
import ProductGrid from '../components/ProductGrid.vue';

const content = useContentStore();
const home = computed(() => content.home);
const site = computed(() => content.site);

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
        <!-- Hero -->
        <section class="overflow-hidden bg-neutral-50">
            <div class="wd-container grid items-center gap-10 py-16 md:py-24 lg:grid-cols-2">
                <div>
                    <p v-if="home.heroKicker" class="font-nav text-sm uppercase tracking-[0.2em] text-primary">
                        {{ home.heroKicker }}
                    </p>
                    <h1 class="mt-4 text-4xl leading-[1.15] md:text-5xl lg:text-6xl">
                        {{ home.heroTitle }}
                    </h1>
                    <p class="mt-6 max-w-xl text-[17px] leading-relaxed text-ink">
                        {{ home.heroBody }}
                    </p>
                    <router-link to="/all-peptides" class="wd-btn mt-8">
                        {{ home.heroCta }}
                        <i class="fa-solid fa-arrow-right"></i>
                    </router-link>
                </div>

                <div class="relative">
                    <img
                        :src="home.heroImage"
                        :alt="site.name"
                        class="mx-auto w-full max-w-lg"
                    />
                </div>
            </div>
        </section>

        <!-- Standards -->
        <StandardsSection :title="home.standardsTitle" :items="site.standards" />

        <!-- Product grid -->
        <section class="border-t border-line bg-neutral-50/60">
            <div class="wd-container py-16 md:py-20">
                <div class="mx-auto max-w-3xl text-center">
                    <h2 class="text-3xl md:text-4xl">
                        {{ home.productsTitle }}
                    </h2>
                    <p class="mt-4 text-neutral-500">
                        {{ home.productsIntro }}
                    </p>
                </div>

                <!-- Filters -->
                <div class="mt-12 space-y-4">
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

                <div class="mt-16">
                    <ProductGrid :products="filteredProducts" />
                </div>

                <div class="mt-12 text-center">
                    <router-link to="/peptide-shop" class="wd-btn wd-btn-outline">
                        {{ home.productsCta }}
                        <i class="fa-solid fa-arrow-right"></i>
                    </router-link>
                </div>
            </div>
        </section>

        <!-- Trusted quality -->
        <section class="wd-container py-16 md:py-20">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <h2 class="text-3xl md:text-4xl">{{ home.trustedTitle }}</h2>
                    <p class="mt-4 text-neutral-500">
                        {{ home.trustedBody }}
                    </p>

                    <ul class="mt-8 space-y-4">
                        <li v-for="t in home.trustedItems" :key="t" class="flex items-start gap-3">
                            <span class="mt-0.5 text-success">
                                <i class="fa-solid fa-circle-check"></i>
                            </span>
                            <span class="text-ink">{{ t }}</span>
                        </li>
                    </ul>

                    <router-link v-if="content.isPublished('coa')" to="/coa" class="wd-btn mt-9">
                        {{ home.trustedCta }}
                        <i class="fa-solid fa-arrow-right"></i>
                    </router-link>
                </div>

                <div class="rounded-card bg-neutral-50 p-8">
                    <img
                        :src="home.trustedImage"
                        :alt="site.name"
                        loading="lazy"
                        class="w-full rounded-card"
                    />
                </div>
            </div>
        </section>

        <!-- 99% purity -->
        <section class="bg-navy-dark text-white">
            <div class="wd-container grid items-center gap-12 py-16 md:py-24 lg:grid-cols-2">
                <div>
                    <p v-if="home.purityKicker" class="font-nav text-sm uppercase tracking-[0.2em] text-secondary">
                        {{ home.purityKicker }}
                    </p>
                    <h2 class="mt-4 text-3xl text-white md:text-4xl">
                        {{ home.purityTitle }}
                    </h2>
                    <p class="mt-3 font-widget text-lg text-secondary">
                        {{ home.puritySubtitle }}
                    </p>

                    <div class="mt-6 space-y-5 text-[15px] leading-relaxed text-white/75">
                        <p v-for="(paragraph, i) in home.purityParagraphs" :key="i">
                            {{ paragraph }}
                        </p>
                        <p class="font-semibold text-white">
                            {{ home.purityNotice }}
                        </p>
                    </div>
                </div>

                <div class="relative">
                    <div class="mx-auto max-w-md rounded-card bg-white p-8">
                        <img
                            :src="home.purityImage"
                            :alt="site.name"
                            loading="lazy"
                            class="w-full"
                        />
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
