<script setup>
import { computed } from 'vue';
import { useContentStore } from '../stores/content';
import { allPeptides } from '../data/products';
import StandardsSection from '../components/StandardsSection.vue';
import ProductGrid from '../components/ProductGrid.vue';

const content = useContentStore();
const home = computed(() => content.home);
const site = computed(() => content.site);
</script>

<template>
    <div>
        <!-- Hero -->
        <section class="overflow-hidden bg-neutral-50">
            <div class="wd-container grid items-center gap-10 py-16 md:py-24 lg:grid-cols-2">
                <div>
                    <p class="font-nav text-sm uppercase tracking-[0.2em] text-primary">
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
        <section class="border-t border-black/10 bg-neutral-50/60">
            <div class="wd-container py-16 md:py-20">
                <div class="mx-auto max-w-3xl text-center">
                    <h2 class="text-3xl md:text-4xl">
                        {{ home.productsTitle }}
                    </h2>
                    <p class="mt-4 text-neutral-500">
                        {{ home.productsIntro }}
                    </p>
                </div>

                <div class="mt-12">
                    <ProductGrid :products="allPeptides" />
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

                    <router-link to="/coa" class="wd-btn mt-9">
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
                    <p class="font-nav text-sm uppercase tracking-[0.2em] text-secondary">
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
                    <img
                        :src="home.purityImage"
                        :alt="site.name"
                        loading="lazy"
                        class="mx-auto w-full max-w-md"
                    />
                </div>
            </div>
        </section>
    </div>
</template>
