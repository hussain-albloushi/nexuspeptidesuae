import assert from 'node:assert/strict';
import { after, afterEach, before, beforeEach, test } from 'node:test';
import { effectScope, reactive } from 'vue';
import { createServer } from 'vite';
import axios from 'axios';

let server;
let useAdminSearch;
let originalAdapter;
let respond;
let scopes = [];
const flush = () => new Promise(setImmediate);

before(async () => {
    server = await createServer({
        server: { middlewareMode: true, watch: null, hmr: false, ws: false },
        appType: 'custom',
    });
    ({ useAdminSearch } = await server.ssrLoadModule('/resources/js/pages/admin/search.js'));
});

beforeEach((context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    originalAdapter = axios.defaults.adapter;
    respond = async () => { throw new Error('Unexpected request'); };
    axios.defaults.adapter = async (config) => ({
        config, status: 200, statusText: 'OK', headers: {}, data: await respond(config),
    });
});

afterEach(() => {
    for (const scope of scopes) scope.stop();
    scopes = [];
    axios.defaults.adapter = originalAdapter;
});

after(async () => {
    await server?.close();
});

function createSearch(query = '', endpoint = null) {
    const route = reactive({ query: { q: query } });
    const router = {
        async replace(location) {
            route.query = Object.fromEntries(Object.entries(location.query).filter(([, value]) => value !== undefined));
        },
    };
    const scope = effectScope();
    scopes.push(scope);
    return { route, ...scope.run(() => useAdminSearch(route, router, endpoint)) };
}

function productResponse(config) {
    return config.url === '/api/products'
        ? [{ id: 1, name: `${config.params.q} product`, sku: 'SKU', is_active: true }]
        : [];
}

test('submitting a second navbar query updates the existing results page', async (context) => {
    respond = async (config) => productResponse(config);
    const state = createSearch('first');
    await flush();
    assert.equal(state.results.value[0].name, 'first product');

    state.route.query = { q: 'second' };
    context.mock.timers.tick(250);
    await flush();

    assert.equal(state.q.value, 'second');
    assert.equal(state.results.value[0].name, 'second product');
    assert.equal(state.loading.value, false);
});

test('a late response cannot overwrite the latest query results', async (context) => {
    const pending = [];
    respond = (config) => new Promise((resolve) => pending.push({ config, resolve }));
    const state = createSearch('old');
    await flush();

    state.q.value = 'new';
    context.mock.timers.tick(250);
    await flush();
    for (const request of pending.filter(({ config }) => config.params.q === 'new')) {
        request.resolve(productResponse(request.config));
    }
    await flush();
    assert.equal(state.results.value[0].name, 'new product');

    for (const request of pending.filter(({ config }) => config.params.q === 'old')) {
        request.resolve(productResponse(request.config));
    }
    await flush();

    assert.equal(state.results.value[0].name, 'new product');
    assert.equal(state.error.value, '');
});

test('clearing a global query clears results and cancels pending searches', async () => {
    respond = async (config) => productResponse(config);
    const state = createSearch('first');
    await flush();
    assert.equal(state.results.value.length, 1);

    state.q.value = '   ';
    await flush();

    assert.deepEqual(state.results.value, []);
    assert.equal(state.loading.value, false);
    assert.equal(state.activeQuery.value, '');
});

test('collection pages send SKU queries to the API and reload when the route query is cleared', async () => {
    const queries = [];
    respond = async (config) => {
        queries.push(config.params.q);
        return [{ id: 1, name: 'Research Product', sku: 'NX_100' }];
    };
    const state = createSearch('NX_100', '/api/products');
    await flush();
    assert.equal(state.results.value[0].sku, 'NX_100');

    state.route.query = {};
    await flush();

    assert.deepEqual(queries, ['NX_100', '']);
    assert.equal(state.q.value, '');
    assert.equal(state.results.value.length, 1);
});

test('global search results deep link to the matching record', async () => {
    respond = async (config) => {
        if (config.url === '/api/orders') {
            return [{ id: 10, order_number: 'NP-12345678', status: 'processing', total_amount: '250.00', user: { name: 'Ada' } }];
        }
        if (config.url === '/api/products') {
            return [{ id: 3, name: 'BPC-157 10mg', sku: 'BPC-157-10MG', is_active: true }];
        }
        return [{ id: 7, name: 'Ada Lovelace', email: 'ada@example.com' }];
    };
    const state = createSearch('ada');
    await flush();

    assert.deepEqual(state.results.value[0].link, { path: '/admin/orders', query: { q: '#NP-12345678' } });
    assert.deepEqual(state.results.value[1].link, { path: '/admin/products', query: { q: 'BPC-157-10MG' } });
    assert.deepEqual(state.results.value[2].link, { path: '/admin/customers', query: { q: 'ada@example.com' } });
});

test('request failures are shown as errors and can be retried', async () => {
    respond = async () => { throw new Error('Request failed'); };
    const state = createSearch('query');
    await flush();
    assert.equal(state.error.value, 'Unable to load search results. Please try again.');
    assert.equal(state.loading.value, false);

    respond = async (config) => productResponse(config);
    await state.search();

    assert.equal(state.error.value, '');
    assert.equal(state.results.value[0].name, 'query product');
});

test('empty global searches make no API requests', async () => {
    let requests = 0;
    respond = async () => { requests++; return []; };
    const state = createSearch();
    await flush();

    assert.equal(requests, 0);
    assert.equal(state.loading.value, false);
    assert.deepEqual(state.results.value, []);
});

test('typing several queries only requests the final query after the debounce', async (context) => {
    const queries = [];
    respond = async (config) => { queries.push(config.params.q); return []; };
    const state = createSearch();

    state.q.value = 'n';
    state.q.value = 'nx';
    state.q.value = 'nx_100';
    context.mock.timers.tick(250);
    await flush();

    assert.deepEqual(queries, ['nx_100', 'nx_100', 'nx_100']);
    assert.equal(state.activeQuery.value, 'nx_100');
});
