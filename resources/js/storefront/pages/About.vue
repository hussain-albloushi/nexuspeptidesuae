<script setup>
import { computed } from 'vue';
import { useContentStore } from '../stores/content';
import StandardsSection from '../components/StandardsSection.vue';

const content = useContentStore();
const about = computed(() => content.about);
const site = computed(() => content.site);
</script>

<template>
    <div>
        <section class="wd-page-title py-14 md:py-16">
            <div class="wd-container">
                <nav class="mb-3 text-sm text-white/60">
                    <router-link to="/" class="hover:text-white">Home</router-link>
                    <span class="mx-2">›</span>
                    <span class="text-white">{{ about.title }}</span>
                </nav>
                <h1 class="text-3xl text-white md:text-4xl">{{ about.title }}</h1>
            </div>
        </section>

        <!-- Who we are -->
        <section class="wd-container py-16 md:py-20">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <p class="font-nav text-sm uppercase tracking-[0.2em] text-primary">{{ about.kicker }}</p>
                    <h2 class="mt-4 text-3xl leading-tight md:text-4xl">
                        {{ about.heading }}
                    </h2>
                    <p class="mt-6 leading-relaxed text-ink">
                        {{ about.body }}
                    </p>
                    <router-link to="/all-peptides" class="wd-btn mt-8">
                        {{ about.cta }}
                        <i class="fa-solid fa-arrow-right"></i>
                    </router-link>
                </div>

                <div class="rounded-card bg-neutral-50 p-8">
                    <img
                        :src="about.image"
                        :alt="site.name"
                        loading="lazy"
                        class="w-full rounded-card"
                    />
                </div>
            </div>
        </section>

        <!-- Why choose -->
        <div class="border-t border-black/10 bg-neutral-50/60">
            <StandardsSection
                :title="about.whyTitle"
                :intro="about.whyIntro"
                :items="site.standards"
            />
        </div>

        <!-- 99% purity -->
        <section class="wd-container py-16 md:py-20">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <p class="font-nav text-sm uppercase tracking-[0.2em] text-primary">
                        {{ about.qualityKicker }}
                    </p>
                    <h2 class="mt-4 text-3xl md:text-4xl">{{ about.qualityTitle }}</h2>
                    <p class="mt-3 font-widget text-lg text-muted">{{ about.qualitySubtitle }}</p>

                    <div class="mt-6 space-y-5 leading-relaxed text-ink">
                        <p v-for="(paragraph, i) in about.qualityParagraphs" :key="i">
                            {{ paragraph }}
                        </p>
                    </div>
                </div>

                <div class="rounded-card bg-neutral-50 p-8">
                    <img
                        :src="about.qualityImage"
                        :alt="site.name"
                        loading="lazy"
                        class="w-full rounded-card"
                    />
                </div>
            </div>
        </section>
    </div>
</template>
