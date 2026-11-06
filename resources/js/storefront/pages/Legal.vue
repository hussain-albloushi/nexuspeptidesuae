<script setup>
import { computed } from 'vue';
import { useContentStore } from '../stores/content';

const props = defineProps({
    page: { type: String, required: true },
});

const content = useContentStore();

const pageContent = computed(() => content.legal[props.page] || content.legal.terms);
</script>

<template>
    <div>
        <section class="bg-background py-14 md:py-16">
            <div class="wd-container">
                <nav class="mb-3 text-sm text-neutral-500">
                    <router-link to="/" class="hover:text-secondary">Home</router-link>
                    <span class="mx-2">›</span>
                    <span class="text-ink">{{ pageContent.title }}</span>
                </nav>
                <h1 class="text-3xl text-navy md:text-4xl">{{ pageContent.title }}</h1>
            </div>
        </section>

        <section class="wd-container max-w-4xl py-14 md:py-16">
            <template v-for="(block, i) in pageContent.blocks" :key="i">
                <h2 v-if="block.kind === 'heading'" class="mt-8 text-xl first:mt-0">{{ block.text }}</h2>
                <p v-else class="mt-4 leading-relaxed text-ink">{{ block.text }}</p>
            </template>
        </section>
    </div>
</template>
