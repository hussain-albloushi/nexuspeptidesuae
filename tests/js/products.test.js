import assert from 'node:assert/strict';
import { afterEach, beforeEach, test } from 'node:test';
import { effectScope, reactive } from 'vue';
import axios from 'axios';
import { useProducts, useProductImage, productForm } from '../../resources/js/pages/admin/products.js';

let originalAdapter;
let respond;
let scope;
const flush = () => new Promise(setImmediate);
const product = { id: 1, name: 'Research product', sku: 'NX-100', slug: 'research-product', price: '100.00', stock_quantity: 5, low_stock_threshold: 5, is_active: true };
const pageData = (items = [product], page = 1, total = items.length, lastPage = 1) => ({ data: items, current_page: page, total, from: items.length ? 1 : null, to: items.length, last_page: lastPage });

beforeEach((context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    originalAdapter = axios.defaults.adapter;
    respond = async (config) => config.url === '/api/products/1' ? product : pageData();
    axios.defaults.adapter = async (config) => ({
        config, status: 200, statusText: 'OK', headers: {},
        data: config.url === '/api/categories' ? [{ id: 2, name: 'Research' }] : await respond(config),
    });
    scope = effectScope();
});

afterEach(() => { scope.stop(); axios.defaults.adapter = originalAdapter; });

function catalog(query = {}) {
    const route = reactive({ query });
    return { route, ...scope.run(() => useProducts(route)) };
}

test('product search debounces input, combines filters, and resets pagination', async (context) => {
    const queries = [];
    respond = async (config) => { queries.push(config.params); return pageData([product], config.params.page, 30, 2); };
    const state = catalog();
    await flush();
    state.page.value = 2;
    context.mock.timers.tick(250);
    await flush();
    state.filters.q = 'old';
    state.filters.q = 'new';
    state.filters.category_id = '2';
    state.filters.min_price = 0;
    state.filters.stock_status = 'low_stock';
    assert.equal(state.page.value, 1);
    assert.equal(state.loading.value, true);
    assert.equal(state.products.value.length, 0);
    context.mock.timers.tick(250);
    await flush();

    assert.equal(queries.length, 3);
    assert.deepEqual(queries[2], { q: 'new', category_id: '2', min_price: 0, stock_status: 'low_stock', page: 1, per_page: 20 });
});

test('late responses cannot replace results from the latest filters', async (context) => {
    const pending = [];
    respond = (config) => new Promise((resolve) => pending.push({ config, resolve }));
    const state = catalog({ q: 'old' });
    await flush();
    state.filters.q = 'new';
    context.mock.timers.tick(250);
    await flush();
    pending[1].resolve(pageData([{ ...product, name: 'New result' }]));
    await flush();
    pending[0].resolve(pageData([{ ...product, name: 'Old result' }]));
    await flush();

    assert.equal(state.products.value[0].name, 'New result');
});

test('reset clears every filter and navbar queries update the current listing', async (context) => {
    const queries = [];
    respond = async (config) => { queries.push(config.params); return pageData(); };
    const state = catalog({ q: 'initial', category_id: '2', is_active: '0' });
    await flush();
    assert.equal(queries.length, 1, 'initial catalog request');
    state.resetFilters();
    await flush();
    context.mock.timers.tick(250);
    await flush();
    assert.equal(state.hasFilters.value, false);
    assert.deepEqual(queries[1], { q: '', page: 1, per_page: 20 });

    state.route.query = { q: 'NX-100' };
    await flush();
    context.mock.timers.tick(250);
    await flush();
    assert.equal(queries[2].q, 'NX-100');
});

test('failed list requests expose an error and can be retried', async () => {
    respond = async () => { throw new Error('Network unavailable'); };
    const state = catalog();
    await flush();
    assert.equal(state.error.value, 'Products could not be loaded. Please try again.');

    respond = async () => pageData();
    await state.refresh();
    assert.equal(state.error.value, '');
    assert.equal(state.products.value[0].id, 1);
});

test('closing details cancels pending responses and missing products show actionable feedback', async () => {
    let resolveDetails;
    const state = catalog();
    await flush();
    respond = () => new Promise((resolve) => { resolveDetails = resolve; });
    const request = state.loadProduct(1);
    await flush();
    state.closeProduct();
    resolveDetails(product);
    await request;
    assert.equal(state.activeProduct.value, null);

    respond = async () => { throw { response: { status: 404 } }; };
    await state.loadProduct(1);
    assert.equal(state.detailError.value, 'This product is no longer available. Refresh the list to continue.');
});

test('save keeps the editor open with field errors then accepts a successful retry', async () => {
    const state = catalog();
    await flush();
    await state.loadProduct(1);
    const form = productForm(state.activeProduct.value);
    form.name = 'Edited name';
    respond = async (config) => {
        if (config.method === 'put') throw { response: { status: 422, data: { errors: { sku: ['The sku has already been taken.'] } } } };
        return pageData();
    };

    assert.equal(await state.saveProduct(form), false);
    assert.equal(form.name, 'Edited name');
    assert.deepEqual(state.fieldErrors.value.sku, ['The sku has already been taken.']);
    assert.equal(state.activeProduct.value.id, 1);
    assert.equal(state.busy.value, false);

    let payload;
    respond = async (config) => {
        if (config.method === 'put') { payload = JSON.parse(config.data); return { product: { ...product, name: 'Edited name' } }; }
        return pageData([{ ...product, name: 'Edited name' }]);
    };
    assert.equal(await state.saveProduct(form), true);
    assert.equal(payload.cost_price, null);
    assert.equal(payload.expected_stock_quantity, 5);
    assert.equal(state.products.value[0].name, 'Edited name');
    assert.match(state.success.value, /updated successfully/);
});

