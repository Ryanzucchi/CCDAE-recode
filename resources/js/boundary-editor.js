import 'leaflet/dist/leaflet.css';

export async function mountBoundaryEditors() {
    const editors = document.querySelectorAll('[data-boundary-editor]');
    if (!editors.length) return;
    const L = (await import('leaflet')).default;
    for (const root of editors) {
        const input = root.querySelector('[data-boundary-value]');
        const status = root.querySelector('[data-boundary-status]');
        let original = null;
        try { original = JSON.parse(input.value || 'null'); } catch { status.textContent = 'Fronteira inválida. Desenhe novamente.'; }
        let geometry = original ? structuredClone(original) : null;
        let drawing = false, rectangle = false, points = [];
        const map = L.map(root.querySelector('[data-boundary-map]')).setView([-16.071, -57.681], 13);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '© OpenStreetMap contributors', maxZoom: 19}).addTo(map);
        const shapes = L.layerGroup().addTo(map);
        const vertices = L.layerGroup().addTo(map);
        function persist() { input.value = geometry ? JSON.stringify(geometry) : ''; }
        function render() {
            shapes.clearLayers(); vertices.clearLayers();
            if (!geometry) return;
            L.geoJSON(geometry, {style: {color:'#0d9488', fillOpacity:0.25}}).addTo(shapes);
            const polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
            for (const polygon of polygons) for (const ring of polygon) {
                ring.slice(0, -1).forEach((coordinate, index) => {
                    const marker = L.marker([coordinate[1], coordinate[0]], {draggable:true, icon:L.divIcon({className:'ccdae-vertex',iconSize:[14,14],iconAnchor:[7,7]})}).addTo(vertices);
                    marker.on('dragend', () => {
                        const position = marker.getLatLng();
                        ring[index] = [position.lng, position.lat];
                        if (index === 0) ring[ring.length-1] = [...ring[0]];
                        persist(); render(); status.textContent = 'Vértice ajustado. Salve para aplicar a alteração.';
                    });
                });
            }
        }
        function preview() {
            shapes.clearLayers();
            L.polyline(points.map(([lng,lat]) => [lat,lng]), {color:'#0d9488'}).addTo(shapes);
            status.textContent = rectangle ? 'Selecione dois cantos opostos do retângulo.' : `${points.length} pontos. Use “Concluir polígono” depois de adicionar pelo menos três.`;
        }
        function start(rectangleMode) {
            drawing = true; rectangle = rectangleMode; points = []; vertices.clearLayers(); shapes.clearLayers();
            input.value = ''; status.textContent = 'Clique no mapa para adicionar os vértices da nova fronteira.';
        }
        root.querySelector('[data-boundary-start]').addEventListener('click', () => start(false));
        root.querySelector('[data-boundary-rectangle]').addEventListener('click', () => { start(true); preview(); });
        map.on('click', event => { if (drawing) { points.push([event.latlng.lng, event.latlng.lat]); if (rectangle && points.length === 2) root.querySelector('[data-boundary-finish]').click(); else preview(); } });
        root.querySelector('[data-boundary-undo]').addEventListener('click', () => { if (drawing) { points.pop(); preview(); } });
        root.querySelector('[data-boundary-finish]').addEventListener('click', () => {
            if (!drawing || points.length < (rectangle ? 2 : 3)) { status.textContent = `Adicione pelo menos ${rectangle ? 'dois' : 'três'} pontos antes de concluir.`; return; }
            if (rectangle) {
                const [[x1, y1], [x2, y2]] = points;
                geometry = {type:'Polygon', coordinates:[[[x1,y1],[x2,y1],[x2,y2],[x1,y2],[x1,y1]]]};
            } else geometry = {type:'Polygon', coordinates:[[...points, [...points[0]]]]};
            drawing = false; persist(); render(); status.textContent = 'Polígono concluído. Arraste os vértices para ajustar ou salve o distrito.';
        });
        root.querySelector('[data-boundary-clear]').addEventListener('click', () => {
            drawing = false; rectangle = false; points = []; geometry = null;
            persist(); render(); status.textContent = 'Fronteira apagada. Desenhe uma nova antes de salvar.';
        });
        root.querySelector('[data-boundary-restore]').addEventListener('click', () => {
            drawing = false; rectangle = false; geometry = original ? structuredClone(original) : null;
            shapes.clearLayers(); vertices.clearLayers(); persist(); render(); status.textContent = 'Fronteira restaurada.';
        });
        root.closest('form').addEventListener('submit', event => {
            if (drawing || !input.value) { event.preventDefault(); status.textContent = 'Conclua uma fronteira antes de salvar.'; root.querySelector('[data-boundary-finish]').focus(); }
        });
        render();
        if (geometry) map.fitBounds(L.geoJSON(geometry).getBounds(), {padding:[30,30]});
    }
}
