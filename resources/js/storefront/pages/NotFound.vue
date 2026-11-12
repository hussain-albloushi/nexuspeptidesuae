<template>
    <div>
        <!-- Page title band, matching every other storefront page -->
        <section class="wd-page-title py-14 md:py-16">
            <div class="wd-container">
                <nav class="mb-3 text-sm text-white/60" aria-label="Breadcrumb">
                    <router-link to="/" class="hover:text-white">Home</router-link>
                    <span class="mx-2">&rsaquo;</span>
                    <span class="text-white">Page not found</span>
                </nav>
                <h1 class="text-3xl text-white md:text-4xl">404 — Page not found</h1>
            </div>
        </section>

        <section class="wd-container flex flex-col items-center py-16 text-center md:py-20">
            <p class="select-none font-widget text-8xl leading-none text-primary/10 md:text-[10rem]" aria-hidden="true">404</p>

            <h2 class="mt-10 font-heading text-3xl text-navy md:text-4xl">We can't find that page</h2>
            <p class="mt-4 max-w-xl text-muted">
                The link may be broken or the page may have moved. Your cart and account are safe — here are some
                helpful places to go next.
            </p>

            <div class="mt-9 flex flex-wrap items-center justify-center gap-4">
                <router-link to="/" class="wd-btn">
                    <i class="fa-solid fa-house" aria-hidden="true"></i>
                    Back to home
                </router-link>
                <router-link to="/peptide-shop" class="wd-btn wd-btn-outline">
                    <i class="fa-solid fa-store" aria-hidden="true"></i>
                    Browse peptides
                </router-link>
            </div>

            <!-- Quick links so the visitor never hits a dead end -->
            <div class="mt-12 w-full max-w-xl rounded-card border border-black/10 bg-white p-6 text-left">
                <h3 class="font-widget text-sm uppercase tracking-wide text-muted">Popular pages</h3>
                <ul class="mt-3 grid gap-x-10 sm:grid-cols-2">
                    <li v-for="link in popularLinks" :key="link.to">
                        <router-link
                            :to="link.to"
                            class="flex items-center justify-between border-b border-black/5 py-2.5 text-sm text-navy transition hover:text-ink"
                        >
                            {{ link.label }}
                            <i class="fa-solid fa-arrow-right text-xs text-muted" aria-hidden="true"></i>
                        </router-link>
                    </li>
                </ul>
            </div>

            <p class="mt-10 text-sm text-muted">
                Still stuck? Email us at
                <a :href="`mailto:${supportEmail}`" class="font-semibold text-navy underline decoration-1 underline-offset-2 hover:text-ink">
                    {{ supportEmail }}
                </a>
            </p>
        </section>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useContentStore } from '../stores/content';

const content = useContentStore();
const supportEmail = computed(() => content.site.supportEmail || 'info@nexuspeptides.com');

const popularLinks = [
    { label: 'All peptides', to: '/all-peptides' },
    { label: 'Peptide shop', to: '/peptide-shop' },
    { label: 'My account', to: '/my-account' },
    { label: 'My orders', to: '/my-orders' },
    { label: 'Help center', to: '/help-center' },
    { label: 'Return & refunds', to: '/return-refunds' },
];
</script>
