export function mountInfrastructureViewer() {
    const controls = document.querySelector('[data-infrastructure-switch]');
    if (!controls) return;
    const map = document.querySelector('[data-infrastructure-map]');
    const viewer = document.querySelector('[data-infrastructure-scene]');
    const warning = viewer.querySelector('[data-infrastructure-warning]');
    const status = viewer.querySelector('[data-infrastructure-status]');
    const buildings = controls.querySelector('[data-infrastructure-buildings]');
    const modeButtons = [...controls.querySelectorAll('[data-infrastructure-mode]')];
    const districtId = new URLSearchParams(location.search).get('distrito_id');
    let scene = null, payload = null;

    buildings.addEventListener('change', () => {
        map.querySelector('[data-geoveil]')?.geoveil?.setLayerVisible('construcoes', buildings.checked);
        if (scene && payload) scene.render(payload, buildings.checked);
    });

    async function show(mode) {
        const three = mode === '3d';
        modeButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.infrastructureMode === mode)));
        map.hidden = three;
        viewer.hidden = !three;
        if (!three) { window.dispatchEvent(new Event('resize')); return; }
        warning.hidden = Boolean(districtId);
        if (!districtId) return;
        if (!window.THREE && document.readyState !== 'complete') await new Promise(resolve => window.addEventListener('load', resolve, { once: true }));
        if (!window.THREE) { status.textContent = 'O motor 3D não foi carregado. Verifique sua conexão e tente novamente.'; return; }
        try {
            status.textContent = 'Carregando modelo do distrito…';
            if (!scene) {
                const { Infrastructure3DScene } = await import('./infrastructure-3d-scene.js');
                scene = new Infrastructure3DScene();
                scene.init();
            }
            if (!payload) {
                const url = new URL(controls.dataset.sceneUrl, location.href);
                url.searchParams.set('distrito_id', districtId);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                payload = await response.json();
            }
            warning.hidden = Boolean(payload.postes?.length);
            if (!payload.postes?.length) { status.textContent = ''; return; }
            await scene.render(payload, buildings.checked);
            status.textContent = '';
            window.dispatchEvent(new Event('resize'));
        } catch (error) { status.textContent = `Não foi possível carregar o visualizador 3D: ${error.message}`; }
    }
    modeButtons.forEach(button => button.addEventListener('click', () => show(button.dataset.infrastructureMode)));
}
