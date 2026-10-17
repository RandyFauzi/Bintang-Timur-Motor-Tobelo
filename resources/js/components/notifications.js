export function notificationCenter(endpoint, counts, total) {
    return {
        counts, total, busy: false, timer: null, controller: null, lastHtml: null,
        init() { this.timer = window.setInterval(() => this.refresh(), 30000); },
        async refresh() {
            if (this.busy || document.hidden || this.$refs.feed.contains(document.activeElement)) return;
            this.busy = true;
            this.controller = new AbortController();
            try {
                const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: this.controller.signal });
                if (!response.ok) return;
                const result = await response.json();
                if (result.page_url) {
                    window.location.replace(result.page_url);
                    return;
                }
                if (result.html !== this.lastHtml) {
                    this.$refs.feed.innerHTML = result.html;
                    this.lastHtml = result.html;
                }
                this.counts = result.counts;
                this.total = result.total;
                window.dispatchEvent(new CustomEvent('notification-count', { detail: { count: result.total } }));
            } catch { /* Keep existing notifications if a refresh fails. */ }
            finally { this.busy = false; }
        },
        destroy() { window.clearInterval(this.timer); this.controller?.abort(); },
    };
}
