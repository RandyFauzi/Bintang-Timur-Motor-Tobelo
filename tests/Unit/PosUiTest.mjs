import { test } from 'node:test';
import assert from 'node:assert/strict';
import { posRegister } from '../../resources/js/components/pos.js';

const product = { id: 1, name: 'Oli', price: 50000.25, available: 3, sku: 'OLI-1' };
function register(products = [{ ...product }]) {
    const state = posRegister(products, { checkout: '/checkout', scan: '/scan' }, 'pos-test');
    state.$dispatch = () => {};
    return state;
}

test('cart combines products, counts units, and calculates precise cash change', () => {
    const state = register();
    state.add(product);
    state.add(product);
    assert.equal(state.cart.length, 1);
    assert.equal(state.units, 2);
    assert.equal(state.total, 100000.5);
    state.paidAmount = 110000;
    assert.equal(state.change, 9999.5);
    assert.equal(state.canCheckout, true);
});

test('stock limits and invalid quantities cannot mutate cart quantities', () => {
    const state = register();
    state.add({ ...product, available: 1 });
    state.add({ ...product, available: 1 });
    state.setQuantity(1, 0);
    state.setQuantity(1, 1.5);
    state.setQuantity(1, 2);
    assert.equal(state.quantity(1), 1);
    state.remove(1);
    assert.equal(state.units, 0);
    assert.equal(state.canCheckout, false);
});

test('refreshed stock and prices replace cached metadata without deleting cart quantities', () => {
    const state = register();
    state.add(product);
    state.add(product);
    state.add({ ...product, available: 1, price: 60000 });
    state.paidAmount = 200000;
    assert.equal(state.quantity(1), 2);
    assert.equal(state.total, 120000);
    assert.equal(state.canCheckout, false);
});

test('cart restores after navigation using current product metadata and ignores malformed rows', () => {
    const originalWindow = globalThis.window;
    try {
        globalThis.window = { sessionStorage: { getItem: () => JSON.stringify([{ ...product, quantity: 2 }, null, { id: 9 }]) } };
        const state = register([{ ...product, price: 60000 }]);
        state.init();
        assert.equal(state.cart.length, 1);
        assert.equal(state.units, 2);
        assert.equal(state.total, 120000);
    } finally { globalThis.window = originalWindow; }
});

test('corrupt or unavailable session storage keeps the register usable', () => {
    const originalWindow = globalThis.window;
    try {
        globalThis.window = { sessionStorage: { getItem: () => '{invalid' } };
        const state = register();
        state.init();
        state.add(product);
        assert.equal(state.units, 1);
    } finally { globalThis.window = originalWindow; }
});

test('underpayment and blank cash input cannot start checkout, exact payment enables it', () => {
    const state = register();
    state.add(product);
    assert.equal(state.canCheckout, false);
    state.paidAmount = 10;
    assert.equal(state.canCheckout, false);
    state.exactPayment();
    assert.equal(state.canCheckout, true);
    assert.equal(state.change, 0);
});

test('one in-flight checkout blocks duplicate requests and clears the cart only after success', async () => {
    const originalFetch = globalThis.fetch;
    const originalDocument = globalThis.document;
    try {
        let resolve;
        let requests = 0;
        const events = [];
        globalThis.document = { querySelector: () => ({ content: 'csrf' }), getElementById: () => ({ close() {} }) };
        globalThis.fetch = () => { requests++; return new Promise((done) => { resolve = done; }); };
        const state = register();
        state.$dispatch = (...args) => events.push(args);
        state.add(product);
        state.exactPayment();
        const first = state.checkout();
        await state.checkout();
        state.remove(1);
        assert.equal(requests, 1);
        assert.equal(state.units, 1);
        resolve({ ok: true, json: async () => ({ success: true, invoice: 'INV-1', total: 50000.25, change: 0 }) });
        await first;
        assert.equal(state.units, 0);
        assert.equal(state.busy, false);
        assert.equal(state.products[0].available, 2);
        assert.equal(state.result.invoice, 'INV-1');
        assert.ok(events.some(([name, detail]) => name === 'open-dialog' && detail.id === 'pos-success'));
    } finally {
        globalThis.fetch = originalFetch;
        globalThis.document = originalDocument;
    }
});

test('server validation errors preserve the cart and release the submit lock', async () => {
    const originalFetch = globalThis.fetch;
    const originalDocument = globalThis.document;
    try {
        globalThis.document = { querySelector: () => ({ content: 'csrf' }) };
        globalThis.fetch = async () => ({ ok: false, json: async () => ({ errors: { items: ['Stok berubah.'] } }) });
        const state = register();
        state.add(product);
        state.exactPayment();
        await state.checkout();
        assert.equal(state.units, 1);
        assert.equal(state.error, 'Stok berubah.');
        assert.equal(state.busy, false);
    } finally {
        globalThis.fetch = originalFetch;
        globalThis.document = originalDocument;
    }
});
