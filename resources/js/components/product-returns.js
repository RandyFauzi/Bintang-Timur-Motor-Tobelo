export function returnSelection(initial = []) {
    return {
        selected: Object.values(initial && typeof initial === 'object' ? initial : {})
            .filter((row) => row && typeof row.id === 'string' && Number.isInteger(Number(row.version)) && Number(row.version) > 0)
            .map((row) => ({ id: row.id, version: Number(row.version) })),
        has(id) { return this.selected.some((row) => row.id === String(id)); },
        toggle(id, version, checked) {
            this.selected = this.selected.filter((row) => row.id !== String(id));
            if (checked) this.selected.push({ id: String(id), version: Number(version) });
        },
        togglePage(rows, checked) {
            for (const row of rows) this.toggle(row.id, row.version, checked);
        },
        pageSelected(rows) { return rows.length > 0 && rows.every((row) => this.has(row.id)); },
    };
}

export function productPicker(endpoint, initial = null) {
    return {
        endpoint, search: initial?.name ?? '', open: false, products: initial ? [initial] : [], selected: initial ? String(initial.id) : '',
        busy: false, error: '', controller: null, sequence: 0,
        get choice() { return this.products.find((product) => String(product.id) === String(this.selected)); },
        select(product) {
            this.controller?.abort();
            this.sequence++;
            this.busy = false;
            this.selected = String(product.id);
            this.search = product.name;
            this.open = false;
        },
        onSearchInput() {
            this.controller?.abort();
            this.sequence++;
            this.selected = '';
            this.products = [];
            this.open = this.search.trim().length > 0;
            this.busy = this.open;
            this.error = '';
        },
        description(product) {
            return [product.sku, product.brand, product.type, product.kind, product.color].filter(Boolean).join(' · ');
        },
        async load() {
            this.controller?.abort();
            const sequence = ++this.sequence;
            if (!this.search.trim() || (this.choice && this.search === this.choice.name)) {
                this.busy = false;
                this.open = false;
                return;
            }
            this.controller = new AbortController();
            this.busy = true;
            this.error = '';
            try {
                const url = new URL(this.endpoint, window.location.origin);
                url.searchParams.set('search', this.search.trim());
                const response = await fetch(url, { signal: this.controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('lookup');
                const result = await response.json();
                if (sequence !== this.sequence) return;
                const chosen = this.choice;
                this.products = result.data;
                if (chosen && !this.products.some((product) => String(product.id) === String(chosen.id))) this.products.unshift(chosen);
            } catch (error) {
                if (error.name !== 'AbortError' && sequence === this.sequence) this.error = 'Produk belum dapat dimuat. Coba cari kembali.';
            } finally {
                if (sequence === this.sequence) this.busy = false;
            }
        },
        destroy() { this.controller?.abort(); },
    };
}
