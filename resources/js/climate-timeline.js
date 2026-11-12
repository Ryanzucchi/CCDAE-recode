export function mountClimateTimeline() {
    const form = document.querySelector('[data-climate-timeline]');
    const map = document.querySelector('[data-geoveil]')?.geoveil;
    if (!form || !map) return;

    const select = form.querySelector('[name=at]');
    const range = form.querySelector('[data-climate-range]');
    const play = form.querySelector('[data-climate-play]');
    const status = form.querySelector('[data-climate-status]');
    const wind = document.querySelector('[data-wind-summary]');
    const times = [...select.options].map(option => option.value).reverse();
    const initialWind = wind?.hidden ? null : wind ? {
        speed: Number.parseFloat(wind.querySelector('[data-wind-speed]').textContent),
        direction: Number.parseFloat(wind.querySelector('[data-wind-arrow]').style.transform.replace(/[^\d.\-]/g, '')),
        description: wind.querySelector('[data-wind-description]').textContent,
    } : null;
    const cache = new Map([[select.value, { features: map.getLayerFeatures('clima'), windSummary: initialWind }]]);
    let timer = null;
    let sequence = 0;
    const label = value => new Date(value.replace(' ', 'T')).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

    function stop() {
        clearInterval(timer);
        timer = null;
        play.textContent = '▶ Reproduzir';
        play.setAttribute('aria-label', 'Reproduzir histórico climático');
    }

    async function show(index) {
        const ticket = ++sequence;
        const at = times[index];
        if (!at) return;
        range.value = String(index);
        select.value = at;
        status.textContent = `Carregando ${label(at)}…`;
        try {
            if (!cache.has(at)) {
                const query = new URLSearchParams(new FormData(form));
                query.set('at', at);
                query.set('frame', '1');
                const response = await fetch(`${form.action || location.pathname}?${query}`, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                cache.set(at, await response.json());
            }
            if (ticket !== sequence) return;
            const frame = cache.get(at);
            map.updateLayer('clima', frame.features);
            if (wind) {
                wind.hidden = !frame.windSummary;
                if (frame.windSummary) {
                    wind.querySelector('[data-wind-arrow]').style.transform = `rotate(${frame.windSummary.direction}deg)`;
                    wind.querySelector('[data-wind-speed]').textContent = `${frame.windSummary.speed} km/h`;
                    wind.querySelector('[data-wind-description]').textContent = frame.windSummary.description.includes(' · ')
                        ? frame.windSummary.description : `${frame.windSummary.cardinal} · ${frame.windSummary.description}`;
                }
            }
            status.textContent = label(at);
        } catch (error) {
            if (ticket === sequence) {
                status.textContent = `Falha ao carregar leitura: ${error.message}`;
                stop();
            }
        }
    }

    range.addEventListener('input', () => { stop(); show(Number(range.value)); });
    select.addEventListener('change', () => { stop(); show(times.indexOf(select.value)); });
    play.addEventListener('click', () => {
        if (timer) { stop(); return; }
        if (Number(range.value) >= times.length - 1) range.value = '0';
        show(Number(range.value));
        play.textContent = '❚❚ Pausar';
        play.setAttribute('aria-label', 'Pausar histórico climático');
        timer = setInterval(() => {
            const next = Number(range.value) + 1;
            if (next >= times.length) { stop(); return; }
            show(next);
        }, 1800);
    });
    form.addEventListener('submit', stop);
}
