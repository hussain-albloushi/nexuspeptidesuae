import { defineStore } from 'pinia';
import { contentDefaults } from '../data/contentDefaults';

function isPlainObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * Merge published content over the defaults. Missing groups and missing keys
 * keep the static defaults, so an unpublished group can never blank a page.
 */
function mergeContent(defaults, published) {
    if (!isPlainObject(defaults) || !isPlainObject(published)) {
        return published === undefined || published === null ? defaults : published;
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
    state: () => ({
        content: mergeContent(contentDefaults, publishedContent()),
    }),

    getters: {
        site: (state) => state.content.site,
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
