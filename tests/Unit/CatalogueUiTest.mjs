import { test } from 'node:test';
import assert from 'node:assert/strict';
import { appDialog, distributorAutocomplete, multiSelect, observationForm } from '../../resources/js/components/catalogue.js';

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

test('single dropdown choice applies immediately, closes the list and keeps the parent form open', () => {
    const filter = multiSelect(options, ['1'], 'Pilih Jasa Cargo', false);
    let focused = false;
    filter.$nextTick = callback => callback();
    filter.$refs = {trigger:{focus() {focused = true;}}};
    filter.open = true;
    filter.choose('2');
    assert.deepEqual(filter.selected, ['2']);
    assert.equal(filter.summary, 'Rem');
    assert.equal(filter.open, false);
    assert.equal(focused, true);
    filter.choose('unknown');
    assert.deepEqual(filter.selected, ['2']);
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


test('closed filters do not steal focus and outside clicks preserve the clicked focus target', () => {
    const filter = multiSelect(options, ['1'], 'Semua');
    let focused = 0;
    filter.$refs = { trigger: { focus() { focused++; } } };
    filter.$nextTick = (callback) => callback();
    filter.close();
    assert.equal(focused, 0);
    filter.open = true;
    filter.draft = ['2'];
    filter.close(false);
    assert.equal(filter.open, false);
    assert.deepEqual(filter.draft, ['1']);
    assert.equal(focused, 0);
});

test('popup follows the visible viewport when the mobile keyboard changes its height', () => {
    const filter = multiSelect(options, [], 'Semua');
    filter.positionPanel({ width: 360, height: 700, offsetLeft: 0, offsetTop: 0 });
    assert.equal(filter.panelPosition['--filter-center-y'], '350px');
    filter.positionPanel({ width: 360, height: 280, offsetLeft: 0, offsetTop: 100 });
    assert.equal(filter.panelPosition['--filter-center-x'], '180px');
    assert.equal(filter.panelPosition['--filter-center-y'], '240px');
    assert.equal(filter.panelPosition['--filter-max-height'], '248px');
    assert.equal(filter.panelPosition['--filter-max-width'], '328px');
});

test('dropdown stays anchored to its trigger and fits the right viewport edge', () => {
    const filter = multiSelect(options, [], 'Semua');
    filter.$refs = {trigger: {getBoundingClientRect: () => ({left: 300, top: 610, bottom: 650, width: 160})}};

    filter.positionPanel({width: 360, height: 700, offsetLeft: 0, offsetTop: 0});

    assert.equal(filter.panelPosition['--filter-anchor-width'], '260px');
    assert.equal(filter.panelPosition['--filter-anchor-left'], '88px');
    assert.equal(filter.panelPosition['--filter-anchor-top'], 'auto');
    assert.equal(filter.panelPosition['--filter-anchor-bottom'], '96px');
});

test('opening a dropdown on mobile does not force the keyboard open', () => {
    const previousWindow = globalThis.window;
    globalThis.window = {innerWidth: 390, innerHeight: 800};
    try {
        const filter = multiSelect(options, [], 'Semua');
        let focused = '';
        filter.$refs = {panel: {focus() { focused = 'panel'; }}, search: {focus() { focused = 'search'; }}};
        filter.$nextTick = callback => callback();

        filter.toggle();

        assert.equal(focused, 'panel');
        assert.equal(filter.open, true);
    } finally {
        if (previousWindow === undefined) delete globalThis.window;
        else globalThis.window = previousWindow;
    }
});


test('viewport listeners follow keyboard resizing and are removed when a filter is destroyed', () => {
    const originalWindow = globalThis.window;
    const listeners = new Map();
    const viewport = {
        width: 360, height: 700, offsetLeft: 0, offsetTop: 0,
        addEventListener(event, callback) { listeners.set(`viewport-${event}`, callback); },
        removeEventListener(event, callback) { if (listeners.get(`viewport-${event}`) === callback) listeners.delete(`viewport-${event}`); },
    };
    globalThis.window = {
        visualViewport: viewport,
        addEventListener(event, callback) { listeners.set(`window-${event}`, callback); },
        removeEventListener(event, callback) { if (listeners.get(`window-${event}`) === callback) listeners.delete(`window-${event}`); },
    };
    try {
        const filter = multiSelect(options, [], 'Semua');
        filter.init();
        filter.open = true;
        viewport.height = 240;
        listeners.get('viewport-resize')();
        assert.equal(filter.panelPosition['--filter-max-height'], '208px');
        filter.destroy();
        assert.equal(listeners.size, 0);
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('mobile form opens within the visible viewport without scrolling to a text field', () => {
    const previousWindow = globalThis.window;
    try {
        globalThis.window = { innerWidth: 390, innerHeight: 800, visualViewport: { width: 390, height: 500, offsetLeft: 0, offsetTop: 0 } };
        const values = {};
        let focused;
        const dialog = appDialog();
        dialog.$el = { open: false, scrollLeft: 80, scrollTop: 150,
            style: { setProperty(key, value) { values[key] = value; } },
            showModal() { this.open = true; },
            querySelector(selector) { return selector === '.dialog-close' ? { focus(options) { focused = options; } } : null; },
        };
        dialog.$nextTick = (callback) => callback();
        dialog.open();
        assert.equal(values['--sheet-width'], '390px');
        assert.equal(values['--sheet-height'], '488px');
        assert.equal(values['--sheet-bottom'], '300px');
        assert.equal(dialog.$el.scrollLeft, 0);
        assert.equal(dialog.$el.scrollTop, 0);
        assert.deepEqual(focused, { preventScroll: true });
    } finally { globalThis.window = previousWindow; }
});

test('active filter indicators reflect applied choices and display both selected names', () => {
    const filter = multiSelect(options, [], 'Semua');
    assert.equal(filter.active, false);
    assert.equal(filter.selectedCount, 0);
    filter.draft = ['1', '2'];
    assert.equal(filter.active, false);
    filter.selected = [...filter.draft];
    assert.equal(filter.active, true);
    assert.equal(filter.selectedCount, 2);
    assert.equal(filter.summary, 'Oli, Rem');
    filter.selected = ['1', '2', '3'];
    assert.equal(filter.summary, 'Oli, Rem, +1');
    filter.selected = [];
    assert.equal(filter.active, false);
    assert.equal(filter.summary, 'Semua');
    const stock = multiSelect([{ id: '', name: 'Semua Stok' }, { id: '1', name: 'Stok Menipis' }], [''], 'Semua Stok');
    assert.equal(stock.active, false);
    stock.selected = ['1'];
    assert.equal(stock.active, true);
    assert.equal(stock.summary, 'Stok Menipis');
});

test('distributorAutocomplete initializes with prefilled data and allows selection', () => {
    const list = [{ id: 10, name: 'PT Sumber Makmur' }, { id: 20, name: 'CV Jaya Abadi' }];
    const comp = distributorAutocomplete(list, 10, 'PT Sumber Makmur', '/distributors');
    assert.equal(comp.selectedId, '10');
    assert.equal(comp.query, 'PT Sumber Makmur');
    assert.equal(comp.exactMatch, true);
    assert.equal(comp.canCreate, false);

    // Filter by typing
    comp.query = 'Jaya';
    comp.onInput();
    assert.equal(comp.filtered.length, 1);
    assert.equal(comp.filtered[0].name, 'CV Jaya Abadi');
    assert.equal(comp.exactMatch, false);
    assert.equal(comp.canCreate, true);

    // Select existing
    comp.select(comp.filtered[0]);
    assert.equal(comp.selectedId, '20');
    assert.equal(comp.query, 'CV Jaya Abadi');
    assert.equal(comp.open, false);

    // Clear
    comp.clear();
    assert.equal(comp.query, '');
    assert.equal(comp.selectedId, '');
    assert.equal(comp.selectedName, '');
});

test('distributorAutocomplete can create a new distributor inline via API', async () => {
    const previousWindow = globalThis.window;
    const previousDocument = globalThis.document;
    const list = [{ id: 1, name: 'Distributor Lama' }];
    try {
        const events = [];
        globalThis.window = {
            dispatchEvent: (e) => events.push(e),
        };
        globalThis.CustomEvent = class {
            constructor(type, init) { this.type = type; this.detail = init?.detail; }
        };
        globalThis.document = {
            querySelector: (sel) => ({ content: 'csrf-test-token' }),
        };
        let requestBody = null;
        globalThis.fetch = async (url, options) => {
            requestBody = JSON.parse(options.body);
            return {
                ok: true,
                json: async () => ({
                    success: true,
                    data: { id: 99, name: requestBody.name },
                }),
            };
        };

        const comp = distributorAutocomplete(list, null, '', '/distributors');
        comp.query = 'Distributor Baru Keren';
        comp.onInput();
        assert.equal(comp.canCreate, true);
        assert.equal(comp.exactMatch, false);

        await comp.createNew();

        assert.equal(requestBody.name, 'Distributor Baru Keren');
        assert.equal(comp.selectedId, '99');
        assert.equal(comp.selectedName, 'Distributor Baru Keren');
        assert.equal(comp.query, 'Distributor Baru Keren');
        assert.equal(comp.newlyAdded, true);
        assert.equal(comp.open, false);
        assert.equal(events.length, 1);
        assert.equal(events[0].type, 'notify');
    } finally {
        globalThis.window = previousWindow;
        globalThis.document = previousDocument;
    }
});
