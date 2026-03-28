(function () {
    const dataNode = document.getElementById('alerta-detalhe-data');
    const mapElement = document.getElementById('mapa');

    if (!dataNode || !mapElement || typeof L === 'undefined') {
        return;
    }

    let pageData;

    try {
        pageData = JSON.parse(dataNode.textContent);
    } catch (error) {
        console.error('Falha ao carregar os dados do detalhe do alerta.', error);
        return;
    }

    if (!pageData.geojson) {
        return;
    }

    const cores = {
        BAIXO: '#CCC9C7',
        MODERADO: '#FFE000',
        ALTO: '#FF7B00',
        EXTREMO: '#7A28C6',
        'MUITO ALTO': '#FF1D08'
    };

    const corPoligono = cores[pageData.nivel] || '#d32f2f';

    const map = L.map(mapElement, {
        preferCanvas: true
    }).setView([-3.5, -52], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const camada = L.geoJSON(pageData.geojson, {
        renderer: L.canvas(),
        style: {
            color: corPoligono,
            weight: 3,
            fillOpacity: 0.35
        }
    }).addTo(map);

    map.fitBounds(camada.getBounds());

    L.control.scale({
        position: 'bottomleft',
        metric: true,
        imperial: false
    }).addTo(map);

    const legenda = L.control({ position: 'bottomright' });
    legenda.onAdd = function onAdd() {
        const div = L.DomUtil.create('div', 'legenda-mapa');
        div.innerHTML = `
            <strong>Legenda</strong><br>
            <span style="color:#CCC9C7">■</span> Baixo<br>
            <span style="color:#FFE000">■</span> Moderado<br>
            <span style="color:#FF7B00">■</span> Alto<br>
            <span style="color:#FF1D08">■</span> Muito Alto<br>
            <span style="color:#7A28C6">■</span> Extremo
        `;
        return div;
    };
    legenda.addTo(map);

    const norte = L.control({ position: 'topright' });
    norte.onAdd = function onAdd() {
        const div = L.DomUtil.create('div', 'norte');
        div.innerHTML = '<img src="/assets/images/norte.png" alt="Norte">';
        return div;
    };
    norte.addTo(map);

    if (!pageData.shouldGenerateImage) {
        return;
    }

    const loading = document.getElementById('loading-imagem');

    if (typeof leafletImage !== 'function') {
        if (loading) {
            loading.style.display = 'block';
            loading.textContent = 'Não foi possível carregar o gerador do mapa para PDF. Recarregue a página e tente novamente.';
        }
        return;
    }

    if (loading) {
        loading.style.display = 'block';
        loading.textContent = 'Gerando imagem do mapa para o PDF...';
    }

    map.whenReady(function () {
        setTimeout(function () {
            leafletImage(map, function (error, canvas) {
                if (error) {
                    if (loading) {
                        loading.textContent = 'Não foi possível gerar a imagem do mapa.';
                    }
                    return;
                }

                let imagemPng;

                try {
                    imagemPng = canvas.toDataURL('image/png');
                } catch (canvasError) {
                    if (loading) {
                        loading.textContent = 'Não foi possível preparar a imagem do mapa para o PDF.';
                    }
                    return;
                }

                fetch('/pages/alertas/salvar_mapa.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.APP_CSRF_TOKEN || ''
                    },
                    body: JSON.stringify({
                        alerta_id: pageData.alertaId,
                        imagem: imagemPng,
                        imagem_base64: imagemPng
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
                        if (!result.ok || result.payload?.status !== 'ok') {
                            throw new Error(result.payload?.erro || 'Falha ao salvar a imagem do mapa.');
                        }

                        if (loading) {
                            loading.textContent = 'Imagem do mapa gerada. Atualizando a página...';
                        }

                        window.location.reload();
                    })
                    .catch(function (requestError) {
                        if (loading) {
                            loading.textContent = requestError?.message || 'Falha ao salvar a imagem do mapa para o PDF.';
                        }
                    });
            });
        }, 400);
    });
})();
