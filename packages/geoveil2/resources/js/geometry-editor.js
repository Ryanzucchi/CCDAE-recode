import 'leaflet/dist/leaflet.css';

export async function mountGeometryEditors() {
    const editors = document.querySelectorAll('[data-geoveil-geometry]');
    if (!editors.length) return;
    const L = (await import('leaflet')).default;
    for (const root of editors) {
        const input = root.querySelector('[data-geometry-value]');
        const lat = root.querySelector('[data-geoveil-latitude]');
        const lng = root.querySelector('[data-geoveil-longitude]');
        const status = root.querySelector('[data-geometry-status]');
        const lineMode = root.dataset.geometryMode === 'line';
        let geometry = null;
        try { geometry = input.value ? JSON.parse(input.value) : null; } catch { status.textContent = 'Geometria inválida. Desenhe novamente.'; }
        let drawing = false, editing = false, rectangle = false, points = [];
        const initial = lat?.value && lng?.value ? [Number(lat.value), Number(lng.value)] : [-16.07083, -57.6875];
        const map = L.map(root.querySelector('[data-geometry-map]')).setView(initial, 14);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '© OpenStreetMap contributors', maxZoom: 19}).addTo(map);
        const shapes = L.layerGroup().addTo(map);
        const handles = L.layerGroup().addTo(map);
        function coordinates(position) { if (lat && lng) { lat.value = position.lat.toFixed(7); lng.value = position.lng.toFixed(7); } }
        function editablePoints() {
            if (geometry?.type === 'LineString') return geometry.coordinates;
            if (geometry?.type === 'Polygon') return geometry.coordinates[0].slice(0, -1);
            return null;
        }
        function render(fit = true) {
            shapes.clearLayers();
            handles.clearLayers();
            if (geometry) L.geoJSON(geometry, {style: {color: '#0d9488', fillOpacity: 0.25}, pointToLayer: (_feature, point) => L.circleMarker(point, {radius: 8, color: '#0d9488', fillColor: '#14b8a6', fillOpacity: 1})}).addTo(shapes);
            else if (lat?.value && lng?.value) L.circleMarker([Number(lat.value), Number(lng.value)], {radius: 8, color: '#0d9488', fillColor: '#14b8a6', fillOpacity: 1}).addTo(shapes);
            if (editing && editablePoints()) editablePoints().forEach(([x, y], index) => {
                const handle = L.marker([y, x], {draggable: true}).addTo(handles);
                handle.on('dragend', () => {
                    const position = handle.getLatLng();
                    const vertices = editablePoints();
                    vertices[index] = [position.lng, position.lat];
                    geometry.coordinates = geometry.type === 'Polygon' ? [[...vertices, [...vertices[0]]], ...geometry.coordinates.slice(1)] : vertices;
                    input.value = JSON.stringify(geometry);
                    const manual = root.querySelector('[data-geometry-manual]'); if (manual) manual.value = '1';
                    coordinates(L.geoJSON(geometry).getBounds().getCenter());
                    render(false);
                    status.textContent = 'Vértice movido. Salve para registrar.';
                });
            });
            if (fit && geometry && ['Polygon', 'MultiPolygon', 'LineString', 'MultiLineString'].includes(geometry.type)) map.fitBounds(L.geoJSON(geometry).getBounds(), {padding: [30, 30]});
        }
        function preview() {
            shapes.clearLayers();
            if (points.length) L.polyline(points.map(([x, y]) => [y, x]), {color: '#0d9488'}).addTo(shapes);
            status.textContent = rectangle ? 'Selecione dois cantos opostos do retângulo.' : `${points.length} pontos. Conclua depois de adicionar pelo menos ${lineMode ? 'dois' : 'três'}.`;
        }
        root.querySelector('[data-geometry-draw]').addEventListener('click', () => { drawing = true; editing = false; rectangle = false; handles.clearLayers(); points = []; status.textContent = 'Clique no mapa para adicionar vértices.'; preview(); });
        root.querySelector('[data-geometry-rectangle]')?.addEventListener('click', () => { drawing = true; editing = false; rectangle = true; handles.clearLayers(); points = []; preview(); });
        root.querySelector('[data-geometry-undo]').addEventListener('click', () => { if (drawing) { points.pop(); preview(); } });
        root.querySelector('[data-geometry-finish]').addEventListener('click', () => {
            if (!drawing || points.length < (rectangle || lineMode ? 2 : 3)) { status.textContent = `Adicione pelo menos ${rectangle || lineMode ? 'dois' : 'três'} pontos.`; return; }
            if (rectangle) {
                const [[x1, y1], [x2, y2]] = points;
                const ring = [[x1, y1], [x2, y1], [x2, y2], [x1, y2], [x1, y1]];
                geometry = {type: 'Polygon', coordinates: [ring]};
            } else geometry = lineMode ? {type: 'LineString', coordinates: points} : {type: 'Polygon', coordinates: [[...points, [...points[0]]]]};
            input.value = JSON.stringify(geometry);
            const manual = root.querySelector('[data-geometry-manual]'); if (manual) manual.value = '1';
            coordinates(L.geoJSON(geometry).getBounds().getCenter());
            drawing = false; render(); status.textContent = `${rectangle ? 'Retângulo' : (lineMode ? 'Linha' : 'Polígono')} concluído. Salve para registrar.`;
        });
        root.querySelector('[data-geometry-edit]').addEventListener('click', () => {
            if (!editablePoints()) { status.textContent = 'Desenhe uma linha ou um polígono para editar os vértices.'; return; }
            drawing = false; editing = !editing; render(false);
            status.textContent = editing ? 'Arraste os marcadores para ajustar os vértices.' : 'Edição de vértices encerrada.';
        });
        root.querySelector('[data-geometry-clear]').addEventListener('click', () => { drawing = false; editing = false; points = []; geometry = null; input.value = ''; if (lat) lat.value = ''; if (lng) lng.value = ''; render(); status.textContent = 'Localização removida.'; });
        root.addEventListener('geoveil:geometry-set', event => {
            editing = false; geometry = event.detail.geometry;
            input.value = JSON.stringify(geometry);
            const manual = root.querySelector('[data-geometry-manual]'); if (manual) manual.value = '0';
            drawing = false; points = []; render(); status.textContent = 'Traçado atualizado pelos nós selecionados.';
        });
        map.on('click', event => {
            if (drawing) { points.push([event.latlng.lng, event.latlng.lat]); if (rectangle && points.length === 2) root.querySelector('[data-geometry-finish]').click(); else preview(); return; }
            if (lineMode || editing) return;
            editing = false; geometry = null; input.value = ''; coordinates(event.latlng); render(); status.textContent = 'Ponto selecionado. Salve para registrar.';
        });
        render();
    }
}
