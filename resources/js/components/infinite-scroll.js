export function mobileInfiniteScroll(config = {}) {
    return {
        nextPageUrl: config.nextPageUrl || null,
        total: Number(config.total) || 0,
        perPage: Number(config.perPage) || 15,
        loading: false,
        hasMore: Boolean(config.nextPageUrl),
        isMobile: false,
        observer: null,

        init() {
            this.checkMobile();
            window.addEventListener('resize', () => {
                const was = this.isMobile;
                this.checkMobile();
                if (!was && this.isMobile && this.hasMore) {
                    this.setupObserver();
                }
            });

            if (this.isMobile && this.hasMore) {
                this.setupObserver();
            }
        },

        checkMobile() {
            this.isMobile = typeof window !== 'undefined' && (window.innerWidth <= 767);
        },

        findContainer(root) {
            if (!root) return null;

            // 1. Grid / Card / Feed container
            const grid = root.querySelector('.catalogue-card-grid, .notification-list, .observation-list, .distributor-list');
            if (grid) {
                return {
                    container: grid,
                    selector: '.catalogue-card-grid, .notification-list, .observation-list, .distributor-list',
                    itemSelector: '.product-card, .notification-item, .distributor-card, .observation-card, article',
                };
            }

            // 2. Table tbody container
            const tbody = root.querySelector('.catalogue-table tbody, .procurement-table tbody, .return-table tbody, table tbody');
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
            if (typeof window === 'undefined' || typeof IntersectionObserver === 'undefined') return;

            const attach = () => {
                const sentinel = this.$refs?.sentinel;
                if (!sentinel) return;

                if (this.observer) {
                    this.observer.disconnect();
                }

                this.observer = new IntersectionObserver((entries) => {
                    const entry = entries[0];
                    if (entry && entry.isIntersecting && this.isMobile && this.hasMore && !this.loading) {
                        this.loadMore();
                    }
                }, {
                    rootMargin: '250px',
                    threshold: 0.05,
                });

                this.observer.observe(sentinel);
            };

            if (this.$nextTick) {
                this.$nextTick(attach);
            } else {
                attach();
            }
        },

        async loadMore() {
            if (this.loading || !this.hasMore || !this.nextPageUrl || !this.isMobile) return;

            this.loading = true;

            try {
                const res = await fetch(this.nextPageUrl, {
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

                const scope = (this.$el && this.$el.closest)
                    ? (this.$el.closest('section') || this.$el.closest('.catalogue-surface') || document.body)
                    : document.body;

                const currentList = this.findContainer(scope);
                const nextList = this.findContainer(doc);

                if (currentList && nextList) {
                    const newItems = nextList.container.querySelectorAll(currentList.itemSelector);
                    newItems.forEach((node) => {
                        const imported = document.importNode ? document.importNode(node, true) : node.cloneNode(true);
                        currentList.container.appendChild(imported);
                        if (typeof window !== 'undefined' && window.Alpine) {
                            window.Alpine.initTree(imported);
                        }
                    });

                    if (this.$dispatch) {
                        this.$dispatch('infinite-scroll-loaded', { count: newItems.length });
                    }
                }

                const nextLink = doc.querySelector('.pagination-links a[aria-label="Halaman berikutnya"]')
                    || doc.querySelector('.pagination-links a[rel="next"]');
                this.nextPageUrl = nextLink ? nextLink.getAttribute('href') : null;
                this.hasMore = Boolean(this.nextPageUrl);

                if (!this.hasMore && this.observer) {
                    this.observer.disconnect();
                }
            } catch (err) {
                console.error('Mobile infinite scroll error:', err);
            } finally {
                this.loading = false;
            }
        },

        destroy() {
            if (this.observer) {
                this.observer.disconnect();
            }
        },
    };
}
