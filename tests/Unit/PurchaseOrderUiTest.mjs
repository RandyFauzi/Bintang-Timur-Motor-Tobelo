import { test } from 'node:test';
import assert from 'node:assert/strict';
import { purchaseOrderForm, purchaseOrderReceipt } from '../../resources/js/components/purchase-orders.js';
import { stockNotifications } from '../../resources/js/components/ui.js';

test('receipt distinguishes unchecked, missing, short, excess and matching deliveries', () => {
    const state = purchaseOrderReceipt([]);
    for (const [actual, expected] of [[null, 'Belum diperiksa'], ['', 'Belum diperiksa'], [0, 'Tidak datang / Kosong'], [5, 'Kurang 5'], [20, 'Lebih 10'], [10, 'Sesuai'], [-1, 'Jumlah tidak valid'], [1.5, 'Jumlah tidak valid'], [1000001, 'Jumlah tidak valid']]) {
        assert.equal(state.label({quantity:10, actual}), expected);
    }
});

test('manual order editor keeps supplier names independent and totals edited rows', () => {
    const state = purchaseOrderForm();
    state.add();
    Object.assign(state.rows[0], {product_name:'Nama di nota', unit:'dus', quantity:'3', unit_price:'123.45'});
    assert.equal(state.total, 370.35);
    state.add();
    Object.assign(state.rows[1], {product_name:'Nama di nota', unit:'pcs', quantity:1, unit_price:'10000'});
    assert.equal(state.rows.length, 2);
    assert.equal(state.total, 10370.35);
    assert.equal(Object.hasOwn(state.rows[0], 'product_id'), false);
    state.rows.splice(0,1);
    assert.equal(state.total, 10000);
});

test('restored manual rows retain entered names and use unique row keys', () => {
    const state = purchaseOrderForm([{product_name:'Barang A',sku:'',unit:'pcs',quantity:2,unit_price:100}]);
    state.add();
    assert.equal(state.rows[0].product_name,'Barang A');
    assert.notEqual(state.rows[0].key,state.rows[1].key);
    for (let i=state.rows.length;i<100;i++) state.add();
    state.add();
    assert.equal(state.rows.length,100);
    assert.equal(state.error,'Maksimal 100 barang per PO.');
});

test('invoice reminder badge and sidebar clear resolved bills during refresh', async () => {
    const saved = {window:globalThis.window, document:globalThis.document, fetch:globalThis.fetch, CustomEvent:globalThis.CustomEvent};
    try {
        const events = [];
        globalThis.window = {location:{origin:'https://example.test'},dispatchEvent:event=>events.push(event)};
        globalThis.document = {hidden:false};
        globalThis.CustomEvent = class {constructor(type,options) {this.type=type; this.detail=options.detail;}};
        const invoice = {message:'Tagihan jatuh tempo',url:'/distributor-invoices/1'};
        const state = stockNotifications([], {count:0,alerts:[]}, '/shipments/notifications', 0, {count:1,alerts:[invoice]});
        assert.equal(state.invoiceCount,1);
        assert.equal(state.current.url,'/distributor-invoices/1');
        globalThis.fetch = async () => ({ok:true,json:async()=>({count:0,alerts:[],stock_count:0,stock_alerts:[],invoice_count:0,invoice_alerts:[],active_count:0})});
        await state.refresh();
        assert.equal(state.invoiceCount,0);
        assert.deepEqual(state.alerts,[]);
        assert.equal(events.at(-1).detail.count,0);
    } finally {Object.assign(globalThis,saved);}
});

import { purchasePlanForm } from '../../resources/js/components/purchase-orders.js';

const planState = () => purchasePlanForm({request_id:'11111111-2222-4333-8444-555555555555',version:0,name:'Pengadaan Oktober',planned_on:'2026-10-21',items:[]}, [{id:1,name:'WMS'},{id:2,name:'WIN'}], {});

