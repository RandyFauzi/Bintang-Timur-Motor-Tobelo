import { test } from 'node:test';
import assert from 'node:assert/strict';
import { revenueChart } from '../../resources/js/components/revenue-chart.js';
import { dateRangeFilter } from '../../resources/js/components/ui.js';

test('an empty sales period keeps a nonzero axis and readable zero values', () => {
    const chart = revenueChart([{ date: '2026-10-01', label: '1 Okt', value: 0 }]);
    assert.ok(chart.maximum > 0);
    assert.equal(chart.compactCurrency(0), '0');
    assert.deepEqual(chart.dateTicks, [{ date: '2026-10-01', label: '1 Okt', value: 0, position: 50 }]);
});

test('a long period keeps both endpoints and limits axis labels', () => {
    const chart = revenueChart(Array.from({ length: 366 }, (_, index) => ({ date: String(index), label: String(index), value: index * 1000 })));
    assert.equal(chart.dateTicks.length, 6);
    assert.equal(chart.dateTicks[0].date, '0');
    assert.equal(chart.dateTicks.at(-1).date, '365');
    assert.ok(chart.maximum >= 365000);
});

test('keyboard selection stays within the available dates', () => {
    const chart = revenueChart([{ label: '1 Okt', value: 100 }, { label: '2 Okt', value: 200 }]);
    chart.select(-1);
    assert.equal(chart.activePoint.value, 100);
    chart.select(50);
    assert.equal(chart.activePoint.value, 200);
});

test('hover selects the date under the pointer relative to the canvas', () => {
    const context = {
        scale() {}, setLineDash() {}, beginPath() {}, moveTo() {}, lineTo() {}, stroke() {},
        closePath() {}, fill() {}, arc() {}, createLinearGradient() { return { addColorStop() {} }; },
    };
    const originalWindow = globalThis.window;
    const originalObserver = globalThis.ResizeObserver;
    globalThis.window = { devicePixelRatio: 1 };
    globalThis.ResizeObserver = class { observe() {} disconnect() {} };
    try {
        const chart = revenueChart([{ value: 100 }, { value: 200 }, { value: 300 }]);
        chart.$nextTick = (callback) => callback();
        chart.$refs = { canvas: {
            clientWidth: 300, clientHeight: 200, getContext: () => context,
            getBoundingClientRect: () => ({ left: 100, width: 300 }),
        } };
        chart.init();
        chart.hover({ clientX: 250 });
        assert.equal(chart.activeIndex, 1);
        chart.hover({ clientX: 500 });
        assert.equal(chart.activeIndex, 2);
        chart.destroy();
    } finally {
        globalThis.window = originalWindow;
        globalThis.ResizeObserver = originalObserver;
    }
});

test('a preset submits both dates through the same validated GET form', () => {
    const filter = dateRangeFilter();
    let submissions = 0;
    filter.$refs = { start: { value: '' }, end: { value: '' }, form: { requestSubmit() { submissions++; } } };
    filter.preset('2026-10-01', '2026-10-15');
    assert.equal(filter.$refs.start.value, '2026-10-01');
    assert.equal(filter.$refs.end.value, '2026-10-15');
    assert.equal(submissions, 1);
});
