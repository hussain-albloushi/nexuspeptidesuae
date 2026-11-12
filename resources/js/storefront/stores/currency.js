/**
 * Currency store — centralised AED currency formatting and VAT logic.
 *
 * Reads the store settings (currency code, symbol style/position,
 * decimal places, VAT rate, etc.) from the content store and provides
 * reactive helpers used across the storefront for displaying prices
 * and calculating totals.
 */

import { defineStore } from 'pinia';
import { useContentStore } from './content';

/**
 * The admin panel stores boolean values from <select> controls as the strings
 * 'true'/'false' (and occasionally as actual booleans). A naive Boolean('false')
 * is truthy, which would leave VAT/age confirmation always enabled. Normalize all
 * boolean-ish paylods before use.
 */
function asBool(value, fallback) {
    if (value === undefined || value === null || value === '') return fallback;
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value !== 0;
    if (typeof value === 'string') {
        return ['true', '1', 'yes', 'on', 'y'].includes(value.trim().toLowerCase());
    }
    return Boolean(value);
}

function asNumber(value, fallback) {
    if (value === undefined || value === null || value === '') return fallback;
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
}

export const useCurrencyStore = defineStore('currency', {
    state: () => ({
        /** Default settings applied when no published content is present. */
        defaults: {
            currency: 'AED',
            symbolStyle: 'svg',
            symbolPosition: 'before',
            symbolText: 'AED',
            decimals: 2,
            vatEnabled: true,
            vatRate: 5,
            vatIncludedInPrice: false,
            vatLabel: 'VAT',
            shippingMode: 'flat',
            shippingOptions: [],
            flatRate: 25,
            freeShippingThreshold: 300,
            codFee: 0,
            returnWindowDays: 14,
            ageConfirmation: true,
        },
    }),

    getters: {
        /** Current settings merged from published content over defaults. */
        settings(state) {
            const content = useContentStore();
            const store = content.store || {};
            const display = typeof store.currency === 'object' && store.currency !== null
                ? { ...store, ...store.currency }
                : store;

            return {
                currency: typeof display.currency === 'string' ? display.currency : state.defaults.currency,
                symbolStyle: display.symbolStyle || state.defaults.symbolStyle,
                symbolPosition: display.symbolPosition || state.defaults.symbolPosition,
                symbolText: display.symbolText || state.defaults.symbolText,
                decimals: Math.max(0, Math.min(8, Math.trunc(asNumber(display.decimals, state.defaults.decimals)))),
                vatEnabled: asBool(store.vatEnabled, state.defaults.vatEnabled),
                vatRate: asNumber(store.vatRate, state.defaults.vatRate),
                vatIncludedInPrice: asBool(store.vatIncludedInPrice, state.defaults.vatIncludedInPrice),
                vatLabel: store.vatLabel || state.defaults.vatLabel,
                shippingMode: store.shippingMode || state.defaults.shippingMode,
                shippingOptions: store.shippingOptions || state.defaults.shippingOptions,
                flatRate: Math.max(0, asNumber(store.flatRate ?? store.shippingOptions?.[0]?.cost, 25)),
                freeShippingThreshold: asNumber(store.freeShippingThreshold, null),
                codFee: Math.max(0, asNumber(store.codFee, state.defaults.codFee)),
                returnWindowDays: asNumber(store.returnWindowDays, state.defaults.returnWindowDays),
                ageConfirmation: asBool(store.ageConfirmation, state.defaults.ageConfirmation),
            };
        },

        /** The rendered currency symbol component string (svg path / unicode / text). */
        symbol(state) {
            const s = state.settings;

            if (s.symbolStyle === 'svg') {
                return '/storage/500px-UAE_Dirham_Symbol001.png';
            }
            if (s.symbolStyle === 'unicode') {
                return 'د.إ';
            }
            return s.symbolText || s.currency;
        },

        /** The plain-text fallback used when SVG cannot be rendered. */
        fallbackText(state) {
            return state.settings.symbolText || state.settings.currency;
        },
    },

    actions: {
        /**
         * Format a numeric value according to the configured currency
         * settings (symbol, position, decimal places).
         *
         * @param {number} value - Amount to format
         * @param {object} [overrides] - Per-call overrides (e.g. { symbolStyle: 'text' })
         * @returns {string} Formatted currency string
         */
        format(value, overrides = {}) {
            const s = this.settings;

            const decimals = overrides.decimals ?? s.decimals;
            const amount = Number(value).toFixed(decimals);

            const symbol =
                overrides.symbolStyle === 'svg'
                    ? '/storage/500px-UAE_Dirham_Symbol001.png'
                    : overrides.symbolStyle === 'unicode'
                    ? 'د.إ'
                    : overrides.symbolText ?? s.symbolText ?? s.currency;

            const position =
                overrides.symbolPosition ?? s.symbolPosition;

            const formatted =
                position === 'after' ? `${amount} ${symbol}` : `${symbol} ${amount}`;

            return formatted;
        },

        /**
         * Format a numeric value as a plain-text currency string with
         * the currency code as fallback (no HTML/ SVG).
         *
         * @param {number} value - Amount to format
         * @returns {string} Formatted currency string (e.g. "AED 100.00")
         */
        formatText(value) {
            const s = this.settings;
            const decimals = s.decimals;
            const amount = Number(value).toFixed(decimals);
            const position = s.symbolPosition;

            return position === 'after'
                ? `${amount} ${s.symbolText ?? s.currency}`
                : `${s.symbolText ?? s.currency} ${amount}`;
        },

        /**
         * Calculate VAT amount for a subtotal.
         *
         * @param {number} subtotal - Pre-VAT subtotal
         * @returns {number} VAT amount (0 if VAT disabled)
         */
        calculateVAT(subtotal) {
            const s = this.settings;
            if (!s.vatEnabled) return 0;
            const vat = s.vatIncludedInPrice
                ? subtotal * (s.vatRate / (100 + s.vatRate))
                : subtotal * (s.vatRate / 100);
            return Math.round(vat * 100) / 100;
        },

        /**
         * Calculate total including VAT and shipping.
         *
         * @param {number} subtotal
         * @param {number} shipping
         * @returns {{ subtotal: number, vat: number, shipping: number, total: number }}
         */
        calculateTotal(subtotal, shipping = 0) {
            const s = this.settings;
            const vat = s.vatEnabled ? this.calculateVAT(subtotal) : 0;
            const total = Math.round((subtotal + shipping + (s.vatIncludedInPrice ? 0 : vat)) * 100) / 100;
            return { subtotal, vat, shipping, total };
        },
    },
});

export default useCurrencyStore;
