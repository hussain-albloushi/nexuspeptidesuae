import { defineStore } from 'pinia';
import axios from 'axios';
import { useCartStore } from './cart';
import { useWishlistStore } from './wishlist';

const KEY = 'nexus_account';
const TOKEN_KEY = 'nexus_token';
const LEGACY_KEY = 'zenvy_account';

function load() {
    try {
        const legacy = localStorage.getItem(LEGACY_KEY);
        if (legacy !== null) {
            if (localStorage.getItem(KEY) === null) {
                localStorage.setItem(KEY, legacy);
            }
            localStorage.removeItem(LEGACY_KEY);
        }
        return JSON.parse(localStorage.getItem(KEY)) || null;
    } catch {
        return null;
    }
}

/**
 * Storefront auth backed by the Laravel API (Sanctum personal access tokens).
 * The token lives in localStorage and is attached to every request by the
 * axios interceptor installed in main.js.
 */
export const useAuthStore = defineStore('auth', {
    state: () => ({ user: load(), token: localStorage.getItem(TOKEN_KEY), lastSessionPing: 0 }),

    getters: {
        isAuthenticated: (state) => Boolean(state.user && state.token),
    },

    actions: {
        persist() {
            localStorage.setItem(KEY, JSON.stringify(this.user));
        },

        rememberSession(user, token) {
            this.user = user;
            this.token = token;
            this.lastSessionPing = Date.now();
            localStorage.setItem(TOKEN_KEY, token);
            this.persist();

            // A new session starts its own cart/wishlist.
            useCartStore().clear();
            const wishlist = useWishlistStore();
            wishlist.switch();
            wishlist.mergeGuestIntoCurrent();
        },

        /** Pull the latest profile (including any saved address) into the store. */
        async refreshUser() {
            const { data } = await axios.get('/api/me');
            this.user = { ...(this.user ?? {}), ...data };
            this.persist();
            return this.user;
        },

        /** Persist the customer's billing/shipping details; also updates the local copy. */
        async saveAddress(savedAddress) {
            const { data } = await axios.put('/api/me/address', { saved_address: savedAddress });
            this.user = { ...(this.user ?? {}), saved_address: data.saved_address };
            this.persist();
            return data.saved_address;
        },

        async login(email, password) {
            const { data } = await axios.post('/api/login', { email, password });
            this.rememberSession(data.user, data.access_token);
            return this.user;
        },

        async register({ name, email, password }) {
            const { data } = await axios.post('/api/register', {
                name,
                email,
                password,
                password_confirmation: password,
            });
            this.rememberSession(data.user, data.access_token);
            return this.user;
        },

        /** Ask the server to email a reset link for (hopefully) this address. Never reveals whether it exists. */
        async forgotPassword(email) {
            const { data } = await axios.post('/api/forgot-password', { email });
            return data;
        },

        /** Complete a password reset with the token from the emailed link. */
        async resetPassword({ token, email, password, password_confirmation }) {
            const { data } = await axios.post('/api/reset-password', { token, email, password, password_confirmation });
            return data;
        },

        /** Ask the server to re-send the verification email for the current account. */
        async resendVerification() {
            const { data } = await axios.post('/api/email/verification-notification');
            return data;
        },

        async logout() {
            const token = this.token;

            // Clear locally first so a failed request can never strand us
            // in a half-logged-in state.
            this.user = null;
            this.token = null;
            localStorage.removeItem(KEY);
            localStorage.removeItem(TOKEN_KEY);
            useCartStore().clear();
            useWishlistStore().switch();

            if (token) {
                try {
                    await axios.post('/api/logout', {}, {
                        headers: { Authorization: `Bearer ${token}` },
                    });
                } catch {
                    // Token already expired/revoked — nothing left to do.
                }
            }
        },

        /**
         * Re-validate the session against the server. Deleting the customer
         * server-side revokes the token, so the next check fails with a 401 and
         * the global response interceptor clears the local session and redirects
         * to the login page. Throttled to once a minute unless forced; transient
         * network/server errors deliberately keep the session alive.
         */
        async pingSession({ force = false } = {}) {
            if (!this.token) {
                return;
            }
            const now = Date.now();
            if (!force && now - this.lastSessionPing < 60000) {
                return;
            }
            this.lastSessionPing = now;
            try {
                await this.refreshUser();
            } catch {
                // 401 is handled by the global response interceptor.
            }
        },

        /** Drop a stale session when the API rejects our token. */
        invalidate() {
            this.user = null;
            this.token = null;
            localStorage.removeItem(KEY);
            localStorage.removeItem(TOKEN_KEY);
        },
    },
});

export default useAuthStore;