test('delete requires explicit confirmation and failed deletion can be retried', async () => {
    const state = catalog();
    await flush();
    await state.loadProduct(1);
    let deletions = 0;
    respond = async (config) => { if (config.method === 'delete') { deletions++; throw new Error('Unavailable'); } return pageData(); };

    assert.equal(await state.deleteProduct(), false);
    assert.equal(deletions, 0);
    assert.equal(await state.deleteProduct(true), false);
    assert.equal(state.activeProduct.value.id, 1);
    assert.equal(state.actionError.value, 'Product could not be deleted. Please try again.');

    respond = async (config) => config.method === 'delete' ? {} : pageData([]);
    assert.equal(await state.deleteProduct(true), true);
    assert.equal(state.activeProduct.value, null);
    assert.equal(state.products.value.length, 0);
    assert.match(state.success.value, /deleted successfully/);
});

test('deleting the last result on a page returns to the last available page', async (context) => {
    const state = catalog();
    await flush();
    respond = async (config) => config.url === '/api/products/1' ? product : pageData([product], config.params?.page, 21, 2);
    state.page.value = 2;
    context.mock.timers.tick(250);
    await flush();
    await state.loadProduct(1);
    const requestedPages = [];
    respond = async (config) => {
        if (config.method === 'delete') return {};
        requestedPages.push(config.params.page);
        return pageData(config.params.page === 2 ? [] : [product], config.params.page, 20, 1);
    };

    assert.equal(await state.deleteProduct(true), true);
    assert.equal(state.page.value, 1);
    assert.deepEqual(requestedPages, [2, 1]);
    assert.equal(state.products.value.length, 1);
});

test('leaving products cancels pending search and detail requests', async (context) => {
    let calls = 0;
    respond = async () => { calls++; return pageData(); };
    const state = catalog();
    await flush();
    state.filters.q = 'pending';
    scope.stop();
    context.mock.timers.tick(250);
    await flush();
    assert.equal(calls, 1);
});

test('selected images have an immediate preview and can be removed or cancelled', () => {
    const editor = scope.run(useProductImage);
    editor.reset('/catalog-images/current.webp');
    assert.equal(editor.preview.value, '/catalog-images/current.webp');
    editor.select(new File(['image'], 'selected.png', { type: 'image/png' }));
    assert.match(editor.preview.value, /^blob:/);
    assert.equal(editor.file.value.name, 'selected.png');
    editor.reset();
    assert.equal(editor.file.value, null);
    assert.equal(editor.preview.value, '');
    editor.reset('/catalog-images/current.webp');
    assert.equal(editor.preview.value, '/catalog-images/current.webp');
});

test('image selection rejects unsupported and oversized files', () => {
    const editor = scope.run(useProductImage);
    editor.select(new File(['pdf'], 'document.pdf', { type: 'application/pdf' }));
    assert.equal(editor.file.value, null);
    assert.match(editor.error.value, /up to 2 MB/);
    editor.select(new File([new Uint8Array(2 * 1024 * 1024 + 1)], 'large.png', { type: 'image/png' }));
    assert.equal(editor.file.value, null);
    editor.select(new File(['image'], 'valid.webp', { type: 'image/webp' }));
    assert.equal(editor.error.value, '');
});

test('image uploads use multipart with method spoofing and preserve boolean values', async () => {
    const state = catalog();
    await flush();
    await state.loadProduct(1);
    let body;
    let method;
    respond = async (config) => {
        if (config.url === '/api/products/1') {
            method = config.method;
            body = config.data;
            return { product: { ...product, featured_image_url: '/storage/products/1/new.png' } };
        }
        return pageData();
    };
    const form = productForm(product);
    form.is_active = false;

    assert.equal(await state.saveProduct(form, new File(['image'], 'new.png', { type: 'image/png' })), true);
    assert.equal(method, 'post');
    assert.equal(body.get('_method'), 'PUT');
    assert.equal(body.get('is_active'), '0');
    assert.equal(body.get('image').name, 'new.png');
});

test('save carries discount fields for the pricing comparison price', async () => {
    const state = catalog();
    await flush();
    await state.loadProduct(1);
    const form = productForm(state.activeProduct.value);
    form.price = '150';
    form.discount_percent = '20';
    form.discount_amount = '';
    let payload;
    respond = async (config) => {
        if (config.method === 'put') { payload = JSON.parse(config.data); return { product: { ...product, name: 'Edited name' } }; }
        return pageData([{ ...product, name: 'Edited name' }]);
    };

    assert.equal(await state.saveProduct(form), true);
    assert.equal(payload.discount_percent, '20');
    assert.equal(payload.discount_amount, null);
    assert.equal(payload.compare_at_price, null);
});
