import assert from 'node:assert/strict';
import { test } from 'node:test';

const SOURCE = '../../resources/js/storefront/data/products.js';

test('storefront catalogue overlays admin-published products from the database', async () => {
    globalThis.window = {
        __STOREFRONT_PRODUCTS__: [
            {
                slug: 'retatrutide-10mg-pen',
                name: 'Retatrutide 10mg Pen',
                price: 399,
                compare_at_price: 499,
                image: '/storage/live.jpg',
            },
            {
                slug: 'tirzepatide-15mg-vial',
                name: 'Tirzepatide 15mg (Vial)',
                price: 329,
                compare_at_price: null,
                image: '/storage/vial.jpg',
            },
        ],
    };

    const { products } = await import(`${SOURCE}?published`);

    // Only the admin-published slugs appear; everything else is hidden.
    assert.equal(products.length, 2);

    const pen = products.find((p) => p.slug === 'retatrutide-10mg-pen');
    assert.ok(pen, 'published product is listed');
    assert.equal(pen.name, 'Retatrutide 10mg Pen');
    assert.equal(pen.price, 399);
    assert.equal(pen.regular, 499);
    assert.equal(pen.discount, 20);
    assert.equal(pen.image, '/storage/live.jpg');

    // The rich static copy is still generated from the published name.
    assert.ok(pen.description.intro.includes('Retatrutide 10mg Pen'));

    // A compare price not above the price means no sale badge.
    const vial = products.find((p) => p.slug === 'tirzepatide-15mg-vial');
    assert.equal(vial.price, 329);
    assert.equal(vial.regular, 329);
    assert.equal(vial.discount, 0);
});

test('storefront catalogue falls back to the static list without a published payload', async () => {
    globalThis.window = {};

    const mod = await import(`${SOURCE}?fallback`);

    assert.equal(mod.products.length, 36);
    assert.equal(mod.findBySlug('retatrutide-10mg-pen').price, 449);
    assert.equal(mod.findBySlug('retatrutide-10mg-pen').discount, 0);
});
