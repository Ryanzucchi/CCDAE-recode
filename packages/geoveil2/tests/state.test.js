import { test } from 'node:test';
import assert from 'node:assert/strict';
import { filteredFeatures, aggregate, colorFor, vectorColor, boundsOf } from '../resources/js/state.js';
const features = [
    { id: 1, properties: { name: 'Centro', city: 'A', population: 100, date: '2026-01-01T23:59:59Z' }, geometry: { type: 'Point', coordinates: [-57, -16] } },
    { id: 2, properties: { name: 'Norte', city: 'B', population: 200, date: '2026-01-02T00:00:00Z' }, geometry: { type: 'Point', coordinates: [-56, -15] } },
    { id: 3, properties: { name: 'Sul', city: 'A', population: null, date: null }, geometry: { type: 'Point', coordinates: [-58, -17] } },
];
const layer = { id: 'districts', data: { features }, color: '#000000', scale: { field: 'population', breaks: [100, 200], colors: ['#111111', '#222222', '#333333'] } };
test('filters compose with search and selection without mutating source', () => {
    const filters = [{ type: 'select', field: 'city', layer: 'districts' }];
    assert.deepEqual(filteredFeatures(layer, filters, { 0: 'A' }, 'centro').map(f => f.id), [1]);
    assert.equal(filteredFeatures(layer, filters, { 0: 'A' }, '', { layer: 'districts', id: 2 }).length, 0);
    assert.equal(features.length, 3);
});
test('date interval includes the whole final UTC day and excludes missing timestamps', () => {
    const filtered = filteredFeatures(layer, [{ type: 'date', field: 'date' }], { 0: { from: '2026-01-01', to: '2026-01-01' } });
    assert.deepEqual(filtered.map(f => f.id), [1]);
});
test('stats use only the supplied filtered data and handle nulls', () => {
    assert.equal(aggregate({ operation: 'sum', field: 'population' }, features), 300);
    assert.equal(aggregate({ operation: 'count' }, features.slice(0, 1)), 1);
});
test('scale boundaries match legend; missing values have a neutral color', () => {
    assert.equal(colorFor(layer, { population: 100 }), '#222222');
    assert.equal(colorFor(layer, { population: 200 }), '#333333');
    assert.equal(colorFor(layer, { population: null }), '#94a3b8');
});
test('ordered category colors follow source order across polygon and point layers', () => {
    const categoryLayer = { categories: { field: 'id', colors: ['#111111', '#222222', '#333333'], orderedValues: [42, 7, 19] } };
    assert.equal(colorFor(categoryLayer, { id: 42 }), '#111111');
    assert.equal(colorFor(categoryLayer, { id: 7 }), '#222222');
    assert.equal(colorFor(categoryLayer, { id: 19 }), '#333333');
    assert.equal(colorFor(categoryLayer, { id: 99 }), '#94a3b8');
});
test('traffic categories keep the original colors in GeoJSON and vector tiles', () => {
    const road = { color: '#22c55e', categories: { field: 'nivel_congestionamento',
        colors: ['#22c55e', '#eab308', '#f97316', '#ef4444'], orderedValues: ['livre', 'moderado', 'intenso', 'parado'] } };
    assert.equal(colorFor(road, { nivel_congestionamento: 'Moderado' }), '#eab308');
    assert.equal(colorFor(road, { nivel_congestionamento: '34' }), '#22c55e');
    assert.deepEqual(vectorColor(road), ['match', ['downcase', ['to-string', ['coalesce', ['get', 'nivel_congestionamento'], '']]],
        'livre', '#22c55e', 'moderado', '#eab308', 'intenso', '#f97316', 'parado', '#ef4444', '#22c55e']);
});
test('bounds support nested polygon coordinates and empty data', () => {
    assert.deepEqual(boundsOf(features), [[-58, -17], [-56, -15]]);
    assert.equal(boundsOf([]), null);
    assert.deepEqual(boundsOf([{ geometry: { coordinates: [[[[1, 2], [3, 4]]]] } }]), [[1, 2], [3, 4]]);
});
test('time series omit missing readings and sort by timestamp', async () => {
    const { timeSeries } = await import('../resources/js/state.js');
    const points = timeSeries({ time: 'date', field: 'population' }, [...features].reverse());
    assert.deepEqual(points.map(point => point.feature.id), [1, 2]);
});
test('surface samples preserve district ids, ignore holes and normalize values', async () => {
    const {sampleFeatures} = await import('../resources/js/surface-data.js');
    const source = [
        {id:1, properties:{value:10},geometry:{type:'Polygon',coordinates:[[[0,0],[1,0],[1,1],[0,1],[0,0]],[[.3,.3],[.7,.3],[.7,.7],[.3,.7],[.3,.3]]]}},
        {id:2,properties:{value:20},geometry:{type:'Point',coordinates:[2,2]}}
    ];
    const output = sampleFeatures(source,'value');
    assert.ok(output.features.length > 1);
    assert.ok(output.features.filter(f=>f.id===1).every(f=>f.properties.weight===0));
    assert.ok(output.features.filter(f=>f.id===1).every(f=>f.geometry.coordinates[0]<.3 || f.geometry.coordinates[0]>.7 || f.geometry.coordinates[1]<.3 || f.geometry.coordinates[1]>.7));
    assert.equal(output.features.at(-1).properties.weight,1);
    assert.equal(sampleFeatures([],'value').features.length,0);
});
