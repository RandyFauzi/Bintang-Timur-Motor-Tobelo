let sequence = 0;

export function selectOptions(select, query = '') {
    const term = query.trim().toLocaleLowerCase('id');
    return Array.from(select.options).map((option, index) => ({
        index, value: option.value, label: option.label || option.textContent,
        disabled: option.disabled || Boolean(option.closest('optgroup')?.disabled),
        selected: option.selected,
    })).filter(option => !term || option.label.toLocaleLowerCase('id').includes(term));
}

export function chooseSelectOption(select, index) {
    const option = selectOptions(select).find(option => option.index === index);
    if (select.matches(':disabled') || !option || option.disabled) return false;
    if (select.selectedIndex !== index) {
        select.selectedIndex = index;
        const Event = select.ownerDocument.defaultView.Event;
        select.dispatchEvent(new Event('input', {bubbles: true}));
        select.dispatchEvent(new Event('change', {bubbles: true}));
    }
    return true;
}

export function startSelectPickers(Alpine, document = globalThis.document) {
    const window = document.defaultView;
    const pickers = new Map();
    const pendingInvalidForms = new WeakSet();
    let opened = null;

    function enhance(select) {
        if (pickers.has(select) || select.multiple || select.size > 1 || select.hasAttribute('data-native-select')) return;
        const id = `select-picker-${++sequence}`;
        const labels = Array.from(select.labels || []);
        const label = select.getAttribute('aria-label') || labels.map(label => {
            const copy = label.cloneNode(true);
            copy.querySelectorAll('select, input, button, textarea').forEach(control => control.remove());
            return copy.textContent.trim();
        }).filter(Boolean).join(' ') || select.name || 'Pilih opsi';
        const wrapper = document.createElement('span');
        wrapper.className = 'select-picker';
        if (select.closest('.filter-select-label, .return-note-filter')) {
            select.closest('.filter-select-label, .return-note-filter').classList.add('select-picker-label');
            wrapper.classList.add('select-picker--embedded');
        }
        if (select.classList.contains('pagination-select')) wrapper.classList.add('select-picker--pagination');
        const button = document.createElement('button');
        button.type = 'button';
        button.id = id;
        button.className = 'select-picker__trigger';
        button.setAttribute('role', 'combobox');
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-controls', `${id}-options`);
        if (select.hasAttribute('aria-describedby')) button.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
        const caption = document.createElement('span');
        caption.className = 'select-picker__caption';
        const caret = document.createElement('span');
        caret.className = 'select-picker__caret';
        caret.setAttribute('aria-hidden', 'true');
        button.append(caption, caret);
        select.before(wrapper);
        wrapper.append(select, button);
        select.classList.add('select-picker__native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        const error = document.createElement('span');
        error.id = `${id}-error`;
        error.className = 'select-picker__error';
        error.setAttribute('role', 'alert');
        error.hidden = true;
        wrapper.append(error);
        let panel = null;
        let search = null;
        let list = null;
        let visible = [];
        let active = -1;
        let openingEvents = null;

        function close(restoreFocus = false) {
            openingEvents?.abort();
            panel?.remove();
            panel = null;
            search = null;
            button.setAttribute('aria-expanded', 'false');
            if (opened === picker) opened = null;
            if (restoreFocus && button.isConnected) button.focus({preventScroll: true});
        }

        function position() {
            if (!panel) return;
            const bounds = button.getBoundingClientRect();
            const viewport = window.visualViewport;
            const viewTop = viewport?.offsetTop || 0;
            const viewLeft = viewport?.offsetLeft || 0;
            const viewWidth = viewport?.width || window.innerWidth;
            const viewBottom = viewTop + (viewport?.height || window.innerHeight);
            const width = Math.min(Math.max(bounds.width, 240), viewWidth - 24);
            const anchorTop = Math.min(Math.max(bounds.top, viewTop + 12), viewBottom - 12);
            const anchorBottom = Math.min(Math.max(bounds.bottom, viewTop + 12), viewBottom - 12);
            const below = viewBottom - anchorBottom - 12;
            const above = anchorTop - viewTop - 12;
            const upwards = below < 220 && above > below;
            const height = Math.min(340, Math.max(96, upwards ? above : below));
            Object.assign(panel.style, {
                width: `${width}px`, maxHeight: `${height}px`,
                left: `${Math.max(viewLeft + 12, Math.min(bounds.left, viewLeft + viewWidth - width - 12))}px`,
                top: upwards ? 'auto' : `${anchorBottom + 6}px`,
                bottom: upwards ? `${window.innerHeight - anchorTop + 6}px` : 'auto',
            });
        }

        function highlight(index) {
            active = index;
            Array.from(list.children).forEach(item => item.classList.toggle('is-focused', Number(item.dataset.index) === active));
            if (active >= 0) {
                search.setAttribute('aria-activedescendant', `${id}-option-${active}`);
                list.querySelector(`[data-index="${active}"]`)?.scrollIntoView({block: 'nearest'});
            } else search.removeAttribute('aria-activedescendant');
        }

        function render() {
            if (!panel) return;
            visible = selectOptions(select, search.value);
            list.replaceChildren();
            for (const option of visible) {
                const item = document.createElement('div');
                item.id = `${id}-option-${option.index}`;
                item.className = 'select-picker__option';
                item.dataset.index = String(option.index);
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', String(option.selected));
                item.setAttribute('aria-disabled', String(option.disabled));
                const text = document.createElement('span');
                text.textContent = option.label;
                item.append(text);
                if (option.selected) {
                    const check = document.createElement('span');
                    check.textContent = '✓';
                    check.setAttribute('aria-hidden', 'true');
                    item.append(check);
                }
                item.addEventListener('click', () => {
                    if (chooseSelectOption(select, option.index)) close(true);
                });
                list.append(item);
            }
            if (!visible.length) {
                const empty = document.createElement('p');
                empty.className = 'select-picker__empty';
                empty.setAttribute('role', 'status');
                empty.textContent = 'Pilihan tidak ditemukan.';
                list.append(empty);
            }
            const enabled = visible.filter(option => !option.disabled);
            highlight(enabled.find(option => option.selected)?.index ?? enabled[0]?.index ?? -1);
            position();
        }

        function open() {
            if (button.disabled) return;
            opened?.close();
            opened = picker;
            openingEvents = new window.AbortController();
            const events = {signal: openingEvents.signal};
            panel = document.createElement('div');
            panel.className = 'select-picker__panel';
            panel.tabIndex = -1;
            search = document.createElement('input');
            search.type = 'search';
            search.className = 'select-picker__search';
            search.placeholder = 'Cari pilihan...';
            search.setAttribute('aria-label', `Cari pilihan: ${label}`);
            search.setAttribute('role', 'combobox');
            search.setAttribute('aria-expanded', 'true');
            search.setAttribute('aria-controls', `${id}-options`);
            search.setAttribute('aria-autocomplete', 'list');
            list = document.createElement('div');
            list.id = `${id}-options`;
            list.className = 'select-picker__options';
            list.setAttribute('role', 'listbox');
            list.setAttribute('aria-label', label);
            panel.append(search, list);
            (select.closest('dialog[open]') || document.body).append(panel);
            button.setAttribute('aria-expanded', 'true');
            render();
            (window.innerWidth <= 767 ? panel : search).focus({preventScroll: true});
            search.addEventListener('input', render, events);
            panel.addEventListener('keydown', event => {
                if (event.key === 'Tab') { close(true); return; }
                if (!['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes(event.key)) return;
                event.preventDefault();
                event.stopPropagation();
                if (event.key === 'Escape') { close(true); return; }
                if (event.key === 'Enter') { if (chooseSelectOption(select, active)) close(true); return; }
                const enabled = visible.filter(option => !option.disabled);
                if (!enabled.length) return;
                const current = enabled.findIndex(option => option.index === active);
                const next = (current + (event.key === 'ArrowDown' ? 1 : -1) + enabled.length) % enabled.length;
                highlight(enabled[next].index);
            }, events);
            document.addEventListener('pointerdown', event => {
                if (!wrapper.contains(event.target) && !panel?.contains(event.target)) close();
            }, events);
            document.addEventListener('scroll', event => { if (!panel?.contains(event.target)) position(); }, {...events, capture: true});
            window.addEventListener('resize', position, events);
            window.visualViewport?.addEventListener('resize', position, events);
            window.visualViewport?.addEventListener('scroll', position, events);
        }

        function sync() {
            const text = select.selectedOptions[0]?.label || select.options[0]?.label || 'Pilih opsi';
            if (caption.textContent !== text) caption.textContent = text;
            button.title = text;
            button.setAttribute('aria-label', `${label}: ${text}`);
            button.disabled = select.matches(':disabled') || Boolean(select.closest('[inert]'));
            button.setAttribute('aria-required', String(select.required));
            button.setAttribute('aria-invalid', String(!error.hidden || select.getAttribute('aria-invalid') === 'true'));
            if (select.validity.valid && !error.hidden) {
                error.hidden = true;
                button.setAttribute('aria-invalid', 'false');
            }
            if (button.disabled) close();
            else if (panel) render();
        }

        button.addEventListener('click', () => panel ? close() : open());
        button.addEventListener('keydown', event => {
            if (['ArrowDown', 'ArrowUp'].includes(event.key)) { event.preventDefault(); open(); }
        });
        select.addEventListener('change', sync);
        select.addEventListener('focus', () => button.focus({preventScroll: true}));
        select.addEventListener('click', event => { event.preventDefault(); if (!panel) open(); });
        select.addEventListener('invalid', event => {
            event.preventDefault();
            error.textContent = select.validationMessage;
            error.hidden = false;
            button.setAttribute('aria-invalid', 'true');
            button.setAttribute('aria-describedby', [select.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
            const form = select.form;
            if (form && !pendingInvalidForms.has(form)) {
                pendingInvalidForms.add(form);
                queueMicrotask(() => { button.focus({preventScroll: false}); pendingInvalidForms.delete(form); });
            }
        });
        const nativeObserver = new window.MutationObserver(sync);
        nativeObserver.observe(select, {subtree: true, childList: true, attributes: true, characterData: true});
        const valueBinding = select.getAttribute(':value') || select.getAttribute('x-bind:value');
        const effect = Alpine.effect(() => {
            select._x_model?.get();
            if (valueBinding) Alpine.evaluate(select, valueBinding);
            queueMicrotask(() => { if (select.isConnected) sync(); });
        });
        const dialog = select.closest('dialog');
        const onDialogClose = () => close();
        dialog?.addEventListener('close', onDialogClose);
        const picker = {sync, close, destroy() { close(); nativeObserver.disconnect(); dialog?.removeEventListener('close', onDialogClose); Alpine.release(effect); pickers.delete(select); }};
        pickers.set(select, picker);
        sync();
    }

    function scan(root) {
        if (root.matches?.('select')) enhance(root);
        root.querySelectorAll?.('select').forEach(enhance);
    }

    scan(document);
    const observer = new window.MutationObserver(records => {
        for (const record of records) {
            if (record.type === 'attributes') {
                for (const [select, picker] of pickers) if (record.target.contains(select)) picker.sync();
            }
            for (const node of record.addedNodes) if (node.nodeType === 1) scan(node);
        }
        for (const [select, picker] of pickers) if (!select.isConnected) picker.destroy();
    });
    observer.observe(document.body, {childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'inert']});
    const onReset = () => queueMicrotask(() => pickers.forEach(picker => picker.sync()));
    document.addEventListener('reset', onReset, true);
    return () => { observer.disconnect(); document.removeEventListener('reset', onReset, true); for (const picker of pickers.values()) picker.destroy(); };
}
