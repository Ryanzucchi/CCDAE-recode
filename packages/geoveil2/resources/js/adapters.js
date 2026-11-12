import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import { boundsOf, colorFor, vectorColor } from './state.js';

function recordPopup(layer, feature) {
    const content = document.createElement('div');
    content.className = 'gv-record-popup';
    const titleField = Object.keys(layer.popup)[0];
    const title = document.createElement('strong');
    title.textContent = String(feature.properties?.[titleField] ?? layer.label);
    content.append(title);
    const fields = Object.entries(layer.popup).slice(1);
    if (fields.length) {
        const details = document.createElement('dl');
        for (const [field, label] of fields) {
            const term = document.createElement('dt');
            term.textContent = label;
            const value = document.createElement('dd');
            const raw = feature.properties?.[field];
            value.textContent = raw == null || raw === '' ? 'Sem informação' : typeof raw === 'boolean' ? (raw ? 'Sim' : 'Não') : String(raw);
            details.append(term, value);
        }
        content.append(details);
    }
    if (layer.recordAction && feature.id != null) {
        const link = document.createElement('a');
        link.textContent = layer.recordAction.label;
        link.href = layer.recordAction.url.replace('{id}', encodeURIComponent(String(feature.id)));
        if (layer.recordAction.modal) link.dataset.geoveilModalLink = '';
        content.append(link);
    }
    return content;
}

function flowArrow(bidirectional) {
    const canvas = document.createElement('canvas'); canvas.width = 48; canvas.height = 24;
    const context = canvas.getContext('2d');
    for (const [color, width] of [['#111827', 7], ['#ffffff', 3]]) {
        context.strokeStyle = color; context.lineWidth = width; context.lineCap = 'round'; context.lineJoin = 'round';
        context.beginPath(); context.moveTo(bidirectional ? 8 : 4, 12); context.lineTo(bidirectional ? 40 : 42, 12);
        context.moveTo(33, 4); context.lineTo(43, 12); context.lineTo(33, 20);
        if (bidirectional) { context.moveTo(15, 4); context.lineTo(5, 12); context.lineTo(15, 20); }
        context.stroke();
    }
    return context.getImageData(0, 0, canvas.width, canvas.height);
}

