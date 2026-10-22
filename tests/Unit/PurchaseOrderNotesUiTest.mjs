import {test} from 'node:test';
import assert from 'node:assert/strict';
import {purchaseOrderNotes} from '../../resources/js/components/purchase-order-notes.js';

const order = (id = 1, quantity = 5) => ({id,reference:'PO-'+id,distributor_id:id,distributor_name:id === 1 ? 'WMS' : 'WIN',origin_port:'Surabaya',version:2,note_issued:true,supplier_note_number:'INV-'+id,supplier_note_on:'2026-10-21',supplier_note_total:'60000.00',finalized:false,can_edit:true,shipment_url:null,shipment_status:null,invoice_url:'/bills/'+id,save_url:'/orders/'+id+'/note',finalize_url:'/orders/'+id+'/finalize',items:[{id:id*10,product_name:'Yamalube 02 Oil',color:'Gold',source_index:0,quantity:15,invoice_quantity:quantity}]});
const state = (orders=[order()]) => purchaseOrderNotes(orders, [{id:1,name:'Cargo Reguler',duration_days:14}], {today:'2026-10-21',planId:4,replanUrl:'/purchase-orders/create'});

test('shortages aggregate suppliers and offset excess instead of ordering the same requirement twice', () => {
    const model=state([order(1,5),order(2,2)]);
    assert.equal(model.shortages.length,1);
    assert.equal(model.shortages[0].quantity,23);
    model.orders[0].saved.items[0].invoice_quantity=20;
    assert.equal(model.shortages[0].quantity,8);
    model.orders[1].saved.items[0].invoice_quantity=15;
    assert.equal(model.shortages.length,0);
});

test('unsaved edits do not change shortages and cancelling restores all saved note fields', () => {
    const model=state();
    const row=model.orders[0];
    model.edit(row);
    row.items[0].invoice_quantity=15;
    row.supplier_note_number='WRONG';
    assert.equal(model.dirty(row),true);
    assert.equal(model.shortages[0].quantity,10);
    model.cancel(row);
    assert.equal(row.items[0].invoice_quantity,5);
    assert.equal(row.supplier_note_number,'INV-1');
    assert.equal(row.editing,false);
    assert.equal(model.dirty(row),false);
});

test('filters use saved issuance and shipment state and include product names', () => {
    const first=order(1,5);
    const second={...order(2),note_issued:false,supplier_note_number:'',supplier_note_on:'',items:[{...order(2).items[0],invoice_quantity:null}]};
    const model=state([first,second]);
    model.noteStatus='unissued';
    assert.deepEqual(model.filteredOrders.map(row=>row.id),[2]);
    model.noteStatus='issued';
    assert.deepEqual(model.filteredOrders.map(row=>row.id),[1]);
    model.orders[0].finalized=true;
    model.noteStatus='shipping';
    assert.deepEqual(model.filteredOrders.map(row=>row.id),[1]);
    model.noteStatus=''; model.search='yamalube';
    assert.equal(model.filteredOrders.length,2);
    model.distributorId='2';
    assert.deepEqual(model.filteredOrders.map(row=>row.id),[2]);
});

test('invoice validation distinguishes an explicit zero from a missing quantity and locks final notes', () => {
    const model=state();
    const row=model.orders[0];
    row.items[0].invoice_quantity=0;
    assert.deepEqual(model.validate(row),[]);
    row.items[0].invoice_quantity='';
    assert.equal(model.validate(row).length,1);
    row.items[0].invoice_quantity=-1;
    assert.equal(model.validate(row).length,1);
    row.items[0].invoice_quantity=2.5;
    assert.equal(model.validate(row).length,1);
    row.items[0].invoice_quantity=5;
    assert.equal(model.canFinalize(row),true);
    row.editing=true;
    assert.equal(model.canFinalize(row),false);
    row.editing=false; row.finalized=true; row.can_edit=false;
    model.edit(row);
    assert.equal(row.editing,false);
    assert.equal(model.canFinalize(row),false);
    row.shipment_status='delivered';
    assert.equal(model.badge(row),'Sudah Sampai');
});

