import { test } from 'node:test';
import assert from 'node:assert/strict';
import { productSelection } from '../../resources/js/components/product-selection.js';

test('product selection deduplicates IDs and select all affects only the current page', () => {
    const state = productSelection([1, '1', 2, null, 'invalid']);
    assert.deepEqual(state.selected, ['1', '2']);
    state.togglePage([3, 4], true);
    assert.equal(state.pageSelected([3, 4]), true);
    state.togglePage([3, 4], false);
    assert.deepEqual(state.selected, ['1', '2']);
    assert.equal(state.pageSelected([]), false);
    state.toggle(1, true);
    assert.deepEqual(state.selected, ['2', '1']);
    state.toggle(1, false);
    assert.deepEqual(state.selected, ['2']);
});

test('invalid flashed selection does not break the product page', () => {
    assert.deepEqual(productSelection('invalid').selected, []);
    assert.deepEqual(productSelection(null).selected, []);
});
