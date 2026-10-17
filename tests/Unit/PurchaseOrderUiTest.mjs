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
