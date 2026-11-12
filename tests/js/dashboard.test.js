import assert from 'node:assert/strict';
import { after, afterEach, before, beforeEach, test } from 'node:test';
import { effectScope } from 'vue';
import { createServer } from 'vite';
import axios from 'axios';

let server;
let useDashboard;
let originalAdapter;
let respond;
let scope;
let listeners;
const flush = () => new Promise(setImmediate);

before(async () => {
    server = await createServer({ server: { middlewareMode: true, watch: null, hmr: false, ws: false }, appType: 'custom' });
    ({ useDashboard } = await server.ssrLoadModule('/resources/js/pages/admin/dashboard.js'));
});

beforeEach((context) => {
    context.mock.timers.enable({ apis: ['setInterval'] });
    listeners = new Map();
    globalThis.document = {
        hidden: false,
        addEventListener: (name, handler) => listeners.set(name, handler),
        removeEventListener: (name) => listeners.delete(name),
    };
    originalAdapter = axios.defaults.adapter;
    respond = async () => ({ generated_at: '2026-11-11T12:00:00Z', metrics: {} });
    axios.defaults.adapter = async (config) => ({ config, data: await respond(config), status: 200, statusText: 'OK', headers: {} });
    scope = effectScope();
});

afterEach(() => {
    scope.stop();
    axios.defaults.adapter = originalAdapter;
    delete globalThis.document;
});

after(async () => { await server?.close(); });

test('dashboard refreshes every 30 seconds and pauses while hidden or disabled', async (context) => {
    let requests = 0;
    respond = async () => ({ count: ++requests });
    const dashboard = scope.run(useDashboard);
    dashboard.start();
    await flush();
    assert.equal(requests, 1);

    context.mock.timers.tick(30000);
    await flush();
    assert.equal(requests, 2);
    document.hidden = true;
    context.mock.timers.tick(30000);
    await flush();
    assert.equal(requests, 2);
    document.hidden = false;
    dashboard.autoRefresh.value = false;
    context.mock.timers.tick(30000);
    await flush();
    assert.equal(requests, 2);

    await dashboard.refresh();
    assert.equal(requests, 3);
});

test('changing the reporting period fetches a new snapshot', async () => {
    const periods = [];
    respond = async (config) => { periods.push(config.params.days); return { days: config.params.days }; };
    const dashboard = scope.run(useDashboard);
    dashboard.start();
    await flush();

    dashboard.days.value = 7;
    await flush();

    assert.deepEqual(periods, [30, 7]);
    assert.equal(dashboard.data.value.days, 7);
});

test('notification polling uses the lightweight endpoint and replaces resolved alerts', async (context) => {
    const endpoints = [];
    let lowStock = 2;
    respond = async (config) => {
        endpoints.push(config.url);
        return { attention: { low_stock: lowStock } };
    };
    const notifications = scope.run(() => useDashboard('/api/dashboard/notifications'));
    notifications.start();
    await flush();
    assert.equal(notifications.data.value.attention.low_stock, 2);

    lowStock = 0;
    context.mock.timers.tick(30000);
    await flush();

    assert.deepEqual(endpoints, ['/api/dashboard/notifications', '/api/dashboard/notifications']);
    assert.equal(notifications.data.value.attention.low_stock, 0);
});

test('a failed refresh preserves the previous snapshot and exposes an error', async () => {
    const dashboard = scope.run(useDashboard);
    await dashboard.refresh();
    const previous = dashboard.data.value;
    respond = async () => { throw new Error('Unavailable'); };

    await dashboard.refresh();

    assert.equal(dashboard.data.value, previous);
    assert.equal(dashboard.error.value, 'Dashboard could not be updated. Please retry.');
    assert.equal(dashboard.loading.value, false);
});

test('an older response cannot overwrite a newer reporting period', async () => {
    const pending = [];
    respond = (config) => new Promise((resolve) => pending.push({ config, resolve }));
    const dashboard = scope.run(useDashboard);
    dashboard.start();
    await flush();
    dashboard.days.value = 90;
    await flush();
    pending[1].resolve({ days: 90 });
    await flush();
    pending[0].resolve({ days: 30 });
    await flush();

    assert.equal(dashboard.data.value.days, 90);
});

test('leaving the dashboard stops polling and removes visibility listeners', async (context) => {
    let requests = 0;
    respond = async () => ({ count: ++requests });
    const dashboard = scope.run(useDashboard);
    dashboard.start();
    await flush();

    scope.stop();
    context.mock.timers.tick(60000);
    await flush();

    assert.equal(requests, 1);
    assert.equal(listeners.size, 0);
});
