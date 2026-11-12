import assert from 'node:assert/strict';
import { afterEach, beforeEach, test } from 'node:test';
import { effectScope, reactive } from 'vue';
import axios from 'axios';
import { addressFields, useOrders } from '../../resources/js/pages/admin/orders.js';

let scope;
let originalAdapter;
let respond;
let downloads;
let popup;
const flush = () => new Promise(setImmediate);
const order = { id: 1, order_number: 'NP-100', status: 'pending', payment_status: 'pending', total_amount: '100.00', allowed_transitions: ['processing', 'cancelled'] };
const pageData = (items = [order], page = 1, total = items.length, last = 1) => ({ data: items, current_page: page, total, from: items.length ? 1 : 0, to: items.length, last_page: last });

beforeEach((context) => {
    context.mock.timers.enable({ apis: ['setTimeout'] });
    scope = effectScope();
    originalAdapter = axios.defaults.adapter;
    respond = async (config) => config.url === '/api/orders/1' ? order : pageData();
    axios.defaults.adapter = async (config) => ({ config, status: 200, statusText: 'OK', headers: {}, data: await respond(config) });
    downloads = [];
    globalThis.document = {
        createElement: () => ({ click() { downloads.push({ href: this.href, download: this.download }); }, remove() {} }),
        body: { append() {} },
    };
    popup = { opener: {}, html: '', document: { open() {}, write(html) { popup.html += html; }, close() {} }, close() { this.closed = true; } };
    globalThis.window = { open: () => popup };
});
afterEach(() => { scope.stop(); axios.defaults.adapter = originalAdapter; delete globalThis.window; delete globalThis.document; });

function state(query = {}) {
    const route = reactive({ query });
    return { route, ...scope.run(() => useOrders(route)) };
}

test('filters, sorting, and page size send a single final request and reset selection', async (context) => {
    const queries = [];
    respond = async (config) => { queries.push(config.params); return pageData([order], config.params.page, 40, 2); };
    const orders = state();
    await flush();
    orders.toggleAll();
    orders.page.value = 2;
    context.mock.timers.tick(250);
    await flush();
    orders.filters.q = 'Alice';
    orders.filters.status = 'pending';
    orders.filters.payment_status = 'pending';
    orders.filters.start_date = '2026-11-01';
    orders.filters.end_date = '2026-11-11';
    orders.sortBy('total_amount');
    orders.perPage.value = 50;
    assert.equal(orders.page.value, 1);
    assert.equal(orders.selected.value.length, 0);
    context.mock.timers.tick(250);
    await flush();

    assert.equal(queries.length, 3);
    assert.deepEqual(queries[2], { q: 'Alice', status: 'pending', payment_status: 'pending', start_date: '2026-11-01', end_date: '2026-11-11', sort: 'total_amount', direction: 'asc', page: 1, per_page: 50 });
    orders.sortBy('total_amount');
    assert.equal(orders.direction.value, 'desc');
});

test('selection offers only shared valid transitions and select-all toggles the current page', async () => {
    respond = async () => pageData([order, { ...order, id: 2, status: 'processing', allowed_transitions: ['shipped', 'cancelled'] }]);
    const orders = state();
    await flush();
    orders.toggleSelection(1);
    assert.deepEqual(orders.commonTransitions.value, ['processing', 'cancelled']);
    orders.toggleAll();
    assert.equal(orders.allSelected.value, true);
    assert.deepEqual(orders.commonTransitions.value, ['cancelled']);
    orders.toggleAll();
    assert.equal(orders.selected.value.length, 0);
});

test('older search responses cannot overwrite newer results', async (context) => {
    const requests = [];
    respond = (config) => new Promise((resolve) => requests.push({ config, resolve }));
    const orders = state({ q: 'old' });
    await flush();
    orders.filters.q = 'new';
    context.mock.timers.tick(250);
    await flush();
    requests[1].resolve(pageData([{ ...order, order_number: 'NEW' }]));
    await flush();
    requests[0].resolve(pageData([{ ...order, order_number: 'OLD' }]));
    await flush();
    assert.equal(orders.orders.value[0].order_number, 'NEW');
});

test('reset clears filters and a new navbar query updates the existing orders page', async (context) => {
    const queries = [];
    respond = async (config) => { queries.push(config.params); return pageData(); };
    const orders = state({ q: 'old', status: 'pending', payment_status: 'paid' });
    await flush();
    orders.resetFilters();
    context.mock.timers.tick(250);
    await flush();
    assert.equal(orders.hasFilters.value, false);
    assert.equal(queries[1].status, undefined);
    orders.route.query = { q: 'NEW' };
    await flush();
    context.mock.timers.tick(250);
    await flush();
    assert.equal(queries[2].q, 'NEW');
});

test('failed list requests are visible and retry restores the list', async () => {
    respond = async () => { throw new Error('offline'); };
    const orders = state();
    await flush();
    assert.equal(orders.error.value, 'Orders could not be loaded. Please try again.');
    respond = async () => pageData();
    await orders.refresh();
    assert.equal(orders.error.value, '');
    assert.equal(orders.orders.value.length, 1);
});

