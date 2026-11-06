import { defineStore } from 'pinia';
import { products, findBySlug } from '../data/products';
import { useCurrencyStore } from './currency';

const KEY = 'zenvy_cart';

function load() {
    try {
        return JSON.parse(localStorage.getItem(KEY)) || [];
    } catch {
        return [];
    }
}

export const useCartStore = defineStore('cart', {
    state: () => ({ items: load() }),

    getters: {
        count: (state) => state.items.reduce((n, i) => n + i.qty, 0),

        lines(state) {
            const currencyStore = useCurrencyStore();
            return state.items
                .map((i) => {
                    const product = findBySlug(i.slug);
                    return product ? { ...i, product, total: product.price * i.qty } : null;
                })
                .filter(Boolean);
        },

        subtotal() {
            const currencyStore = useCurrencyStore();
            const decimals = currencyStore.settings.decimals;
            return this.lines.reduce((sum, l) => sum + l.total, 0);
        },

        shipping() {
            const currencyStore = useCurrencyStore();
            const subtotal = this.subtotal;

            if (!this.lines.length) return 0;

            if (currencyStore.settings.shippingMode === 'free') {
                return 0;
            }

            if (currencyStore.settings.shippingMode === 'flat') {
                const flatRate = currencyStore.settings.flatRate || 0;
                const freeThreshold = currencyStore.settings.freeShippingThreshold || Infinity;
                if (subtotal >= freeThreshold) return 0;
                return flatRate;
            }

            // 'custom' or 'to be confirmed' mode - no shipping charged
            return 0;
        },

        tax() {
            const currencyStore = useCurrencyStore();
            const subtotal = this.subtotal;

            if (!currencyStore.settings.vatEnabled) return 0;

            if (currencyStore.settings.vatIncludedInPrice) {
                // VAT is already included in the price; extract it for display
                return subtotal * (currencyStore.settings.vatRate / (100 + currencyStore.settings.vatRate));
            }

            return subtotal * (currencyStore.settings.vatRate / 100);
        },

        total() {
            const currencyStore = useCurrencyStore();
            const subtotal = this.subtotal;
            const shipping = this.shipping;
            const tax = this.tax;

            if (currencyStore.settings.vatIncludedInPrice) {
                return subtotal + shipping;
            }

            return subtotal + shipping + tax;
        },

        formattedTotal() {
            const currencyStore = useCurrencyStore();
            return currencyStore.format(this.total);
        },

        isEmpty: (state) => state.items.length === 0,
    },

    actions: {
        persist() {
            localStorage.setItem(KEY, JSON.stringify(this.items));
        },

        add(slug, qty = 1) {
            if (!findBySlug(slug)) return false;
            const line = this.items.find((i) => i.slug === slug);
            if (line) line.qty += qty;
            else this.items.push({ slug, qty });
            this.persist();
            return true;
        },

        setQty(slug, qty) {
            const line = this.items.find((i) => i.slug === slug);
            if (!line) return;
            if (qty <= 0) return this.remove(slug);
            line.qty = qty;
            this.persist();
        },

        remove(slug) {
            this.items = this.items.filter((i) => i.slug !== slug);
            this.persist();
        },

        clear() {
            this.items = [];
            this.persist();
        },
    },
});

export default useCartStore;
