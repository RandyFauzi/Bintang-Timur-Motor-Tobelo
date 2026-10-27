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
            globalThis.window?.addEventListener('scroll', this.viewportListener, true);
        },
        destroy() {
            this.viewport?.removeEventListener('resize', this.viewportListener);
            this.viewport?.removeEventListener('scroll', this.viewportListener);
            globalThis.window?.removeEventListener('resize', this.viewportListener);
            globalThis.window?.removeEventListener('scroll', this.viewportListener, true);
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
            const bounds = this.$refs?.trigger?.getBoundingClientRect?.();
            if (bounds) {
                const top = viewport?.offsetTop ?? 0;
                const left = viewport?.offsetLeft ?? 0;
                const anchorTop = Math.min(Math.max(bounds.top, top + 12), top + height - 12);
                const anchorBottom = Math.min(Math.max(bounds.bottom, top + 12), top + height - 12);
                const below = top + height - anchorBottom - 12;
                const above = anchorTop - top - 12;
                const upwards = below < 240 && above > below;
                const panelWidth = Math.min(Math.max(bounds.width, 260), width - 24);
                Object.assign(this.panelPosition, {
                    '--filter-anchor-width': `${panelWidth}px`,
                    '--filter-anchor-left': `${Math.max(left + 12, Math.min(bounds.left, left + width - panelWidth - 12))}px`,
                    '--filter-anchor-top': upwards ? 'auto' : `${anchorBottom + 6}px`,
                    '--filter-anchor-bottom': upwards ? `${(globalThis.window?.innerHeight ?? height) - anchorTop + 6}px` : 'auto',
                    '--filter-anchor-height': `${Math.min(400, Math.max(100, upwards ? above : below))}px`,
                });
            }
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
                this.$nextTick(() => (globalThis.window?.innerWidth <= 767 ? this.$refs.panel : this.$refs.search)?.focus({ preventScroll: true }));
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
        choose(id) {
            if (!this.options.some(option => option.id === id)) return;
            this.draft = [id];
            this.apply();
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

export function distributorAutocomplete(options = [], initialId = null, initialName = '', createUrl = '') {
    return {
        options: Array.isArray(options) ? options.map((opt) => ({ id: String(opt.id), name: opt.name })) : [],
        query: initialName ? String(initialName) : '',
        selectedId: initialId ? String(initialId) : '',
        selectedName: initialName ? String(initialName) : '',
        open: false,
        highlightedIndex: -1,
        isAdding: false,
        newlyAdded: false,
        createUrl,
        init() {
            if (this.selectedId && !this.query) {
                const match = this.options.find((opt) => opt.id === this.selectedId);
                if (match) {
                    this.query = match.name;
                    this.selectedName = match.name;
                }
            }
        },
        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (!q) return this.options.slice(0, 20);
            return this.options.filter((opt) => opt.name.toLowerCase().includes(q)).slice(0, 20);
        },
        get exactMatch() {
            const q = this.query.trim().toLowerCase();
            if (!q) return false;
            return this.options.some((opt) => opt.name.toLowerCase() === q);
        },
        get canCreate() {
            return this.query.trim().length > 0 && !this.exactMatch;
        },
        openDropdown() {
            this.open = true;
            this.highlightedIndex = -1;
        },
        close() {
            this.open = false;
            this.highlightedIndex = -1;
            const q = this.query.trim();
            if (q && !this.selectedId) {
                const match = this.options.find((opt) => opt.name.toLowerCase() === q.toLowerCase());
                if (match) {
                    this.selectedId = match.id;
                    this.selectedName = match.name;
                    this.query = match.name;
                } else {
                    this.selectedName = q;
                }
            }
        },
        onInput() {
            this.open = true;
            this.highlightedIndex = -1;
            this.newlyAdded = false;
            const q = this.query.trim().toLowerCase();
            const match = this.options.find((opt) => opt.name.toLowerCase() === q);
            if (match) {
                this.selectedId = match.id;
                this.selectedName = match.name;
            } else {
                this.selectedId = '';
                this.selectedName = this.query.trim();
            }
        },
        select(item) {
            this.selectedId = String(item.id);
            this.selectedName = item.name;
            this.query = item.name;
            this.open = false;
            this.highlightedIndex = -1;
            this.newlyAdded = false;
        },
        clear() {
            this.query = '';
            this.selectedId = '';
            this.selectedName = '';
            this.open = false;
            this.newlyAdded = false;
            this.highlightedIndex = -1;
        },
        onArrowDown() {
            if (!this.open) {
                this.open = true;
                return;
            }
            const total = this.filtered.length + (this.canCreate ? 1 : 0);
            if (total === 0) return;
            this.highlightedIndex = (this.highlightedIndex + 1) % total;
        },
        onArrowUp() {
            if (!this.open) {
                this.open = true;
                return;
            }
            const total = this.filtered.length + (this.canCreate ? 1 : 0);
            if (total === 0) return;
            this.highlightedIndex = (this.highlightedIndex - 1 + total) % total;
        },
        onEnter(event) {
            if (!this.open) return;
            const filteredCount = this.filtered.length;
            if (this.highlightedIndex >= 0 && this.highlightedIndex < filteredCount) {
                event.preventDefault();
                this.select(this.filtered[this.highlightedIndex]);
            } else if (this.canCreate && (this.highlightedIndex === filteredCount || filteredCount === 0)) {
                event.preventDefault();
                this.createNew();
            } else if (filteredCount > 0 && this.highlightedIndex === -1) {
                event.preventDefault();
                this.select(this.filtered[0]);
            }
        },
        async createNew() {
            if (!this.canCreate || this.isAdding) return;
            const name = this.query.trim();
            if (!name) return;
            this.isAdding = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                const response = await fetch(this.createUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({ name }),
                });
                if (response.ok) {
                    const result = await response.json();
                    const newDistributor = result.data ? { id: String(result.data.id), name: result.data.name } : { id: String(Date.now()), name };
                    this.options.unshift(newDistributor);
                    this.select(newDistributor);
                    this.newlyAdded = true;
                    window.dispatchEvent(new CustomEvent('notify', {
                        detail: { message: `Distributor "${name}" berhasil ditambahkan.`, type: 'success' },
                    }));
                } else {
                    this.selectedId = '';
                    this.selectedName = name;
                    this.open = false;
                }
            } catch {
                this.selectedId = '';
                this.selectedName = name;
                this.open = false;
            } finally {
                this.isAdding = false;
            }
        },
    };
}
