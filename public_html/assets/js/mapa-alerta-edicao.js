document.addEventListener('DOMContentLoaded', function () {

    const map = L.map('mapa').setView([-3.5, -52], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

    setTimeout(() => map.invalidateSize(), 300);

    const drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    if (POLIGONO_EXISTENTE) {
        const layer = L.geoJSON(POLIGONO_EXISTENTE);
        drawnItems.addLayer(layer);
        map.fitBounds(layer.getBounds());
    }

    const drawControl = new L.Control.Draw({
        draw: {
            polygon: true,
            rectangle: true,
            polyline: false,
            marker: false,
            circle: false,
            circlemarker: false
        },
        edit: {
            featureGroup: drawnItems
        }
    });

    map.addControl(drawControl);

    map.on(L.Draw.Event.CREATED, function (e) {
        drawnItems.clearLayers();
        drawnItems.addLayer(e.layer);

        document.getElementById('poligono').value =
            JSON.stringify(e.layer.toGeoJSON().geometry);
    });

    const form = document.getElementById('form-alerta');

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        html2canvas(document.getElementById('mapa'), {
            useCORS: true,
            allowTaint: true
        }).then(canvas => {
            document.getElementById('imagem_mapa').value =
                canvas.toDataURL('image/png');
            form.submit();
        });
    });

});

