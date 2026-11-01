import { test } from 'node:test';
import assert from 'node:assert/strict';
import { invoiceSelection } from '../../resources/js/components/invoice-selection.js';

function setup(count) {
    const state = invoiceSelection();
    const rows = Array.from({length: count}, (_, i) => ({dataset: {invoiceId: String(i + 1), invoiceVersion: 2}}));
    state.$el = {querySelectorAll: () => rows};
    state.init();
    return {state, rows};
}

test('select all uses loaded invoice versions and later pages preserve previous selections', () => {
    const {state, rows} = setup(2);
    state.toggleAll(true);
    assert.deepEqual(state.selected, [{id:'1', version:2}, {id:'2', version:2}]);
    assert.equal(state.allSelected, true);
    rows.push({dataset:{invoiceId:'3', invoiceVersion:4}});
    state.refreshRows();
    assert.equal(state.allSelected, false);
    assert.equal(state.selected.length, 2);
    state.toggle('3', 4, true);
    assert.equal(state.allSelected, true);
    state.toggleAll(false);
    assert.deepEqual(state.selected, []);
});

test('selection never exceeds the server limit and repeat toggles do not duplicate invoices', () => {
    const {state} = setup(101);
    state.toggleAll(true);
    assert.equal(state.selected.length, 100);
    assert.ok(state.limitMessage);
    state.toggle(101, 2, true);
    assert.equal(state.has(101), false);
    state.toggle(1, 2, true);
    assert.equal(state.selected.length, 100);
    state.toggle(1, 2, false);
    state.toggle(101, 2, true);
    assert.equal(state.has(101), true);
    assert.equal(state.selected.length, 100);
});

test('invalid flashed selection does not break the page', () => {
    assert.deepEqual(invoiceSelection('bad').selected, []);
    assert.deepEqual(invoiceSelection([null, {id:'x'}, {id:1,version:0}]).selected, []);
});