test('plan stages require a selected product and exact positive distributor allocations', () => {
    const state = planState();
    state.next();
    assert.equal(state.step, 1);
    state.add();
    state.select(state.rows[0], {product_id:7,product_name:'Oli',quantity:0,current_stock:40,unit_price:12000});
    state.rows[0].quantity = 30;
    state.next();
    assert.equal(state.step, 2);
    state.rows[0].allocations = [{distributor_id:1,quantity:15}];
    state.next();
    assert.equal(state.step, 2);
    assert.equal(state.allocationNote(state.rows[0]), 'Belum dialokasikan 15 unit');
    state.rows[0].allocations.push({distributor_id:2,quantity:15});
    state.next();
    assert.equal(state.step, 3);
    assert.equal(state.supplierCount, 2);
    assert.equal(state.units, 30);
    assert.equal(state.allocationNote(state.rows[0]), 'Teralokasi semua');
    state.rows[0].allocations[1].distributor_id = 1;
    assert.notEqual(state.validateAllocations(), '');
    state.rows[0].allocations[1] = {distributor_id:2,quantity:16};
    assert.equal(state.allocationNote(state.rows[0]), 'Kelebihan alokasi 1 unit');
    assert.notEqual(state.validateAllocations(), '');
});

test('catalogue attributes are copied into plan rows and UI-only fields never enter saved payload', () => {
    const state = planState();
    const product = {product_id:7,product_name:'Oli Katalog',color:'Gold',brand:'Yamaha',quantity:0,current_stock:40,unit_price:'12000.00'};
    state.add();
    state.select(state.rows[0], product);
    state.rows[0].product_name = 'Nama Rencana';
    state.rows[0].quantity = '23';
    const payload = state.payload('draft');
    assert.equal(product.product_name,'Oli Katalog');
    assert.equal(payload.items[0].product_name,'Nama Rencana');
    assert.equal(payload.items[0].quantity,23);
    assert.equal(Object.hasOwn(payload.items[0],'key'), false);
    assert.equal(Object.hasOwn(payload.items[0],'results'), false);
    assert.equal(state.displayReference,'PGD-20261021-111111112222');
});

test('opening an allocation closes the previous product without losing either allocation', () => {
    const state = planState();
    for (const name of ['Oli', 'Busi']) {
        state.add();
        state.select(state.rows.at(-1), {product_name:name, quantity:0});
        state.rows.at(-1).quantity = 10;
    }
    state.next();
    const [first, second] = state.rows;
    assert.equal(state.openAllocationKey, first.key);
    first.allocations = [{distributor_id:1, quantity:10}];
    state.toggleAllocation(second);
    assert.equal(state.openAllocationKey, second.key);
    assert.deepEqual(first.allocations, [{distributor_id:1, quantity:10}]);
    state.toggleAllocation(second);
    assert.equal(state.openAllocationKey, null);
    state.toggleAllocation(first);
    state.remove(0);
    assert.equal(state.openAllocationKey, null);
    state.goTo(1);
    state.next();
    assert.equal(state.openAllocationKey, second.key);
});

test('manual product modal keeps attributes and quantity in the plan without a catalogue identifier', () => {
    const state = planState();
    const events = [];
    state.$dispatch = (name,detail) => events.push({name,detail});
    state.add();
    state.rows[0].quantity = 23;
    state.openManual(0);
    Object.assign(state.manual,{product_name:'Spakbor Mio M3',color:'Gold',product_kind:'variation',brand:'Yamaha',part_type:'Body Halus'});
    state.applyManual();
    assert.equal(state.rows[0].quantity,23);
    assert.equal(state.rows[0].product_name,'Spakbor Mio M3');
    assert.equal(state.rows[0].product_id,null);
    assert.equal(state.rows[0].pickerOpen,false);
    assert.equal(events.at(-1).name,'close-plan-manual');
});

test('stale catalogue search results cannot replace a newer query', async () => {
    const saved = {window:globalThis.window,fetch:globalThis.fetch};
    try {
        globalThis.window = {location:{origin:'https://example.test'}};
        const requests = [];
        globalThis.fetch = () => new Promise(resolve => requests.push(resolve));
        const state = planState(); state.urls.products = '/purchase-plans/products'; state.add();
        const row = state.rows[0];
        row.product_name='O'; const first=state.search(row);
        row.product_name='Oli'; const second=state.search(row);
        requests[1]({ok:true,json:async()=>[{product_id:2,product_name:'Oli'}]});
        await second;
        requests[0]({ok:true,json:async()=>[{product_id:1,product_name:'Older'}]});
        await first;
        assert.equal(row.results[0].product_id,2);
        assert.equal(row.searching,false);
    } finally { Object.assign(globalThis,saved); }
});

