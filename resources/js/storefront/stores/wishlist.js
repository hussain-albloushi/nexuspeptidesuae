import { defineStore } from 'pinia';

// Per-user scope (logged-in users). Guest visitors also get a key so they
// don't accidentally pick up one account's wishlist while browsing as another.
const NEW_PREFIX = 'nexus_wishlist:';
const LEGACY_PREFIX = 'nexus peptides_wishlist:';
const LEGACY_GLOBAL = 'nexus peptides_wishlist';
const GUEST_KEY = `${NEW_PREFIX}guest`;

function userKey(email) {
    return `${NEW_PREFIX}${email}`;
}

function read(key) {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) || [] : [];
    } catch {
        return [];
    }
}

function currentKey() {
    try {
        const user = JSON.parse(localStorage.getItem('nexus_account')) || {};
        return user.email ? userKey(user.email) : GUEST_KEY;
    } catch {
        return GUEST_KEY;
    }
}

/**
 * Move wishlist data stored under the old `nexus peptides_*` keys forward.
 */
function migrateLegacy() {
    try {
        // Global legacy key from before per-user scoping.
        const legacy = localStorage.getItem(LEGACY_GLOBAL);
        if (legacy !== null) {
            if (localStorage.getItem(GUEST_KEY) === null) {
                localStorage.setItem(GUEST_KEY, legacy);
            }
            localStorage.removeItem(LEGACY_GLOBAL);
        }

        // Old per-user keys: nexus peptides_wishlist:<email> -> nexus_wishlist:<email>
        for (let i = localStorage.length - 1; i >= 0; i -= 1) {
            const key = localStorage.key(i);
            if (key && key.startsWith(LEGACY_PREFIX)) {
                const value = localStorage.getItem(key);
                const migrated = NEW_PREFIX + key.slice(LEGACY_PREFIX.length);
                if (value !== null && localStorage.getItem(migrated) === null) {
                    localStorage.setItem(migrated, value);
                }
                localStorage.removeItem(key);
            }
        }
    } catch {
        // Legacy data unreadable — nothing more to do.
    }
}

export const useWishlistStore = defineStore('wishlist', {
    state: () => {
        migrateLegacy();
        return { slugs: read(currentKey()) };
    },

    getters: {
        count: (state) => state.slugs.length,
        isEmpty: (state) => state.slugs.length === 0,
    },

    actions: {
        persist() {
            localStorage.setItem(currentKey(), JSON.stringify(this.slugs));
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

        /** Pull every item from the guest wishlist into the current scope. */
        mergeGuestIntoCurrent() {
            const guest = read(GUEST_KEY);
            if (!guest.length) return;

            // Union the user's saved wishlist with the guest's slugs, keeping order.
            this.slugs = Array.from(new Set([...this.slugs, ...guest]));
            this.persist();
            localStorage.removeItem(GUEST_KEY);
        },

        /** Rebuild the live `slugs` array from the current user's wishlist. */
        switch() {
            this.slugs = read(currentKey());
        },
    },
});

export default useWishlistStore;
