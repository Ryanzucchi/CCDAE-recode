export function mountOptionalColumns(root = document) {
    root.querySelectorAll('[data-resource-table]').forEach(table => {
        const toggles = [...root.querySelectorAll(`[data-column-picker="${table.dataset.resourceTable}"] [data-column-toggle]`)];
        if (!toggles.length) return;
        const key = `ccdae:columns:${table.dataset.resourceTable}`;
        let saved = [];
        try { saved = JSON.parse(localStorage.getItem(key) || '[]'); } catch (_) { saved = []; }
        if (!Array.isArray(saved)) saved = [];
        const update = () => {
            const selected = toggles.filter(toggle => toggle.checked).map(toggle => toggle.dataset.columnToggle);
            table.querySelectorAll('[data-optional-column]').forEach(cell => {
                cell.hidden = !selected.includes(cell.dataset.optionalColumn);
            });
            try { localStorage.setItem(key, JSON.stringify(selected)); } catch (_) {}
        };
        toggles.forEach(toggle => {
            toggle.checked = saved.includes(toggle.dataset.columnToggle);
            toggle.addEventListener('change', update);
        });
        table.addEventListener('geoveil:table-updated', update);
        update();
    });
}
