export function mountRemoteSelects(root = document) {
    for (const widget of root.querySelectorAll('[data-geoveil-remote-select]')) {
        const query = widget.querySelector('[data-remote-query]');
        const value = widget.querySelector('[data-remote-value]');
        const results = widget.querySelector('[data-remote-results]');
        const status = widget.querySelector('[data-remote-status]');
        const endpoint = widget.dataset.endpoint;
        let timer = null, controller = null;
        const clear = () => { results.replaceChildren(); results.hidden = true; query.setAttribute('aria-expanded', 'false'); };
        async function search(params) {
            controller?.abort(); controller = new AbortController();
            const url = new URL(endpoint, location.href);
            for (const [key, item] of Object.entries(params)) url.searchParams.set(key, item);
            try {
                const response = await fetch(url, {headers: {Accept: 'application/json'}, signal: controller.signal});
                if (!response.ok) throw new Error('Busca indisponível');
                const payload = await response.json();
                return Array.isArray(payload.results) ? payload.results : [];
            } catch (error) {
                if (error.name !== 'AbortError') status.textContent = 'Não foi possível buscar registros.';
                return [];
            }
        }
        if (value.value && !query.value) search({id: value.value}).then(items => {
            const selected = items.find(item => String(item.id) === value.value);
            if (selected) { query.value = selected.label; widget.dispatchEvent(new CustomEvent('geoveil:remote-selected', {bubbles: true, detail: {field: value.name, item: selected, initial: true}})); }
        });
        query.addEventListener('input', () => {
            value.value = ''; widget.dispatchEvent(new CustomEvent('geoveil:remote-selected', {bubbles: true, detail: {field: value.name, item: null}})); clear(); clearTimeout(timer);
            const term = query.value.trim();
            if (term.length < 2) { status.textContent = 'Digite pelo menos 2 caracteres.'; return; }
            status.textContent = 'Buscando…';
            timer = setTimeout(async () => {
                const items = await search({q: term});
                if (query.value.trim() !== term) return;
                results.replaceChildren();
                for (const item of items) {
                    const option = document.createElement('button');
                    option.type = 'button'; option.setAttribute('role', 'option');
                    option.textContent = item.label;
                    option.addEventListener('click', () => { value.value = item.id; query.value = item.label; clear(); status.textContent = 'Selecionado: ' + item.label; widget.dispatchEvent(new CustomEvent('geoveil:remote-selected', {bubbles: true, detail: {field: value.name, item}})); });
                    results.append(option);
                }
                results.hidden = items.length === 0;
                query.setAttribute('aria-expanded', String(items.length > 0));
                status.textContent = items.length ? `${items.length} resultado(s).` : 'Nenhum registro encontrado.';
            }, 250);
        });
        query.addEventListener('keydown', event => { if (event.key === 'Escape') clear(); });
        widget.addEventListener('focusout', event => { if (!widget.contains(event.relatedTarget)) clear(); });
    }
}
