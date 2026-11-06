/**
 * Currency store — centralised AED currency formatting and VAT logic.
 *
 * Reads the store settings (currency code, symbol style/position,
 * decimal places, VAT rate, etc.) from the content store and provides
 * reactive helpers used across the storefront for displaying prices
 * and calculating totals.
 */

import { defineStore } from 'pinia';
import { computed } from 'vue';
import { useContentStore } from './content';

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
            shippingMode: 'custom',
            shippingOptions: [],
            returnWindowDays: 14,
            ageConfirmation: true,
        },
    }),

    getters: {
        /** Current settings merged from published content over defaults. */
        settings(state) {
            const content = useContentStore();
            const store = content.store || {};

            return {
                currency: store.currency || state.defaults.currency,
                symbolStyle: store.symbolStyle || state.defaults.symbolStyle,
                symbolPosition: store.symbolPosition || state.defaults.symbolPosition,
                symbolText: store.symbolText || state.defaults.symbolText,
                decimals: parseInt(store.decimals, 10) || state.defaults.decimals,
                vatEnabled: Boolean(store.vatEnabled ?? state.defaults.vatEnabled),
                vatRate: parseFloat(store.vatRate) || state.defaults.vatRate,
                vatIncludedInPrice: Boolean(store.vatIncludedInPrice ?? state.defaults.vatIncludedInPrice),
                vatLabel: store.vatLabel || state.defaults.vatLabel,
                shippingMode: store.shippingMode || state.defaults.shippingMode,
                shippingOptions: store.shippingOptions || state.defaults.shippingOptions,
                returnWindowDays: parseInt(store.returnWindowDays, 10) || state.defaults.returnWindowDays,
                ageConfirmation: Boolean(store.ageConfirmation ?? state.defaults.ageConfirmation),
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
            const decimals = parseInt(s.decimals, 10) || 2;
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
            return subtotal * (s.vatRate / 100);
        },

        /**
         * Calculate total including VAT and shipping.
         *
         * @param {number} subtotal
         * @param {number} shipping
         * @returns {{ subtotal: number, vat: number, shipping: number, total: number }}
         */
        calculateTotal(subtotal, shipping) {
            const s = this.settings;
            const vat = s.vatEnabled ? this.calculateVAT(subtotal) : 0;
            const total = subtotal + shipping + vat;
            return { subtotal, vat, shipping, total };
        },
    },
});

export default useCurrencyStore;