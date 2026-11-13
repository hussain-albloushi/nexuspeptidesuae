<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useContentStore } from '../stores/content';
import { swal, swalToast, swalConfirm } from '../services/alerts';
import { UAE_CITIES } from '../constants';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const content = useContentStore();
const site = computed(() => content.site);

const login = ref({ email: '', password: '', remember: false });
const register = ref({ name: '', email: '', password: '', agree: false, ageAgree: false });
const showLoginPassword = ref(false);
const showRegisterPassword = ref(false);
const lostOpen = ref(false);
const lostEmail = ref('');
const lostBusy = ref(false);
const busy = ref(false);
const resendBusy = ref(false);
const addressBusy = ref(false);

const addressForm = ref({
    name: '',
    email: '',
    phone: '',
    address: '',
    zip: '',
    city: '',
    country: 'United Arab Emirates',
});

// Hydrate the address form from the saved details on the customer's account.
onMounted(async () => {
    // The verification email links back here with ?verified=1 / ?verified=invalid.
    if (route.query.verified === '1') {
        swalToast('Your email is verified. You can now sign in.');
    } else if (route.query.verified === 'invalid') {
        swalToast('That verification link is invalid or has expired. Request a new one below.', 'error');
    }
    if (route.query.verified !== undefined) {
        const query = { ...route.query };
        delete query.verified;
        router.replace({ query });
    }

    if (!auth.isAuthenticated) {
        return;
    }

    try {
        await auth.refreshUser();
    } catch {
        // Keep whatever is already in the store.
    }

    hydrateAddressForm();
});

function hydrateAddressForm() {
    const saved = auth.user?.saved_address;

    addressForm.value = {
        name: saved?.name ?? auth.user?.name ?? '',
        email: saved?.email ?? auth.user?.email ?? '',
        phone: saved?.phone ?? auth.user?.phone ?? '',
        address: saved?.address ?? '',
        zip: saved?.zip ?? '',
        city: saved?.city ?? '',
        country: saved?.country ?? 'United Arab Emirates',
    };
}

async function doSaveAddress() {
    const f = addressForm.value;
    const required = ['name', 'email', 'phone', 'address', 'city', 'country'];
    const missing = required.filter((k) => !String(f[k]).trim());

    if (missing.length) {
        swalToast('Please complete all required fields.', 'error');
        return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email)) {
        swalToast('Please enter a valid email address.', 'error');
        return;
    }

    addressBusy.value = true;
    try {
        await auth.saveAddress({ ...f });
        swalToast('Your billing details have been saved.');
    } catch (err) {
        swalToast(apiError(err, 'Unable to save your details. Please try again.'), 'error');
    } finally {
        addressBusy.value = false;
    }
}

async function doLogin() {
    const f = login.value;
    if (!f.email.trim() || !f.password) {
        swalToast('Please enter your email address and password.', 'error');
        return;
    }
    busy.value = true;
    try {
        await auth.login(f.email.trim(), f.password);
        login.value = { email: '', password: '', remember: false };
        swalToast('You are now logged in.');
        router.push('/');
    } catch (err) {
        swalToast(apiError(err, 'Unable to log in. Please check your email and password.'), 'error');
    } finally {
        busy.value = false;
    }
}

async function doRegister() {
    const f = register.value;
    if (!f.name.trim() || !f.email.trim() || !f.password) {
        swalToast('Please enter your full name, email address and password.', 'error');
        return;
    }
    if (f.name.trim().length < 2) {
        swalToast('Please enter your full name.', 'error');
        return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email)) {
        swalToast('Please enter a valid email address.', 'error');
        return;
    }
    if (f.password.length < 8) {
        swalToast('Your password must be at least 8 characters.', 'error');
        return;
    }
    if (!f.agree) {
        swalToast('Please read and accept the privacy policy to continue.', 'error');
        return;
    }
    if (!f.ageAgree) {
        swalToast('Please confirm you are 18 years or older to register.', 'error');
        return;
    }
    busy.value = true;
    try {
        await auth.register({ name: f.name.trim(), email: f.email.trim(), password: f.password });
        register.value = { name: '', email: '', password: '', agree: false, ageAgree: false };
        await swal({
            icon: 'success',
            title: 'Account created',
            text: 'Check your inbox for a verification link. You can sign in after verifying your email.',
            confirmButtonText: 'OK',
        });
        router.push('/');
    } catch (err) {
        swalToast(apiError(err, 'Unable to create your account. Please try again.'), 'error');
    } finally {
        busy.value = false;
    }
}