test('failed saves retain edited input and successful saves replace snapshots and server versions', async () => {
    const model=state();
    const row=model.orders[0]; model.edit(row); row.items[0].invoice_quantity=8;
    model.request=async()=>{throw {errors:['Nota sudah berubah.']};};
    await model.save(row);
    assert.equal(row.items[0].invoice_quantity,8);
    assert.equal(row.saved.items[0].invoice_quantity,5);
    assert.equal(row.editing,true);
    assert.equal(row.saving,false);
    model.request=async()=>({order:{...order(1,8),version:3},message:'Tersimpan'});
    await model.save(row);
    assert.equal(row.version,3);
    assert.equal(row.saved.items[0].invoice_quantity,8);
    assert.equal(row.editing,false);
    assert.equal(model.shortages[0].quantity,7);
});

test('finalization creates cargo using the selected catalogue service and keeps the dialog open on failure', async () => {
    const model=state(); const events=[]; model.$dispatch=(name)=>events.push(name);
    model.openFinalize(model.orders[0]);
    assert.equal(model.shipment.origin_port,'Surabaya');
    assert.equal(model.shipment.shipping_service_id,'');
    model.shipment.shipping_service_id='1';
    model.request=async()=>{throw {errors:['Cargo tidak aktif.']};};
    await model.finalize();
    assert.equal(model.orders[0].finalized,false);
    assert.deepEqual(model.finalizeErrors,['Cargo tidak aktif.']);
    assert.equal(model.finalizing,false);
    let sent;
    model.request=async(url,method,payload)=>{sent={url,method,payload}; return {order:{...order(),finalized:true,can_edit:false,shipment_url:'/shipments?shipment=9',shipment_status:'in_transit'},message:'Cargo dibuat'};};
    await model.finalize();
    assert.equal(sent.payload.shipping_service_id,'1');
    assert.equal(sent.payload.version,2);
    assert.equal(Object.hasOwn(sent.payload,'package_count'),false);
    assert.equal(model.orders[0].shipment_url,'/shipments?shipment=9');
    assert.equal(model.badge(model.orders[0]),'Dalam Pengiriman');
    assert.equal(events.at(-1),'close-plan-shipment');
});

test('shipping preview uses saved invoice quantities for only the selected distributor and excludes empty items', () => {
    const model=state([order(1,5),order(2,2)]); model.$dispatch=()=>{};
    const first=model.orders[0], second=model.orders[1];
    first.saved.items.push({id:99,product_name:'Barang kosong',quantity:10,invoice_quantity:0});
    first.items[0].invoice_quantity=900;
    model.openFinalize(first);
    assert.equal(model.selectedOrder.distributor_name,'WMS');
    assert.equal(model.shipmentItems.length,1);
    assert.equal(model.shipmentUnits,5);
    assert.equal(model.shipmentItems[0].invoice_quantity,5);
    model.openFinalize(second);
    assert.equal(model.selectedOrder.distributor_name,'WIN');
    assert.equal(model.shipmentUnits,2);
    assert.equal(model.shipmentItems[0].id,20);
});

test('reorder URL sends only selected source identities and server calculates missing quantities', () => {
    const saved=globalThis.window;
    try {
        let destination;
        globalThis.window={location:{origin:'https://example.test',assign:url=>destination=url}};
        const model=state([order(1,5),order(2,2)]);
        model.selected=['0','999'];
        model.createReplan();
        const url=new URL(destination);
        assert.equal(url.searchParams.get('replan'),'4');
        assert.deepEqual(url.searchParams.getAll('source_items[]'),['0']);
        assert.equal(url.searchParams.has('quantity'),false);
    } finally {globalThis.window=saved;}
});

test('entered note total is required, validated independently, saved exactly and restored on cancel', async () => {
    const model=state(); const row=model.orders[0];
    model.edit(row);
    for (const value of ['', '-1', '1.001', '1e4', '90000000000001']) {
        row.supplier_note_total=value;
        assert.equal(model.validate(row).length,1);
    }
    row.supplier_note_total='0.00';
    assert.deepEqual(model.validate(row),[]);
    row.supplier_note_total='62500.75';
    let sent;
    model.request=async(url,method,payload)=>{sent=payload; return {order:{...order(),version:3,supplier_note_total:'62500.75'},message:'Tersimpan'};};
    await model.save(row);
    assert.equal(sent.supplier_note_total,'62500.75');
    assert.equal(row.saved.supplier_note_total,'62500.75');
    model.edit(row); row.supplier_note_total='90000';
    assert.equal(model.dirty(row),true);
    model.cancel(row);
    assert.equal(row.supplier_note_total,'62500.75');
    row.note_issued=false; model.toggleIssued(row);
    assert.equal(row.supplier_note_total,'');
});
