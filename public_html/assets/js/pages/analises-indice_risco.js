(function () {
    const dataNode = document.getElementById('indice-risco-data');

    if (!dataNode || typeof Chart === 'undefined') {
        return;
    }

    let pageData;

    try {
        pageData = JSON.parse(dataNode.textContent);
    } catch (error) {
        console.error('Falha ao carregar os dados dos índices de risco.', error);
        return;
    }

    window.abrirModal = function abrirModal() {
        const modal = document.getElementById('modalInfo');
        if (modal) {
            modal.style.display = 'flex';
        }
    };

    window.fecharModal = function fecharModal() {
        const modal = document.getElementById('modalInfo');
        if (modal) {
            modal.style.display = 'none';
        }
    };

    window.limparFiltros = function limparFiltros() {
        const destino = new URL(window.location.href);
        const manterEmbedPublico = destino.searchParams.get('embed') === '1';

        destino.search = '';

        if (manterEmbedPublico) {
            destino.searchParams.set('embed', '1');
        }

        window.location.href = destino.pathname + destino.search;
    };

    const graficoIRP = document.getElementById('graficoIRP');
    if (graficoIRP) {
        new Chart(graficoIRP, {
            type: 'bar',
            data: {
                labels: pageData.labelsIRP || [],
                datasets: [{
                    label: 'IRP',
                    data: pageData.valoresIRP || [],
                    backgroundColor: '#0b3c68'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    const graficoIPT = document.getElementById('graficoIPT');
    if (graficoIPT) {
        new Chart(graficoIPT, {
            type: 'bar',
            data: {
                labels: pageData.labelsIPT || [],
                datasets: [{
                    label: 'IPT',
                    data: pageData.valoresIPT || [],
                    backgroundColor: '#c62828'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                scales: {
                    x: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    const regiaoSelect = document.getElementById('filtro-regiao');
    const municipioSelect = document.getElementById('filtro-municipio');

    if (!regiaoSelect || !municipioSelect) {
        return;
    }

    const regiaoAtual = regiaoSelect.value;
    const municipioAtual = pageData.municipioAtual || '';

    if (regiaoAtual) {
        carregarMunicipios(regiaoAtual, municipioAtual);
    }

    regiaoSelect.addEventListener('change', function () {
        const regiao = regiaoSelect.value;

        if (!regiao) {
            municipioSelect.innerHTML = '<option value="">Selecione uma região</option>';
            municipioSelect.disabled = true;
            return;
        }

        carregarMunicipios(regiao, null);
    });

    function carregarMunicipios(regiao, municipioSelecionado) {
        municipioSelect.disabled = true;
        municipioSelect.innerHTML = '<option value="">Carregando municípios...</option>';

        fetch(`/pages/analises/ajax_municipios.php?regiao=${encodeURIComponent(regiao)}`)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Falha HTTP ao buscar municípios: ' + response.status);
                }

                return response.json();
            })
            .then(function (lista) {
                if (!Array.isArray(lista)) {
                    throw new Error('Resposta inválida para lista de municípios.');
                }

                municipioSelect.innerHTML = '<option value="">Todos</option>';

                lista.forEach(function (nome) {
                    const option = document.createElement('option');
                    const nomeNormalizado = String(nome == null ? '' : nome).trim();

                    if (!nomeNormalizado) {
                        return;
                    }

                    option.value = nomeNormalizado;
                    option.textContent = nomeNormalizado;

                    if (nomeNormalizado === municipioSelecionado) {
                        option.selected = true;
                    }

                    municipioSelect.appendChild(option);
                });

                municipioSelect.disabled = false;
                municipioSelect.onchange = function () {
                    if (typeof municipioSelect.form.requestSubmit === 'function') {
                        municipioSelect.form.requestSubmit();
                        return;
                    }

                    municipioSelect.form.submit();
                };
            })
            .catch(function () {
                municipioSelect.innerHTML = '<option value="">Erro ao carregar municípios</option>';
                municipioSelect.disabled = true;
            });
    }
})();
