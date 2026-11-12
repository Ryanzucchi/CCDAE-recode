import { numeric, boundsOf } from './state.js';
export const surfaceColors = ['#172554','#1d4ed8','#06b6d4','#10b981','#84cc16','#facc15','#f97316','#ef4444','#a21caf','#39ff14'];
function insideRing([x,y], ring) {
    let inside = false;
    for (let i=0,j=ring.length-1;i<ring.length;j=i++) {
        const [xi,yi]=ring[i], [xj,yj]=ring[j];
        if ((yi>y)!==(yj>y) && x<(xj-xi)*(y-yi)/((yj-yi)||1e-12)+xi) inside=!inside;
    }
    return inside;
}
export function sampleFeatures(features, field) {
    const valid = features.filter(feature => numeric(feature.properties[field]) !== null);
    const values = valid.map(feature => Number(feature.properties[field]));
    const min = Math.min(...values), max = Math.max(...values), range = max-min;
    const points = [];
    for (const feature of valid) {
        const value = Number(feature.properties[field]);
        const weight = range > 0 ? (value-min)/range : .5;
        const add = coordinates => points.push({type:'Feature',id:feature.id,geometry:{type:'Point',coordinates},properties:{...feature.properties,value,weight}});
        if (feature.geometry.type === 'Point') { add(feature.geometry.coordinates); continue; }
        const bounds = boundsOf([feature]); if (!bounds) continue;
        const [[west,south],[east,north]] = bounds;
        const cols = Math.max(3,Math.min(8,Math.ceil(Math.max(.0001,east-west)/.0022)));
        const rows = Math.max(3,Math.min(8,Math.ceil(Math.max(.0001,north-south)/.0022)));
        const polygons = feature.geometry.type === 'Polygon' ? [feature.geometry.coordinates] : feature.geometry.coordinates;
        const initial = points.length;
        for (let row=0;row<rows;row++) for (let col=0;col<cols;col++) {
            const point = [west+(col+.5)/cols*(east-west),south+(row+.5)/rows*(north-south)];
            if (polygons.some(polygon => insideRing(point,polygon[0]) && !polygon.slice(1).some(hole => insideRing(point,hole)))) add(point);
        }
        // A very narrow polygon still contributes a point on its boundary.
        if (points.length === initial && polygons[0]?.[0]?.[0]) add(polygons[0][0][0]);
    }
    return {type:'FeatureCollection',features:points};
}
