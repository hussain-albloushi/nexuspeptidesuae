import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { createServer } from 'vite';

let server;
let ai;

before(async () => {
    server = await createServer({ server: { middlewareMode: true, watch: null, hmr: false, ws: false }, appType: 'custom' });
    ai = await server.ssrLoadModule('/resources/js/pages/admin/ai.js');
});

after(async () => { await server?.close(); });

test('periodQuery uses preset periods and encodes custom ranges', () => {
    assert.equal(ai.periodQuery('30d'), 'period=30d');
    assert.equal(ai.periodQuery('', ''), 'period=30d');
    assert.equal(ai.periodQuery('custom', '2026-10-01', '2026-10-31'), 'start=2026-10-01&end=2026-10-31');
    // A custom selection without both dates falls back to a preset period.
    assert.equal(ai.periodQuery('custom', '2026-10-01', ''), 'period=custom');
});

test('trendMeta maps deltas to direction icons and classes', () => {
    assert.deepEqual(ai.trendMeta(12.5), { icon: 'bi-arrow-up-right', class: 'text-success', label: 'Up' });
    assert.deepEqual(ai.trendMeta(-3), { icon: 'bi-arrow-down-right', class: 'text-danger', label: 'Down' });
    assert.deepEqual(ai.trendMeta(0), { icon: 'bi-dash', label: 'Flat', class: 'text-secondary' });
    assert.equal(ai.trendMeta(null).label, 'No comparison');
    assert.equal(ai.trendMeta(undefined).class, 'text-secondary');
});

test('formatters keep missing values explicit instead of guessing', () => {
    assert.equal(ai.money(null), '—');
    assert.equal(ai.percent(undefined), '—');
    assert.equal(ai.number(null), '—');
    assert.equal(ai.money(1234.5), 'AED 1,234.50');
    assert.equal(ai.signedPercent(4.25), '+4.3%');
    assert.equal(ai.signedPercent(-4.25), '-4.3%');
    assert.equal(ai.signedNumber(3), '+3');
    assert.equal(ai.kpiValue(12.5, '%'), '12.50%');
    assert.equal(ai.kpiValue(12.5, '%', 1), '12.5%');
    assert.equal(ai.kpiValue(12, ''), '12');
    assert.equal(ai.kpiDelta(-5, ''), '-5');
});

test('formatDate keeps date-only calendar days stable', () => {
    assert.equal(ai.formatDate('2026-11-13'), '13 Nov 2026');
    assert.equal(ai.formatDate(''), '—');
});

test('titleCase turns API keys into readable labels', () => {
    assert.equal(ai.titleCase('out_of_stock'), 'Out Of Stock');
    assert.equal(ai.titleCase('high-revenue'), 'High Revenue');
    assert.equal(ai.titleCase(null), '');
});

test('aiErrorMessage surfaces the provider detail when present', () => {
    assert.equal(
        ai.aiErrorMessage({ data: { message: 'The AI provider rejected the request.', detail: '"tools" must be an array' } }),
        'The AI provider rejected the request. Provider said: "tools" must be an array',
    );
    assert.equal(ai.aiErrorMessage({ data: { message: 'Nope.' } }), 'Nope.');
    assert.equal(ai.aiErrorMessage(undefined, 'Fallback'), 'Fallback');
    assert.equal(ai.aiErrorMessage({ data: { detail: '   ' } }), 'The assistant could not answer. Please try again.');
});

test('renderMarkdown escapes HTML before adding formatting', () => {
    const html = ai.renderMarkdown('<img src=x onerror=alert(1)>');
    assert.ok(html.includes('&lt;img'));
    assert.ok(!html.includes('<img'));
});

test('renderMarkdown does not double-escape text', () => {
    assert.equal(ai.renderMarkdown('Tom & Jerry'), '<p>Tom &amp; Jerry</p>');
});

test('renderMarkdown renders headings, bullets, bold and inline code', () => {
    const html = ai.renderMarkdown('## Revenue\n\n- **Revenue:** AED 100\n- Orders: `5`');
    assert.ok(html.includes('<h4>Revenue</h4>'));
    assert.ok(html.includes('<ul>'));
    assert.ok(html.includes('<strong>Revenue:</strong>'));
    assert.ok(html.includes('<code>5</code>'));
});

test('renderMarkdown renders GitHub-style tables', () => {
    const html = ai.renderMarkdown('| Product | Revenue |\n|---|---|\n| Product A | 100 |');
    assert.ok(html.includes('ai-markdown-table'));
    assert.ok(html.includes('<th>Product</th>'));
    assert.ok(html.includes('<td>Product A</td>'));
    assert.ok(html.includes('<td>100</td>'));
});
