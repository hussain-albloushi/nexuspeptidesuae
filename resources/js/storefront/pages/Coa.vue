<script setup>
import { computed, ref } from 'vue';
import { useContentStore } from '../stores/content';

const content = useContentStore();
const coa = computed(() => content.coa);

/**
 * Every thumbnail on the source site currently 404s (the files under
 * uploads/2026/05 are gone upstream), so fall back to a styled tile
 * instead of a broken-image icon.
 */
const failed = ref(new Set());

function onImgError(url) {
    failed.value = new Set([...failed.value, url]);
}
</script>

<template>
    <div>
        <section class="wd-page-title py-14 md:py-16">
            <div class="wd-container">
                <nav class="mb-3 text-sm text-white/60">
                    <router-link to="/" class="hover:text-white">Home</router-link>
                    <span class="mx-2">›</span>
                    <span class="text-white">Certificate of Analysis</span>
                </nav>
                <h1 class="text-3xl text-white md:text-4xl">{{ coa.title }}</h1>
            </div>
        </section>

        <section class="wd-container py-14 md:py-16">
            <p class="mx-auto max-w-3xl text-center leading-relaxed text-ink">
                {{ coa.intro }}
            </p>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="certificate in coa.coas"
                    :key="`${certificate.name}-${certificate.batch}`"
                    class="flex flex-col rounded-card border border-black/10 bg-white p-6 transition hover:shadow-lg"
                >
                    <div class="flex items-center gap-4">
                        <img
                            v-if="certificate.image && !failed.has(certificate.image)"
                            :src="certificate.image"
                            :alt="certificate.name"
                            loading="lazy"
                            class="h-20 w-20 shrink-0 rounded-card object-contain"
                            @error="onImgError(certificate.image)"
                        />
                        <span
                            v-else
                            class="grid h-20 w-20 shrink-0 place-items-center rounded-card bg-neutral-100 text-neutral-400"
                            aria-hidden="true"
                        >
                            <i class="fa-solid fa-file-lines text-2xl"></i>
                        </span>
                        <h2 class="font-widget text-lg leading-tight text-ink">{{ certificate.name }}</h2>
                    </div>

                    <dl class="mt-5 space-y-1.5 text-sm">
                        <div class="flex gap-2">
                            <dt class="text-neutral-500">Batch:</dt>
                            <dd class="font-medium text-ink">{{ certificate.batch }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="text-neutral-500">Date:</dt>
                            <dd class="font-medium text-ink">{{ certificate.date }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="text-neutral-500">Purity:</dt>
                            <dd class="font-medium text-ink">{{ certificate.purity }}</dd>
                        </div>
                    </dl>

                    <a
                        v-if="certificate.file"
                        :href="certificate.file"
                        target="_blank"
                        rel="noopener"
                        class="wd-btn mt-6 w-full"
                    >
                        <i class="fa-solid fa-download"></i>
                        Download COA
                    </a>
                </article>
            </div>
        </section>
    </div>
</template>
