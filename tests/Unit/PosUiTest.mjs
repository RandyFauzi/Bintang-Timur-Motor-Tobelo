import { test } from 'node:test';
import assert from 'node:assert/strict';
import { posRegister } from '../../resources/js/components/pos.js';

const product = { id: 1, name: 'Oli', price: 50000.25, available: 3, sku: 'OLI-1' };
function register(products = [{ ...product }]) {
    const state = posRegister(products, { checkout: '/checkout', scan: '/scan' }, 'pos-test');
    state.$dispatch = () => {};
    return state;
}

test('cart combines products, counts units, and calculates precise transaction totals', () => {
    const state = register();
    state.add(product);
    state.add(product);
    assert.equal(state.cart.length, 1);
    assert.equal(state.units, 2);
    assert.equal(state.total, 100000.5);
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

test('checkout needs products and available stock without requiring cash input', () => {
    const state = register();
    assert.equal(state.canCheckout, false);
    state.add(product);
    assert.equal(state.canCheckout, true);
    state.setQuantity(product.id, 3);
    assert.equal(state.total, 150000.75);
    assert.equal(state.canCheckout, true);
    state.busy = true;
    assert.equal(state.canCheckout, false);
});

test('one in-flight checkout blocks duplicate requests and clears the cart only after success', async () => {
    const originalFetch = globalThis.fetch;
    const originalDocument = globalThis.document;
    try {
        let resolve;
        let requests = 0;
        let payload;
        const events = [];
        globalThis.document = { querySelector: () => ({ content: 'csrf' }), getElementById: () => ({ close() {} }) };
        globalThis.fetch = (url, options) => { payload = JSON.parse(options.body); requests++; return new Promise((done) => { resolve = done; }); };
        const state = register();
        state.$dispatch = (...args) => events.push(args);
        state.add(product);
        const first = state.checkout();
        await state.checkout();
        state.remove(1);
        assert.equal(requests, 1);
        assert.equal(payload.paid_amount, 50000.25);
        assert.equal(payload.payment_method, 'cash');
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
        await state.checkout();
        assert.equal(state.units, 1);
        assert.equal(state.error, 'Stok berubah.');
        assert.equal(state.busy, false);
    } finally {
        globalThis.fetch = originalFetch;
        globalThis.document = originalDocument;
    }
});

test('manual quantity input immediately restores values that exceed available stock', () => {
    const state = register();
    state.add(product);
    const input = { value: '4' };
    state.updateQuantityInput(1, input);
    assert.equal(input.value, '1');
    assert.equal(state.quantity(1), 1);
    input.value = '3';
    state.updateQuantityInput(1, input);
    assert.equal(state.quantity(1), 3);
    state.add(product);
    assert.equal(state.quantity(1), 3);
    input.value = '';
    state.updateQuantityInput(1, input);
    assert.equal(input.value, '');
    assert.equal(state.quantity(1), 3);
    state.updateQuantityInput(1, input, true);
    assert.equal(input.value, '3');
});

test('cart cannot advance to review with quantities above refreshed stock', () => {
    const state = register();
    state.add(product);
    state.cart[0].available = 0;
    const events = [];
    state.$dispatch = (...args) => events.push(args);
    assert.equal(state.canCheckout, false);
    state.review();
    assert.equal(events.some(([name]) => name === 'open-dialog'), false);
    assert.equal(events[0][1].type, 'error');
    state.cart[0].available = 3;
    state.cart[0].quantity = -1;
    assert.equal(state.canCheckout, false);
    state.cart[0].quantity = 1.5;
    assert.equal(state.canCheckout, false);
});

test('decrement reduces quantity or removes product from cart, and onProductClick adds only unselected items', () => {
    const state = register();
    assert.equal(state.quantity(product.id), 0);

    // Clicking unselected product adds it
    state.onProductClick(product);
    assert.equal(state.quantity(product.id), 1);
    assert.equal(state.units, 1);

    // Clicking already selected product via onProductClick does not auto-add (user uses stepper)
    state.onProductClick(product);
    assert.equal(state.quantity(product.id), 1);

    // Incrementing via add
    state.add(product);
    assert.equal(state.quantity(product.id), 2);

    // Decrementing via decrement
    state.decrement(product.id);
    assert.equal(state.quantity(product.id), 1);
    assert.equal(state.units, 1);

    // Decrementing on quantity 1 removes product from cart completely
    state.decrement(product.id);
    assert.equal(state.quantity(product.id), 0);
    assert.equal(state.units, 0);
    assert.equal(state.cart.length, 0);

    // Out of stock product is not added on click
    state.onProductClick({ ...product, available: 0 });
    assert.equal(state.quantity(product.id), 0);
});
