<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useContentStore } from '../stores/content';
import { useRouter } from 'vue-router';
import TopBar from './TopBar.vue';
import SiteHeader from './SiteHeader.vue';
import MobileMenu from './MobileMenu.vue';
import SearchOverlay from './SearchOverlay.vue';
import CartDrawer from './CartDrawer.vue';
import FeatureBar from './FeatureBar.vue';
import SiteFooter from './SiteFooter.vue';

const searchOpen = ref(false);
const menuOpen = ref(false);
const cartOpen = ref(false);
const searchQuery = ref('');
const router = useRouter();

function openSearch(query = '') {
    searchQuery.value = query;
    searchOpen.value = true;
    menuOpen.value = false;
}

function closeAll() {
    searchOpen.value = false;
    menuOpen.value = false;
    cartOpen.value = false;
}

// Any navigation dismisses the overlays, matching the source site.
const content = useContentStore();
const refreshSettings = () => { if (!document.hidden) void content.refreshStoreSettings(); };
watch(cartOpen, open => { if (open) refreshSettings(); });
const stopNavigation = router.afterEach(() => { closeAll(); refreshSettings(); });
let settingsTimer;
onMounted(() => {
    refreshSettings();
    settingsTimer = setInterval(refreshSettings, 30000);
    document.addEventListener('visibilitychange', refreshSettings);
});
onBeforeUnmount(() => {
    stopNavigation();
    clearInterval(settingsTimer);
    document.removeEventListener('visibilitychange', refreshSettings);
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background">
        <TopBar />
        <SiteHeader
            @open-search="openSearch()"
            @open-menu="menuOpen = true"
            @open-cart="cartOpen = true"
        />

        <main id="main-content" class="flex-1">
            <router-view />
        </main>

        <FeatureBar />
        <SiteFooter />

        <SearchOverlay
            :open="searchOpen"
            :initial-query="searchQuery"
            @close="searchOpen = false"
        />
        <MobileMenu
            :open="menuOpen"
            @close="menuOpen = false"
            @open-search="openSearch"
        />
        <CartDrawer :open="cartOpen" @close="cartOpen = false" />
    </div>
</template>
