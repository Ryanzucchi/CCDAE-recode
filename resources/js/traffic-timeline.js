export function mountTrafficTimeline() {
    const form = document.querySelector('[data-traffic-timeline]');
    const map = document.querySelector('[data-geoveil]')?.geoveil;
    if (!form || !map) return;
    const select = form.querySelector('[name=at]');
    const range = form.querySelector('[data-traffic-range]');
    const play = form.querySelector('[data-traffic-play]');
    const status = form.querySelector('[data-traffic-status]');
    const times = [...select.options].map(option => option.value).filter(Boolean).reverse();
    const cache = new Map();
    const initial = {
        vias: map.getLayerFeatures('vias'),
        viasSemLeitura: map.getLayerFeatures('vias-sem-leitura'),
    };
    const initialAt = select.value;
    if (initialAt) cache.set(initialAt, initial);
    let timer = null, sequence = 0;
    const label = value => value ? new Date(value.replace(' ', 'T')).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : 'Situação atual';
    function stop() {
        clearInterval(timer); timer = null;
        play.textContent = '▶ Reproduzir'; play.setAttribute('aria-label', 'Reproduzir histórico');
    }
    async function show(index) {
        const ticket = ++sequence;
        const at = times[index] ?? '';
        select.value = at; range.value = String(index);
        status.textContent = at ? `Carregando ${label(at)}…` : 'Situação atual';
        try {
            let frame = initial;
            if (at || initialAt) {
                if (!cache.has(at)) {
                    const query = new URLSearchParams(new FormData(form));
                    query.set('at', at); query.set('frame', '1');
                    const response = await fetch(`${form.action || location.pathname}?${query}`, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    cache.set(at, await response.json());
                }
                frame = cache.get(at);
            }
            if (ticket !== sequence) return;
            map.updateLayer('vias', frame.vias);
            map.updateLayer('vias-sem-leitura', frame.viasSemLeitura);
            status.textContent = label(at);
        } catch (error) {
            if (ticket === sequence) { status.textContent = `Falha ao carregar leitura: ${error.message}`; stop(); }
        }
    }
    range.addEventListener('input', () => { stop(); show(Number(range.value)); });
    select.addEventListener('change', () => { stop(); show(times.indexOf(select.value) < 0 ? times.length : times.indexOf(select.value)); });
    play.addEventListener('click', () => {
        if (timer) { stop(); return; }
        range.value = String(Number(range.value) >= times.length ? 0 : Number(range.value));
        show(Number(range.value));
        play.textContent = '❚❚ Pausar'; play.setAttribute('aria-label', 'Pausar histórico');
        timer = setInterval(() => {
            const next = Number(range.value) + 1;
            if (next > times.length) { stop(); return; }
            show(next);
        }, 1800);
    });
    form.addEventListener('submit', () => stop());
}
