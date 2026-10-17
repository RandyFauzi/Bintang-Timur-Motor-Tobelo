export function productSelection(initial = []) {
    return {
        selected: Array.isArray(initial) ? [...new Set(initial.filter((id) => /^\d+$/.test(String(id)) && Number(id) > 0).map(String))] : [],
        has(id) { return this.selected.includes(String(id)); },
        toggle(id, checked) {
            this.selected = this.selected.filter((value) => value !== String(id));
            if (checked) this.selected.push(String(id));
        },
        pageSelected(ids) { return ids.length > 0 && ids.every((id) => this.has(id)); },
        togglePage(ids, checked) { ids.forEach((id) => this.toggle(id, checked)); },
    };
}
