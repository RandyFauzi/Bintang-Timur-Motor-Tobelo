import { test } from 'node:test';
import assert from 'node:assert/strict';
import { multiSelect, observationForm, appDialog } from '../../resources/js/components/catalogue.js';

const options = [{ id: '1', name: 'Oli' }, { id: '2', name: 'Rem' }, { id: '3', name: 'Rantai' }];

test('draft filter changes are not applied until confirmation', () => {
    const filter = multiSelect(options, ['1'], 'Semua Pengerjaan');
    let submissions = 0;
    filter.$nextTick = (callback) => callback();
    filter.$el = { closest: () => ({ requestSubmit() { submissions++; } }) };
    filter.draft = ['2', '3'];
    assert.equal(filter.summary, 'Oli');
    filter.apply();
    assert.equal(filter.summary, 'Rem, Rantai');
    assert.equal(submissions, 1);
});

test('select all uses search results and retains choices outside the search', () => {
    const filter = multiSelect(options, ['1'], 'Semua Pengerjaan');
    filter.search = 'R';
    filter.selectAll(true);
    assert.deepEqual(filter.draft, ['1', '2', '3']);
    assert.equal(filter.allSelected, true);
    filter.selectAll(false);
    assert.deepEqual(filter.draft, ['1']);
});

test('choosing suppliers in a product editor does not submit a partial product', () => {
    const filter = multiSelect(options, [], 'Pilih Distributor', false);
    let focused = false;
    filter.$refs = { trigger: { focus() { focused = true; } } };
    filter.$nextTick = (callback) => callback();
    filter.draft = ['1'];
    filter.apply();
    assert.deepEqual(filter.selected, ['1']);
    assert.equal(focused, true);
});

test('reopening a cancelled filter restores applied choices', () => {
    const filter = multiSelect(options, ['1'], 'Semua');
    filter.$nextTick = (callback) => callback();
    filter.$refs = { search: { focus() {} } };
    filter.draft = ['2'];
    filter.toggle();
    assert.deepEqual(filter.draft, ['1']);
});

test('closing a filter cancels draft changes and closes popup', () => {
    const filter = multiSelect(options, ['1'], 'Semua');
    let focused = false;
    filter.$refs = { trigger: { focus() { focused = true; } } };
    filter.$nextTick = (callback) => callback();
    filter.open = true;
    filter.draft = ['2'];
    filter.close();
    assert.equal(filter.open, false);
    assert.deepEqual(filter.draft, ['1']);
    assert.equal(focused, true);
});

test('observation quantities preserve selected work and prevent duplicate types', () => {
    const form = observationForm({ items: [{ work_type_id: 1, quantity: '3' }] }, options, [], {});
    form.toggleType(1, true);
    form.toggleType(2, true);
    assert.equal(form.total, 4);
    assert.equal(form.form.items.length, 2);
    form.form.items[1].quantity = 6;
    assert.equal(form.total, 9);
    form.toggleType(1, false);
    assert.equal(form.total, 6);
    assert.equal(form.typeName(2), 'Rem');
});

test('a successful inline lookup selects new types while preserving existing quantities', async () => {
    const originalFetch = globalThis.fetch;
    const originalDocument = globalThis.document;
    let closed = false;
    globalThis.document = { querySelector: () => ({ content: 'csrf' }), getElementById: () => ({ close() { closed = true; } }) };
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ data: [{ id: 4, name: 'Karbu' }], message: 'Tersimpan' }) });
    try {
        const form = observationForm({ items: [{ work_type_id: 1, quantity: 5 }] }, [...options], [], { types: '/types' });
        form.$dispatch = () => {};
        form.lookupKind = 'types';
        form.newNames = ['Karbu'];
        await form.saveLookup();
        assert.equal(form.form.items[0].quantity, 5);
        assert.equal(form.hasType(4), true);
        assert.equal(form.total, 6);
        assert.equal(closed, true);
        assert.equal(form.lookupBusy, false);
    } finally { globalThis.fetch = originalFetch; globalThis.document = originalDocument; }
});

test('lookup validation errors keep the form open and allow a retry', async () => {
    const originalFetch = globalThis.fetch;
    const originalDocument = globalThis.document;
    globalThis.document = { querySelector: () => ({ content: 'csrf' }) };
    globalThis.fetch = async () => ({ ok: false, json: async () => ({ errors: { 'names.0': ['Nama sudah digunakan.'] } }) });
    try {
        const form = observationForm({ items: [] }, [], [], { workshops: '/workshops' });
        await form.saveLookup();
        assert.equal(form.lookupError, 'Nama sudah digunakan.');
        assert.equal(form.lookupBusy, false);
        assert.deepEqual(form.workshops, []);
    } finally { globalThis.fetch = originalFetch; globalThis.document = originalDocument; }
});

test('dialog backdrop closes only clicks outside its bounds', () => {
    const dialog = appDialog();
    let closed = 0;
    dialog.$el = { getBoundingClientRect: () => ({ left: 10, right: 100, top: 10, bottom: 100 }), close() { closed++; } };
    dialog.backdrop({ target: dialog.$el, clientX: 50, clientY: 50 });
    assert.equal(closed, 0);
    dialog.backdrop({ target: dialog.$el, clientX: 0, clientY: 50 });
    assert.equal(closed, 1);
});
