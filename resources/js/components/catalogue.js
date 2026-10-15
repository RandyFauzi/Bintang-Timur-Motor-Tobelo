export function appDialog(initiallyOpen = false) {
    return {
        submitting: false,
        init() { if (initiallyOpen) this.$nextTick(() => this.open()); },
        open() {
            this.submitting = false;
            if (!this.$el.open) this.$el.showModal();
            this.$nextTick(() => {
                const field = this.$el.querySelector('[aria-invalid="true"]')
                    ?? this.$el.querySelector('input:not([type="hidden"]), select, textarea');
                field?.focus();
            });
        },
        backdrop(event) {
            if (event.target !== this.$el) return;
            const bounds = this.$el.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) this.$el.close();
        },
    };
}

export function multiSelect(options, selected, label, submitOnApply = true) {
    return {
        options, selected, draft: [...selected], label, open: false, search: '',
        get filtered() {
            const search = this.search.trim().toLocaleLowerCase('id');
            return this.options.filter((item) => item.name.toLocaleLowerCase('id').includes(search));
        },
        get summary() {
            const names = this.options.filter((item) => this.selected.includes(item.id)).map((item) => item.name);
            if (!names.length) return this.label;
            return names.slice(0, 2).join(', ') + (names.length > 2 ? ', +' + (names.length - 2) : '');
        },
        get allSelected() { return this.filtered.length > 0 && this.filtered.every((item) => this.draft.includes(item.id)); },
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.draft = [...this.selected];
                this.search = '';
                this.$nextTick(() => this.$refs.search.focus());
            }
        },
        selectAll(checked) {
            const ids = this.filtered.map((item) => item.id);
            this.draft = checked ? [...new Set([...this.draft, ...ids])] : this.draft.filter((id) => !ids.includes(id));
        },
        apply() {
            this.selected = [...this.draft];
            this.open = false;
            this.$nextTick(() => {
                if (submitOnApply) this.$el.closest('form').requestSubmit();
                else this.$refs.trigger.focus();
            });
        },
    };
}

export function observationForm(initial, types, workshops, endpoints) {
    return {
        form: { ...initial, items: Object.values(initial.items ?? {}).map((item) => ({ work_type_id: String(item.work_type_id), quantity: Number(item.quantity) })) },
        types, workshops, endpoints, lookupKind: 'workshops', newNames: [''], lookupBusy: false, lookupError: '',
        get total() { return this.form.items.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0); },
        typeName(id) { return this.types.find((type) => String(type.id) === String(id))?.name ?? ''; },
        hasType(id) { return this.form.items.some((item) => item.work_type_id === String(id)); },
        toggleType(id, checked) {
            const value = String(id);
            if (checked && !this.hasType(value)) this.form.items.push({ work_type_id: value, quantity: 1 });
            if (!checked) this.form.items = this.form.items.filter((item) => item.work_type_id !== value);
        },
        openLookup(kind) {
            this.lookupKind = kind;
            this.newNames = [''];
            this.lookupError = '';
            this.$dispatch('open-dialog', { id: 'report-lookup' });
        },
        async saveLookup() {
            if (this.lookupBusy) return;
            this.lookupBusy = true;
            this.lookupError = '';
            try {
                const response = await fetch(this.endpoints[this.lookupKind], {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ names: this.newNames.map((name) => name.trim()) }),
                });
                const result = await response.json();
                if (!response.ok) {
                    this.lookupError = Object.values(result.errors ?? {}).flat().join(' ') || result.message || 'Data belum dapat disimpan.';
                    return;
                }
                if (this.lookupKind === 'workshops') {
                    this.workshops.push(...result.data);
                    this.form.workshop_id = String(result.data[0].id);
                } else {
                    this.types.push(...result.data);
                    for (const type of result.data) this.toggleType(type.id, true);
                }
                document.getElementById('report-lookup').close();
                this.$dispatch('notify', { message: result.message, type: 'success' });
            } catch {
                this.lookupError = 'Koneksi terputus. Coba simpan kembali.';
            } finally { this.lookupBusy = false; }
        },
    };
}
