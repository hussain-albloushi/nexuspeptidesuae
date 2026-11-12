import assert from 'node:assert/strict';
import { after, before, beforeEach, test } from 'node:test';
import { createServer } from 'vite';
import axios from 'axios';

let server;
let setActivePinia;
let createPinia;
let useContentStore;
let useCurrencyStore;
let useCartStore;
let useAuthStore;
let products;
let contentDefaults;
const storage = new Map();

before(async () => {
    globalThis.window = { __STOREFRONT_CONTENT__: {} };
    Object.defineProperty(globalThis, 'localStorage', {
        configurable: true,
        value: {
            getItem: (key) => storage.get(key) ?? null,
            setItem: (key, value) => storage.set(key, String(value)),
            removeItem: (key) => storage.delete(key),
        },
    });

    server = await createServer({
        server: { middlewareMode: true, watch: null, hmr: false, ws: false },
        appType: 'custom',
    });
    ({ createPinia, setActivePinia } = await server.ssrLoadModule('pinia'));
    ({ useContentStore } = await server.ssrLoadModule('/resources/js/storefront/stores/content.js'));
    ({ useCurrencyStore } = await server.ssrLoadModule('/resources/js/storefront/stores/currency.js'));
    ({ useCartStore } = await server.ssrLoadModule('/resources/js/storefront/stores/cart.js'));
    ({ useAuthStore } = await server.ssrLoadModule('/resources/js/storefront/stores/auth.js'));
    ({ products } = await server.ssrLoadModule('/resources/js/storefront/data/products.js'));
    ({ contentDefaults } = await server.ssrLoadModule('/resources/js/storefront/data/contentDefaults.js'));
});

beforeEach(() => {
    storage.clear();
    setActivePinia(createPinia());
    useContentStore().content = structuredClone(contentDefaults);
});

after(async () => {
    await server?.close();
});

test('currency settings saved by the editor control storefront formatting', () => {
    useContentStore().content.store.currency = {
        currency: 'USD', symbolStyle: 'text', symbolPosition: 'after', symbolText: '$', decimals: 0,
    };

    assert.equal(useCurrencyStore().formatText(123.45), '123 $');
});

test('zero VAT rate and zero decimal places are preserved', () => {
    Object.assign(useContentStore().content.store, { vatRate: '0', decimals: 0 });

    assert.equal(useCurrencyStore().calculateVAT(100), 0);
    assert.equal(useCurrencyStore().formatText(123.45), 'AED 123');
});

test('cart charges the configured flat shipping option', () => {
    Object.assign(useContentStore().content.store, {
        shippingMode: 'flat', flatRate: null, shippingOptions: [{ name: 'Standard', cost: '25' }],
        freeShippingThreshold: null,
    });
    const cart = useCartStore();
    cart.add(products[0].slug);

    assert.equal(cart.shipping, 25);
    assert.equal(typeof cart.total, 'number');
});

test('free shipping threshold removes the flat shipping charge', () => {
    Object.assign(useContentStore().content.store, {
        shippingMode: 'flat', flatRate: '25', freeShippingThreshold: '1',
    });
    const cart = useCartStore();
    cart.add(products[0].slug);

    assert.equal(cart.shipping, 0);
});

test('VAT already included in the price is extracted without increasing the total', () => {
    Object.assign(useContentStore().content.store, { vatIncludedInPrice: 'true' });

    assert.deepEqual(useCurrencyStore().calculateTotal(105, 25), {
        subtotal: 105, vat: 5, shipping: 25, total: 130,
    });
});

test('logout sends the previous token after clearing local storage', async () => {
    const auth = useAuthStore();
    auth.rememberSession({ email: 'customer@example.com' }, 'current-token');
    const originalAdapter = axios.defaults.adapter;
    let authorization;
    axios.defaults.adapter = async (config) => {
        authorization = config.headers.get('Authorization');
        return { status: 200, statusText: 'OK', headers: {}, data: {}, config };
    };

    try {
        await auth.logout();
        assert.equal(authorization, 'Bearer current-token');
        assert.equal(auth.isAuthenticated, false);
        assert.equal(localStorage.getItem('nexus_token'), null);
    } finally {
        axios.defaults.adapter = originalAdapter;
    }
});
