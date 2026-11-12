<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { swalToast } from '../services/alerts';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const token = computed(() => String(route.query.token ?? ''));
const email = ref(String(route.query.email ?? ''));
const password = ref('');
const passwordConfirmation = ref('');
const busy = ref(false);

const linkValid = computed(() => token.value !== '' && email.value !== '');

onMounted(() => {
    if (!linkValid.value) {
        swalToast('This reset link is incomplete. Request a new one from the login page.', 'error');
    }
});

async function submit() {
    if (!linkValid.value) {
        return;
    }
    if (password.value.length < 8) {
        swalToast('Your password must be at least 8 characters.', 'error');
        return;
    }
    if (password.value !== passwordConfirmation.value) {
        swalToast('The passwords do not match.', 'error');
        return;
    }
    busy.value = true;
    try {
        await auth.resetPassword({ token: token.value, email: email.value, password: password.value, password_confirmation: passwordConfirmation.value });
        swalToast('Your password has been reset. Please log in.');
        router.push('/my-account');
    } catch (err) {
        const data = err?.response?.data;
        const first = data?.errors ? Object.values(data.errors)[0] : null;
        const message = (Array.isArray(first) && first[0]) || data?.message || 'Unable to reset your password. The link may have expired.';
        swalToast(message, 'error');
    } finally {
        busy.value = false;
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
                    <router-link to="/my-account" class="hover:text-white">My account</router-link>
                    <span class="mx-2">›</span>
                    <span class="text-white">Reset password</span>
                </nav>
                <h1 class="text-3xl text-white md:text-4xl">Reset password</h1>
            </div>
        </section>

        <section class="wd-container max-w-xl py-14 md:py-16">
            <div v-if="!linkValid" class="rounded-card border border-danger/40 bg-danger/10 p-6">
                <h2 class="text-xl text-ink">This reset link is incomplete</h2>
                <p class="mt-2 text-sm text-neutral-600">The link in your email must include a token and an email address. Request a fresh one from the login page.</p>
                <router-link to="/my-account" class="wd-btn !px-7 !py-3 mt-5 inline-block text-sm">Back to login</router-link>
            </div>

            <form v-else class="space-y-5" @submit.prevent="submit">
                <p class="text-sm text-neutral-500">Choose a new password for <strong class="text-ink">{{ email }}</strong>. This link expires 60 minutes after it was sent.</p>
                <div>
                    <label class="mb-2 block text-sm font-medium text-ink" for="rp-email">Email address</label>
                    <input id="rp-email" v-model="email" type="email" class="wd-field" required :disabled="busy" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-ink" for="rp-password">New password</label>
                    <input id="rp-password" v-model="password" type="password" class="wd-field" autocomplete="new-password" required :disabled="busy" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-ink" for="rp-confirm">Confirm new password</label>
                    <input id="rp-confirm" v-model="passwordConfirmation" type="password" class="wd-field" autocomplete="new-password" required :disabled="busy" />
                </div>
                <button type="submit" class="wd-btn" :disabled="busy">
                    <i v-if="busy" class="fa-solid fa-circle-notch fa-spin"></i>
                    Reset password
                </button>
            </form>
        </section>
    </div>
</template>
