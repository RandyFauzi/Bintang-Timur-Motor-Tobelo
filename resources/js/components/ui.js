export function appShell() {
    return {
        collapsed: false,
        mobileMenuOpen: false,
        isMobile: window.innerWidth < 1024,
        media: null,
        mediaListener: null,
        init() {
            try { this.collapsed = localStorage.getItem('sidebarCollapsed') === 'true'; } catch {}
            this.media = window.matchMedia('(max-width: 1023px)');
            this.mediaListener = (event) => {
                this.isMobile = event.matches;
                this.mobileMenuOpen = false;
                document.body.style.overflow = '';
            };
            this.media.addEventListener('change', this.mediaListener);
            this.$watch('collapsed', (value) => {
                try { localStorage.setItem('sidebarCollapsed', String(value)); } catch {}
            });
        },
        toggleSidebar() {
            if (this.isMobile) this.closeMobileMenu();
            else this.collapsed = !this.collapsed;
        },
        openMobileMenu() {
            this.mobileMenuOpen = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => document.querySelector('#app-sidebar a')?.focus());
        },
        closeMobileMenu() {
            if (!this.mobileMenuOpen) return;
            this.mobileMenuOpen = false;
            document.body.style.overflow = '';
            this.$refs.menuTrigger?.focus();
        },
        trapMobileFocus(event) {
            if (!this.isMobile || !this.mobileMenuOpen) return;
            const focusable = [...event.currentTarget.querySelectorAll('a[href], button:not([disabled])')]
                .filter((element) => element.getClientRects().length > 0);
            const first = focusable[0];
            const last = focusable.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault(); last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault(); first?.focus();
            }
        },
        destroy() { this.media?.removeEventListener('change', this.mediaListener); },
    };
}

export function dateRangeFilter() {
    return {
        open: false,
        submitting: false,
        close() { this.open = false; this.$refs.trigger.focus(); },
        preset(start, end) {
            this.$refs.start.value = start;
            this.$refs.end.value = end;
            this.$refs.form.requestSubmit();
        },
    };
}

export function stockNotifications(stockAlerts, shipmentOverview = { count: 0, alerts: [] }, endpoint = null) {
    return {
        stockAlerts, alerts: [...shipmentOverview.alerts, ...stockAlerts], shipmentCount: shipmentOverview.count, index: 0,
        ids: [], timer: null, controller: null, busy: false,
        get current() { return this.alerts[this.index] ?? { name: '', message: '', quantity: 0, url: '#' }; },
        next() { if (this.alerts.length) this.index = (this.index + 1) % this.alerts.length; },
        previous() { if (this.alerts.length) this.index = (this.index - 1 + this.alerts.length) % this.alerts.length; },
        init() {
            if (endpoint) this.timer = window.setInterval(() => this.refresh(), 60000);
        },
        watch(ids) { this.ids = ids; this.refresh(); },
        async refresh() {
            if (!endpoint || this.busy || document.hidden) return;
            this.busy = true;
            this.controller = new AbortController();
            try {
                const url = new URL(endpoint, window.location.origin);
                this.ids.forEach((id) => url.searchParams.append('ids[]', id));
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: this.controller.signal });
                if (!response.ok) return;
                const result = await response.json();
                this.shipmentCount = result.count;
                this.alerts = [...result.alerts, ...this.stockAlerts];
                this.index = this.alerts.length ? this.index % this.alerts.length : 0;
                window.dispatchEvent(new CustomEvent('shipment-updates', { detail: result }));
            } catch { /* Retain the last known alerts until the next successful refresh. */ }
            finally { this.busy = false; }
        },
        destroy() { window.clearInterval(this.timer); this.controller?.abort(); },
    };
}

export function toast() {
    return {
        visible: false, message: '', type: 'info', timer: null,
        show(detail) {
            this.pause();
            this.message = detail.message;
            this.type = detail.type ?? 'info';
            this.visible = true;
            this.schedule();
        },
        pause() { window.clearTimeout(this.timer); },
        schedule() { this.timer = window.setTimeout(() => { this.visible = false; }, 6500); },
        destroy() { this.pause(); },
    };
}