export async function maplibreAdapter(container, config, select, report, onViewport) {
    const maplibregl = await import('maplibre-gl');
    maplibregl.setWorkerUrl(workerUrl);
    const hasSurface = config.layers.some(layer => layer.type === 'surface');
    const has3d = config.layers.some(layer => layer.type === 'building');
    const { surfaceLayer } = hasSurface ? await import('./surface.js') : {};
    const surfaces = new Map(), signatures = new Map(), cutoffs = new Map();
    const surfaceLayers = new Map(config.layers.filter(layer => layer.type === 'surface').map(layer => [layer.id, layer]));
    const map = new maplibregl.Map({ container, center: config.center, zoom: config.zoom, pitch: hasSurface ? 62 : has3d ? 48 : 0, bearing: hasSurface ? -18 : 0, style: {
        version: 8, sources: { base: { type: 'raster', tiles: [config.plugin.tiles ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize: 256, maxzoom: 19, attribution: config.plugin.attribution } },
        layers: [{ id: 'base', type: 'raster', source: 'base', paint: config.theme === 'dark' ? { 'raster-brightness-max': 0.36, 'raster-saturation': -0.72, 'raster-contrast': 0.18 } : {} }],
    }});
    map.on('error', () => report('Não foi possível carregar parte do mapa. Verifique sua conexão e a fonte cartográfica.'));
    map.addControl(new maplibregl.NavigationControl());
    map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }));
    let ready = false;
    let pending = config.layers.map(layer => layer.data.features);
    function colored(layer, features) {
        return { type: 'FeatureCollection', features: features.map(feature => ({ ...feature, properties: { ...feature.properties, __gv_color: colorFor(layer, feature.properties) } })) };
    }
    function updateSurface(layer, features) {
        const signature = features.map(feature => `${feature.id}:${feature.properties?.[layer.surface.field]}`).join('|');
        if (signatures.get(layer.id) === signature) return;
        signatures.set(layer.id, signature);
        if (map.getLayer(layer.id)) map.removeLayer(layer.id);
        surfaces.delete(layer.id);
        const surface = surfaceLayer(layer.id, features, layer.surface);
        if (surface) { surfaces.set(layer.id, surface); map.addLayer(surface); surface.setCutoff(cutoffs.get(layer.id) ?? 1); }
    }
    const tooltip = hasSurface ? document.createElement('div') : null;
    if (tooltip) {
        tooltip.className = 'gv-surface-tooltip';
        tooltip.hidden = true;
        container.append(tooltip);
    }
    const classifications = ['Mínimo', 'Muito baixo', 'Baixo', 'Baixo moderado', 'Moderado baixo', 'Moderado', 'Moderado alto', 'Elevado', 'Alto', 'Muito alto', 'Severo', 'Muito severo', 'Crítico', 'Extremo', 'Máximo'];
    function nearestSurface(point, radius = 24) {
        let closest = null, distance = radius;
        for (const [id, surface] of surfaces) for (const candidate of surface.candidates) {
            if (candidate.weight < surface.minWeight) continue;
            const projected = surface.project(candidate.coordinate, map.getCanvas().clientWidth, map.getCanvas().clientHeight);
            if (!projected) continue;
            const gap = Math.hypot(projected.x - point.x, projected.y - point.y);
            if (gap < distance) { distance = gap; closest = { id, surface, candidate }; }
        }
        return closest;
    }
    function hideSurfaceTooltip() {
        if (tooltip) tooltip.hidden = true;
        if (hasSurface) map.getCanvas().style.cursor = '';
    }
    if (tooltip) {
        map.on('mousemove', event => {
            const closest = nearestSurface(event.point);
            if (!closest) { hideSurfaceTooltip(); return; }
            const { id, surface, candidate } = closest;
            const layer = surfaceLayers.get(id);
            const titleField = Object.keys(layer.popup ?? {})[0] ?? 'name';
            const value = Number(candidate.properties?.value) || 0;
            const relative = Math.min(.999, Math.max(0, (candidate.weight - surface.minWeight) / Math.max(.001, surface.maxWeight - surface.minWeight)));
            const band = Math.min(9, Math.floor(relative * 10));
            const rows = [
                `${layer.label}: ${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: Math.abs(value) >= 100 ? 0 : Math.abs(value) >= 1 ? 1 : 2 }).format(value)}`,
                `Classificação no corte: ${classifications[Math.min(14, Math.floor(relative * 15))]}`,
                `Faixa de cor ativa: ${band * 10}–${(band + 1) * 10}%`,
                `Percentual geral na cidade: ${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 }).format(candidate.weight * 100)}%`,
            ];
            if (Number(candidate.properties?.streets) > 1) rows.push(`${candidate.properties.streets} vias nesta área`);
            const title = document.createElement('strong');
            title.textContent = String(candidate.properties?.[titleField] ?? 'Local sem via identificada');
            tooltip.replaceChildren(title, ...rows.map(row => { const node = document.createElement('div'); node.textContent = row; return node; }));
            tooltip.style.left = `${event.point.x + 14}px`;
            tooltip.style.top = `${event.point.y + 14}px`;
            tooltip.hidden = false;
            map.getCanvas().style.cursor = 'pointer';
        });
        map.on('mouseout', hideSurfaceTooltip);
    }
    map.on('click', event => {
        const closest = nearestSurface(event.point, 28);
        if (closest) select(closest.id, closest.candidate.id);
    });
    const applyTheme = theme => {
        config.theme = theme;
        if (!ready) return;
        map.setPaintProperty('base', 'raster-brightness-max', theme === 'dark' ? .36 : 1);
        map.setPaintProperty('base', 'raster-saturation', theme === 'dark' ? -.72 : 0);
        map.setPaintProperty('base', 'raster-contrast', theme === 'dark' ? .18 : 0);
    };
    map.on('load', () => {
        ready = true;
        applyTheme(config.theme);
        if (config.layers.some(layer => layer.flowDirection)) {
            map.addImage('gv-flow-arrow', flowArrow(false), { pixelRatio: 2 });
            map.addImage('gv-flow-bidirectional', flowArrow(true), { pixelRatio: 2 });
        }
        config.layers.forEach((layer, index) => {
            if (layer.type === 'surface') { updateSurface(layer, pending[index]); return; }
            if (layer.vector) map.addSource(layer.id, { type: 'vector', url: layer.vector.tilejson });
            else map.addSource(layer.id, { type: 'geojson', data: colored(layer, pending[index]) });
            const types = { polygon: 'fill', point: 'circle', line: 'line', heatmap: 'heatmap', building: 'fill-extrusion' };
            const color = layer.vector ? vectorColor(layer) : ['get', '__gv_color'];
            const buildingColor = layer.scale ? ['interpolate', ['linear'], ['to-number', ['get', layer.scale.field], 0],
                0, layer.scale.colors[0], ...layer.scale.breaks.flatMap((breakpoint, index) => [breakpoint, layer.scale.colors[index + 1]])] : color;
            const paints = {
                polygon: { 'fill-color': color, 'fill-opacity': 0.72, 'fill-outline-color': '#134e4a' },
                point: { 'circle-color': color, 'circle-radius': 7, 'circle-stroke-width': 2, 'circle-stroke-color': '#ffffff' },
                line: { 'line-color': color, 'line-width': 4 },
                heatmap: { 'heatmap-radius': 28, 'heatmap-opacity': 0.75 },
                building: { 'fill-extrusion-color': buildingColor, 'fill-extrusion-height': ['to-number', ['get', layer.height ?? 'height'], 0], 'fill-extrusion-base': ['to-number', ['get', 'render_min_height'], 0], 'fill-extrusion-opacity': 0.78 },
            };
            map.addLayer({ id: layer.id, type: types[layer.type], source: layer.id,
                ...(layer.minZoom !== null ? { minzoom: layer.minZoom } : {}),
                ...(layer.vector ? { 'source-layer': layer.vector.sourceLayer, ...(layer.vector.filter ? { filter: layer.vector.filter } : {}) } : {}),
                paint: paints[layer.type] });
            if (layer.flowDirection) map.addLayer({
                id: `${layer.id}-direction`, type: 'symbol', source: layer.id,
                layout: { 'symbol-placement': 'line', 'symbol-spacing': 85,
                    'icon-image': ['match', ['coalesce', ['get', layer.flowDirection], 'ambos'], 'ambos', 'gv-flow-bidirectional', 'gv-flow-arrow'],
                    'icon-rotate': ['case', ['==', ['get', layer.flowDirection], 'fim_inicio'], 180, 0],
                    'icon-size': 0.8, 'icon-rotation-alignment': 'map', 'icon-keep-upright': false, 'icon-allow-overlap': true },
            });
            map.on('click', layer.id, event => {
                const feature = event.features?.[0];
                if (feature) {
                    select(layer.id, feature.id, feature);
                    if (Object.keys(layer.popup).length) {
                        new maplibregl.Popup({ maxWidth: '240px' })
                            .setLngLat(event.lngLat).setDOMContent(recordPopup(layer, feature)).addTo(map);
                    }
                }
            });
            map.on('mouseenter', layer.id, () => { map.getCanvas().style.cursor = 'pointer'; });
            map.on('mouseleave', layer.id, () => { map.getCanvas().style.cursor = ''; });
        });
    });
    const viewportLayers = config.layers.filter(layer => layer.viewport);
    let viewportRequest = null, viewportTimer = null;
    async function loadViewport() {
        if (!viewportLayers.length || !ready) return;
        viewportRequest?.abort(); viewportRequest = new AbortController();
        const current = viewportRequest;
        const bounds = map.getBounds();
        try {
            const layers = await Promise.all(viewportLayers.map(async layer => {
                if (map.getZoom() < (layer.minZoom ?? 0)) return [layer.id, []];
                const url = new URL(layer.viewport, window.location.href);
                for (const [key, value] of Object.entries({ west: bounds.getWest(), south: bounds.getSouth(), east: bounds.getEast(), north: bounds.getNorth() })) url.searchParams.set(key, String(value));
                const response = await fetch(url, { signal: current.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`Não foi possível carregar ${layer.label} da área visível (HTTP ${response.status}).`);
                return [layer.id, (await response.json()).features ?? []];
            }));
            if (!current.signal.aborted) onViewport?.(Object.fromEntries(layers));
        } catch (error) { if (!current.signal.aborted) report(error.message); }
    }
    map.on('moveend', () => { clearTimeout(viewportTimer); viewportTimer = setTimeout(loadViewport, 250); });
    map.once('load', loadViewport);
    const observer = new ResizeObserver(() => map.resize()); observer.observe(container);
    return {
        update(data, visible) {
            pending = data;
            if (ready) config.layers.forEach((layer, index) => {
                if (layer.type === 'surface') updateSurface(layer, data[index]);
                else if (layer.vector) {
                    map.setLayoutProperty(layer.id, 'visibility', visible?.has(layer.id) ? 'visible' : 'none');
                    if (layer.flowDirection) map.setLayoutProperty(`${layer.id}-direction`, 'visibility', visible?.has(layer.id) ? 'visible' : 'none');
                }
                else map.getSource(layer.id)?.setData(colored(layer, data[index]));
            });
        },
        theme: applyTheme,
        cutoff(id, percentage) { cutoffs.set(id, percentage); surfaces.get(id)?.setCutoff(percentage); hideSurfaceTooltip(); },
        fit(features) { const bounds = boundsOf(features); if (bounds) map.fitBounds(bounds, { padding: 55, maxZoom: 15, duration: 450 }); },
        destroy() { clearTimeout(viewportTimer); viewportRequest?.abort(); observer.disconnect(); map.remove(); },
    };
}

