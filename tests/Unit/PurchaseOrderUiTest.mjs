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
