import { invoiceSelection } from './invoice-selection.js';

export function procurementSelection(initial = []) {
    const base = invoiceSelection([], 'pengadaan');
    return {
        ...base,
        get allSelected() { return this.rows.length > 0 && this.rows.slice(0,100).every(row => this.has(row.id)); },
        deletion: {url:'', reference:'', version:0},
        draftDeletion: {url:'', name:'', version:0},
        selected: Array.isArray(initial) ? initial.filter(row => ['draft','order'].includes(row?.type) && Number(row.id) > 0 && Number(row.version) > 0 && typeof row.snapshot === 'string').slice(0,100).map(row => ({...row, recordId:String(row.id), id:row.type+':'+row.id})) : [],
        refreshRows() {
            this.rows = Array.from(this.$el.querySelectorAll('[data-procurement-key]')).map(row => ({id:row.dataset.procurementKey, recordId:row.dataset.procurementId, type:row.dataset.procurementType, version:Number(row.dataset.procurementVersion), snapshot:row.dataset.procurementSnapshot}));
        },
        toggle(id, version, checked) {
            base.toggle.call(this, id, version, checked);
            if (checked && this.has(id)) Object.assign(this.selected.find(row => row.id === String(id)), this.rows.find(row => row.id === String(id)));
        },
    };
}
