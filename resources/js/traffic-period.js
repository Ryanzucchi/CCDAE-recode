export function mountTrafficPeriod(root = document) {
    for (const form of root.querySelectorAll('[data-traffic-period]')) {
        const mode = form.querySelector('[data-period-mode]');
        const end = form.querySelector('[data-period-end]');
        if (!mode || !end) continue;
        const sync = () => { end.hidden = mode.value === 'hour'; };
        mode.addEventListener('change', sync);
        sync();
    }
}
