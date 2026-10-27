import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mobileInfiniteScroll } from '../../resources/js/components/infinite-scroll.js';

test('mobileInfiniteScroll initializes with mobile detection and config', () => {
    const saved = { window: globalThis.window };
    try {
        globalThis.window = { innerWidth: 375, addEventListener: () => {} };
        const scroller = mobileInfiniteScroll({
            nextPageUrl: '/products?page=2',
            total: 30,
            perPage: 10,
        });

        assert.equal(scroller.nextPageUrl, '/products?page=2');
        assert.equal(scroller.total, 30);
        assert.equal(scroller.perPage, 10);
        assert.equal(scroller.hasMore, true);
        assert.equal(scroller.loading, false);

        scroller.checkMobile();
        assert.equal(scroller.isMobile, true);

        globalThis.window.innerWidth = 1024;
        scroller.checkMobile();
        assert.equal(scroller.isMobile, false);
    } finally {
        Object.assign(globalThis, saved);
    }
});

test('mobileInfiniteScroll findContainer locates card grids and table bodies', () => {
    const mockRoot = {
        querySelector(selector) {
            if (selector.includes('catalogue-card-grid')) {
                return { isCardGrid: true };
            }
            if (selector.includes('catalogue-table tbody')) {
                return { isTableBody: true };
            }
            return null;
        },
    };

    const scroller = mobileInfiniteScroll();
    const result = scroller.findContainer(mockRoot);
    assert.ok(result);
    assert.equal(result.container.isCardGrid, true);
    assert.ok(result.itemSelector.includes('.product-card'));
});

test('mobileInfiniteScroll loadMore loads next page and updates state', async () => {
    const saved = { window: globalThis.window, document: globalThis.document, fetch: globalThis.fetch, DOMParser: globalThis.DOMParser };
    try {
        globalThis.window = { innerWidth: 400, addEventListener: () => {}, Alpine: { initTree: () => {} } };

        const appended = [];
        const mockContainer = {
            appendChild: (node) => appended.push(node),
        };

        const docNextContainer = {
            querySelectorAll: () => [{ id: 'item-2', cloneNode: () => ({ id: 'item-2' }) }],
        };

        const mockDoc = {
            querySelector: (sel) => {
                if (sel.includes('catalogue-card-grid')) return docNextContainer;
                if (sel.includes('pagination-links')) return { getAttribute: () => '/products?page=3' };
                return null;
            },
        };

        globalThis.DOMParser = class {
            parseFromString() {
                return mockDoc;
            }
        };

        globalThis.fetch = async () => ({
            ok: true,
            text: async () => '<html>...</html>',
        });

        globalThis.document = {
            body: {
                querySelector: (sel) => (sel.includes('catalogue-card-grid') ? mockContainer : null),
            },
            importNode: (node) => node,
        };

        const scroller = mobileInfiniteScroll({
            nextPageUrl: '/products?page=2',
            total: 25,
            perPage: 10,
        });
        scroller.checkMobile();
        scroller.$el = { closest: () => null };

        assert.equal(scroller.hasMore, true);
        await scroller.loadMore();

        assert.equal(appended.length, 1);
        assert.equal(scroller.nextPageUrl, '/products?page=3');
        assert.equal(scroller.hasMore, true);
        assert.equal(scroller.loading, false);
    } finally {
        Object.assign(globalThis, saved);
    }
});
