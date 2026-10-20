import { readFileSync } from 'node:fs';
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
        const stock = { name: 'Oli', message: 'Oli habis', quantity: 0, url: '/products' };
        let result = { stock_count: 7, stock_alerts: [stock], active_count: 8, count: 1, alerts: [cargo], states: { 7: { status: 'delayed' } }, counts: { delayed: 1 } };
        globalThis.fetch = async (url) => { requested = url; return { ok: true, json: async () => result }; };
        const state = stockNotifications([stock], { count: 0, alerts: [] }, '/shipments/notifications');
        state.ids = [7];
        await state.refresh();
        assert.equal(requested.searchParams.get('ids[]'), '7');
        assert.equal(state.shipmentCount, 1);
        assert.deepEqual(state.alerts, [cargo, stock]);
        assert.equal(events[0].type, 'shipment-updates');
        assert.equal(events[1].type, 'notification-count');
        assert.equal(events[1].detail.count, 8);
        assert.equal(state.stockCount, 7);
        assert.equal(events[0].detail.states[7].status, 'delayed');
        state.index = 1;
        result = { stock_count: 0, stock_alerts: [], active_count: 0, count: 0, alerts: [], states: { 7: { status: 'delivered' } }, counts: { delivered: 1 } };
        await state.refresh();
        assert.deepEqual(state.alerts, []);
        assert.equal(state.stockCount, 0);
        assert.equal(events.at(-1).detail.count, 0);
        assert.equal(state.index, 0);
        globalThis.fetch = async () => { throw new Error('offline'); };
        await state.refresh();
        assert.deepEqual(state.alerts, []);
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

test('unknown ports never invent a route and invalid progress remains bounded', () => {
    const state = shippingVoyage('Kota Lain', 'Pelabuhan Tujuan', -20);
    assert.equal(state.fraction, 0);
    assert.equal(state.known, false);
    assert.equal(state.path, '');
    state.progress = 150;
    assert.equal(state.fraction, 1);
    state.progress = 'invalid';
    assert.equal(state.fraction, 0);
    assert.ok(state.viewBox.split(' ').every((value) => Number.isFinite(Number(value))));
    assert.ok(!state.path.includes('NaN'));
});


test('port markers follow geographic coordinates and reject partial-name matches', () => {
    const state = shippingVoyage('Makassar', 'Tobelo', 100);
    assert.equal(state.known, true);
    assert.ok(Math.abs(state.originPort.longitude - 119.4) < 0.01);
    assert.ok(Math.abs(state.destinationPort.longitude - 128.0172) < 0.0001);
    assert.ok(state.end.y < state.start.y);
    assert.deepEqual(state.boat, state.end);
    assert.equal(shippingVoyage('Balikpapan', 'Tobelo').originPort.id, 'balikpapan');
    assert.equal(shippingVoyage('Notobelo', 'Makassar').known, false);
});

test('reverse voyages use the same sea corridor and same-port voyages remain stationary', () => {
    const forward = shippingVoyage('Makassar', 'Tobelo', 0);
    const reverse = shippingVoyage('Tobelo', 'Makassar', 100);
    assert.deepEqual(reverse.points, [...forward.points].reverse());
    const same = shippingVoyage('Tobelo', 'Tobelo', 75);
    assert.deepEqual(same.boat, same.start);
    assert.equal(same.heading, 0);
});


test('all bundled sea corridors stay off the Natural Earth land polygons', () => {
    const geography = JSON.parse(readFileSync(new URL('../../resources/js/components/voyage-geography.json', import.meta.url), 'utf8'));
    const coast = readFileSync(new URL('../../resources/images/maps/indonesia-coast.svg', import.meta.url), 'utf8');
    const polygons = [...coast.matchAll(/<path d="([^"]+)"/g)].map(match => {
        const numbers = match[1].split(/[MLZ,\s]+/).filter(Boolean).map(Number);
        const points = [];
        for (let i=0;i<numbers.length;i+=2) points.push([numbers[i],numbers[i+1]]);
        return {points,minX:Math.min(...points.map(p=>p[0])),maxX:Math.max(...points.map(p=>p[0])),minY:Math.min(...points.map(p=>p[1])),maxY:Math.max(...points.map(p=>p[1]))};
    });
    const onLand = ([longitude,latitude]) => {
        const x=(longitude-geography.bounds[0])*geography.scale, y=(geography.bounds[3]-latitude)*geography.scale;
        let inside=false;
        for (const ring of polygons) {
            if (x<ring.minX||x>ring.maxX||y<ring.minY||y>ring.maxY) continue;
            for (let i=0,j=ring.points.length-1;i<ring.points.length;j=i++) {
                const a=ring.points[i], b=ring.points[j];
                if ((a[1]>y)!==(b[1]>y) && x<(b[0]-a[0])*(y-a[1])/(b[1]-a[1])+a[0]) inside=!inside;
            }
        }
        return inside;
    };
    assert.equal(Object.keys(geography.routes).length, 120);
    for (const [key,route] of Object.entries(geography.routes)) {
        for (let i=1;i<route.length;i++) {
            const a=route[i-1],b=route[i],steps=Math.max(1,Math.ceil(Math.hypot(b[0]-a[0],b[1]-a[1])/.02));
            for (let j=0;j<=steps;j++) {
                const p=[a[0]+(b[0]-a[0])*j/steps,a[1]+(b[1]-a[1])*j/steps];
                assert.equal(onLand(p),false, `${key} crosses land near ${p}`);
            }
        }
    }
});
