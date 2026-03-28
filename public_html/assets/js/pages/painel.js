(function () {
    const dataNode = document.getElementById('painel-data');
    const mapElement = document.getElementById('mapaPainel');

    if (!dataNode || !mapElement || typeof L === 'undefined') {
        return;
    }

    let pageData;

    try {
        pageData = JSON.parse(dataNode.textContent);
    } catch (error) {
        console.error('Falha ao carregar os dados do painel.', error);
        return;
    }

    const geojsonAlertas = pageData.geojsonAlertas || {
        type: 'FeatureCollection',
        features: []
    };
    const labelsGravidade = pageData.labelsGravidade || [];
    const dadosGravidade = pageData.dadosGravidade || [];
    const labelsEvento = pageData.labelsEvento || [];
    const dadosEvento = pageData.dadosEvento || [];

    const mapa = L.map(mapElement).setView([-3.5, -52], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(mapa);

    let camadaSelecionada = null;
    let camadasSelecionadas = [];

    function estiloAlerta(feature) {
        const cores = {
            BAIXO: '#CCC9C7',
            MODERADO: '#FFE000',
            ALTO: '#FF7B00',
            EXTREMO: '#7A28C6',
            'MUITO ALTO': '#FF1D08'
        };

        return {
            color: cores[feature.properties.gravidade] || '#607d8b',
            fillColor: cores[feature.properties.gravidade] || '#607d8b',
            weight: 2,
            fillOpacity: 0.45
        };
    }

    function limparDestaqueRegional() {
        camadasSelecionadas.forEach(function (layer) {
            camadaAlertas.resetStyle(layer);
        });

        camadasSelecionadas = [];
    }

    function preencherTopoTerritorio(props) {
        if (!props) {
            console.warn('Propriedades do alerta não informadas.');
            return;
        }

        document.getElementById('t-alerta-numero').textContent =
            props.numero ? `Nº do alerta: ${props.numero}` : 'Alerta -';

        document.getElementById('t-evento').textContent = props.evento ?? '-';

        const gravidadeEl = document.getElementById('t-gravidade');
        const gravidade = props.gravidade?.toUpperCase() ?? '';

        gravidadeEl.textContent = gravidade || '-';
        gravidadeEl.style.backgroundColor = {
            BAIXO: '#CCC9C7',
            MODERADO: '#FFE000',
            ALTO: '#FF7B00',
            EXTREMO: '#7A28C6',
            'MUITO ALTO': '#FF1D08'
        }[gravidade] ?? '#90a4ae';
        gravidadeEl.style.color = '#07273C';

        document.getElementById('t-data').textContent = props.data_alerta || '-';

        const inicio = props.inicio_alerta || '-';
        const fim = props.fim_alerta || '-';
        document.getElementById('t-vigencia').textContent = `${inicio} -> ${fim}`;

        document.getElementById('t-total-municipios').textContent =
            props.total_municipios ?? '-';
    }

    function abrirTerritorio(alertaId) {
        const drawer = document.getElementById('drawer-territorio');
        const overlay = document.getElementById('overlay-territorio');
        const conteudo = document.getElementById('conteudo-territorio');

        if (!drawer || !overlay || !conteudo) {
            return;
        }

        drawer.classList.add('aberto');
        overlay.classList.add('ativo');
        conteudo.innerHTML = '<p class="loading">Carregando regiões e municípios...</p>';

        fetch(`/pages/alertas/territorio_alerta.php?alerta_id=${alertaId}`)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP error');
                }

                return response.json();
            })
            .then(function (dados) {
                if (!dados || Object.keys(dados).length === 0) {
                    conteudo.innerHTML = '<p>Nenhuma informação territorial encontrada.</p>';
                    return;
                }

                let html = '';

                Object.keys(dados).forEach(function (regiao) {
                    html += `<h4>${regiao}</h4><ul>`;
                    dados[regiao].forEach(function (municipio) {
                        html += `<li>${municipio}</li>`;
                    });
                    html += '</ul>';
                });

                conteudo.innerHTML = html;
            })
            .catch(function (error) {
                console.error(error);
                conteudo.innerHTML = '<p>Erro ao carregar o território do alerta.</p>';
            });
    }

    function fecharTerritorio() {
        const drawer = document.getElementById('drawer-territorio');
        const overlay = document.getElementById('overlay-territorio');

        if (drawer) {
            drawer.classList.remove('aberto');
        }

        if (overlay) {
            overlay.classList.remove('ativo');
        }
    }

    window.abrirTerritorio = abrirTerritorio;
    window.fecharTerritorio = fecharTerritorio;

    function destacarRegiao(alertaId) {
        limparDestaqueRegional();

        fetch(`/pages/alertas/alertas_por_regiao.php?id=${alertaId}`)
            .then(function (response) {
                return response.json();
            })
            .then(function (ids) {
                camadaAlertas.eachLayer(function (layer) {
                    const id = layer.feature.properties.alerta_id;

                    if (!ids.includes(id)) {
                        return;
                    }

                    layer.setStyle({
                        weight: 4,
                        dashArray: '6,4',
                        fillOpacity: 0.6
                    });

                    camadasSelecionadas.push(layer);
                });
            });
    }

    const camadaAlertas = L.geoJSON(geojsonAlertas, {
        style: estiloAlerta,
        onEachFeature: function (feature, layer) {
            layer.bindTooltip(
                `<strong>${feature.properties.numero}</strong><br>${feature.properties.evento}<br>Gravidade: ${feature.properties.gravidade}`,
                { sticky: true }
            );

            layer.on('mouseover', function () {
                layer.getElement()?.style.setProperty('cursor', 'pointer');
            });

            layer.on('click', function () {
                if (camadaSelecionada) {
                    camadaAlertas.resetStyle(camadaSelecionada);
                }

                layer.setStyle({
                    weight: 4,
                    fillOpacity: 0.65
                });

                camadaSelecionada = layer;
                preencherTopoTerritorio(feature.properties);
                abrirTerritorio(feature.properties.alerta_id);
                destacarRegiao(feature.properties.alerta_id);
            });
        }
    }).addTo(mapa);

    if (camadaAlertas.getLayers().length) {
        mapa.fitBounds(camadaAlertas.getBounds());
    }

    const legenda = L.control({ position: 'bottomright' });
    legenda.onAdd = function onAdd() {
        const div = L.DomUtil.create('div', 'legenda-mapa');
        div.innerHTML = `
            <strong>Grau de Severidade</strong>
            <div class="legenda-item">
                <span class="legenda-cor" style="background:#CCC9C7"></span> BAIXO
            </div>
            <div class="legenda-item">
                <span class="legenda-cor" style="background:#FFE000"></span> MODERADO
            </div>
            <div class="legenda-item">
                <span class="legenda-cor" style="background:#FF7B00"></span> ALTO
            </div>
            <div class="legenda-item">
                <span class="legenda-cor" style="background:#FF1D08"></span> MUITO ALTO
            </div>
            <div class="legenda-item">
                <span class="legenda-cor" style="background:#7A28C6"></span> EXTREMO
            </div>
        `;
        return div;
    };
    legenda.addTo(mapa);

    const rosaVentos = L.control({ position: 'bottomleft' });
    rosaVentos.onAdd = function onAdd() {
        const div = L.DomUtil.create('div', 'rosa-dos-ventos leaflet-control');
        div.innerHTML = '<img src="/assets/images/norte.png" alt="Rosa dos Ventos">';
        return div;
    };
    rosaVentos.addTo(mapa);

    if (typeof Chart === 'undefined') {
        return;
    }

    const coresSeveridade = {
        BAIXO: '#CCC9C7',
        MODERADO: '#FFEA2B',
        ALTO: '#FF7B00',
        EXTREMO: '#7A28C6',
        'MUITO ALTO': '#FF1D08'
    };

    const canvasGravidade = document.getElementById('graficoGravidade');
    if (canvasGravidade) {
        const coresGravidade = labelsGravidade.map(function (nivel) {
            return coresSeveridade[nivel] ?? '#90a4ae';
        });

        new Chart(canvasGravidade, {
            type: 'doughnut',
            data: {
                labels: labelsGravidade,
                datasets: [{
                    data: dadosGravidade,
                    backgroundColor: coresGravidade
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const total = context.dataset.data.reduce(function (accumulator, value) {
                                    return accumulator + Number(value || 0);
                                }, 0);

                                const valor = Number(
                                    context.raw
                                    ?? context.parsed?.y
                                    ?? context.parsed?.x
                                    ?? context.parsed
                                    ?? 0
                                );
                                const percentual = total > 0
                                    ? ((valor / total) * 100).toFixed(1)
                                    : '0.0';
                                return `${context.label}: ${valor} alertas (${percentual}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    const canvasEvento = document.getElementById('graficoEvento');
    if (canvasEvento) {
        new Chart(canvasEvento, {
            type: 'bar',
            data: {
                labels: labelsEvento,
                datasets: [{
                    label: 'Alertas Ativos',
                    data: dadosEvento,
                    backgroundColor: '#0b3c68'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true }
                }
            }
        });
    }
})();
