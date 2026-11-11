import 'leaflet/dist/leaflet.css';
export async function mountPointEditors() {
    const fields = document.querySelectorAll('[data-geoveil-point]');
    if (!fields.length) return;
    const L = (await import('leaflet')).default;
    fields.forEach(field => {
        const lat = field.parentElement.querySelector('[data-geoveil-latitude]');
        const lng = field.parentElement.querySelector('[data-geoveil-longitude]');
        const initial = Number.isFinite(Number(lat.value)) && lat.value !== '' && lng.value !== ''
            ? [Number(lat.value), Number(lng.value)] : [-16.07083, -57.6875];
        const map = L.map(field).setView(initial, 14);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution:'© OpenStreetMap contributors', maxZoom:19}).addTo(map);
        let marker = null;
        function place(position) {
            if (!marker) marker = L.circleMarker(position, {radius:8,color:'#0d9488',fillColor:'#14b8a6',fillOpacity:1}).addTo(map);
            else marker.setLatLng(position);
            lat.value = position.lat; lng.value = position.lng;
        }
        if (lat.value !== '' && lng.value !== '') place(L.latLng(initial));
        map.on('click', event => place(event.latlng));
    });
}
