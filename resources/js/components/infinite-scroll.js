export function mobileInfiniteScroll(config = {}) {
    return {
        nextPageUrl: config.nextPageUrl || null,
        total: Number(config.total) || 0,
        perPage: Number(config.perPage) || 15,
        loaded: Number(config.loaded ?? config.perPage ?? 15),
        loading: false,
        hasMore: Boolean(config.nextPageUrl),
        isMobile: false,
        desktop: config.desktop ?? true,
        error: '',
        observer: null,
        scrollHandler: null,

        init() {
            this.listRoot = this.$el.closest('section') || this.$el.closest('.catalogue-surface') || document.body;
            this.checkMobile();
            this.resizeHandler = () => {
                this.checkMobile();
            };
            window.addEventListener('resize', this.resizeHandler);

            if (this.hasMore) {
                this.setupObserver();
            }
        },

        checkMobile() {
            this.isMobile = typeof window !== 'undefined' && (
                window.innerWidth <= 767 ||
                (typeof window.matchMedia === 'function' && window.matchMedia('(max-width: 767.98px)').matches)
            );
        },

        findContainer(root) {
            if (!root) return null;

            // 1. Grid / Card / Feed container
            const grid = root.matches?.('.catalogue-card-grid, .notification-list, .observation-list, .distributor-list')
                ? root
                : root.querySelector?.('.catalogue-card-grid, .notification-list, .observation-list, .distributor-list');

            if (grid) {
                return {
                    container: grid,
                    selector: '.catalogue-card-grid, .notification-list, .observation-list, .distributor-list',
                    itemSelector: '.product-card, .notification-item, .distributor-card, .observation-card, article',
                };
            }

            // 2. Table tbody container
            const tbody = root.matches?.('tbody')
                ? root
                : root.querySelector?.('.catalogue-table tbody, .procurement-table tbody, .return-table tbody, table tbody');

            if (tbody) {
                return {
                    container: tbody,
                    selector: '.catalogue-table tbody, .procurement-table tbody, .return-table tbody, table tbody',
                    itemSelector: 'tr:not(.empty-state-row):not(:has(.empty-state))',
                };
            }

            return null;
        },

        setupObserver() {
            if (typeof window === 'undefined') return;

            // 1. IntersectionObserver
            if (typeof IntersectionObserver !== 'undefined') {
                const attach = () => {
                    const sentinel = this.$refs?.sentinel;
                    if (!sentinel) return;

                    if (this.observer) {
                        this.observer.disconnect();
                    }

                    this.observer = new IntersectionObserver((entries) => {
                        const entry = entries[0];
                        if (entry && entry.isIntersecting && this.hasMore && !this.loading && !this.error && (this.isMobile || this.desktop)) {
                            this.loadMore();
                        }
                    }, {
                        rootMargin: '400px 0px',
                        threshold: 0,
                    });

                    this.observer.observe(sentinel);
                };

                if (this.$nextTick) {
                    this.$nextTick(attach);
                } else {
                    attach();
                }
            }

            // 2. Scroll listener fallback for mobile
            if (!this.scrollHandler) {
                this.scrollHandler = () => {
                    if (!this.hasMore || this.loading || this.error || (!this.isMobile && !this.desktop)) return;
                    const sentinel = this.$refs?.sentinel;
                    const bounds = sentinel?.getBoundingClientRect();
                    if (bounds && bounds.top <= window.innerHeight + 400 && bounds.bottom >= 0) {
                        this.loadMore();
                    }
                };
                window.addEventListener('scroll', this.scrollHandler, { passive: true, capture: true });
                this.$nextTick?.(() => this.scrollHandler?.());
            }
        },

        async loadMore() {
            if (this.loading || !this.hasMore || !this.nextPageUrl || (!this.isMobile && !this.desktop)) return;

            this.loading = true;
            this.error = '';
            this.requestController = new AbortController();

            try {
                let requestUrl = this.nextPageUrl;
                if (window.location?.href) {
                    const next = new URL(requestUrl, window.location.href);
                    if (next.host !== window.location.host) throw new Error('Alamat halaman tidak valid.');
                    requestUrl = next.pathname + next.search;
                }
                const res = await fetch(requestUrl, {
                    signal: this.requestController.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!res.ok) {
                    throw new Error('Gagal memuat data');
                }

                const html = await res.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                const currentList = this.findContainer(this.listRoot || document.body);
                const nextList = this.findContainer(doc);

                if (!currentList || !nextList) throw new Error('Daftar data tidak ditemukan.');
                if (currentList && nextList) {
                    const newItems = nextList.container.querySelectorAll(currentList.itemSelector);
                    let appendedCount = 0;
                    newItems.forEach((node) => {
                        if (node.dataset?.procurementKey && currentList.container.querySelector(`[data-procurement-key="${node.dataset.procurementKey}"]`)) return;
                        if (node.dataset?.transactionId && currentList.container.querySelector(`[data-transaction-id="${node.dataset.transactionId}"]`)) return;
                        if (node.dataset?.productId && currentList.container.querySelector(`[data-product-id="${node.dataset.productId}"]`)) return;
                        if (node.dataset?.invoiceId && currentList.container.querySelector(`[data-invoice-id="${node.dataset.invoiceId}"]`)) return;
                        const imported = document.importNode ? document.importNode(node, true) : node.cloneNode(true);
                        currentList.container.appendChild(imported);
                        appendedCount++;
                    });

                    this.loaded += appendedCount;
                    if (this.$dispatch) {
                        this.$dispatch('infinite-scroll-loaded', { count: appendedCount });
                    }
                }

                const nextLink = doc.querySelector('.pagination-links a[aria-label="Halaman berikutnya"]')
                    || doc.querySelector('.pagination-links a[rel="next"]');
                this.nextPageUrl = nextLink ? nextLink.getAttribute('href') : null;
                this.hasMore = Boolean(this.nextPageUrl);

                if (!this.hasMore) {
                    if (this.observer) {
                        this.observer.disconnect();
                        this.observer = null;
                    }
                    if (this.scrollHandler) {
                        window.removeEventListener('scroll', this.scrollHandler, true);
                        this.scrollHandler = null;
                    }
                } else {
                    this.$nextTick?.(() => {
                        this.setupObserver();
                    });
                }
            } catch (err) {
                if (err.name !== 'AbortError') this.error = 'Gagal memuat data. Tekan Muat lebih banyak untuk mencoba lagi.';
            } finally {
                this.loading = false;
                if (this.hasMore && !this.error) this.$nextTick?.(() => this.scrollHandler?.());
            }
        },

        destroy() {
            this.requestController?.abort();
            window.removeEventListener('resize', this.resizeHandler);
            if (this.observer) {
                this.observer.disconnect();
                this.observer = null;
            }
            if (this.scrollHandler) {
                window.removeEventListener('scroll', this.scrollHandler, true);
                this.scrollHandler = null;
            }
        },
    };
}
