document.addEventListener('DOMContentLoaded', function () {

    var mapaDiv = document.getElementById('mapa');
    if (!mapaDiv) {
        console.error('Div #mapa não encontrada');
        return;
    }

    // Inicializa mapa
    var map = L.map('mapa').setView([-3.5, -52], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        crossOrigin: true
    }).addTo(map);

    // 🔴 Garante renderização correta
    setTimeout(function () {
        map.invalidateSize();
    }, 300);

    var drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    var drawControl = new L.Control.Draw({
        draw: {
            polygon: true,
            rectangle: true,
            polyline: false,
            circle: false,
            marker: false,
            circlemarker: false
        },
        edit: {
            featureGroup: drawnItems
        }
    });

    map.addControl(drawControl);

    map.on(L.Draw.Event.CREATED, function (event) {
        drawnItems.clearLayers();
        drawnItems.addLayer(event.layer);

        var geojson = event.layer.toGeoJSON().geometry;
        document.getElementById('poligono').value = JSON.stringify(geojson);
    });

    // SUBMIT CONTROLADO
    var form = document.getElementById('form-alerta');

    if (!form) {
        console.error('Formulário #form-alerta não encontrado');
        return;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!document.getElementById('poligono').value) {
            alert('E obrigatorio desenhar a area afetada no mapa.');
            return;
        }

        html2canvas(document.getElementById('mapa'), {
            useCORS: true,
            allowTaint: true
        }).then(function (canvas) {

            document.getElementById('imagem_mapa').value =
                canvas.toDataURL('image/png');

            form.submit();
        });
    });

});

