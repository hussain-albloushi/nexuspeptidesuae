<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useContentStore } from '../stores/content';
import { useCartStore } from '../stores/cart';
import { useWishlistStore } from '../stores/wishlist';
import { useCurrencyStore } from '../stores/currency';
import { useAuthStore } from '../stores/auth';
import { swalConfirm } from '../services/alerts';

const emit = defineEmits(['open-search', 'open-menu', 'open-cart']);

const content = useContentStore();
const site = computed(() => content.site);

const cart = useCartStore();
const wishlist = useWishlistStore();
const auth = useAuthStore();
const router = useRouter();

const accountMenuOpen = ref(false);
const accountDropdown = ref(null);

function handleClickOutside(e) {
    if (accountDropdown.value && !accountDropdown.value.contains(e.target)) {
        accountMenuOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

async function doLogout() {
    accountMenuOpen.value = false;
    const ok = await swalConfirm('Log out?', 'Are you sure you want to log out?', 'Log out');
    if (!ok) return;
    await auth.logout();
    router.push('/');
}

</script>

<template>
    <header class="sticky top-0 z-40 border-b border-line bg-white">
        <div class="wd-container flex h-[74px] items-center gap-6">
            <!-- Logo -->
            <router-link to="/" class="shrink-0" :aria-label="site.name">
                <img :src="site.logo" :alt="site.name" class="h-14 w-auto" />
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
                    class="relative hidden cursor-pointer text-ink transition hover:text-secondary sm:flex"
                    aria-label="Shopping cart"
                    @click="emit('open-cart')"
                >
                    <i class="fa-solid fa-cart-shopping text-[20px]"></i>
                    <span
                        v-if="cart.count"
                        class="absolute -right-2.5 -top-5 grid h-4 min-w-4 place-items-center rounded-full bg-primary px-1 text-[10px] font-bold text-white"
                    >
                        {{ cart.count }}
                    </span>
                </button>

                <!-- Guest: Login / Register -->
                <router-link
                    v-if="!auth.isAuthenticated"
                    to="/my-account"
                    class="hidden items-center gap-2 text-ink transition hover:text-secondary xl:flex"
                >
                    <i class="fa-solid fa-user text-[16px]"></i>
                    <span class="text-sm font-medium">Login / Register</span>
                </router-link>

                <!-- Logged in: user name + dropdown -->
                <div v-else ref="accountDropdown" class="relative hidden xl:block">
                    <button
                        type="button"
                        class="flex items-center gap-2 text-ink transition hover:text-secondary"
                        @click="accountMenuOpen = !accountMenuOpen"
                    >
                        <i class="fa-solid fa-user text-[16px]"></i>
                        <span class="text-sm font-medium">{{ auth.user.name }}</span>
                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </button>

                    <div
                        v-if="accountMenuOpen"
                        class="absolute right-0 top-full mt-2 w-48 rounded-card border border-line bg-white py-2 shadow-lg"
                    >
                        <router-link
                            to="/my-orders"
                            class="block px-4 py-2 text-sm text-ink transition hover:bg-neutral-50 hover:text-secondary"
                            @click="accountMenuOpen = false"
                        >
                            <i class="fa-solid fa-box-open mr-2 text-xs"></i> Orders
                        </router-link>
                        <router-link
                            to="/my-account"
                            class="block px-4 py-2 text-sm text-ink transition hover:bg-neutral-50 hover:text-secondary"
                            @click="accountMenuOpen = false"
                        >
                            <i class="fa-solid fa-user mr-2 text-xs"></i> My Account
                        </router-link>
                        <router-link
                            to="/cart"
                            class="block px-4 py-2 text-sm text-ink transition hover:bg-neutral-50 hover:text-secondary"
                            @click="accountMenuOpen = false"
                        >
                            <i class="fa-solid fa-cart-shopping mr-2 text-xs"></i> Cart
                        </router-link>
                        <router-link
                            to="/wishlist"
                            class="block px-4 py-2 text-sm text-ink transition hover:bg-neutral-50 hover:text-secondary"
                            @click="accountMenuOpen = false"
                        >
                            <i class="fa-solid fa-heart mr-2 text-xs"></i> Wishlist
                        </router-link>
                        <hr class="my-1 border-line" />
                        <button
                            type="button"
                            class="block w-full px-4 py-2 text-left text-sm text-danger transition hover:bg-neutral-50"
                            @click="doLogout"
                        >
                            <i class="fa-solid fa-right-from-bracket mr-2 text-xs"></i> Log out
                        </button>
                    </div>
                </div>

                <!-- Mobile cart + burger -->
                <button
                    type="button"
                    class="relative text-ink sm:hidden"
                    aria-label="Shopping cart"
                    @click="emit('open-cart')"
                >
                    <i class="fa-solid fa-cart-shopping text-[18px]"></i>
                    <span
                        v-if="cart.count"
                        class="absolute -right-2 -top-2 grid h-4 min-w-4 place-items-center rounded-full bg-primary px-1 text-[10px] font-bold text-white"
                    >
                        {{ cart.count }}
                    </span>
                </button>

                <button
                    type="button"
                    class="flex items-center gap-2 text-ink lg:hidden"
                    aria-label="Menu"
                    @click="emit('open-menu')"
                >
                    <i class="fa-solid fa-bars text-[19px]"></i>
                    <span class="font-nav text-[15px] capitalize hidden md:inline">Menu</span>
                </button>
            </div>
        </div>
    </header>
</template>
