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

test('order editor prevents duplicate products and recalculates totals after edits and removal', () => {
    const state = purchaseOrderForm();
    state.add({id:1, name:'Kampas', sku:'K-1', purchase_price:'123.45'});
    state.rows[0].quantity = '3';
    assert.equal(state.total, 370.35);
    state.add({id:1, name:'Kampas', sku:'K-1', purchase_price:'123.45'});
    assert.equal(state.rows.length, 1);
    assert.equal(state.error, 'Barang ini sudah ada di pesanan.');
    state.add({id:2, name:'Oli', sku:'O-2', purchase_price:'10000'});
    assert.equal(state.total, 10370.35);
    state.rows.splice(0,1);
    assert.equal(state.total, 10000);
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
