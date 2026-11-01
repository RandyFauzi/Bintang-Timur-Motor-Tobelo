import { test } from 'node:test';
import assert from 'node:assert/strict';
import { procurementSelection } from '../../resources/js/components/procurement-selection.js';

function row(type,id,version=1) {return {dataset:{procurementKey:`${type}:${id}`, procurementType:type, procurementId:String(id), procurementVersion:version, procurementSnapshot:'a'.repeat(64)}};}

test('selection distinguishes draft IDs from order IDs and preserves their snapshots', () => {
    const state = procurementSelection();
    const rows = [row('draft',1), row('order',1,3)];
    state.$el = {querySelectorAll:()=>rows};
    state.init();
    state.toggle('draft:1',1,true);
    assert.equal(state.allSelected,false);
    state.toggle('order:1',3,true);
    assert.equal(state.allSelected,true);
    assert.equal(state.selected.length,2);
    assert.equal(state.selected[1].type,'order');
    assert.equal(state.selected[1].snapshot,'a'.repeat(64));
    assert.equal(state.selected[1].recordId,'1');
    rows.push(row('draft',2));
    state.refreshRows();
    assert.equal(state.allSelected,false);
    assert.equal(state.selected.length,2);
    state.toggleAll(true);
    assert.equal(state.selected.length,3);
    assert.equal(state.allSelected,true);
});

test('flashed server selections restore stable composite keys and invalid data is ignored', () => {
    const state = procurementSelection([{type:'draft',id:1,version:2,snapshot:'a'.repeat(64)}, null, {type:'bad',id:4}]);
    assert.equal(state.selected.length,1);
    assert.equal(state.has('draft:1'),true);
    assert.deepEqual(procurementSelection('bad').selected,[]);
});
