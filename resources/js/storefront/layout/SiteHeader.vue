<script setup>
import { computed } from 'vue';
import { useContentStore } from '../stores/content';
import { useCartStore } from '../stores/cart';
import { useWishlistStore } from '../stores/wishlist';
import { useCurrencyStore } from '../stores/currency';

const emit = defineEmits(['open-search', 'open-menu', 'open-cart']);

const content = useContentStore();
const site = computed(() => content.site);

const cart = useCartStore();
const wishlist = useWishlistStore();

const cartLabelItems = computed(() => {
    const currency = useCurrencyStore();
    return cart.count === 0
        ? `Cart 0 items`
        : `Cart ${cart.count} items`;
});

const cartLabelTotal = computed(() => {
    const currency = useCurrencyStore();
    return currency.format(0);
});
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-black/10 bg-white">
        <div class="wd-container flex h-[74px] items-center gap-6">
            <!-- Logo -->
            <router-link to="/" class="shrink-0" :aria-label="site.name">
                <img :src="site.logo" :alt="site.name" class="h-11 w-auto" />
            </router-link>

            <!-- Primary nav -->
            <nav class="hidden flex-1 items-center gap-7 lg:flex" aria-label="Main">
                <router-link
                    v-for="item in site.nav"
                    :key="item.to"
                    :to="item.to"
                    class="font-nav text-[17px] capitalize text-ink transition hover:text-secondary"
                    active-class="text-primary"
                >
                    {{ item.label }}
                </router-link>
            </nav>

            <!-- Actions -->
            <div class="ml-auto flex items-center gap-5">
                <button
                    type="button"
                    class="text-ink transition hover:text-secondary"
                    aria-label="Search"
                    @click="emit('open-search')"
                >
                    <i class="fa-solid fa-magnifying-glass text-[17px]"></i>
                </button>

                <router-link
                    to="/wishlist"
                    class="relative text-ink transition hover:text-secondary"
                    aria-label="Wishlist"
                >
                    <i class="fa-solid fa-heart text-[17px]"></i>
                    <span
                        v-if="wishlist.count"
                        class="absolute -right-2.5 -top-2 grid h-4 min-w-4 place-items-center rounded-full bg-primary px-1 text-[10px] font-bold text-white"
                    >
                        {{ wishlist.count }}
                    </span>
                </router-link>

                <button
                    type="button"
                    class="hidden items-center gap-2 text-ink transition hover:text-secondary sm:flex"
                    aria-label="Shopping cart"
                    @click="emit('open-cart')"
                >
                    <i class="fa-solid fa-bag-shopping text-[17px]"></i>
                    <span class="text-sm font-medium">{{ cartLabelItems }}</span>
                    <PriceDisplay :value="0" class="h-[1.1em] w-auto inline-block align-baseline" />
                </button>

                <router-link
                    to="/my-account"
                    class="hidden items-center gap-2 text-ink transition hover:text-secondary xl:flex"
                >
                    <i class="fa-solid fa-user text-[16px]"></i>
                    <span class="text-sm font-medium">Login / Register</span>
                </router-link>

                <!-- Mobile cart + burger -->
                <button
                    type="button"
                    class="relative text-ink sm:hidden"
                    aria-label="Shopping cart"
                    @click="emit('open-cart')"
                >
                    <i class="fa-solid fa-bag-shopping text-[18px]"></i>
                    <span
                        v-if="cart.count"
                        class="absolute -right-2 -top-2 grid h-4 min-w-4 place-items-center rounded-full bg-primary px-1 text-[10px] font-bold text-white"
                    >
                        {{ cart.count }}
                    </span>
                    <PriceDisplay :value="0" class="h-[1.1em] w-auto inline-block align-baseline hidden sm:inline-block" />
                </button>

                <button
                    type="button"
                    class="flex items-center gap-2 text-ink lg:hidden"
                    aria-label="Menu"
                    @click="emit('open-menu')"
                >
                    <i class="fa-solid fa-bars text-[19px]"></i>
                    <span class="font-nav text-[15px] capitalize">Menu</span>
                </button>
            </div>
        </div>
    </header>
</template>
