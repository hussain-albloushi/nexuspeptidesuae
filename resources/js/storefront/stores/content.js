import { defineStore } from 'pinia';
import { contentDefaults } from '../data/contentDefaults';
import axios from 'axios';

let settingsRequest;

function isPlainObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * Merge published content over the defaults. A key missing entirely from the
 * published payload keeps the static default. An explicitly empty value
 * ('' or null) means the editor cleared it — respect it and render nothing.
 */
function mergeContent(defaults, published) {
    if (!isPlainObject(defaults) || !isPlainObject(published)) {
        return published === undefined ? defaults : published;
    }

    const merged = { ...defaults };

    for (const [key, value] of Object.entries(published)) {
        merged[key] = mergeContent(defaults[key], value);
    }

    return merged;
}

function publishedContent() {
    const payload = window.__STOREFRONT_CONTENT__;

    return isPlainObject(payload) ? payload : {};
}

export const useContentStore = defineStore('storefront-content', {
    state: () => {
        const published = publishedContent();

        return {
            content: mergeContent(contentDefaults, published),
            /** Groups the admin has published (drafts are absent from the payload). */
            publishedGroups: Object.keys(published),
        };
    },

    actions: {
        async refreshStoreSettings() {
            if (settingsRequest) return settingsRequest;
            settingsRequest = axios.get('/api/store-settings').then(({ data }) => {
                this.content.store = mergeContent(contentDefaults.store, data);
            }).catch(() => {
                // Retain the settings embedded in the page during temporary network failures.
            }).finally(() => { settingsRequest = null; });
            return settingsRequest;
        },
    },

    getters: {
        site: (state) => state.content.site,
        /** True when the admin has published that content group. */
        isPublished: (state) => (key) => state.publishedGroups.includes(key),
        store: (state) => state.content.store || {},
        home: (state) => state.content.home,
        about: (state) => state.content.about,
        help: (state) => state.content.help,
        catalogue: (state) => state.content.catalogue,
        coa: (state) => state.content.coa,
        legal: (state) => ({
            terms: state.content.legal_terms,
            privacy: state.content.legal_privacy,
            returns: state.content.legal_returns,
            shipping: state.content.legal_shipping,
            disclaimer: state.content.legal_disclaimer,
        }),
    },
});

export default useContentStore;