async function withDraftBrowser(run) {
    const saved = {window:globalThis.window, document:globalThis.document, fetch:globalThis.fetch};
    const state = planState();
    const navigation = [], histories = [], events = [];
    const watchers = new Map();
    globalThis.window = {
        location:{origin:'https://example.test', assign:url => navigation.push(url)},
        history:{state:null, replaceState:(_state, _unused, url) => histories.push(url)},
        addEventListener() {}, removeEventListener() {},
    };
    globalThis.document = {querySelector:() => ({content:'csrf'}), addEventListener() {}, removeEventListener() {}};
    state.urls.save = '/plans';
    state.$watch = (property, callback) => watchers.set(property, callback);
    state.$dispatch = (name, detail) => events.push({name, detail});
    state.init();
    const response = version => ({ok:true, json:async () => ({version, reference:'PGD-SAVED', save_url:'/plans/7', edit_url:'/plans/7/edit', redirect:'/plans/7'})});
    try { await run({state, navigation, histories, events, response, watchers}); }
    finally {state.destroy(); Object.assign(globalThis, saved);}
}

test('product and allocation changes debounce into one autosave and clean UI changes send nothing', async () => {
    const savedTimers = {setTimeout:globalThis.setTimeout, clearTimeout:globalThis.clearTimeout};
    const timers = new Map();
    let timerId = 0;
    globalThis.setTimeout = (callback, delay) => {assert.equal(delay, 1500); timers.set(++timerId, callback); return timerId;};
    globalThis.clearTimeout = id => timers.delete(id);
    try {
        await withDraftBrowser(async ({state, watchers, response}) => {
            let calls = 0;
            globalThis.fetch = async () => {calls++; return response(1);};
            state.rows = [{product_id:null, product_name:'Oli', quantity:10, allocations:[]}];
            watchers.get('rows')();
            state.rows[0].allocations.push({distributor_id:1, quantity:4});
            watchers.get('rows')();
            state.rows[0].allocations[0].quantity = 7;
            watchers.get('rows')();
            assert.equal(calls, 0);
            assert.equal(timers.size, 1);
            const callback = [...timers.values()][0];
            await callback();
            assert.equal(calls, 1);
            assert.equal(state.dirty, false);
            state.rows[0].results = [{product_name:'Hasil pencarian'}];
            watchers.get('rows')();
            assert.equal(timers.size, 0);
            assert.equal(calls, 1);
        });
    } finally {Object.assign(globalThis, savedTimers);}
});

test('autosave creates an unnamed draft in place and subsequent allocations update its current version', async () => {
    await withDraftBrowser(async ({state, navigation, histories, response}) => {
        const requests = [];
        globalThis.fetch = async (url, options) => {requests.push({url, ...options, body:JSON.parse(options.body)}); return response(requests.length);};
        state.name = '';
        state.add();
        state.select(state.rows[0], {product_name:'Busi'});
        state.rows[0].quantity = 10;
        state.step = 2;
        state.openAllocationKey = state.rows[0].key;
        assert.equal(await state.save('draft', true), true);
        assert.equal(requests[0].method, 'POST');
        assert.equal(requests[0].body.name, 'Draft Pengadaan 2026-10-21');
        assert.equal(requests[0].body.autosave, true);
        assert.equal(state.step, 2);
        assert.equal(state.openAllocationKey, state.rows[0].key);
        assert.equal(state.dirty, false);
        assert.deepEqual(navigation, []);
        assert.deepEqual(histories, ['/plans/7/edit']);
        state.rows[0].allocations = [{distributor_id:1, quantity:4}];
        assert.equal(await state.save('draft', true), true);
        assert.equal(requests[1].url, '/plans/7');
        assert.equal(requests[1].method, 'PATCH');
        assert.equal(requests[1].body.version, 1);
        assert.equal(requests[1].body.items[0].allocations[0].quantity, 4);
        assert.equal(state.version, 2);
        assert.equal(state.dirty, false);
    });
});

