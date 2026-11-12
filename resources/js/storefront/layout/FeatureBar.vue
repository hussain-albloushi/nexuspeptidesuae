<script setup>
import { computed } from 'vue';
import { useContentStore } from '../stores/content';

const content = useContentStore();

/** Two identical tracks so the marquee loops seamlessly. */
const track = computed(() => {
    const features = content.site.features || [];
    return [...features, ...features];
});
</script>

<template>
    <section class="overflow-hidden border-y border-line bg-background py-7">
        <div class="wd-marquee flex w-max gap-14 md:gap-20">
            <div
                v-for="(f, i) in track"
                :key="i"
                class="flex w-64 shrink-0 items-start gap-3 md:w-72"
            >
                <span class="mt-0.5 text-secondary">
                    <i :class="f.icon" class="text-xl"></i>
                </span>
                <div>
                    <h3 class="font-widget text-[15px] capitalize text-ink">{{ f.title }}</h3>
                    <p class="mt-1 text-[13px] leading-relaxed text-neutral-500">{{ f.body }}</p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.wd-marquee {
    animation: wd-scroll 38s linear infinite;
}

@keyframes wd-scroll {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(-50%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .wd-marquee {
        animation: none;
    }
}
</style>