async function doLogout() {
    const ok = await swalConfirm('Log out?', 'Are you sure you want to log out?', 'Log out');
    if (!ok) return;
    await auth.logout();
    swalToast('You have been logged out.', 'info');
    router.push('/');
}

/** Pull the first readable message out of a Laravel validation/API error. */
function apiError(err, fallback) {
    const data = err?.response?.data;
    if (data?.message && !data?.errors) return data.message;
    const first = data?.errors ? Object.values(data.errors)[0] : null;
    if (Array.isArray(first) && first.length) return first[0];
    if (typeof first === 'string' && first) return first;
    return fallback;
}

async function sendReset() {
    const value = lostEmail.value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        swalToast('Please enter a valid email address.', 'error');
        return;
    }
    lostBusy.value = true;
    try {
        await auth.forgotPassword(value);
        lostEmail.value = '';
        lostOpen.value = false;
        swalToast('If that email address is on file, a reset link has been sent.');
    } catch {
        swalToast('Unable to send the reset link right now. Please try again later.', 'error');
    } finally {
        lostBusy.value = false;
    }
}

async function resendVerification() {
    resendBusy.value = true;
    try {
        const { message } = await auth.resendVerification();
        await auth.refreshUser();
        swalToast(message);
    } catch (err) {
        swalToast(apiError(err, 'Unable to send the verification email right now.'), 'error');
    } finally {
        resendBusy.value = false;
    }
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
            <div v-if="auth.isAuthenticated" class="mx-auto max-w-2xl rounded-card border border-line p-8">
                <div class="flex items-center gap-4">
                    <span class="grid h-14 w-14 place-items-center rounded-full bg-primary/15 text-primary">
                        <i class="fa-solid fa-user text-xl"></i>
                    </span>
                    <div>
                        <h2 class="text-2xl">Hello, {{ auth.user.name }}</h2>
                        <p class="mt-1 text-sm text-neutral-500">{{ auth.user.email }}</p>
                    </div>
                </div>

                <div v-if="auth.user && !auth.user.email_verified_at" class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-card border border-amber-500/40 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <span><i class="fa-solid fa-triangle-exclamation mr-1" aria-hidden="true"></i> Your email address is not verified yet. Check your inbox for the verification link.</span>
                    <button type="button" class="font-semibold underline decoration-dotted underline-offset-4 hover:text-amber-700 disabled:opacity-60" :disabled="resendBusy" @click="resendVerification">
                        {{ resendBusy ? 'Sending…' : 'Resend email' }}
                    </button>
                </div>

                <div class="mt-8 grid gap-4 sm:grid-cols-4">
                    <router-link
                        to="/my-orders"
                        class="rounded-card border border-line p-5 text-center transition hover:border-secondary"
                    >
                        <i class="fa-solid fa-box-open text-primary"></i>
                        <p class="mt-2 text-sm font-medium text-ink">Orders</p>
                    </router-link>
                    <router-link
                        to="/peptide-shop"
                        class="rounded-card border border-line p-5 text-center transition hover:border-secondary"
                    >
                        <i class="fa-solid fa-bag-shopping text-primary"></i>
                        <p class="mt-2 text-sm font-medium text-ink">Shop</p>
                    </router-link>
                    <router-link
                        to="/wishlist"
                        class="rounded-card border border-line p-5 text-center transition hover:border-secondary"
                    >
                        <i class="fa-solid fa-heart text-primary"></i>
                        <p class="mt-2 text-sm font-medium text-ink">Wishlist</p>
                    </router-link>
                    <router-link
                        to="/cart"
                        class="rounded-card border border-line p-5 text-center transition hover:border-secondary"
                    >
                        <i class="fa-solid fa-cart-shopping text-primary"></i>
                        <p class="mt-2 text-sm font-medium text-ink">Cart</p>
                    </router-link>
                </div>

                <!-- Saved billing details -->
                <form class="mt-8 border-t border-line pt-8" @submit.prevent="doSaveAddress">
                    <h2 class="text-xl">Billing &amp; delivery details</h2>
                    <p class="mt-1 text-sm text-neutral-500">
                        Saved to your account and pre-filled at checkout. Changing them here does not
                        affect past orders.
                    </p>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="aname">
                                Full name *
                            </label>
                            <input id="aname" v-model="addressForm.name" type="text" class="wd-field" required />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="aemail">
                                Email address *
                            </label>
                            <input id="aemail" v-model="addressForm.email" type="email" class="wd-field" required />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="aphone">
                                Phone *
                            </label>
                            <input id="aphone" v-model="addressForm.phone" type="tel" class="wd-field" required />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="acountry">
                                Country *
                            </label>
                            <input
                                id="acountry"
                                v-model="addressForm.country"
                                type="text"
                                class="wd-field cursor-not-allowed opacity-70"
                                disabled
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-ink" for="aaddress">
                                Street address *
                            </label>
                            <input
                                id="aaddress"
                                v-model="addressForm.address"
                                type="text"
                                class="wd-field"
                                placeholder="House number and street name"
                                required
                            />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="acity">
                                Town / City *
                            </label>
                            <select
                                id="acity"
                                v-model="addressForm.city"
                                class="wd-field !w-full py-2 px-3 rounded-md text-sm bg-white"
                                required
                            >
                                <option value="" disabled>Select your city…</option>
                                <option v-for="city in UAE_CITIES" :key="city" :value="city">
                                    {{ city }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-ink" for="azip">
                                Zip / postal code
                            </label>
                            <input id="azip" v-model="addressForm.zip" type="text" class="wd-field" placeholder="Optional" />
                        </div>
                    </div>

                    <button type="submit" class="wd-btn !px-7 !py-3 text-sm" :disabled="addressBusy">
                        <i v-if="addressBusy" class="fa-solid fa-circle-notch fa-spin"></i>
                        Save details
                    </button>
                </form>

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
                                Email address <span class="text-danger">Required</span>
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
                            <div class="relative">
                                <input
                                    id="lpass"
                                    v-model="login.password"
                                    :type="showLoginPassword ? 'text' : 'password'"
                                    class="wd-field !pr-12"
                                    required
                                />
                                <button
                                    type="button"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-neutral-500 transition hover:text-ink"
                                    :aria-label="showLoginPassword ? 'Hide password' : 'Show password'"
                                    :aria-pressed="showLoginPassword"
                                    @click="showLoginPassword = !showLoginPassword"
                                >
                                    <i class="fa-solid" :class="showLoginPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
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
                                Lost your password? Please enter your email address. You
                                will receive a link to create a new password via email.
                            </p>
                            <label class="mb-2 block text-sm font-medium text-ink" for="remail">
                                Email address
                            </label>
                            <input id="remail" v-model="lostEmail" type="email" class="wd-field" :disabled="lostBusy" />
                            <button type="button" class="wd-btn !px-7 !py-3 text-sm" :disabled="lostBusy" @click="sendReset">
                                <i v-if="lostBusy" class="fa-solid fa-circle-notch fa-spin"></i>
                                {{ lostBusy ? 'Sending…' : 'Reset password' }}
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
                            <label class="mb-2 block text-sm font-medium text-ink" for="rname">
                                Full name <span class="text-danger">Required</span>
                            </label>
                            <input
                                id="rname"
                                v-model="register.name"
                                type="text"
                                class="wd-field"
                                required
                            />
                        </div>

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
                            <div class="relative">
                                <input
                                    id="rpass"
                                    v-model="register.password"
                                    :type="showRegisterPassword ? 'text' : 'password'"
                                    class="wd-field !pr-12"
                                    required
                                />
                                <button
                                    type="button"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-neutral-500 transition hover:text-ink"
                                    :aria-label="showRegisterPassword ? 'Hide password' : 'Show password'"
                                    :aria-pressed="showRegisterPassword"
                                    @click="showRegisterPassword = !showRegisterPassword"
                                >
                                    <i class="fa-solid" :class="showRegisterPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
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

                        <label class="flex cursor-pointer items-start gap-2 text-sm text-ink">
                            <input
                                v-model="register.ageAgree"
                                type="checkbox"
                                class="mt-1 accent-primary"
                            />
                            I confirm that I am 18 years of age or older
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
