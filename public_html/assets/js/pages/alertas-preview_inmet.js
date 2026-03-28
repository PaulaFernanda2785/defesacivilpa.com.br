(function () {
    const dataNode = document.getElementById('preview-inmet-data');
    const mapElement = document.getElementById('mapaPreview');

    if (!dataNode || !mapElement || typeof L === 'undefined') {
        return;
    }

    let pageData;

    try {
        pageData = JSON.parse(dataNode.textContent);
    } catch (error) {
        console.error('Falha ao carregar os dados da prévia do INMET.', error);
        return;
    }

    let geojson = pageData.geojson || null;

    if (typeof geojson === 'string') {
        try {
            geojson = JSON.parse(geojson);
        } catch (error) {
            console.error('Falha ao interpretar o GeoJSON da prévia do INMET.', error);
            geojson = null;
        }
    }

    const map = L.map(mapElement, {
        preferCanvas: true
    }).setView([-3.5, -52], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const layer = L.geoJSON(geojson, {
        style: {
            color: pageData.color || '#0b3c68',
            weight: 3,
            fillOpacity: 0.3
        }
    }).addTo(map);

    if (layer.getLayers().length) {
        map.fitBounds(layer.getBounds());
    } else {
        map.setView([-3.5, -52], 6);
    }

    setTimeout(function () {
        map.invalidateSize();
    }, 180);
})();
