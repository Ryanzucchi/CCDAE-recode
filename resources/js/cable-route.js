export async function mountCableRoute() {
    const form = document.querySelector('[data-geoveil-form="cabeamentos"]');
    if (!form) return;
    const editor = form.querySelector('[data-geoveil-geometry][data-geometry-mode="line"]');
    const nodes = ['poste_origem_id', 'poste_destino_id', 'central_origem_id', 'central_destino_id'];
    const coordinates = new Map();
    async function initialCoordinate(field) {
        const widget = form.querySelector(`[data-geoveil-remote-select]:has([name="${field}"])`);
        const id = widget?.querySelector('[data-remote-value]')?.value;
        if (!id) return;
        const url = new URL(widget.dataset.endpoint, location.href); url.searchParams.set('id', id);
        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}});
            if (!response.ok) return;
            const item = (await response.json()).results?.[0];
            if (String(widget.querySelector('[data-remote-value]').value) === String(item?.id)) remember(field, item);
        } catch (_) {}
    }
    function remember(field, item) {
        if (Number.isFinite(Number(item?.longitude)) && Number.isFinite(Number(item?.latitude)) && item?.longitude !== null && item?.latitude !== null) {
            coordinates.set(field, [Number(item.longitude), Number(item.latitude)]);
        } else coordinates.delete(field);
    }
    await Promise.all(nodes.map(initialCoordinate));
    form.addEventListener('geoveil:remote-selected', event => {
        const {field, item, initial} = event.detail;
        if (!nodes.includes(field)) return;
        remember(field, item);
        if (initial) return;
        const from = coordinates.get('poste_origem_id') ?? coordinates.get('central_origem_id');
        const to = coordinates.get('poste_destino_id') ?? coordinates.get('central_destino_id');
        if (!from || !to) return;
        const geometry = {type: 'LineString', coordinates: [from, to]};
        editor.dispatchEvent(new CustomEvent('geoveil:geometry-set', {detail: {geometry}}));
        const length = Math.hypot((to[0] - from[0]) * Math.cos((from[1] + to[1]) / 2 * Math.PI / 180) * 111320,
            (to[1] - from[1]) * 110540);
        const meters = form.querySelector('[name="extensao_metros"]');
        if (meters) meters.value = length.toFixed(2);
    });
}