export async function leafletAdapter(container, config, select, report, onViewport) {
    const { default: L } = await import('leaflet');
    container.classList.toggle('gv-dark-tiles', config.theme === 'dark');
    const map = L.map(container, { preferCanvas: true }).setView([config.center[1], config.center[0]], config.zoom);
    L.tileLayer(config.plugin.tiles ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: config.plugin.attribution })
        .on('tileerror', () => report('Não foi possível carregar parte do mapa. Verifique sua conexão e a fonte cartográfica.')).addTo(map);
    L.control.scale({ imperial: false }).addTo(map);
    const groups = config.layers.map(layer => L.geoJSON([], {
        style: feature => ({ color: layer.type === 'polygon' ? '#334155' : colorFor(layer, feature.properties), fillColor: colorFor(layer, feature.properties), weight: layer.type === 'polygon' ? 1.5 : 3, fillOpacity: 0.78 }),
        pointToLayer: (feature, latlng) => L.circleMarker(latlng, { radius: 7, fillOpacity: 0.9, color: colorFor(layer, feature.properties) }),
        onEachFeature: (feature, item) => item.on('click', event => {
            select(layer.id, feature.id);
            if (Object.keys(layer.popup).length) {
                L.popup({ maxWidth: 280 }).setLatLng(event.latlng)
                    .setContent(recordPopup(layer, feature)).openOn(map);
            }
        }),
    }).addTo(map));
    const viewportLayers = config.layers.filter(layer => layer.viewport);
    let request = null;
    async function loadViewport() {
        if (!viewportLayers.length) return;
        request?.abort(); request = new AbortController();
        const current = request;
        const bounds = map.getBounds();
        const area = { west: bounds.getWest(), south: bounds.getSouth(), east: bounds.getEast(), north: bounds.getNorth() };
        try {
            const layers = await Promise.all(viewportLayers.map(async layer => {
                if (map.getZoom() < (layer.minZoom ?? 0)) return [layer.id, []];
                const url = new URL(layer.viewport, window.location.href);
                for (const [key, value] of Object.entries(area)) url.searchParams.set(key, String(value));
                const response = await fetch(url, { signal: current.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`Não foi possível carregar ${layer.label} da área visível (HTTP ${response.status}).`);
                const body = await response.json();
                return [layer.id, body.features ?? []];
            }));
            if (!current.signal.aborted) onViewport?.(Object.fromEntries(layers));
        } catch (error) {
            if (!current.signal.aborted) report(error.message);
        }
    }
    map.on('moveend', loadViewport);
    loadViewport();
    const observer = new ResizeObserver(() => map.invalidateSize()); observer.observe(container);
    return {
        theme(theme) { container.classList.toggle('gv-dark-tiles', theme === 'dark'); },
        update(data) { groups.forEach((group, index) => group.clearLayers().addData({ type: 'FeatureCollection', features: data[index] })); },
        fit(features) { const bounds = boundsOf(features); if (bounds) map.fitBounds(bounds.map(([lng, lat]) => [lat, lng]), { padding: [35, 35], maxZoom: 15 }); },
        destroy() { request?.abort(); observer.disconnect(); map.remove(); },
    };
}
