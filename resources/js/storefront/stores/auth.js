import { defineStore } from 'pinia';

const KEY = 'zenvy_account';

function load() {
    try {
        return JSON.parse(localStorage.getItem(KEY)) || null;
    } catch {
        return null;
    }
}

/**
 * Local-only auth. The static clone has no backend, so credentials are
 * simply remembered in localStorage — swap for the Laravel API later.
 */
export const useAuthStore = defineStore('auth', {
    state: () => ({ user: load() }),

    getters: {
        isAuthenticated: (state) => Boolean(state.user),
    },

    actions: {
        persist() {
            localStorage.setItem(KEY, JSON.stringify(this.user));
        },

        login(email) {
            this.user = { email, name: email.split('@')[0] };
            this.persist();
            return this.user;
        },

        register({ name, email }) {
            this.user = { name, email };
            this.persist();
            return this.user;
        },

        logout() {
            this.user = null;
            localStorage.removeItem(KEY);
        },
    },
});

export default useAuthStore;
