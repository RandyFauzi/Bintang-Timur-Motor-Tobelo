import { test } from 'node:test';
import assert from 'node:assert/strict';
import { shipmentForm, shippingVoyage } from '../../resources/js/components/shipments.js';
import { stockNotifications } from '../../resources/js/components/ui.js';

test('shipment preview adds calendar days across months and handles missing values', () => {
    const state = shipmentForm({ 1: 14, 2: 2 }, 1, '2026-10-25');
    assert.equal(state.estimatedArrival, '8 November 2026');
    state.serviceId = '2';
    state.departedOn = '2028-02-28';
    assert.equal(state.estimatedArrival, '1 Maret 2028');
    state.serviceId = '';
    assert.equal(state.estimatedArrival, 'Pilih jasa dan tanggal berangkat');
    state.serviceId = '1';
    state.departedOn = 'bad';
    assert.equal(state.estimatedArrival, 'Periksa tanggal berangkat');
});

test('shipping alerts refresh without losing stock alerts and disappear after receipt', async () => {
    const original = { window: globalThis.window, document: globalThis.document, fetch: globalThis.fetch, CustomEvent: globalThis.CustomEvent };
    try {
        const events = [];
        globalThis.window = { location: { origin: 'https://example.test' }, dispatchEvent: (event) => events.push(event) };
        globalThis.document = { hidden: false };
        globalThis.CustomEvent = class { constructor(type, options) { this.type = type; this.detail = options.detail; } };
        let requested;
        const cargo = { message: 'Cargo terlambat', url: '/shipments?shipment=7' };
        let result = { count: 1, alerts: [cargo], states: { 7: { status: 'delayed' } }, counts: { delayed: 1 } };
        globalThis.fetch = async (url) => { requested = url; return { ok: true, json: async () => result }; };
        const stock = { name: 'Oli', message: 'Oli habis', quantity: 0, url: '/products' };
        const state = stockNotifications([stock], { count: 0, alerts: [] }, '/shipments/notifications');
        state.ids = [7];
        await state.refresh();
        assert.equal(requested.searchParams.get('ids[]'), '7');
        assert.equal(state.shipmentCount, 1);
        assert.deepEqual(state.alerts, [cargo, stock]);
        assert.equal(events[0].type, 'shipment-updates');
        assert.equal(events[0].detail.states[7].status, 'delayed');
        state.index = 1;
        result = { count: 0, alerts: [], states: { 7: { status: 'delivered' } }, counts: { delivered: 1 } };
        await state.refresh();
        assert.deepEqual(state.alerts, [stock]);
        assert.equal(state.current.name, 'Oli');
        assert.equal(state.index, 0);
        globalThis.fetch = async () => { throw new Error('offline'); };
        await state.refresh();
        assert.deepEqual(state.alerts, [stock]);
        assert.equal(state.busy, false);
    } finally {
        Object.assign(globalThis, original);
    }
});

test('empty notifications have safe navigation', () => {
    const state = stockNotifications([]);
    state.next();
    state.previous();
    assert.equal(state.index, 0);
    assert.equal(state.current.url, '#');
});


test('illustrated voyage recognizes port aliases and moves the boat with estimated progress', () => {
    const state = shippingVoyage('Pelabuhan Tanjung Perak, Surabaya', 'Toko Gamsungi Tobelo', 0);
    assert.deepEqual(state.boat, { x: state.start.x, y: state.start.y });
    state.progress = 50;
    assert.ok(state.boat.x > state.start.x && state.boat.x < state.end.x);
    assert.ok(state.completedPath.endsWith(`${state.boat.x} ${state.boat.y}`));
    state.progress = 100;
    assert.deepEqual(state.boat, { x: state.end.x, y: state.end.y });
    assert.equal(shippingVoyage('Surabaya', 'Tobelo').start.x, state.start.x);
    assert.notEqual(shippingVoyage('Jakarta', 'Tobelo').start.x, state.start.x);
});

test('unknown ports and invalid progress retain a bounded illustrative route', () => {
    const state = shippingVoyage('Kota Lain', 'Pelabuhan Tujuan', -20);
    assert.equal(state.fraction, 0);
    state.progress = 150;
    assert.equal(state.fraction, 1);
    state.progress = 'invalid';
    assert.equal(state.fraction, 0);
    assert.ok(state.viewBox.split(' ').every((value) => Number.isFinite(Number(value))));
    assert.ok(!state.path.includes('NaN'));
});
