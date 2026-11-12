export function mountLocalSelects(root = document) {
    for (const query of root.querySelectorAll('[data-geoveil-select-filter]')) {
        const select = root.getElementById(query.dataset.geoveilSelectFilter);
        if (!select) continue;
        const options = Array.from(select.options, option => option.cloneNode(true));
        query.addEventListener('input', () => {
            const term = query.value.trim().toLocaleLowerCase('pt-BR');
            const selected = select.value;
            const matching = options.filter(option => !option.value || option.value === selected || option.textContent.toLocaleLowerCase('pt-BR').includes(term));
            select.replaceChildren(...matching.map(option => option.cloneNode(true)));
            select.value = selected;
        });
        query.addEventListener('keydown', event => {
            if (event.key === 'Escape') { query.value = ''; query.dispatchEvent(new Event('input')); select.focus(); }
            if (event.key === 'ArrowDown') { event.preventDefault(); select.focus(); }
        });
    }
}
