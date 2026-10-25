import {test} from 'node:test';
import assert from 'node:assert/strict';
import {selectOptions, chooseSelectOption} from '../../resources/js/components/select-picker.js';

class Select extends EventTarget {
    constructor(options) {
        super();
        this.options = options.map(([value, label, disabled = false, groupDisabled = false]) => ({
            value, label, textContent: label, disabled, selected: false,
            closest: () => groupDisabled ? {disabled: true} : null,
        }));
        this.ownerDocument = {defaultView: {Event}};
        this.disabled = false;
        this.selectedIndex = 0;
    }
    get selectedIndex() { return this.index; }
    set selectedIndex(index) {
        this.index = index;
        this.options.forEach((option, position) => { option.selected = position === index; });
    }
    get value() { return this.options[this.selectedIndex]?.value ?? ''; }
    matches() { return this.disabled; }
}

test('search matches option labels without losing original select indices or disabled groups', () => {
    const select = new Select([['', 'Semua'], ['1', 'Surya Motor'], ['2', 'Borneo Motor', false, true], ['3', 'Manado Motor', true]]);

    assert.deepEqual(selectOptions(select, ' MOTOR ').map(option => [option.index, option.value, option.disabled]), [[1, '1', false], [2, '2', true], [3, '3', true]]);
    assert.deepEqual(selectOptions(select, 'surya').map(option => option.label), ['Surya Motor']);
    assert.equal(selectOptions(select, 'Tidak Ada').length, 0);
    assert.equal(selectOptions(select)[0].selected, true);
});

test('choosing an option updates the original value and invokes existing form handlers exactly once', () => {
    const select = new Select([['', 'Semua'], ['1', 'Admin'], ['2', 'Kasir']]);
    const events = [];
    let submittedValue = null;
    select.addEventListener('input', () => events.push('input'));
    select.addEventListener('change', event => { events.push('change'); submittedValue = event.target.value; });

    assert.equal(chooseSelectOption(select, 2), true);
    assert.equal(select.value, '2');
    assert.equal(submittedValue, '2');
    assert.deepEqual(events, ['input', 'change']);
    assert.equal(chooseSelectOption(select, 2), true);
    assert.deepEqual(events, ['input', 'change']);
    assert.equal(chooseSelectOption(select, 0), true);
    assert.equal(submittedValue, '');
});

test('disabled options, disabled groups and invalid indices cannot change submitted values', () => {
    const select = new Select([['1', 'Aktif'], ['2', 'Nonaktif', true], ['3', 'Arsip', false, true]]);
    let changed = 0;
    select.addEventListener('change', () => changed++);
    for (const index of [-1, 1, 2, 999]) assert.equal(chooseSelectOption(select, index), false);
    select.disabled = true;
    assert.equal(chooseSelectOption(select, 0), false);
    assert.equal(select.value, '1');
    assert.equal(changed, 0);
});

test('options added dynamically are searchable and update existing boolean model handlers', () => {
    const select = new Select([['0', 'Belum Terbit']]);
    let issued = false;
    select.addEventListener('change', event => { issued = event.target.value === '1'; });
    select.options.push({value: '1', label: 'Sudah Terbit', textContent: 'Sudah Terbit', disabled: false, selected: false, closest: () => null});

    const option = selectOptions(select, 'sudah')[0];
    chooseSelectOption(select, option.index);
    assert.equal(issued, true);
    assert.equal(select.value, '1');
});
