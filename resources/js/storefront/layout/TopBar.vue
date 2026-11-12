<script setup>
import { computed } from 'vue';
import { useContentStore } from '../stores/content';

const content = useContentStore();
const site = computed(() => content.site);
</script>

<template>
    <div class="bg-navy-dark text-white">
        <!-- Hidden on desktop on the source site (whb-hidden-desktop) -->
        <div class="wd-container hidden items-center justify-between gap-4 py-2 text-[13px] md:flex">
            <p class="tracking-wide opacity-90">
                {{ site.topbar }}
            </p>

            <router-link
                v-if="content.isPublished('coa')"
                to="/coa"
                class="hidden shrink-0 font-semibold text-white underline decoration-dotted underline-offset-4 hover:opacity-80 sm:block"
            >
                Certificate of Analysis
            </router-link>
        </div>
    </div>
</template>
