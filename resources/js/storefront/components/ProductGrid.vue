<script setup>
import ProductCard from './ProductCard.vue';

defineProps({
    products: { type: Array, required: true },
    columns: { type: String, default: 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4' },
});
</script>

<template>
    <div :class="['grid gap-x-6 gap-y-10', columns]">
        <ProductCard v-for="p in products" :key="p.slug" :product="p" />
    </div>
</template>
