(function () {
    const IMAGE_ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    const IMAGE_MAX_BYTES = 5 * 1024 * 1024;
    const KML_MAX_BYTES = 10 * 1024 * 1024;

    function collectAreaFeatures(node, features) {
        if (!node || typeof node !== 'object') {
            return;
        }

        const type = node.type || null;

        if (type === 'FeatureCollection') {
            (node.features || []).forEach(function (feature) {
                collectAreaFeatures(feature, features);
            });
            return;
        }

        if (type === 'Feature') {
            collectAreaFeatures(node.geometry || null, features);
            return;
        }

        if (type === 'GeometryCollection') {
            (node.geometries || []).forEach(function (geometry) {
                collectAreaFeatures(geometry, features);
            });
            return;
        }

        if (type !== 'Polygon' && type !== 'MultiPolygon') {
            return;
        }

        features.push({
            type: 'Feature',
            properties: {},
            geometry: {
                type: type,
                coordinates: node.coordinates || []
            }
        });
    }

    function normalizeGeoJSON(input) {
        const features = [];
        collectAreaFeatures(input, features);

        return {
            type: 'FeatureCollection',
            features: features
        };
    }

    function formatBytes(bytes) {
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return '0 KB';
        }

        if (bytes < 1024 * 1024) {
            return Math.max(1, Math.round(bytes / 1024)) + ' KB';
        }

        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function extractFileName(path) {
        return String(path || '').split('/').pop() || '';
    }

    function setInputFile(input, file) {
        if (!input) {
            return false;
        }

        try {
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
            return true;
        } catch (error) {
            return false;
        }
    }

    function clearInputFile(input) {
        if (!input) {
            return;
        }

        input.value = '';

        try {
            input.files = new DataTransfer().files;
        } catch (error) {
            // Ignore browsers that do not allow programmatic reset of FileList.
        }
    }

    function getElementsByLocalName(root, localName) {
        if (!root || typeof root.querySelectorAll !== 'function') {
            return [];
        }

        const expected = String(localName || '').toLowerCase();

        return Array.from(root.querySelectorAll('*')).filter(function (node) {
            const nodeName = String(node.localName || node.nodeName || '').toLowerCase();
            return nodeName === expected;
        });
    }

    function getFirstElementByLocalName(root, localName) {
        const nodes = getElementsByLocalName(root, localName);
        return nodes.length ? nodes[0] : null;
    }

    function closeRing(points) {
        if (points.length < 3) {
            return points;
        }

        const first = points[0];
        const last = points[points.length - 1];

        if (first[0] === last[0] && first[1] === last[1]) {
            return points;
        }

        return points.concat([[first[0], first[1]]]);
    }

    function parseCoordinateText(text) {
        const coordinates = [];
        const source = String(text || '');
        const coordinatePattern = /(-?\d+(?:\.\d+)?(?:e[-+]?\d+)?)\s*,\s*(-?\d+(?:\.\d+)?(?:e[-+]?\d+)?)(?:\s*,\s*(-?\d+(?:\.\d+)?(?:e[-+]?\d+)?))?/gi;
        let match = coordinatePattern.exec(source);

        while (match !== null) {
            const lng = Number(match[1]);
            const lat = Number(match[2]);

            if (Number.isFinite(lng) && Number.isFinite(lat)) {
                coordinates.push([lng, lat]);
            }

            match = coordinatePattern.exec(source);
        }

        return closeRing(coordinates);
    }

    function parseBoundaryRings(polygonNode, boundaryTag) {
        return getElementsByLocalName(polygonNode, boundaryTag).map(function (boundaryNode) {
            const coordinatesNode = getFirstElementByLocalName(boundaryNode, 'coordinates');
            const ring = parseCoordinateText(coordinatesNode ? coordinatesNode.textContent : '');
            return ring.length >= 4 ? ring : null;
        }).filter(Boolean);
    }

    function polygonFeatureFromKmlPolygon(polygonNode) {
        const outerRings = parseBoundaryRings(polygonNode, 'outerboundaryis');

        if (!outerRings.length) {
            return null;
        }

        const coordinates = [outerRings[0]];

        parseBoundaryRings(polygonNode, 'innerboundaryis').forEach(function (ring) {
            coordinates.push(ring);
        });

        return {
            type: 'Feature',
            properties: {},
            geometry: {
                type: 'Polygon',
                coordinates: coordinates
            }
        };
    }

    function parseKmlDocument(xml) {
        const features = [];

        getElementsByLocalName(xml, 'polygon').forEach(function (polygonNode) {
            const feature = polygonFeatureFromKmlPolygon(polygonNode);

            if (feature) {
                features.push(feature);
            }
        });

        return normalizeGeoJSON({
            type: 'FeatureCollection',
            features: features
        });
    }

    function parseKml(file) {
        return new Promise(function (resolve, reject) {
            if (!file) {
                reject(new Error('Nenhum arquivo KML foi informado.'));
                return;
            }

            const reader = new FileReader();

            reader.onerror = function () {
                reject(new Error('Não foi possível ler o arquivo KML.'));
            };

            reader.onload = function () {
                const parser = new DOMParser();
                const xml = parser.parseFromString(String(reader.result || ''), 'text/xml');

                if (xml.querySelector('parsererror')) {
                    reject(new Error('O arquivo enviado não contém um KML válido.'));
                    return;
                }

                const normalized = parseKmlDocument(xml);

                if (!normalized.features.length) {
                    reject(new Error('O KML precisa conter pelo menos uma geometria de área.'));
                    return;
                }

                resolve(normalized);
            };

            reader.readAsText(file);
        });
    }

    function createPreviewImage(container, src, alt) {
        container.innerHTML = '';

        const image = document.createElement('img');
        image.src = src;
        image.alt = alt;
        container.appendChild(image);
    }

    function updateCharacterCounter(field, counter) {
        if (!field || !counter) {
            return;
        }

        const limit = Number(field.getAttribute('maxlength') || 0);
        const current = field.value.length;
        counter.textContent = limit > 0 ? current + '/' + limit : String(current);
    }

    function initCharacterCounters(root) {
        root.querySelectorAll('[data-char-target]').forEach(function (counter) {
            const targetId = counter.getAttribute('data-char-target');
            const field = targetId ? root.querySelector('#' + targetId) : null;

            if (!field) {
                return;
            }

            updateCharacterCounter(field, counter);
            field.addEventListener('input', function () {
                updateCharacterCounter(field, counter);
            });
        });
    }

    function initImageUpload(config) {
        const dropzone = document.getElementById(config.dropzoneId);
        const input = document.getElementById(config.inputId);
        const preview = document.getElementById(config.previewId);
        const title = document.getElementById(config.titleId);
        const details = document.getElementById(config.detailsId);
        const clearButton = document.getElementById(config.clearButtonId);
        const emptyText = config.emptyText || 'Nenhuma imagem selecionada.';
        let objectUrl = null;

        if (!dropzone || !input || !preview || !title || !details || !clearButton) {
            return;
        }

        function revokeObjectUrl() {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
        }

        function showExistingImage() {
            revokeObjectUrl();

            if (config.currentImageUrl) {
                createPreviewImage(preview, config.currentImageUrl, 'Imagem do alerta');
                dropzone.classList.add('has-file');
                title.textContent = config.currentImageTitle || 'Imagem atual';
                details.textContent = config.currentImageName || extractFileName(config.currentImageUrl);
                clearButton.hidden = true;
                return;
            }

            dropzone.classList.remove('has-file');
            preview.innerHTML = '<div class="upload-preview-empty">' + emptyText + '</div>';
            title.textContent = 'Imagem informativa';
            details.textContent = 'Arraste, cole ou selecione uma imagem JPG, PNG ou WEBP com até 5 MB.';
            clearButton.hidden = true;
        }

        function applyImageFile(file) {
            if (!file) {
                showExistingImage();
                return;
            }

            if (!IMAGE_ALLOWED_MIMES.includes(file.type)) {
                window.alert('Formato de imagem inválido. Envie JPG, PNG ou WEBP.');
                return;
            }

            if (file.size > IMAGE_MAX_BYTES) {
                window.alert('A imagem excede o limite permitido de 5 MB.');
                return;
            }

            if (!setInputFile(input, file)) {
                window.alert('Não foi possível anexar a imagem arrastada neste navegador. Use o seletor de arquivo.');
                return;
            }

            revokeObjectUrl();
            objectUrl = URL.createObjectURL(file);
            createPreviewImage(preview, objectUrl, file.name);
            dropzone.classList.add('has-file');
            title.textContent = 'Nova imagem pronta para envio';
            details.textContent = file.name + ' - ' + formatBytes(file.size);
            clearButton.hidden = false;
        }

        input.addEventListener('change', function () {
            applyImageFile(input.files && input.files[0] ? input.files[0] : null);
        });

        dropzone.addEventListener('click', function () {
            input.click();
            dropzone.focus();
        });

        dropzone.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                input.click();
            }
        });

        dropzone.addEventListener('dragover', function (event) {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        });

        dropzone.addEventListener('dragleave', function () {
            dropzone.classList.remove('is-dragover');
        });

        dropzone.addEventListener('drop', function (event) {
            event.preventDefault();
            dropzone.classList.remove('is-dragover');

            const file = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[0] : null;
            if (file) {
                applyImageFile(file);
            }
        });

        dropzone.addEventListener('paste', function (event) {
            const items = event.clipboardData ? Array.from(event.clipboardData.items || []) : [];
            const item = items.find(function (clipboardItem) {
                return clipboardItem.kind === 'file' && clipboardItem.type.startsWith('image/');
            });

            if (!item) {
                return;
            }

            event.preventDefault();
            applyImageFile(item.getAsFile());
        });

        clearButton.addEventListener('click', function () {
            clearInputFile(input);
            showExistingImage();
        });

        showExistingImage();
    }

    function initTerritorioPreview(config, form) {
        const summary = document.getElementById('territorioResumo');
        const list = document.getElementById('territorioLista');
        const card = document.getElementById('territorioPreviewCard');
        const csrfField = form.querySelector('input[name="csrf_token"]');
        let requestToken = 0;

        if (!summary || !list || !card || !config.territorioPreviewUrl) {
            return {
                refresh: function () {}
            };
        }

        function setEmpty(message) {
            card.classList.remove('is-loading');
            summary.textContent = 'Aguardando área válida.';
            list.innerHTML = '<div class="territorio-preview-empty">' + message + '</div>';
        }

        function setLoading() {
            card.classList.add('is-loading');
            summary.textContent = 'Atualizando impacto territorial...';
            list.innerHTML = '<div class="territorio-preview-empty">Identificando regiões e municípios afetados...</div>';
        }

        function renderResult(payload) {
            const regioes = Array.isArray(payload.regioes) ? payload.regioes : [];
            card.classList.remove('is-loading');

            if (!regioes.length) {
                setEmpty('Nenhum município do Pará foi identificado para a geometria atual.');
                return;
            }

            summary.textContent = payload.total_regioes + ' regiões e ' + payload.total_municipios + ' municípios mapeados.';
            list.innerHTML = '';

            regioes.forEach(function (item) {
                const block = document.createElement('div');
                block.className = 'territorio-region-block';

                const title = document.createElement('div');
                title.className = 'territorio-region-title';
                title.textContent = item.regiao;

                const meta = document.createElement('div');
                meta.className = 'territorio-region-meta';
                meta.textContent = (item.municipios || []).length + ' municípios';

                const municipalities = document.createElement('div');
                municipalities.className = 'territorio-region-municipios';
                municipalities.textContent = (item.municipios || []).join(', ');

                block.appendChild(title);
                block.appendChild(meta);
                block.appendChild(municipalities);
                list.appendChild(block);
            });
        }

        function refresh(geojson) {
            requestToken += 1;
            const currentRequest = requestToken;

            if (!geojson || !geojson.features || !geojson.features.length) {
                setEmpty('Desenhe no mapa ou carregue um KML para identificar as regiões afetadas.');
                return;
            }

            setLoading();

            fetch(config.territorioPreviewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfField ? csrfField.value : ''
                },
                body: JSON.stringify({
                    area_geojson: geojson
                })
            })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        return {
                            ok: response.ok,
                            payload: payload
                        };
                    });
                })
                .then(function (result) {
                    if (currentRequest !== requestToken) {
                        return;
                    }

                    if (!result.ok || !result.payload || result.payload.ok === false) {
                        throw new Error(result.payload && result.payload.erro ? result.payload.erro : 'Não foi possível identificar o território.');
                    }

                    renderResult(result.payload);
                })
                .catch(function (error) {
                    if (currentRequest !== requestToken) {
                        return;
                    }

                    card.classList.remove('is-loading');
                    summary.textContent = 'Falha ao identificar território.';
                    list.innerHTML = '<div class="territorio-preview-empty">' + error.message + '</div>';
                });
        }

        setEmpty('Desenhe no mapa ou carregue um KML para identificar as regiões afetadas.');

        return {
            refresh: refresh
        };
    }

    function initKmlUpload(config, mapState) {
        const dropzone = document.getElementById(config.dropzoneId);
        const input = document.getElementById(config.inputId);
        const title = document.getElementById(config.titleId);
        const details = document.getElementById(config.detailsId);
        const pills = document.getElementById(config.pillsId);
        const clearButton = document.getElementById(config.clearButtonId);

        if (!dropzone || !input || !title || !details || !pills || !clearButton) {
            return;
        }

        function renderStatus(options) {
            title.textContent = options.title;
            details.textContent = options.details;
            pills.innerHTML = '';

            (options.pills || []).forEach(function (text) {
                const pill = document.createElement('span');
                pill.className = 'kml-pill';
                pill.textContent = text;
                pills.appendChild(pill);
            });

            dropzone.classList.toggle('has-file', Boolean(options.active));
            dropzone.classList.toggle('is-invalid', Boolean(options.error));
            clearButton.hidden = !options.active;
        }

        function restoreInitialState() {
            clearInputFile(input);
            mapState.restoreInitialGeojson();

            if (config.currentKmlName) {
                renderStatus({
                    active: true,
                    title: 'KML atual mantido',
                    details: config.currentKmlName,
                    pills: config.currentKmlPills || [],
                    error: false
                });
                return;
            }

            renderStatus({
                active: false,
                title: 'KML opcional',
                details: 'Arraste ou selecione um arquivo KML para carregar a área automaticamente.',
                pills: [],
                error: false
            });
        }

        function showKmlError(message) {
            clearInputFile(input);
            mapState.restoreInitialGeojson();

            renderStatus({
                active: false,
                title: 'KML nao aceito',
                details: String(message || 'Nao foi possivel processar o arquivo KML.'),
                pills: config.currentKmlName
                    ? ['KML atual mantido no alerta.']
                    : ['Revise o arquivo e tente novamente.'],
                error: true
            });
        }

        function applyKmlFile(file) {
            if (!file) {
                restoreInitialState();
                return;
            }

            if (file.size > KML_MAX_BYTES) {
                showKmlError('O arquivo KML excede o limite permitido de 10 MB.');
                return;
            }

            parseKml(file)
                .then(function (geojson) {
                    if (!setInputFile(input, file)) {
                        showKmlError('Nao foi possivel anexar o KML neste navegador. Use o seletor de arquivo.');
                        return;
                    }

                    mapState.loadGeojson(geojson, 'KML');

                    const polygonCount = geojson.features.filter(function (feature) {
                        return feature.geometry.type === 'Polygon';
                    }).length;

                    const multiPolygonCount = geojson.features.filter(function (feature) {
                        return feature.geometry.type === 'MultiPolygon';
                    }).length;

                    renderStatus({
                        active: true,
                        title: 'KML carregado no mapa',
                        details: file.name + ' - ' + formatBytes(file.size),
                        pills: [
                            geojson.features.length + ' geometrias de área',
                            polygonCount + ' polígonos',
                            multiPolygonCount + ' multipolígonos'
                        ],
                        error: false
                    });
                })
                .catch(function (error) {
                    showKmlError(error && error.message ? error.message : 'Nao foi possivel processar o arquivo KML.');
                });
        }

        input.addEventListener('change', function () {
            applyKmlFile(input.files && input.files[0] ? input.files[0] : null);
        });

        dropzone.addEventListener('click', function () {
            input.click();
            dropzone.focus();
        });

        dropzone.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                input.click();
            }
        });

        dropzone.addEventListener('dragover', function (event) {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        });

        dropzone.addEventListener('dragleave', function () {
            dropzone.classList.remove('is-dragover');
        });

        dropzone.addEventListener('drop', function (event) {
            event.preventDefault();
            dropzone.classList.remove('is-dragover');

            const file = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[0] : null;
            if (file) {
                applyKmlFile(file);
            }
        });

        clearButton.addEventListener('click', function () {
            restoreInitialState();
        });

        restoreInitialState();
    }

    function initMap(config) {
        const mapElement = document.getElementById(config.mapId);
        const geojsonField = document.getElementById(config.geojsonFieldId);
        const areaOrigemField = document.getElementById(config.areaOrigemFieldId);
        const areaOrigemBadge = document.getElementById(config.areaOrigemBadgeId);
        const onAreaChange = typeof config.onAreaChange === 'function'
            ? config.onAreaChange
            : function () {};

        if (!mapElement || !geojsonField || !areaOrigemField || typeof L === 'undefined') {
            return null;
        }

        const initialGeojson = config.initialGeojson && config.initialGeojson.features
            ? normalizeGeoJSON(config.initialGeojson)
            : null;
        const initialAreaOrigem = config.initialAreaOrigem === 'KML' ? 'KML' : 'DESENHO';
        const map = L.map(mapElement).setView([-3.5, -52], 6);
        const drawnItems = new L.FeatureGroup();

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        map.addLayer(drawnItems);

        map.addControl(new L.Control.Draw({
            edit: {
                featureGroup: drawnItems
            },
            draw: {
                polygon: true,
                rectangle: true,
                circle: false,
                marker: false,
                polyline: false,
                circlemarker: false
            }
        }));

        function updateAreaBadge(value) {
            if (!areaOrigemBadge) {
                return;
            }

            areaOrigemBadge.textContent = value === 'KML' ? 'Origem atual: KML' : 'Origem atual: desenho/manual';
        }

        function setAreaOrigem(value) {
            const normalized = value === 'KML' ? 'KML' : 'DESENHO';
            areaOrigemField.value = normalized;
            updateAreaBadge(normalized);
        }

        function writeGeojson(geojson, origem) {
            const normalized = normalizeGeoJSON(geojson || {});
            geojsonField.value = normalized.features.length ? JSON.stringify(normalized) : '';
            setAreaOrigem(origem);
            onAreaChange(normalized.features.length ? normalized : null);
            return normalized;
        }

        function syncFromDrawnItems() {
            writeGeojson(drawnItems.toGeoJSON(), 'DESENHO');
        }

        function loadGeojson(geojson, origem) {
            const normalized = normalizeGeoJSON(geojson || {});
            drawnItems.clearLayers();

            if (normalized.features.length) {
                L.geoJSON(normalized).eachLayer(function (layer) {
                    drawnItems.addLayer(layer);
                });

                if (drawnItems.getLayers().length) {
                    map.fitBounds(drawnItems.getBounds(), { padding: [20, 20] });
                }
            }

            writeGeojson(normalized, origem);

            window.setTimeout(function () {
                map.invalidateSize();
            }, 100);
        }

        function restoreInitialGeojson() {
            if (initialGeojson && initialGeojson.features.length) {
                loadGeojson(initialGeojson, initialAreaOrigem);
                return;
            }

            drawnItems.clearLayers();
            writeGeojson(null, 'DESENHO');

            window.setTimeout(function () {
                map.invalidateSize();
            }, 100);
        }

        map.on('draw:created', function (event) {
            drawnItems.clearLayers();
            drawnItems.addLayer(event.layer);
            syncFromDrawnItems();
        });

        map.on('draw:edited', function () {
            syncFromDrawnItems();
        });

        map.on('draw:deleted', function () {
            syncFromDrawnItems();
        });

        restoreInitialGeojson();

        return {
            loadGeojson: loadGeojson,
            restoreInitialGeojson: restoreInitialGeojson
        };
    }

    function validateForm(config) {
        const geojsonField = document.getElementById(config.geojsonFieldId);
        const inicioField = document.getElementById(config.inicioFieldId);
        const fimField = document.getElementById(config.fimFieldId);

        if (!geojsonField || !geojsonField.value) {
            window.alert('Desenhe a área afetada no mapa ou carregue um arquivo KML válido.');
            return false;
        }

        const inicio = inicioField ? inicioField.value : '';
        const fim = fimField ? fimField.value : '';

        if (inicio && fim && new Date(inicio) > new Date(fim)) {
            window.alert('A vigência inicial não pode ser maior que a vigência final.');
            return false;
        }

        return true;
    }

    function init(config) {
        const form = document.getElementById(config.formId);

        if (!form) {
            return;
        }

        initCharacterCounters(form);

        const territoryPreview = initTerritorioPreview(config, form);
        const mapState = initMap(Object.assign({}, config, {
            onAreaChange: territoryPreview.refresh
        }));

        initImageUpload({
            inputId: config.imageInputId,
            dropzoneId: config.imageDropzoneId,
            previewId: config.imagePreviewId,
            titleId: config.imageTitleId,
            detailsId: config.imageDetailsId,
            clearButtonId: config.imageClearButtonId,
            currentImageUrl: config.currentImageUrl || '',
            currentImageName: config.currentImageName || '',
            currentImageTitle: config.currentImageUrl ? 'Imagem atual' : '',
            emptyText: 'Nenhuma imagem informativa selecionada ainda.'
        });

        if (mapState) {
            initKmlUpload({
                inputId: config.kmlInputId,
                dropzoneId: config.kmlDropzoneId,
                titleId: config.kmlTitleId,
                detailsId: config.kmlDetailsId,
                pillsId: config.kmlPillsId,
                clearButtonId: config.kmlClearButtonId,
                currentKmlName: config.currentKmlName || '',
                currentKmlPills: config.currentKmlName ? ['KML atual preservado'] : []
            }, mapState);
        }

        form.addEventListener('submit', function (event) {
            if (!validateForm(config)) {
                event.preventDefault();
            }
        });
    }

    window.AlertaFormPage = {
        init: init
    };
})();
