import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { createServer } from 'vite';

let server;
let po;

before(async () => {
    server = await createServer({ server: { middlewareMode: true, watch: null, hmr: false, ws: false }, appType: 'custom' });
    po = await server.ssrLoadModule('/resources/js/pages/admin/purchaseOrders.js');
});

after(async () => { await server?.close(); });

test('lineTotal applies discount then tax', () => {
    assert.equal(po.lineTotal({ quantity: 2, unit_cost: 100, discount: 10, tax: 5 }), 195);
    assert.equal(po.lineTotal({ quantity: 1, unit_cost: 50 }), 50);
});

test('lineTotal never goes negative on an oversized discount', () => {
    assert.equal(po.lineTotal({ quantity: 1, unit_cost: 10, discount: 25, tax: 2 }), 2);
});

test('orderTotal adds lines and charges', () => {
    const total = po.orderTotal(
        [{ quantity: 2, unit_cost: 100, discount: 10, tax: 5 }, { quantity: 1, unit_cost: 50 }],
        { shipping_cost: 20, customs_cost: 5, additional_charges: 2.5 },
    );

    assert.equal(total, 272.5);
});

test('orderTotal tolerates blank rows and missing charges', () => {
    assert.equal(po.orderTotal([{ product_id: '', quantity: 1, unit_cost: 0 }]), 0);
    assert.equal(po.orderTotal(), 0);
});
