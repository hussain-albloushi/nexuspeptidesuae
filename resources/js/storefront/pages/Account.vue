<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useContentStore } from '../stores/content';
import { swalToast, swalConfirm } from '../services/alerts';

const router = useRouter();
const auth = useAuthStore();
const content = useContentStore();
const site = computed(() => content.site);

const login = ref({ email: '', password: '', remember: false });
const register = ref({ email: '', password: '', agree: false });
const lostOpen = ref(false);
const lostEmail = ref('');
const busy = ref(false);

function doLogin() {
    const f = login.value;
    if (!f.email.trim() || !f.password) {
        swalToast('Please enter your username or email and password.', 'error');
        return;
    }
    busy.value = true;
    auth.login(f.email.trim());
    busy.value = false;
    login.value = { email: '', password: '', remember: false };
    swalToast('You are now logged in.');
    router.push('/');
}

function doRegister() {
    const f = register.value;
    if (!f.email.trim() || !f.password) {
        swalToast('Please enter your email address and password.', 'error');
        return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email)) {
        swalToast('Please enter a valid email address.', 'error');
        return;
    }
    if (f.password.length < 6) {
        swalToast('Your password must be at least 6 characters.', 'error');
        return;
    }
    if (!f.agree) {
        swalToast('Please read and accept the privacy policy to continue.', 'error');
        return;
    }
    busy.value = true;
    auth.register({ name: f.email.split('@')[0], email: f.email.trim() });
    busy.value = false;
    register.value = { email: '', password: '', agree: false };
    swalToast('Your account has been created.');
    router.push('/');
}

async function doLogout() {
    const ok = await swalConfirm('Log out?', 'Are you sure you want to log out?', 'Log out');
    if (!ok) return;
    auth.logout();
    swalToast('You have been logged out.', 'info');
}

function sendReset() {
    const value = lostEmail.value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        swalToast('Please enter a valid email address.', 'error');
        return;
    }
    lostEmail.value = '';
    lostOpen.value = false;
    swalToast('If that email address is on file, a reset link has been sent.');
}
</script>