test('edits during autosave remain unsaved and overlapping saves cannot send stale versions', async () => {
    await withDraftBrowser(async ({state, response}) => {
        let finish;
        let calls = 0;
        globalThis.fetch = () => {calls++; return new Promise(resolve => {finish = resolve;});};
        state.add();
        state.rows[0].quantity = 3;
        const pending = state.save('draft', true);
        state.rows[0].quantity = 9;
        assert.equal(await state.save('draft'), false);
        assert.equal(calls, 1);
        finish(response(1));
        await pending;
        assert.equal(state.version, 1);
        assert.equal(state.rows[0].quantity, 9);
        assert.equal(state.dirty, true);
        globalThis.fetch = async (_url, options) => {
            assert.equal(JSON.parse(options.body).version, 1);
            assert.equal(JSON.parse(options.body).items[0].quantity, 9);
            return response(2);
        };
        await state.save('draft', true);
        assert.equal(state.dirty, false);
    });
});

test('failed saving blocks exit and warns on refresh until a retry saves the draft', async () => {
    await withDraftBrowser(async ({state, navigation, events, response}) => {
        state.add();
        state.rows[0].product_name = 'Produk belum selesai';
        let prevented = 0;
        const unload = {preventDefault:() => prevented++};
        state.warnBeforeUnload(unload);
        assert.equal(prevented, 1);
        assert.equal(unload.returnValue, '');
        state.guardLink({button:0, target:{closest:() => ({href:'/purchase-orders', target:'', hasAttribute:() => false, getAttribute:() => '/purchase-orders'})}, preventDefault:() => prevented++, stopImmediatePropagation() {}});
        assert.equal(events.at(-1).detail.id, 'plan-unsaved');
        globalThis.fetch = async () => {throw new Error('offline');};
        await state.saveAndLeave();
        assert.deepEqual(navigation, []);
        assert.equal(state.dirty, true);
        assert.equal(state.version, 0);
        assert.match(state.draftStatus, /belum tersimpan/);
        globalThis.fetch = async () => response(1);
        await state.saveAndLeave();
        assert.deepEqual(navigation, ['/purchase-orders']);
        assert.equal(state.dirty, false);
        state.warnBeforeUnload(unload);
        assert.equal(prevented, 2);
    });
});

test('saved unfinished drafts still alert on refresh or close and confirm navigation without saving again', async () => {
    await withDraftBrowser(async ({state, response, events, navigation}) => {
        let warnings = 0;
        const unload = {preventDefault:() => warnings++};
        state.name = '';
        state.savedFingerprint = state.fingerprint();
        state.warnBeforeUnload(unload);
        assert.equal(warnings, 0);
        state.add();
        globalThis.fetch = async () => response(1);
        await state.save('draft', true);
        assert.equal(state.dirty, false);
        state.warnBeforeUnload(unload);
        assert.equal(warnings, 1);
        assert.equal(unload.returnValue, '');
        state.guardLink({button:0, target:{closest:() => ({href:'/dashboard', target:'', hasAttribute:() => false, getAttribute:() => '/dashboard'})}, preventDefault() {}, stopImmediatePropagation() {}});
        assert.equal(events.at(-1).detail.id, 'plan-unsaved');
        assert.deepEqual(navigation, []);
        globalThis.fetch = async () => {throw new Error('Already saved drafts must not need another request');};
        await state.saveAndLeave();
        assert.deepEqual(navigation, ['/dashboard']);
        state.warnBeforeUnload(unload);
        assert.equal(warnings, 1);
    });
});

test('version conflicts retain local changes and finalization uses the last saved version', async () => {
    await withDraftBrowser(async ({state, navigation, response}) => {
        state.add(); state.select(state.rows[0], {product_name:'Oli'});
        state.rows[0].quantity = 10;
        state.rows[0].allocations = [{distributor_id:1, quantity:10}];
        globalThis.fetch = async () => ({ok:false, json:async () => ({errors:{version:['Rencana telah berubah. Muat ulang halaman sebelum menyimpan.']}})});
        assert.equal(await state.save('draft', true), false);
        assert.equal(state.dirty, true);
        assert.match(state.draftStatus, /Rencana telah berubah/);
        assert.equal(state.version, 0);
        globalThis.fetch = async () => response(1);
        await state.save('draft', true);
        globalThis.fetch = async (_url, options) => {
            const body = JSON.parse(options.body);
            assert.equal(body.version, 1);
            assert.equal(body.intent, 'finalize');
            return response(2);
        };
        assert.equal(await state.save('finalize'), true);
        assert.deepEqual(navigation, ['/plans/7']);
        assert.equal(state.leaving, true);
    });
});
