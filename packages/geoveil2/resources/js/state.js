export function numeric(value) {
    if (value === null || value === undefined || value === '') return null;
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}
export function colorFor(layer, properties) {
    if (layer.categories) {
        const value = properties[layer.categories.field];
        if (value === null || value === undefined || value === '') return layer.color ?? '#94a3b8';
        const key = String(value).toLocaleLowerCase('pt-BR');
        if (layer.categories.orderedValues?.length) {
            const index = layer.categories.orderedValues.findIndex(item => String(item).toLocaleLowerCase('pt-BR') === key);
            return index < 0 ? layer.color ?? '#94a3b8' : layer.categories.colors[index % layer.categories.colors.length];
        }
        let hash = 0;
        for (let index = 0; index < key.length; index++) hash = (hash * 31 + key.charCodeAt(index)) >>> 0;
        return layer.categories.colors[hash % layer.categories.colors.length];
    }
    const scale = layer.scale;
    if (!scale) return layer.color;
    const value = numeric(properties[scale.field]);
    if (value === null) return '#94a3b8';
    return scale.colors[scale.breaks.filter(limit => value >= limit).length];
}
export function vectorColor(layer) {
    if (layer.categories?.orderedValues?.length) {
        const expression = ['match', ['downcase', ['to-string', ['coalesce', ['get', layer.categories.field], '']]]];
        layer.categories.orderedValues.forEach((value, index) => expression.push(String(value).toLocaleLowerCase('pt-BR'), layer.categories.colors[index % layer.categories.colors.length]));
        expression.push(layer.color ?? '#94a3b8');
        return expression;
    }
    return layer.color;
}
export function filteredFeatures(layer, filters, values, search = '', selected = null) {
    const query = search.toLocaleLowerCase('pt-BR').trim();
    return layer.data.features.filter(feature => {
        const props = feature.properties;
        if (selected && (selected.layer !== layer.id || String(selected.id) !== String(feature.id))) return false;
        if (query && !Object.values(props).some(value => String(value ?? '').toLocaleLowerCase('pt-BR').includes(query))) return false;
        return filters.every((filter, index) => {
            if (filter.layer && filter.layer !== layer.id) return true;
            const value = values[index];
            if (!value || (filter.type === 'date' && !value.from && !value.to)) return true;
            if (filter.type === 'select') return String(props[filter.field] ?? '') === value;
            const date = Date.parse(props[filter.field]);
            if (!Number.isFinite(date)) return false;
            return (!value.from || date >= Date.parse(value.from + 'T00:00:00Z')) && (!value.to || date < Date.parse(value.to + 'T00:00:00Z') + 86400000);
        });
    });
}
export function boundsOf(features) {
    let west = Infinity, south = Infinity, east = -Infinity, north = -Infinity;
    function visit(value) {
        if (!Array.isArray(value)) return;
        if (typeof value[0] === 'number' && typeof value[1] === 'number') {
            if (!Number.isFinite(value[0]) || !Number.isFinite(value[1])) return;
            west = Math.min(west, value[0]); east = Math.max(east, value[0]);
            south = Math.min(south, value[1]); north = Math.max(north, value[1]);
        } else value.forEach(visit);
    }
    features.forEach(feature => visit(feature.geometry?.coordinates));
    return west === Infinity ? null : [[west, south], [east, north]];
}
export function aggregate(widget, features) {
    if (widget.operation === 'count') return features.length;
    return features.reduce((sum, feature) => sum + (numeric(feature.properties[widget.field]) ?? 0), 0);
}

export function timeSeries(widget, features) {
    return features.map(feature => ({ feature, time: Date.parse(feature.properties[widget.time]), value: numeric(feature.properties[widget.field]) }))
        .filter(point => Number.isFinite(point.time) && point.value !== null)
        .sort((a, b) => a.time - b.time);
}
