import { surfaceColors } from './surface-data.js';
import 'maplibre-gl/dist/maplibre-gl.css';
import 'leaflet/dist/leaflet.css';
import '../css/workspace.css';
import { maplibreAdapter, leafletAdapter } from './adapters.js';
import { filteredFeatures, aggregate, numeric, timeSeries } from './state.js';

const plugins = new Map([['maplibre', maplibreAdapter], ['leaflet', leafletAdapter]]);
export function registerPlugin(name, factory) { plugins.set(name, factory); }
const number = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 });
function el(tag, className, text) {
    const node = document.createElement(tag); node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}
function button(label, action, className = 'gv-button') {
    const node = el('button', className, label); node.type = 'button'; node.addEventListener('click', action); return node;
}
export async function mount(root, config) {
    if (root.geoveil) root.geoveil.destroy();
    root.replaceChildren();
    root.dataset.theme = config.theme ?? 'light';
    root.dataset.layout = config.layout ?? 'workspace';
    const controller = new AbortController();
    const surfaceControls = new Map();
    const values = {}; const visible = new Set(config.layers.map(layer => layer.id));
    let search = '', selected = null, selectedFeature = null, adapter = null, disposed = false;
    const header = el('header', 'gv-header');
    const brand = el('div', 'gv-brand'); brand.append(el('span', 'gv-eyebrow', 'GEOVEIL 2 / EXPLORADOR'), el('h1', '', config.title));
    header.append(brand, el('span', 'gv-engine', ({ maplibre: 'MapLibre', leaflet: 'Leaflet' })[config.plugin.id] ?? config.plugin.id));
    const body = el('div', 'gv-body');
    const aside = el('aside', 'gv-sidebar'); aside.setAttribute('aria-label', 'Filtros e camadas');
    const stage = el('main', 'gv-stage');
    const toolbar = el('div', 'gv-toolbar');
    const canvas = el('div', 'gv-canvas'); canvas.setAttribute('aria-label', 'Mapa interativo');
    const status = el('p', 'gv-status', 'Carregando mapa…'); status.setAttribute('role', 'status');
    const panel = el('aside', 'gv-panel'); panel.setAttribute('aria-label', 'Análises e detalhes');
    const stats = el('div', 'gv-stats');
    const detail = el('section', 'gv-detail');
    const charts = el('section', 'gv-charts');
    const results = el('section', 'gv-results');
    toolbar.append(el('span', 'gv-toolbar-title', 'Visão territorial'), button('Enquadrar dados', () => adapter?.fit(current().flat())), button('Limpar seleção', () => { selected = null; render(); }));
    if (config.layout === 'map') {
        const togglePanel = (target, label) => {
            const control = button(label, () => { target.hidden = !target.hidden; control.setAttribute('aria-expanded', String(!target.hidden)); });
            target.hidden = true; control.setAttribute('aria-expanded', 'false'); toolbar.prepend(control);
            return control;
        };
        togglePanel(aside, 'Filtros e camadas');
        togglePanel(panel, 'Indicadores e detalhes');
    }
    const overlays = el('div', 'gv-map-overlays');
    config.layers.filter(layer => layer.type === 'surface').forEach(layer => {
        const legend = el('section', 'gv-surface-legend');
        legend.append(el('strong', '', layer.label), el('p', 'gv-muted', 'Intensidade relativa na cidade'));
        const values = layer.data.features.map(feature => numeric(feature.properties[layer.surface.field])).filter(value => value !== null);
        const range = el('div', 'gv-range'); range.append(el('span', '', `Mín. ${number.format(values.length ? Math.min(...values) : 0)}`), el('span', '', `Máx. ${number.format(values.length ? Math.max(...values) : 0)}`));
        const gradient = el('div', 'gv-gradient'); gradient.style.background = `linear-gradient(90deg, ${surfaceColors.map((color,index) => `${color} ${index*10}%, ${color} ${(index+1)*10}%`).join(',')})`;
        legend.append(range,gradient,el('div','gv-range','Baixo · Moderado · Alto · Máximo'));
        const control = el('section','gv-altitude'); const label = el('label','','Altitude · corte de intensidade');
        const input = el('input',''); input.type='range'; input.min='1'; input.max='100'; input.value='1';
        const output = el('output','','Visível: 1%–100%');
        const set = value => { input.value=value; output.textContent=`Visível: ${value}%–100%`; adapter?.cutoff?.(layer.id,Number(value)); updateSurfaceLegends(current()); };
        input.addEventListener('input',()=>set(input.value));
        label.append(input); control.append(label,output);
        const presets = el('div','gv-presets'); [['Tudo',1],['50%',50],['Pico',100]].forEach(([title,value])=>presets.append(button(title,()=>set(value))));
        control.append(presets,el('p','gv-muted','Altura representa intensidade, não relevo.'));
        overlays.append(legend,control);
        surfaceControls.set(layer.id, {legend, control, range, input});
    });
    if (config.layout !== 'embedded') stage.append(toolbar);
    stage.append(canvas, status, overlays);
    aside.append(el('h2', '', 'Explorar'));
    const fields = [];
    if (config.search) {
        const label = el('label', 'gv-field', 'Buscar nos dados');
        const input = el('input', 'gv-input'); input.type = 'search'; input.placeholder = 'Nome, cidade ou atributo';
        input.addEventListener('input', () => { search = input.value; selected = null; render(); });
        label.append(input); aside.append(label); fields.push(input);
    }
    if (config.geocoder) {
        const form = el('form', 'gv-geocoder');
        const label = el('label', 'gv-field', 'Buscar lugar ou endereço');
        const input = el('input', 'gv-input'); input.type = 'search'; input.required = true; input.minLength = 3; input.maxLength = 200; input.placeholder = 'Cidade, rua ou lugar';
        const submit = el('button', 'gv-button', 'Localizar'); submit.type = 'submit';
        const output = el('div', 'gv-geocode-results'); output.setAttribute('aria-live', 'polite');
        label.append(input); form.append(label, submit, output); aside.append(form);
        form.addEventListener('submit', async event => {
            event.preventDefault(); submit.disabled = true; output.textContent = 'Buscando…';
            try {
                const url = new URL(config.geocoder, window.location.href); url.searchParams.set('q', input.value.trim());
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message ?? 'Não foi possível localizar.');
                output.replaceChildren();
                (payload.results ?? []).forEach(result => output.append(button(result.label, () => {
                    adapter?.fit([{ geometry: { type: 'Point', coordinates: [result.longitude, result.latitude] } }]);
                    root.dispatchEvent(new CustomEvent('geoveil:geocode', { bubbles: true, detail: result }));
                }, 'gv-result')));
                if (!payload.results?.length) output.textContent = 'Nenhum lugar encontrado.';
            } catch (error) { if (!disposed) output.textContent = error.message; }
            finally { submit.disabled = false; }
        });
    }
    config.filters.forEach((filter, index) => {
        const label = el(filter.type === 'date' ? 'fieldset' : 'label', 'gv-field');
        label.append(el(filter.type === 'date' ? 'legend' : 'span', '', filter.label));
        if (filter.type === 'select') {
            const input = el('select', 'gv-input'); input.append(new Option('Todos', ''));
            const options = new Set(config.layers.filter(layer => !filter.layer || layer.id === filter.layer).flatMap(layer => layer.data.features.map(feature => feature.properties[filter.field])).filter(value => value !== null && value !== undefined));
            [...options].sort((a,b) => String(a).localeCompare(String(b), 'pt-BR')).forEach(value => input.append(new Option(String(value), String(value))));
            input.addEventListener('change', () => { values[index] = input.value; selected = null; render(); }); label.append(input); fields.push(input);
        } else {
            for (const [part, caption] of [['from', 'De'], ['to', 'Até (UTC)']]) {
                const sublabel = el('label', '', caption); const input = el('input', 'gv-input'); input.type = 'date';
                input.addEventListener('change', () => { values[index] = { ...values[index], [part]: input.value }; selected = null; render(); });
                sublabel.append(input); label.append(sublabel); fields.push(input);
            }
        }
        aside.append(label);
    });
    aside.append(button('Redefinir filtros', () => { fields.forEach(input => { input.value = ''; }); Object.keys(values).forEach(key => delete values[key]); search = ''; selected = null; render(); }, 'gv-reset'));
    aside.append(el('h2', '', 'Camadas'));
    const layerToggles = new Map();
    config.layers.forEach(layer => {
        const label = el('label', 'gv-layer'); const toggle = document.createElement('input'); toggle.type = 'checkbox'; toggle.checked = true;
        toggle.addEventListener('change', () => { toggle.checked ? visible.add(layer.id) : visible.delete(layer.id); selected = null; render(); });
        layerToggles.set(layer.id, toggle);
        label.append(toggle, el('span', '', layer.label)); aside.append(label);
        if (layer.scale) {
            const legend = el('div', 'gv-legend'); legend.append(el('span', 'gv-caption', layer.scale.field));
            layer.scale.colors.forEach((color, index) => {
                const row = el('div', 'gv-legend-row'); const swatch = el('span', 'gv-swatch'); swatch.style.backgroundColor = color;
                const breaks = layer.scale.breaks;
                const text = index === 0 ? `< ${number.format(breaks[0])}` : index === breaks.length ? `≥ ${number.format(breaks[index - 1])}` : `${number.format(breaks[index - 1])} – < ${number.format(breaks[index])}`;
                row.append(swatch, el('span', '', text)); legend.append(row);
            });
            aside.append(legend);
        }
    });
    panel.append(stats, detail, charts, results);
    if (config.layout === 'embedded') body.append(stage);
    else body.append(aside, stage, panel);
    if (config.layout !== 'embedded') root.append(header);
    root.append(body);
    function current(selection = null) {
        return config.layers.map(layer => visible.has(layer.id) ? filteredFeatures(layer, config.filters, values, search, selection) : []);
    }
    function select(layer, id, renderedFeature = null) {
        selected = { layer, id }; selectedFeature = renderedFeature;
        if (config.layout === 'map') { panel.hidden = false; const control = [...toolbar.querySelectorAll('button')].find(button => button.textContent === 'Indicadores e detalhes'); control?.setAttribute('aria-expanded', 'true'); }
        render();
        const item = renderedFeature ?? config.layers.find(item => item.id === layer)?.data.features.find(feature => String(feature.id) === String(id));
        if (item && config.fitOnSelect !== false) adapter?.fit([item]);
        root.dispatchEvent(new CustomEvent('geoveil:select', { bubbles: true, detail: { layer, feature: item } }));
    }
    function updateSurfaceLegends(data) {
        config.layers.forEach((layer,index) => {
            const ui = surfaceControls.get(layer.id); if (!ui) return;
            const values = data[index].map(feature => numeric(feature.properties[layer.surface.field])).filter(value => value !== null);
            ui.legend.hidden = ui.control.hidden = !values.length;
            if (!values.length) return;
            const min = Math.min(...values), max = Math.max(...values), percentage = Number(ui.input.value);
            const start = percentage === 1 ? min : percentage === 100 ? max : min + (max-min)*percentage/100;
            ui.range.replaceChildren(el('span','',`Mín. ${number.format(start)}`), el('span','',`Máx. ${number.format(max)}`));
        });
    }
    function render() {
        const data = current(); adapter?.update(data, visible); updateSurfaceLegends(data);
        const scoped = current(selected);
        const count = data.reduce((sum, features) => sum + features.length, 0);
        results.replaceChildren(el('h2', '', `Registros · ${number.format(count)}`));
        detail.replaceChildren(el('h2', '', 'Detalhes'));
        if (selected) {
            const layer = config.layers.find(layer => layer.id === selected.layer);
            const feature = selectedFeature ?? layer.data.features.find(feature => String(feature.id) === String(selected.id));
            const entries = Object.entries(layer.popup);
            if (feature) {
                const dl = el('dl', 'gv-attributes');
                entries.forEach(([field, label]) => {
                    const value = feature.properties[field];
                    dl.append(el('dt', '', label), el('dd', '', value == null ? 'Sem informação' : typeof value === 'boolean' ? (value ? 'Sim' : 'Não') : String(value)));
                });
                detail.append(dl);
                if (layer.recordAction && feature.id != null) {
                    const link = el('a', 'gv-record-action', layer.recordAction.label);
                    link.href = layer.recordAction.url.replace('{id}', encodeURIComponent(String(feature.id)));
                    if (layer.recordAction.modal) link.dataset.geoveilModalLink = '';
                    detail.append(link);
                }
            }
        } else detail.append(el('p', 'gv-muted', 'Selecione um elemento no mapa, gráfico ou lista para consultar seus atributos.'));
        stats.replaceChildren(); charts.replaceChildren();
        config.widgets.forEach(widget => {
            const index = config.layers.findIndex(layer => layer.id === widget.layer);
            const features = scoped[index] ?? [];
            if (widget.type === 'stat') {
                const card = el('article', 'gv-stat'); card.append(el('span', '', widget.label), el('strong', '', number.format(aggregate(widget, features)))); stats.append(card);
            } else if (widget.type === 'line') {
                const card = el('article', 'gv-chart'); card.append(el('h2', '', widget.label));
                const points = timeSeries(widget, features);
                if (!points.length) card.append(el('p', 'gv-muted', 'Sem medições para os filtros atuais.'));
                else {
                    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                    svg.setAttribute('viewBox', '0 0 260 140'); svg.setAttribute('role', 'img'); svg.setAttribute('aria-label', widget.label);
                    const lo = Math.min(...points.map(point => point.value)); const hi = Math.max(...points.map(point => point.value));
                    const start = points[0].time, end = points.at(-1).time;
                    const positions = points.map(point => [10 + 240 * (point.time - start) / (end - start || 1), 120 - 100 * (point.value - lo) / (hi - lo || 1)]);
                    const path = document.createElementNS(svg.namespaceURI, 'polyline');
                    path.setAttribute('points', positions.map(point => point.join(',')).join(' ')); path.setAttribute('fill', 'none'); path.setAttribute('stroke', '#0f766e'); path.setAttribute('stroke-width', '2'); svg.append(path);
                    positions.forEach(([x,y], index) => {
                        const dot = document.createElementNS(svg.namespaceURI, 'circle'); dot.setAttribute('cx', x); dot.setAttribute('cy', y); dot.setAttribute('r', '3'); dot.setAttribute('fill', '#0f766e');
                        const title = document.createElementNS(svg.namespaceURI, 'title'); title.textContent = `${new Date(points[index].time).toISOString()}: ${number.format(points[index].value)}`; dot.append(title); svg.append(dot);
                    });
                    card.append(svg, el('p', 'gv-muted', `${new Date(start).toLocaleDateString('pt-BR', { timeZone: 'UTC' })} – ${new Date(end).toLocaleDateString('pt-BR', { timeZone: 'UTC' })} · ${number.format(lo)} a ${number.format(hi)}`));
                    const list = el('details', 'gv-series-data'); list.append(el('summary', '', 'Consultar medições (UTC)'));
                    points.slice(0, 50).forEach(point => list.append(button(`${new Date(point.time).toISOString()} · ${number.format(point.value)}`, () => select(widget.layer, point.feature.id), 'gv-result')));
                    if (points.length > 50) list.append(el('p', 'gv-muted', 'Lista limitada às primeiras 50 medições. O gráfico inclui todas.'));
                    card.append(list);
                }
                charts.append(card);
            } else {
                const card = el('article', 'gv-chart'); card.append(el('h2', '', widget.label));
                const rows = [...(data[index] ?? [])].filter(feature => numeric(feature.properties[widget.field]) !== null).sort((a,b) => Number(b.properties[widget.field]) - Number(a.properties[widget.field]));
                const max = Math.max(1, ...rows.map(feature => Math.abs(Number(feature.properties[widget.field]))));
                rows.slice(0, 12).forEach(feature => {
                    const row = button('', () => select(widget.layer, feature.id), 'gv-bar-row');
                    row.setAttribute('aria-pressed', String(selected?.layer === widget.layer && String(selected.id) === String(feature.id)));
                    const value = Number(feature.properties[widget.field]);
                    row.append(el('span', 'gv-bar-label', String(feature.properties[widget.labelField] ?? feature.id)), el('strong', '', number.format(value)));
                    const track = el('span', 'gv-bar-track'); const bar = el('span', 'gv-bar'); bar.style.width = `${Math.abs(value) / max * 100}%`; track.append(bar); row.append(track); card.append(row);
                });
                if (!rows.length) card.append(el('p', 'gv-muted', 'Sem dados para os filtros atuais.'));
                if (rows.length > 12) card.append(el('p', 'gv-muted', 'Exibindo os 12 maiores valores.'));
                charts.append(card);
            }
        });
        let shown = 0;
        config.layers.forEach((layer, index) => data[index].forEach(feature => {
            if (shown++ >= 50) return;
            const first = Object.keys(layer.popup)[0];
            results.append(button(String(feature.properties[first] ?? feature.id), () => select(layer.id, feature.id), 'gv-result'));
        }));
        if (!count) results.append(el('p', 'gv-muted', 'Nenhum registro encontrado.'));
        else if (count > 50) results.append(el('p', 'gv-muted', 'Exibindo os primeiros 50. Refine os filtros.'));
    }
    const instance = { setLayerVisible(id, enabled) {
        const toggle = layerToggles.get(id);
        if (!toggle) throw new Error(`Camada desconhecida: ${id}`);
        toggle.checked = Boolean(enabled);
        toggle.dispatchEvent(new Event('change'));
    }, getLayerFeatures(id) {
        const layer = config.layers.find(item => item.id === id);
        if (!layer) throw new Error(`Camada desconhecida: ${id}`);
        return layer.data.features.map(feature => structuredClone(feature));
    }, updateLayer(id, features) {
        const layer = config.layers.find(item => item.id === id);
        if (!layer) throw new Error(`Camada desconhecida: ${id}`);
        if (!Array.isArray(features)) throw new TypeError('As feições da camada devem ser uma lista.');
        layer.data.features = features;
        if (selected?.layer === id && !features.some(feature => String(feature.id) === String(selected.id))) selected = null;
        render();
    }, setTheme(theme) {
        if (!['dark', 'light'].includes(theme)) throw new Error('Tema inválido.');
        config.theme = theme; root.dataset.theme = theme; adapter?.theme?.(theme);
    }, destroy() { disposed = true; controller.abort(); adapter?.destroy(); root.replaceChildren(); delete root.geoveil; } };
    root.geoveil = instance;
    window.addEventListener('pagehide', event => { if (!event.persisted) instance.destroy(); }, { once: true, signal: controller.signal });
    render();
    try {
        const factory = plugins.get(config.plugin.id);
        if (!factory) throw new Error(`Plugin não registrado: ${config.plugin.id}`);
        adapter = await factory(canvas, config, select, message => { status.textContent = message; }, layers => {
            for (const [id, features] of Object.entries(layers)) {
                const layer = config.layers.find(item => item.id === id);
                if (layer) layer.data.features = features;
            }
            if (!disposed) render();
        });
        if (disposed) { adapter.destroy(); return instance; }
        status.textContent = '';
        adapter.theme?.(config.theme);
        for (const [id, ui] of surfaceControls) adapter.cutoff?.(id, Number(ui.input.value));
        render();
        if (config.fitOnLoad) adapter.fit(current().flat());
    } catch (error) { status.textContent = `Não foi possível iniciar o mapa: ${error.message}`; }
    return instance;
}
export function boot(scope = document, options = {}) {
    return Promise.all([...scope.querySelectorAll('[data-geoveil]')].filter(root => !root.geoveil).map(root => {
        const config = JSON.parse(root.querySelector('[data-geoveil-config]').textContent);
        if (options.theme) config.theme = options.theme;
        return mount(root, config);
    }));
}
