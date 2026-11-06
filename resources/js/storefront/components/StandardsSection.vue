<script setup>
defineProps({
    title: { type: String, required: true },
    intro: { type: String, default: '' },
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <section class="wd-container py-16 md:py-20">
        <div class="mx-auto max-w-3xl text-center">
            <h2 class="text-3xl md:text-4xl">{{ title }}</h2>
            <p v-if="intro" class="mt-4 text-neutral-500">{{ intro }}</p>
        </div>

        <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="item in items"
                :key="item.title"
                class="rounded-card border border-black/10 bg-white p-7 transition hover:shadow-lg"
            >
                <span class="grid h-14 w-14 place-items-center rounded-full bg-primary/15 text-primary">
                    <i :class="item.icon" class="text-2xl"></i>
                </span>
                <h3 class="mt-5 font-widget text-lg capitalize text-ink">{{ item.title }}</h3>
                <p class="mt-3 text-sm leading-relaxed text-neutral-500">{{ item.body }}</p>
            </div>
        </div>
    </section>
</template>
