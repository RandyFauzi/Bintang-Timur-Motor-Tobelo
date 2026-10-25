import { test } from 'node:test';
import assert from 'node:assert/strict';
import { notificationCenter } from '../../resources/js/components/notifications.js';
import { toast } from '../../resources/js/components/ui.js';

test('automatic notification refresh replaces resolved rows and updates the shared badge', async () => {
    const saved = { window: globalThis.window, document: globalThis.document, fetch: globalThis.fetch, CustomEvent: globalThis.CustomEvent };
    try {
        const events = [];
        globalThis.window = { dispatchEvent: (event) => events.push(event) };
        globalThis.document = { hidden: false, activeElement: null };
        globalThis.CustomEvent = class { constructor(type, options) { this.type = type; this.detail = options.detail; } };
        let result = { total: 1, counts: { low_stock: 1 }, html: '<article>Oli</article>', page_url: null };
        globalThis.fetch = async () => ({ ok: true, json: async () => result });
        const state = notificationCenter('/notifications?type=low_stock', { low_stock: 2 }, 2);
        state.$refs = { feed: { contains: () => false, innerHTML: '<article>Old</article>' } };
        await state.refresh();
        assert.equal(state.$refs.feed.innerHTML, '<article>Oli</article>');
        assert.equal(state.total, 1);
        assert.equal(events[0].type, 'notification-count');
        assert.equal(events[0].detail.count, 1);
        result = { total: 0, counts: { low_stock: 0 }, html: '<p>Tidak ada notifikasi</p>', page_url: null };
        await state.refresh();
        assert.equal(state.total, 0);
        assert.equal(events.at(-1).detail.count, 0);
        assert.ok(!state.$refs.feed.innerHTML.includes('Oli'));
        globalThis.fetch = async () => { throw new Error('offline'); };
        await state.refresh();
        assert.equal(state.total, 0);
        assert.equal(state.busy, false);
    } finally { Object.assign(globalThis, saved); }
});

test('refresh preserves focused pagination controls and follows a valid page after data shrinks', async () => {
    const saved = { window: globalThis.window, document: globalThis.document, fetch: globalThis.fetch };
    try {
        let target;
        let requests = 0;
        globalThis.window = { location: { replace: (url) => { target = url; } } };
        globalThis.document = { hidden: false, activeElement: {} };
        globalThis.fetch = async () => { requests++; return { ok: true, json: async () => ({ page_url: '/notifications?type=low_stock&page=1' }) }; };
        const state = notificationCenter('/notifications?page=2', {}, 1);
        state.$refs = { feed: { contains: () => true } };
        await state.refresh();
        assert.equal(requests, 0);
        state.$refs.feed.contains = () => false;
        await state.refresh();
        assert.equal(requests, 1);
        assert.equal(target, '/notifications?type=low_stock&page=1');
    } finally { Object.assign(globalThis, saved); }
});

test('toast can be minimized and restored', () => {
    const saved = { window: globalThis.window };
    try {
        let timerCleared = false;
        let timerSet = false;
        globalThis.window = {
            setTimeout: (fn, delay) => { timerSet = true; return 123; },
            clearTimeout: (id) => { timerCleared = true; },
        };
        const t = toast();
        assert.equal(t.minimized, false);
        t.show({ message: 'Barang berhasil disimpan', type: 'success' });
        assert.equal(t.visible, true);
        assert.equal(t.minimized, false);
        assert.equal(t.message, 'Barang berhasil disimpan');

        t.toggleMinimize();
        assert.equal(t.minimized, true);

        t.toggleMinimize();
        assert.equal(t.minimized, false);

        t.toggleMinimize();
        assert.equal(t.minimized, true);
        t.show({ message: 'Pesan baru', type: 'info' });
        assert.equal(t.minimized, false);
        assert.equal(t.message, 'Pesan baru');
    } finally {
        Object.assign(globalThis, saved);
    }
});
