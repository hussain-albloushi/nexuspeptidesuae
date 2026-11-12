<script setup>
import { computed, ref } from 'vue';
import { useContentStore } from '../stores/content';

const content = useContentStore();
const site = computed(() => content.site);

defineProps({ open: Boolean });
const emit = defineEmits(['close', 'open-search']);

const query = ref('');
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="open"
                class="fixed inset-0 z-50 bg-black/50"
                @click="emit('close')"
            ></div>
        </Transition>

        <Transition name="slide-right">
            <aside
                v-if="open"
                class="fixed right-0 top-0 z-50 flex h-full w-[85%] max-w-sm flex-col bg-white shadow-2xl"
                aria-label="Mobile navigation"
            >
                <div class="flex items-center justify-between border-b border-line px-6 py-5">
                    <img :src="site.logo" :alt="site.name" class="h-11 w-auto" />
                    <button type="button" aria-label="Close" @click="emit('close')">
                        <i class="fa-solid fa-xmark text-xl text-ink"></i>
                    </button>
                </div>

                <div class="relative px-6 py-4">
                    <input
                        type="text"
                        placeholder="Search products..."
                        class="wd-field w-full py-2 pr-10 rounded-md text-sm"
                        v-model="query"
                        @input="emit('open-search', query)"
                    />
                    <i class="fa-solid fa-magnifying-glass absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 pointer-events-none"></i>
                </div>

                <nav class="flex flex-col px-6 py-4">
                    <router-link
                        v-for="item in site.nav"
                        :key="item.to"
                        :to="item.to"
                        class="border-b border-line py-4 font-nav text-base capitalize text-ink hover:text-secondary"
                        @click="emit('close')"
                    >
                        {{ item.label }}
                    </router-link>

                    <router-link
                        to="/coa"
                        class="border-b border-line py-4 font-nav text-base capitalize text-ink hover:text-secondary"
                        @click="emit('close')"
                    >
                        Certificate of Analysis
                    </router-link>
                </nav>

                <div class="mt-auto flex flex-col gap-3 border-t border-line px-6 py-6">
                    <router-link
                        to="/my-account"
                        class="wd-btn w-full"
                        @click="emit('close')"
                    >
                        <i class="fa-solid fa-user"></i> Login / Register
                    </router-link>
                    <router-link
                        to="/wishlist"
                        class="wd-btn wd-btn-outline w-full"
                        @click="emit('close')"
                    >
                        <i class="fa-solid fa-heart"></i> Wishlist
                    </router-link>
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-right-enter-active,
.slide-right-leave-active {
    transition: transform 0.3s ease;
}
.slide-right-enter-from,
.slide-right-leave-to {
    transform: translateX(100%);
}
</style>
