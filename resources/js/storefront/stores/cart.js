import { defineStore } from 'pinia';
import { findBySlug } from '../data/products';
import { useCurrencyStore } from './currency';

const KEY = 'nexus_cart';

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
            return state.items
                .map((i) => {
                    const product = findBySlug(i.slug);
                    return product ? { ...i, product, total: product.price * i.qty } : null;
                })
                .filter(Boolean);
        },

        subtotal() {
            return Math.round(this.lines.reduce((sum, l) => sum + l.total, 0) * 100) / 100;
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
                const freeThreshold = currencyStore.settings.freeShippingThreshold ?? Infinity;
                if (subtotal >= freeThreshold) return 0;
                return flatRate;
            }

            // 'custom' or 'to be confirmed' mode - no shipping charged
            return 0;
        },

        freeShippingRemaining() {
            const settings = useCurrencyStore().settings;
            if (settings.shippingMode !== 'flat' || settings.freeShippingThreshold === null || settings.flatRate <= 0) return null;
            return Math.max(0, Math.round((settings.freeShippingThreshold - this.subtotal) * 100) / 100);
        },

        freeShippingProgress() {
            const threshold = useCurrencyStore().settings.freeShippingThreshold;
            return threshold > 0 ? Math.max(0, Math.min(100, this.subtotal / threshold * 100)) : 100;
        },

        tax() {
            const currencyStore = useCurrencyStore();
            return currencyStore.calculateVAT(this.subtotal);
        },

        total() {
            const currencyStore = useCurrencyStore();
            return currencyStore.calculateTotal(this.subtotal, this.shipping).total;
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
            const product = findBySlug(slug);
            if (!product) return false;
            const stock = product.stock;
            if (stock !== null && stock !== undefined && Number(stock) <= 0) return false;
            const line = this.items.find((i) => i.slug === slug);
            if (line) {
                const newQty = line.qty + qty;
                if (stock !== null && stock !== undefined) {
                    line.qty = Math.min(newQty, Number(stock));
                } else {
                    line.qty = newQty;
                }
            } else {
                const initial = stock !== null && stock !== undefined ? Math.min(qty, Number(stock)) : qty;
                if (initial <= 0) return false;
                this.items.push({ slug, qty: initial });
            }
            this.persist();
            return true;
        },

        setQty(slug, qty) {
            const line = this.items.find((i) => i.slug === slug);
            if (!line) return;
            if (qty <= 0) return this.remove(slug);
            const product = findBySlug(slug);
            const stock = product?.stock;
            if (stock !== null && stock !== undefined) {
                qty = Math.min(qty, Number(stock));
                if (qty <= 0) return this.remove(slug);
            }
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
