export function appDialog(initiallyOpen = false) {
    return {
        submitting: false,
        init() {
            this.viewport = globalThis.window?.visualViewport;
            this.viewportListener = () => { if (this.$el.open) this.positionSheet(); };
            this.viewport?.addEventListener('resize', this.viewportListener);
            this.viewport?.addEventListener('scroll', this.viewportListener);
            globalThis.window?.addEventListener('resize', this.viewportListener);
            if (initiallyOpen) this.$nextTick(() => this.open());
        },
        destroy() {
            this.viewport?.removeEventListener('resize', this.viewportListener);
            this.viewport?.removeEventListener('scroll', this.viewportListener);
            globalThis.window?.removeEventListener('resize', this.viewportListener);
        },
        positionSheet() {
            const view = globalThis.window?.visualViewport;
            const width = view?.width ?? globalThis.window?.innerWidth;
            const height = view?.height ?? globalThis.window?.innerHeight;
            if (!width || !height) return;
            this.$el.style.setProperty('--sheet-left', `${view?.offsetLeft ?? 0}px`);
            this.$el.style.setProperty('--sheet-width', `${width}px`);
            this.$el.style.setProperty('--sheet-height', `${Math.max(0, height - 12)}px`);
            this.$el.style.setProperty('--sheet-bottom', `${Math.max(0, (globalThis.window?.innerHeight ?? height) - (view?.offsetTop ?? 0) - height)}px`);
        },
        open() {
            this.submitting = false;
            this.positionSheet();
            if (!this.$el.open) this.$el.showModal();
            this.$el.scrollLeft = 0;
            this.$el.scrollTop = 0;
            this.$nextTick(() => {
                const field = this.$el.querySelector('[aria-invalid="true"]:not(:disabled)')
                    ?? (globalThis.window?.innerWidth <= 767 ? this.$el.querySelector('.dialog-close') : null)
                    ?? this.$el.querySelector('input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled)');
                field?.focus({ preventScroll: true });
                this.$el.scrollLeft = 0;
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
        options, selected, draft: [...selected], label, open: false, search: '', panelPosition: {},
        init() {
            this.viewport = globalThis.window?.visualViewport;
            this.viewportListener = () => { if (this.open) this.positionPanel(); };
            this.viewport?.addEventListener('resize', this.viewportListener);
            this.viewport?.addEventListener('scroll', this.viewportListener);
            globalThis.window?.addEventListener('resize', this.viewportListener);
        },
        destroy() {
            this.viewport?.removeEventListener('resize', this.viewportListener);
            this.viewport?.removeEventListener('scroll', this.viewportListener);
            globalThis.window?.removeEventListener('resize', this.viewportListener);
        },
        positionPanel(viewport = globalThis.window?.visualViewport) {
            const width = viewport?.width ?? globalThis.window?.innerWidth;
            const height = viewport?.height ?? globalThis.window?.innerHeight;
            if (!width || !height) return;
            this.panelPosition = {
                '--filter-center-x': `${(viewport?.offsetLeft ?? 0) + width / 2}px`,
                '--filter-center-y': `${(viewport?.offsetTop ?? 0) + height / 2}px`,
                '--filter-max-height': `${Math.max(0, height - 32)}px`,
                '--filter-max-width': `${Math.max(0, width - 32)}px`,
            };
        },
        get filtered() {
            const search = this.search.trim().toLocaleLowerCase('id');
            return this.options.filter((item) => item.name.toLocaleLowerCase('id').includes(search));
        },
        get selectedOptions() { return this.options.filter((item) => item.id !== '' && this.selected.includes(item.id)); },
        get selectedCount() { return this.selectedOptions.length; },
        get active() { return this.selectedCount > 0; },
        get summary() {
            const names = this.selectedOptions.map((item) => item.name);
            if (!names.length) return this.label;
            return names.slice(0, 2).join(', ') + (names.length > 2 ? ', +' + (names.length - 2) : '');
        },
        get allSelected() { return this.filtered.length > 0 && this.filtered.every((item) => this.draft.includes(item.id)); },
        toggle() {
            if (this.open) { this.close(); return; }
            this.open = true;
            this.positionPanel();
            if (this.open) {
                this.draft = [...this.selected];
                this.search = '';
                this.$nextTick(() => this.$refs.search?.focus({ preventScroll: true }));
            }
        },
        close(restoreFocus = true) {
            if (!this.open) return;
            this.draft = [...this.selected];
            this.open = false;
            if (restoreFocus) this.$nextTick(() => this.$refs.trigger?.focus());
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