<template>
    <div>
        <section class="wd-page-title py-14 md:py-16">
            <div class="wd-container">
                <nav class="mb-3 text-sm text-white/60">
                    <router-link to="/" class="hover:text-white">Home</router-link>
                    <span class="mx-2">›</span>
                    <span class="text-white">My account</span>
                </nav>
                <h1 class="text-3xl text-white md:text-4xl">My account</h1>
            </div>
        </section>

        <section class="wd-container py-14 md:py-16">
            <!-- Logged in -->
            <div v-if="auth.isAuthenticated" class="mx-auto max-w-2xl rounded-card border border-black/10 p-8">
                <div class="flex items-center gap-4">
                    <span class="grid h-14 w-14 place-items-center rounded-full bg-primary/15 text-primary">
                        <i class="fa-solid fa-user text-xl"></i>
                    </span>
                    <div>
                        <h2 class="text-2xl">Hello, {{ auth.user.name }}</h2>
                        <p class="mt-1 text-sm text-neutral-500">{{ auth.user.email }}</p>
                    </div>
                </div>

                <div class="mt-8 grid gap-4 sm:grid-cols-3">
                    <router-link
                        to="/peptide-shop"
                        class="rounded-card border border-black/10 p-5 text-center transition hover:border-secondary"
                    >
                        <i class="fa-solid fa-bag-shopping text-primary"></i>
                        <p class="mt-2 text-sm font-medium text-ink">Shop</p>
                    </router-link>
                    <router-link
                        to="/wishlist"
                        class="rounded-card border border-black/10 p-5 text-center transition hover:border-secondary"
                    >
                        <i class="fa-solid fa-heart text-primary"></i>
                        <p class="mt-2 text-sm font-medium text-ink">Wishlist</p>
                    </router-link>
                    <router-link
                        to="/cart"
                        class="rounded-card border border-black/10 p-5 text-center transition hover:border-secondary"
                    >
                        <i class="fa-solid fa-cart-shopping text-primary"></i>
                        <p class="mt-2 text-sm font-medium text-ink">Cart</p>
                    </router-link>
                </div>

                <button type="button" class="wd-btn wd-btn-outline mt-8" @click="doLogout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    Log out
                </button>
            </div>

            <!-- Guest: Login + Register, matching the source layout -->
            <div v-else class="grid gap-12 lg:grid-cols-2 lg:gap-16">
                <!-- Login -->
                <div>
                    <h2 class="text-2xl">Login</h2>

                    <form class="mt-6 space-y-5" @submit.prevent="doLogin">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="lemail">
                                Username or email address <span class="text-danger">Required</span>
                            </label>
                            <input
                                id="lemail"
                                v-model="login.email"
                                type="text"
                                class="wd-field"
                                required
                            />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="lpass">
                                Password <span class="text-danger">Required</span>
                            </label>
                            <input
                                id="lpass"
                                v-model="login.password"
                                type="password"
                                class="wd-field"
                                required
                            />
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-ink">
                                <input v-model="login.remember" type="checkbox" class="accent-primary" />
                                Remember me
                            </label>
                            <button
                                type="button"
                                class="text-sm text-navy underline decoration-dotted underline-offset-4 hover:text-secondary"
                                @click="lostOpen = !lostOpen"
                            >
                                Lost your password?
                            </button>
                        </div>

                        <!-- Lost password -->
                        <div v-if="lostOpen" class="space-y-3 rounded-card bg-neutral-50 p-5">
                            <p class="text-sm text-ink">
                                Lost your password? Please enter your username or email address. You
                                will receive a link to create a new password via email.
                            </p>
                            <label class="mb-2 block text-sm font-medium text-ink" for="remail">
                                Username or email address
                            </label>
                            <input id="remail" v-model="lostEmail" type="email" class="wd-field" />
                            <button type="button" class="wd-btn !px-7 !py-3 text-sm" @click="sendReset">
                                Reset password
                            </button>
                        </div>

                        <button type="submit" class="wd-btn" :disabled="busy">
                            <i v-if="busy" class="fa-solid fa-circle-notch fa-spin"></i>
                            Log in
                        </button>
                    </form>
                </div>

                <!-- Register -->
                <div>
                    <h2 class="text-2xl">Register</h2>

                    <p class="mt-4 leading-relaxed text-ink">
                        Registering for {{ site.name }} allows you to access your order status and
                        history. Just fill in the fields below, and we'll get a new account set up
                        for you in no time. We will only ask you for the information necessary to
                        make the purchase process faster and easier.
                    </p>

                    <form class="mt-6 space-y-5" @submit.prevent="doRegister">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="remail2">
                                Email address <span class="text-danger">Required</span>
                            </label>
                            <input
                                id="remail2"
                                v-model="register.email"
                                type="email"
                                class="wd-field"
                                required
                            />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="rpass">
                                Password <span class="text-danger">Required</span>
                            </label>
                            <input
                                id="rpass"
                                v-model="register.password"
                                type="password"
                                class="wd-field"
                                required
                            />
                        </div>

                        <!-- Anti-spam honeypot, as on the source form -->
                        <input
                            type="text"
                            name="website"
                            tabindex="-1"
                            autocomplete="off"
                            class="hidden"
                            aria-hidden="true"
                        />

                        <p class="text-sm leading-relaxed text-neutral-500">
                            Your personal data will be used to support your experience throughout
                            this website, to manage access to your account, and for other purposes
                            described in our
                            <router-link to="/privacy-policy" class="text-navy underline underline-offset-2">
                                privacy policy
                            </router-link>.
                        </p>

                        <label class="flex cursor-pointer items-start gap-2 text-sm text-ink">
                            <input
                                v-model="register.agree"
                                type="checkbox"
                                class="mt-1 accent-primary"
                            />
                            I have read and accept the privacy policy
                        </label>

                        <button type="submit" class="wd-btn" :disabled="busy">
                            <i v-if="busy" class="fa-solid fa-circle-notch fa-spin"></i>
                            Register
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </div>
</template>