test('closing the drawer prevents a late detail response from reopening it', async () => {
    const orders = state();
    await flush();
    let resolveDetail;
    respond = () => new Promise((resolve) => { resolveDetail = resolve; });
    const pending = orders.loadOrder(1);
    await flush();
    orders.closeOrder();
    resolveDetail(order);
    await pending;
    assert.equal(orders.detail.value, null);
});

test('status updates use expected status and replace detail history after success', async () => {
    const orders = state();
    await flush();
    await orders.loadOrder(1);
    let payload;
    respond = async (config) => {
        if (config.method === 'patch') {
            payload = JSON.parse(config.data);
            return { message: 'Order updated.', order: { ...order, status: 'processing', status_history: [{ new_status: 'processing' }] } };
        }
        return pageData([{ ...order, status: 'processing' }]);
    };

    assert.equal(await orders.updateStatuses([order], 'processing', 'Packed'), true);
    assert.deepEqual(payload, { status: 'processing', expected_status: 'pending', notes: 'Packed' });
    assert.equal(orders.detail.value.status_history[0].new_status, 'processing');
    assert.equal(orders.success.value, 'Order updated.');
});

test('cancellation requires explicit confirmation before sending a request', async () => {
    const orders = state();
    await flush();
    let mutations = 0;
    respond = async (config) => {
        if (config.method === 'patch') { mutations++; return { message: 'Cancelled.' }; }
        return pageData();
    };

    assert.equal(await orders.updateStatuses([order], 'cancelled', 'Customer request'), false);
    assert.equal(mutations, 0);
    assert.equal(await orders.updateStatuses([order], 'cancelled', 'Customer request', '', true), true);
    assert.equal(mutations, 1);
});

test('bulk updates send every selected status snapshot and keep errors visible for retry', async () => {
    const orders = state();
    await flush();
    let payload;
    respond = async (config) => {
        payload = JSON.parse(config.data);
        throw { response: { status: 422, data: { errors: { orders: ['An order changed. Refresh before updating.'] } } } };
    };

    assert.equal(await orders.updateStatuses([order, { ...order, id: 2 }], 'processing'), false);
    assert.equal(payload.orders.length, 2);
    assert.equal(payload.orders[1].expected_status, 'pending');
    assert.equal(orders.actionError.value, 'An order changed. Refresh before updating.');
    assert.equal(orders.busy.value, false);
});

test('CSV export includes all filters but does not restrict exports to the current page', async (context) => {
    const orders = state({ status: 'pending' });
    await flush();
    let params;
    respond = async (config) => { params = config.params; return new Blob(['order,total\nNP-100,100'], { type: 'text/csv' }); };

    assert.equal(await orders.exportOrders('csv'), true);
    assert.equal(params.status, 'pending');
    assert.equal(params.page, undefined);
    assert.equal(params.per_page, undefined);
    assert.equal(downloads.length, 1);
    assert.match(downloads[0].download, /^orders-.*\.csv$/);
    context.mock.timers.tick(1000);
});

test('selected export never exports an empty selection or unrelated orders', async (context) => {
    const orders = state({ status: 'pending' });
    await flush();
    assert.equal(await orders.exportOrders('csv', true), false);
    orders.toggleSelection(1);
    let params;
    respond = async (config) => { params = config.params; return new Blob(['selected']); };

    assert.equal(await orders.exportOrders('csv', true), true);
    assert.deepEqual(params.ids, [1]);
    assert.equal(params.status, undefined);
    context.mock.timers.tick(1000);
});

test('print export and invoice open printable HTML and explain blocked pop-ups', async () => {
    const orders = state();
    await flush();
    respond = async () => '<h1>Print / Save as PDF</h1>';

    assert.equal(await orders.exportOrders('print'), true);
    assert.match(popup.html, /Save as PDF/);
    assert.equal(popup.opener, null);
    assert.equal(await orders.printInvoice(order), true);
    window.open = () => null;
    assert.equal(await orders.printInvoice(order), false);
    assert.match(orders.exportError.value, /Allow pop-ups/);
    assert.equal(orders.exportBusy.value, false);
});

test('legacy and structured addresses are displayed as plain data', () => {
    assert.deepEqual(addressFields('{"city":"Dubai","phone":"0501234567"}'), [{ label: 'City', value: 'Dubai' }, { label: 'Phone', value: '0501234567' }]);
    assert.deepEqual(addressFields('Legacy address'), [{ label: 'Address', value: 'Legacy address' }]);
    assert.deepEqual(addressFields(null), []);
});

test('leaving orders cancels debounced requests and pending details', async (context) => {
    let calls = 0;
    respond = async () => { calls++; return pageData(); };
    const orders = state();
    await flush();
    orders.filters.q = 'pending';
    scope.stop();
    context.mock.timers.tick(250);
    await flush();
    assert.equal(calls, 1);
});
