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

export function notificationDropdown(initialItems = [], totalCount = 0) {
    return {
        open: false,
        items: initialItems,
        total: totalCount,
        readKeys: [],
        get unreadCount() {
            return this.items.filter((item) => !this.readKeys.includes(item.key)).length;
        },
        init() {
            try {
                const stored = localStorage.getItem('read_notification_keys');
                this.readKeys = stored ? JSON.parse(stored) : [];
            } catch {
                this.readKeys = [];
            }
        },
        isUnread(key) {
            return !this.readKeys.includes(key);
        },
        markAsRead(key) {
            if (!this.readKeys.includes(key)) {
                this.readKeys.push(key);
                this.persistReadKeys();
            }
        },
        markAllAsRead() {
            this.items.forEach((item) => {
                if (!this.readKeys.includes(item.key)) {
                    this.readKeys.push(item.key);
                }
            });
            this.persistReadKeys();
        },
        persistReadKeys() {
            try {
                if (this.readKeys.length > 200) {
                    this.readKeys = this.readKeys.slice(-200);
                }
                localStorage.setItem('read_notification_keys', JSON.stringify(this.readKeys));
            } catch {}
        },
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
    };
}
