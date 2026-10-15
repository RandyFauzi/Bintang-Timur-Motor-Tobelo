import { test } from 'node:test';
import assert from 'node:assert/strict';
import { transactionEditor } from '../../resources/js/components/transaction-editor.js';

test('editor calculates quantities with historical prices and proportional item discounts', () => {
    const editor = transactionEditor([{ item_id: 'a', product_id: 1, price: 50000.25, quantity: 2, original_quantity: 2, discount: 1000 }], 2000, 500);
    assert.equal(editor.units, 2);
    assert.equal(editor.total, 97500.5);
    editor.items[0].quantity = 3;
    assert.equal(editor.units, 3);
    assert.equal(editor.total, 147000.75);
});

test('adding an existing product preserves its historical price and combines quantity', () => {
    const editor = transactionEditor([{ item_id: 'a', product_id: 1, name: 'Lama', price: 50000, quantity: 1 }]);
    editor.add({ id: 1, name: 'Baru', price: 999999 });
    assert.equal(editor.items.length, 1);
    assert.equal(editor.items[0].price, 50000);
    assert.equal(editor.total, 100000);
});

test('new products use lookup prices and can be removed without affecting other rows', () => {
    const editor = transactionEditor([]);
    editor.add({ id: 1, name: 'Oli', price: 50000 });
    editor.add({ id: 2, name: 'Busi', price: 25000 });
    editor.remove(0);
    assert.equal(editor.units, 1);
    assert.equal(editor.total, 25000);
    assert.equal(editor.items[0].product_id, 2);
});
