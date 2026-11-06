import { defineStore } from 'pinia';

const KEY = 'zenvy_wishlist';

function load() {
    try {
        return JSON.parse(localStorage.getItem(KEY)) || [];
    } catch {
        return [];
    }
}

export const useWishlistStore = defineStore('wishlist', {
    state: () => ({ slugs: load() }),

    getters: {
        count: (state) => state.slugs.length,
        isEmpty: (state) => state.slugs.length === 0,
    },

    actions: {
        persist() {
            localStorage.setItem(KEY, JSON.stringify(this.slugs));
        },

        has(slug) {
            return this.slugs.includes(slug);
        },

        /** Returns true when the product was added (false when removed). */
        toggle(slug) {
            const exists = this.has(slug);
            this.slugs = exists ? this.slugs.filter((s) => s !== slug) : [...this.slugs, slug];
            this.persist();
            return !exists;
        },

        remove(slug) {
            this.slugs = this.slugs.filter((s) => s !== slug);
            this.persist();
        },

        clear() {
            this.slugs = [];
            this.persist();
        },
    },
});

export default useWishlistStore;
