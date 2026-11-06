<script setup>
import { ref } from 'vue';
import { useRoute } from 'vue-router';
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

const route = useRoute();

function closeAll() {
    searchOpen.value = false;
    menuOpen.value = false;
    cartOpen.value = false;
}

// Any navigation dismisses the overlays, matching the source site.
route?.afterEach?.(closeAll);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background">
        <TopBar />
        <SiteHeader
            @open-search="searchOpen = true"
            @open-menu="menuOpen = true"
            @open-cart="cartOpen = true"
        />

        <main id="main-content" class="flex-1">
            <router-view />
        </main>

        <FeatureBar />
        <SiteFooter />

        <SearchOverlay :open="searchOpen" @close="searchOpen = false" />
        <MobileMenu :open="menuOpen" @close="menuOpen = false" />
        <CartDrawer :open="cartOpen" @close="cartOpen = false" />
    </div>
</template>
