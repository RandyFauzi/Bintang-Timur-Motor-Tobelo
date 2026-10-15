import { test } from 'node:test';
import assert from 'node:assert/strict';
import { returnSelection, productPicker } from '../../resources/js/components/product-returns.js';

test('selecting a return again replaces its version without duplicate rows', () => {
    const state = returnSelection();
    state.toggle('a', 1, true);
    state.toggle('a', 2, true);
    assert.deepEqual(state.selected, [{ id: 'a', version: 2 }]);
    state.toggle('a', 2, false);
    assert.deepEqual(state.selected, []);
});

test('select all and deselect all only affect the current page', () => {
    const state = returnSelection([{ id: 'other', version: 1 }]);
    const page = [{ id: 'a', version: 2 }, { id: 'b', version: 3 }];
    assert.equal(state.pageSelected([]), false);
    state.togglePage(page, true);
    assert.equal(state.pageSelected(page), true);
    state.togglePage(page, false);
    assert.deepEqual(state.selected, [{ id: 'other', version: 1 }]);
});

test('invalid old selection input does not prevent the editor from initializing', () => {
    assert.deepEqual(returnSelection(null).selected, []);
    assert.deepEqual(returnSelection('bad').selected, []);
    assert.deepEqual(returnSelection([null, {}, { id: 'a', version: 'invalid' }, { id: 'b', version: '2' }]).selected, [{ id: 'b', version: 2 }]);
});

test('product search preserves the chosen product outside the new results', async () => {
    const originalFetch = globalThis.fetch;
    const originalWindow = globalThis.window;
    try {
        globalThis.window = { location: { origin: 'https://example.test' } };
        let requested;
        globalThis.fetch = async (url) => {
            requested = url;
            return { ok: true, json: async () => ({ data: [{ id: 2, name: 'Rem', sku: 'REM', available: 2 }] }) };
        };
        const chosen = { id: 1, name: 'Oli', sku: 'OLI', available: 4 };
        const picker = productPicker('/returns/products', chosen);
        picker.search = ' Rem ';
        await picker.load();
        assert.equal(requested.searchParams.get('search'), 'Rem');
        assert.deepEqual(picker.choice, chosen);
        assert.equal(picker.products.length, 2);
        assert.equal(picker.busy, false);
    } finally {
        globalThis.fetch = originalFetch;
        globalThis.window = originalWindow;
    }
});

test('late product responses cannot overwrite results from a newer search', async () => {
    const originalFetch = globalThis.fetch;
    const originalWindow = globalThis.window;
    try {
        globalThis.window = { location: { origin: 'https://example.test' } };
        const pending = [];
        globalThis.fetch = () => new Promise((resolve) => pending.push(resolve));
        const picker = productPicker('/returns/products');
        const first = picker.load();
        const second = picker.load();
        pending[1]({ ok: true, json: async () => ({ data: [{ id: 2, name: 'New' }] }) });
        await second;
        pending[0]({ ok: true, json: async () => ({ data: [{ id: 1, name: 'Old' }] }) });
        await first;
        assert.deepEqual(picker.products, [{ id: 2, name: 'New' }]);
        assert.equal(picker.busy, false);
    } finally {
        globalThis.fetch = originalFetch;
        globalThis.window = originalWindow;
    }
});

test('failed product searches show an error and retain the selected product', async () => {
    const originalFetch = globalThis.fetch;
    const originalWindow = globalThis.window;
    try {
        globalThis.window = { location: { origin: 'https://example.test' } };
        globalThis.fetch = async () => ({ ok: false });
        const chosen = { id: 1, name: 'Oli' };
        const picker = productPicker('/returns/products', chosen);
        await picker.load();
        assert.equal(picker.busy, false);
        assert.notEqual(picker.error, '');
        assert.deepEqual(picker.choice, chosen);
    } finally {
        globalThis.fetch = originalFetch;
        globalThis.window = originalWindow;
    }
});
