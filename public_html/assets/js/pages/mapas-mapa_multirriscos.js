(function () {
    const bootstrapNode = document.getElementById('multirrisco-bootstrap');
    const mapElement = document.getElementById('mapa');
    const multirriscoConfig = window.MULTIRRISCO_CONFIG || {};
    const mapaApiBase = String(multirriscoConfig.apiBase || '/api/mapa').replace(/\/$/, '');
    const compdecHabilitado = multirriscoConfig.enableCompdec !== false;

    function renderizarFalhaMapa(mensagem) {
        if (!mapElement) return;

        let overlay = mapElement.querySelector('.multirrisco-map-error');

        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'multirrisco-map-error';
            mapElement.appendChild(overlay);
        }

        overlay.innerHTML = `<div class="multirrisco-empty-box">${mensagem}</div>`;
    }

    if (!mapElement) {
        return;
    }

    if (typeof L === 'undefined') {
        renderizarFalhaMapa('Não foi possível carregar a biblioteca do mapa. Verifique a conexão da página e recarregue.');
        return;
    }

    let bootstrap = { municipios: [], regioes: [] };

    if (bootstrapNode) {
        try {
            bootstrap = JSON.parse(bootstrapNode.textContent || '{}');
        } catch (error) {
            console.error('Falha ao carregar dados iniciais do mapa multirriscos.', error);
        }
    }

    const el = {
        form: document.getElementById('multirrisco-form'),
        dataInicio: document.getElementById('data_inicio'),
        dataFim: document.getElementById('data_fim'),
        tipoEvento: document.getElementById('tipo_evento'),
        gravidade: document.getElementById('gravidade'),
        fonte: document.getElementById('fonte'),
        regiao: document.getElementById('regiao'),
        municipio: document.getElementById('municipio'),
        btnLimparFiltros: document.getElementById('btnLimparFiltros'),
        btnAbrirAjuda: document.getElementById('btnAbrirAjuda'),
        btnAbrirIRP: document.getElementById('btnAbrirIRP'),
        btnLimparFiltroDia: document.getElementById('btnLimparFiltroDia'),
        resumoFiltros: document.getElementById('resumo-filtros'),
        heroAlertas: document.getElementById('hero-alertas-ativos'),
        heroFocoValue: document.getElementById('hero-foco-value'),
        heroFocoNote: document.getElementById('hero-foco-note'),
        focoTitulo: document.getElementById('foco-territorial-titulo'),
        focoTexto: document.getElementById('foco-territorial-texto'),
        kpiAtivos: document.getElementById('kpi-ativos'),
        kpiMunicipios: document.getElementById('kpi-municipios'),
        kpiRegioes: document.getElementById('kpi-regioes'),
        chipModo: document.getElementById('chip-modo-territorial'),
        chipTerritorio: document.getElementById('chip-filtro-territorial'),
        status: document.getElementById('status-atualizacao'),
        listaRegioes: document.getElementById('lista-regioes'),
        mapaLoading: document.getElementById('mapaLoading'),
        filtroDiaAtivo: document.getElementById('filtro-dia-ativo'),
        diaTxt: document.getElementById('diaSelecionadoTxt'),
        graficoLinhaTempo: document.getElementById('graficoLinhaTempo'),
        toggleAlertas: document.getElementById('toggle-alertas'),
        toggleCompdec: document.getElementById('toggle-compdec'),
        radiosModo: document.querySelectorAll('input[name="modoTerritorial"]'),
        modalTerritorio: document.getElementById('modalTerritorio'),
        modalTerritorioKicker: document.getElementById('modalTerritorioKicker'),
        modalTerritorioTitulo: document.getElementById('modalTerritorioTitulo'),
        modalTerritorioResumo: document.getElementById('modalTerritorioResumo'),
        modalTerritorioBody: document.getElementById('modalTerritorioBody'),
        modalIRP: document.getElementById('modalIRP'),
        modalAjuda: document.getElementById('modalAjuda'),
        drawerCompdec: document.getElementById('drawer-compdec'),
        conteudoCompdec: document.getElementById('conteudo-compdec'),
        overlayCompdec: document.getElementById('overlay-compdec')
    };

    const municipiosCatalogo = Array.isArray(bootstrap.municipios) ? bootstrap.municipios : [];
    const municipiosPorCodigo = {};
    const municipiosPorRegiao = {};

    municipiosCatalogo.sort((a, b) => String(a.municipio || '').localeCompare(String(b.municipio || ''), 'pt-BR'));

    municipiosCatalogo.forEach((item) => {
        const codIbge = String(item.cod_ibge || '');
        const municipio = String(item.municipio || '');
        const regiao = String(item.regiao || '');

        if (!codIbge || !municipio) {
            return;
        }

        municipiosPorCodigo[codIbge] = { cod_ibge: codIbge, municipio, regiao };

        if (!municipiosPorRegiao[regiao]) {
            municipiosPorRegiao[regiao] = [];
        }

        municipiosPorRegiao[regiao].push({ cod_ibge: codIbge, municipio, regiao });
    });

    Object.keys(municipiosPorRegiao).forEach((regiao) => {
        municipiosPorRegiao[regiao].sort((a, b) => a.municipio.localeCompare(b.municipio, 'pt-BR'));
    });

    let filtroDiaSelecionado = null;
    let graficoIRP = null;
    let primeiraCarga = true;
    let totalCompdecSim = 0;
    let totalCompdecNao = 0;
    let dadosMunicipios = {};
    let dadosRegioes = {};
    let focoManual = null;
    let camadaTemporaria = null;
    let camadaTemporariaTipo = null;
    let dashboardController = null;
    let dashboardRequestId = 0;
    let resizeTimer = null;
    let compdecCarregado = false;
    let carregamentoCompdec = null;
    let timerDestaqueTemporario = null;

    const TEMPO_DESTAQUE_TERRITORIAL_MS = 3200;

    const mapa = L.map('mapa', { zoomControl: true, preferCanvas: true }).setView([-3.5, -52], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(mapa);

    mapa.createPane('paneCompdec');
    mapa.getPane('paneCompdec').style.zIndex = 650;
    mapa.getPane('paneCompdec').style.pointerEvents = 'none';

    function erroAbortado(error) {
        return error?.name === 'AbortError';
    }

    function obterJson(url, signal = null) {
        return fetch(url, {
            signal,
            headers: { Accept: 'application/json' }
        }).then((response) => {
            if (!response.ok) {
                throw new Error(`Falha ao carregar ${url} (${response.status})`);
            }

            return response.json();
        });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatarData(valor) {
        if (!valor) return '-';
        const partes = String(valor).split(' ')[0].split('-');
        return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : String(valor);
    }

    function formatarDataCurtaGrafico(valor, incluirAno = false) {
        if (!valor) return '-';
        const partes = String(valor).split(' ')[0].split('-');

        if (partes.length !== 3) {
            return String(valor);
        }

        const [ano, mes, dia] = partes;
        return incluirAno ? `${dia}/${mes}/${ano.slice(-2)}` : `${dia}/${mes}`;
    }

    function formatarDataHora(valor) {
        if (!valor) return '-';
        const [data, hora] = String(valor).split(' ');
        const dataFormatada = formatarData(data);
        return hora ? `${dataFormatada} ${hora.slice(0, 5)}` : dataFormatada;
    }

    function formatarVigencia(inicio, fim) {
        const inicioFormatado = formatarDataHora(inicio);
        const fimFormatado = formatarDataHora(fim);
        return inicioFormatado === '-' && fimFormatado === '-' ? '-' : `${inicioFormatado} até ${fimFormatado}`;
    }

    function pesoGravidade(nivel) {
        switch (String(nivel || '').toUpperCase()) {
            case 'BAIXO': return 1;
            case 'MODERADO': return 2;
            case 'ALTO': return 3;
            case 'MUITO ALTO': return 4;
            case 'EXTREMO': return 5;
            default: return 0;
        }
    }

    function corNivel(nivel) {
        return {
            EXTREMO: '#7A28C6',
            'MUITO ALTO': '#FF1D08',
            ALTO: '#FF7B00',
            MODERADO: '#FFE000',
            BAIXO: '#CCC9C7'
        }[String(nivel || '').toUpperCase()] || '#D7E1E7';
    }

    function formatarPressao(valor) {
        return `${Number(valor || 0)} pts`;
    }

    function normalizarTexto(valor) {
        return String(valor || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    const camadaMunicipios = L.geoJSON(null);
    const camadaAlertas = L.geoJSON(null);
    const camadaRegioes = L.geoJSON(null);
    const camadaCompdec = L.layerGroup();

    function modoAtual() {
        const ativo = Array.from(el.radiosModo).find((radio) => radio.checked);
        return ativo ? ativo.value : 'municipios';
    }

    function municipioSelecionado() {
        return el.municipio?.value || '';
    }

    function regiaoSelecionada() {
        return el.regiao?.value || '';
    }

    function obterFiltros() {
        const filtros = {
            data_inicio: el.dataInicio?.value || '',
            data_fim: el.dataFim?.value || '',
            tipo_evento: el.tipoEvento?.value || '',
            gravidade: el.gravidade?.value || '',
            fonte: el.fonte?.value || '',
            regiao: regiaoSelecionada(),
            municipio: municipioSelecionado()
        };

        if (filtroDiaSelecionado) {
            filtros.data_inicio = filtroDiaSelecionado;
            filtros.data_fim = filtroDiaSelecionado;
        }

        return filtros;
    }

    function filtrosQuery() {
        const query = new URLSearchParams();

        Object.entries(obterFiltros()).forEach(([chave, valor]) => {
            if (valor) {
                query.append(chave, valor);
            }
        });

        return query.toString();
    }

    function preencherMunicipios(regiao = '', municipio = '') {
        if (!el.municipio) return;

        const lista = regiao && municipiosPorRegiao[regiao]
            ? municipiosPorRegiao[regiao]
            : municipiosCatalogo;

        el.municipio.innerHTML = '<option value="">Todos os municípios</option>';

        lista.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.cod_ibge;
            option.textContent = item.municipio;
            el.municipio.appendChild(option);
        });

        if (municipio && lista.some((item) => item.cod_ibge === municipio)) {
            el.municipio.value = municipio;
        }
    }

    function resumoFiltrosTexto() {
        const filtros = obterFiltros();
        const itens = [];

        if (filtros.data_inicio) itens.push(`início ${formatarData(filtros.data_inicio)}`);
        if (filtros.data_fim) itens.push(`fim ${formatarData(filtros.data_fim)}`);
        if (filtros.tipo_evento) itens.push(`evento ${filtros.tipo_evento}`);
        if (filtros.gravidade) itens.push(`gravidade ${filtros.gravidade}`);
        if (filtros.fonte) itens.push(`fonte ${filtros.fonte}`);
        if (filtros.regiao) itens.push(`região ${filtros.regiao}`);
        if (filtros.municipio) itens.push(`município ${(municipiosPorCodigo[filtros.municipio] || {}).municipio || filtros.municipio}`);
        if (filtroDiaSelecionado) itens.push(`dia ${formatarData(filtroDiaSelecionado)}`);

        return itens.length ? itens.join(' | ') : 'sem recorte adicional';
    }

    function atualizarResumoFiltros() {
        if (el.resumoFiltros) {
            el.resumoFiltros.textContent = `Filtros ativos: ${resumoFiltrosTexto()}.`;
        }

        if (el.chipModo) {
            el.chipModo.textContent = `Modo: ${modoAtual() === 'regioes' ? 'regiões' : 'municípios'}`;
        }

        if (el.chipTerritorio) {
            if (municipioSelecionado()) {
                const municipio = municipiosPorCodigo[municipioSelecionado()];
                el.chipTerritorio.textContent = `Município: ${municipio ? municipio.municipio : municipioSelecionado()}`;
            } else if (regiaoSelecionada()) {
                el.chipTerritorio.textContent = `Região: ${regiaoSelecionada()}`;
            } else {
                el.chipTerritorio.textContent = 'Sem recorte territorial';
            }
        }
    }

    function kpiNumerico(node) {
        const valor = String(node?.textContent || '').replace(/[^\d]/g, '');
        return valor ? Number(valor) : 0;
    }

    function obterRankingRegioesIA() {
        return Object.values(dadosRegioes)
            .sort((a, b) => Number(b.pressao || 0) - Number(a.pressao || 0) || Number(b.alertas || 0) - Number(a.alertas || 0))
            .slice(0, 5)
            .map((item) => ({
                regiao: item.regiao,
                pressao: Number(item.pressao || 0),
                alertas: Number(item.alertas || 0),
                municipios: Number(item.municipios || 0),
                gravidade: item.gravidade || null
            }));
    }

    function obterContextoMapaIA() {
        const codIbge = municipioSelecionado();
        const municipio = codIbge ? municipiosPorCodigo[codIbge] || null : null;

        return {
            filtros: {
                data_inicio: el.dataInicio?.value || '',
                data_fim: el.dataFim?.value || '',
                tipo_evento: el.tipoEvento?.value || '',
                gravidade: el.gravidade?.value || '',
                fonte: el.fonte?.value || '',
                regiao: regiaoSelecionada(),
                municipio: codIbge,
                municipio_nome: municipio?.municipio || '',
                modo: modoAtual(),
                dia: filtroDiaSelecionado || ''
            },
            indicadores: {
                alertas_ativos: kpiNumerico(el.kpiAtivos),
                municipios_afetados: kpiNumerico(el.kpiMunicipios),
                regioes_afetadas: kpiNumerico(el.kpiRegioes),
                foco_titulo: el.focoTitulo?.textContent?.trim() || '',
                foco_texto: el.focoTexto?.textContent?.trim() || ''
            },
            camadas: {
                alertas_ativos: Boolean(el.toggleAlertas?.checked),
                compdec: Boolean(el.toggleCompdec?.checked)
            },
            ranking_regioes: obterRankingRegioesIA()
        };
    }

    function notificarContextoMapaIA() {
        window.dispatchEvent(new CustomEvent('multirrisco:contexto-atualizado', {
            detail: obterContextoMapaIA()
        }));
    }

    function atualizarStatus(texto, loading = false) {
        if (el.status) {
            el.status.textContent = texto;
            el.status.classList.toggle('is-loading', loading);
        }

        if (el.mapaLoading) {
            el.mapaLoading.hidden = !loading;
        }
    }

    function atualizarFocoPadrao() {
        if (focoManual) {
            el.heroFocoValue.textContent = focoManual.titulo;
            el.heroFocoNote.textContent = focoManual.texto;
            el.focoTitulo.textContent = focoManual.titulo;
            el.focoTexto.textContent = focoManual.texto;
            return;
        }

        if (municipioSelecionado()) {
            const municipio = municipiosPorCodigo[municipioSelecionado()];
            const dado = dadosMunicipios[municipioSelecionado()];
            const titulo = municipio ? municipio.municipio : 'Município selecionado';
            const texto = dado
                ? `${dado.alertas} alertas ativos e pressão ${formatarPressao(dado.pressao)} no recorte atual.`
                : 'Sem alertas ativos para o município selecionado.';

            el.heroFocoValue.textContent = titulo;
            el.heroFocoNote.textContent = texto;
            el.focoTitulo.textContent = titulo;
            el.focoTexto.textContent = texto;
            return;
        }

        if (regiaoSelecionada()) {
            const dado = dadosRegioes[regiaoSelecionada()];
            const texto = dado
                ? `${dado.alertas} alertas ativos, ${dado.municipios} municípios e pressão ${formatarPressao(dado.pressao)}.`
                : 'Sem alertas ativos para a região selecionada.';

            el.heroFocoValue.textContent = regiaoSelecionada();
            el.heroFocoNote.textContent = texto;
            el.focoTitulo.textContent = regiaoSelecionada();
            el.focoTexto.textContent = texto;
            return;
        }

        el.heroFocoValue.textContent = 'Sem recorte territorial';
        el.heroFocoNote.textContent = 'Selecione região, município ou clique no mapa para abrir o detalhamento operacional.';
        el.focoTitulo.textContent = 'Nenhum foco definido';
        el.focoTexto.textContent = 'Clique em um município ou região para abrir o modal detalhado com os alertas ativos.';
    }

    function definirFocoManual(titulo, texto) {
        focoManual = { titulo, texto };
        atualizarFocoPadrao();
    }

    function limparFocoManual() {
        focoManual = null;
        atualizarFocoPadrao();
    }

    function estiloMunicipio(feature) {
        const codIbge = String(feature.properties.cod_ibge);
        const dado = dadosMunicipios[codIbge];
        const selecionado = codIbge === municipioSelecionado();
        const temporario = camadaTemporariaTipo === 'municipio' && camadaTemporaria && camadaTemporaria.feature === feature;

        return {
            color: selecionado || temporario ? '#041E2D' : '#4E6570',
            weight: selecionado || temporario ? 3.2 : 1.1,
            fillColor: dado ? corNivel(dado.nivel) : '#F1F5F7',
            fillOpacity: selecionado ? 0.92 : temporario ? 0.84 : dado ? 0.72 : 0.22
        };
    }

    function estiloAlerta(feature) {
        const propriedades = feature.properties || {};
        const cor = corNivel(propriedades.gravidade || propriedades.nivel_gravidade);

        return {
            color: cor,
            weight: 2.2,
            fillColor: cor,
            fillOpacity: 0.28
        };
    }

    function estiloRegiao(feature) {
        const nome = String(feature.properties.regiao_integracao || '');
        const dado = dadosRegioes[nome];
        const selecionado = nome === regiaoSelecionada();
        const temporario = camadaTemporariaTipo === 'regiao' && camadaTemporaria && camadaTemporaria.feature === feature;

        return {
            color: selecionado || temporario ? '#041E2D' : '#0F3D57',
            weight: selecionado || temporario ? 3.4 : 2,
            dashArray: selecionado ? null : '7,5',
            fillColor: dado ? corNivel(dado.gravidade) : '#DCE8EE',
            fillOpacity: selecionado ? 0.58 : temporario ? 0.48 : dado ? 0.3 : 0.05
        };
    }

    function restaurarDestaqueTemporario() {
        if (timerDestaqueTemporario) {
            window.clearTimeout(timerDestaqueTemporario);
            timerDestaqueTemporario = null;
        }

        if (!camadaTemporaria) return;

        if (camadaTemporariaTipo === 'municipio') {
            camadaTemporaria.setStyle(estiloMunicipio(camadaTemporaria.feature));
        }

        if (camadaTemporariaTipo === 'regiao') {
            camadaTemporaria.setStyle(estiloRegiao(camadaTemporaria.feature));
        }

        camadaTemporaria = null;
        camadaTemporariaTipo = null;
    }

    function destacarTemporariamente(layer, tipo) {
        restaurarDestaqueTemporario();
        camadaTemporaria = layer;
        camadaTemporariaTipo = tipo;

        if (tipo === 'municipio') {
            layer.setStyle({ color: '#041E2D', weight: 3.2, fillOpacity: 0.88 });
        }

        if (tipo === 'regiao') {
            layer.setStyle({ color: '#041E2D', weight: 3.6, dashArray: null, fillOpacity: 0.48 });
        }

        if (typeof layer.bringToFront === 'function') {
            layer.bringToFront();
        }

        timerDestaqueTemporario = window.setTimeout(() => {
            restaurarDestaqueTemporario();
        }, TEMPO_DESTAQUE_TERRITORIAL_MS);
    }

    function tooltipMunicipio(codIbge, nome) {
        const dado = dadosMunicipios[codIbge];

        if (!dado) {
            return `<strong>${escapeHtml(nome)}</strong><br>Sem alertas ativos`;
        }

        return `
            <strong>${escapeHtml(nome)}</strong><br>
            Pressão: ${escapeHtml(formatarPressao(dado.pressao))}<br>
            Alertas ativos: ${escapeHtml(dado.alertas)}
        `;
    }

    function tooltipRegiao(nome) {
        const dado = dadosRegioes[nome];

        if (!dado) {
            return `<strong>${escapeHtml(nome)}</strong><br>Sem alertas ativos`;
        }

        return `
            <strong>${escapeHtml(nome)}</strong><br>
            Pressão: ${escapeHtml(formatarPressao(dado.pressao))}<br>
            Alertas ativos: ${escapeHtml(dado.alertas)}
        `;
    }

    function criarMunicipioVazio(codIbge, nomeFallback) {
        const municipio = municipiosPorCodigo[codIbge];

        return {
            cod_ibge: codIbge,
            municipio: municipio ? municipio.municipio : String(nomeFallback || 'Município'),
            regiao: municipio ? municipio.regiao : '',
            alertas: 0,
            nivel: null,
            pressao: 0,
            tipos_evento: [],
            detalhes: [],
            quantidade_alertas_ativos: 0
        };
    }

    function criarRegiaoVazia(nome) {
        return {
            regiao: nome,
            alertas: 0,
            municipios: 0,
            gravidade: null,
            pressao: 0,
            tipos_evento: [],
            municipios_lista: [],
            detalhes: [],
            quantidade_alertas_ativos: 0
        };
    }

    function renderizarChips(lista) {
        if (!Array.isArray(lista) || !lista.length) {
            return '<span class="multirrisco-inline-muted">Sem eventos ativos.</span>';
        }

        return lista.map((item) => `<span class="multirrisco-pill">${escapeHtml(item)}</span>`).join('');
    }

    function renderizarLista(lista) {
        if (!Array.isArray(lista) || !lista.length) {
            return '<span class="multirrisco-inline-muted">Nenhum município ativo neste recorte.</span>';
        }

        return `<div class="multirrisco-inline-list">${lista.map((item) => `<span class="multirrisco-pill multirrisco-pill-soft">${escapeHtml(item)}</span>`).join('')}</div>`;
    }

    function resumoTerritorialHtml(cards) {
        return `
            <div class="modal-territorio-summary-grid">
                ${cards.map((card) => `
                    <article class="modal-territorio-summary-card">
                        <span class="modal-territorio-summary-label">${escapeHtml(card.label)}</span>
                        <strong class="modal-territorio-summary-value">${card.value}</strong>
                    </article>
                `).join('')}
            </div>
        `;
    }

    function cardAlertaHtml(detalhe, territorioLabel, territorioValor, exibirMunicipios) {
        const gravidade = String(detalhe.gravidade || 'Sem gravidade');
        const pesoGravidadeDetalhe = Number(detalhe.peso_gravidade ?? pesoGravidade(gravidade));
        const pesoGravidadeNormalizado = Number.isFinite(pesoGravidadeDetalhe) ? pesoGravidadeDetalhe : 0;
        const municipiosNoRecorte = Number(detalhe.municipios_total || 0);
        const formulaIRP = exibirMunicipios
            ? `${pesoGravidadeNormalizado} x ${municipiosNoRecorte}`
            : `${pesoGravidadeNormalizado} x 1`;
        const extraMunicipios = exibirMunicipios ? `
            <div class="multirrisco-alerta-extra">
                <span class="multirrisco-alerta-extra-label">Municípios cobertos neste alerta</span>
                ${renderizarLista(detalhe.municipios || [])}
            </div>
        ` : '';

        return `
            <article class="multirrisco-alerta-card">
                <div class="multirrisco-alerta-card-head">
                    <div>
                        <span class="multirrisco-alerta-kicker">Alerta ${escapeHtml(detalhe.numero || '-')}</span>
                        <strong class="multirrisco-alerta-title">${escapeHtml(detalhe.tipo_evento || 'Evento não informado')}</strong>
                    </div>
                    <span class="multirrisco-alerta-badge" style="background:${escapeHtml(corNivel(gravidade))}22;border-color:${escapeHtml(corNivel(gravidade))}55;">${escapeHtml(gravidade)}</span>
                </div>
                <div class="multirrisco-alerta-grid">
                    <div class="multirrisco-alerta-item"><span>Número do alerta</span><strong>${escapeHtml(detalhe.numero || '-')}</strong></div>
                    <div class="multirrisco-alerta-item"><span>Data do alerta</span><strong>${escapeHtml(formatarData(detalhe.data_alerta))}</strong></div>
                    <div class="multirrisco-alerta-item"><span>Vigência</span><strong>${escapeHtml(formatarVigencia(detalhe.inicio_alerta, detalhe.fim_alerta))}</strong></div>
                    <div class="multirrisco-alerta-item"><span>Gravidade</span><strong>${escapeHtml(gravidade)}</strong></div>
                    <div class="multirrisco-alerta-item"><span>Peso gravidade</span><strong>${escapeHtml(String(pesoGravidadeNormalizado))}</strong></div>
                    <div class="multirrisco-alerta-item"><span>Fórmula IRP</span><strong>${escapeHtml(formulaIRP)}</strong></div>
                    <div class="multirrisco-alerta-item"><span>Pontos IRP</span><strong>${escapeHtml(formatarPressao(detalhe.pressao))}</strong></div>
                    <div class="multirrisco-alerta-item"><span>Fonte</span><strong>${escapeHtml(detalhe.fonte || '-')}</strong></div>
                    <div class="multirrisco-alerta-item"><span>${escapeHtml(territorioLabel)}</span><strong>${escapeHtml(territorioValor)}</strong></div>
                    ${exibirMunicipios ? `<div class="multirrisco-alerta-item"><span>Municípios no recorte</span><strong>${escapeHtml(String(municipiosNoRecorte))}</strong></div>` : ''}
                </div>
                ${extraMunicipios}
            </article>
        `;
    }

    function abrirModalTerritorio(config) {
        if (!el.modalTerritorio) return;

        el.modalTerritorioKicker.textContent = config.kicker;
        el.modalTerritorioTitulo.textContent = config.titulo;
        el.modalTerritorioResumo.textContent = config.resumo;

        const cardsHtml = resumoTerritorialHtml(config.cards);
        const detalhesHtml = config.detalhes.length
            ? `<div class="multirrisco-alerta-list">${config.detalhes.map((detalhe) => cardAlertaHtml(detalhe, config.territorioLabel, config.territorioValor, config.exibirMunicipios)).join('')}</div>`
            : '<div class="multirrisco-empty-box multirrisco-empty-box-modal">Nenhum alerta ativo encontrado para este território com os filtros atuais.</div>';

        el.modalTerritorioBody.innerHTML = `${cardsHtml}${detalhesHtml}`;
        el.modalTerritorio.classList.add('ativo');
        el.modalTerritorio.setAttribute('aria-hidden', 'false');
    }

    function fecharModalTerritorio() {
        if (!el.modalTerritorio) return;
        el.modalTerritorio.classList.remove('ativo');
        el.modalTerritorio.setAttribute('aria-hidden', 'true');
    }

    function abrirModalMunicipio(codIbge, nomeFallback = '') {
        const registro = dadosMunicipios[codIbge] || criarMunicipioVazio(codIbge, nomeFallback);
        const resumo = registro.alertas
            ? `${registro.alertas} alertas ativos e pressão territorial ${formatarPressao(registro.pressao)} neste município.`
            : 'Nenhum alerta ativo localizado neste município para o recorte atual.';

        definirFocoManual(registro.municipio, resumo);

        abrirModalTerritorio({
            kicker: 'Município',
            titulo: registro.municipio,
            resumo,
            cards: [
                { label: 'Região', value: escapeHtml(registro.regiao || 'Não informada') },
                { label: 'Alertas ativos', value: escapeHtml(registro.quantidade_alertas_ativos || 0) },
                { label: 'Pressão territorial', value: escapeHtml(formatarPressao(registro.pressao)) },
                { label: 'Tipos de evento', value: renderizarChips(registro.tipos_evento || []) }
            ],
            detalhes: registro.detalhes || [],
            territorioLabel: 'Município',
            territorioValor: registro.municipio,
            exibirMunicipios: false
        });
    }

    function abrirModalRegiao(nomeRegiao) {
        const registro = dadosRegioes[nomeRegiao] || criarRegiaoVazia(nomeRegiao);
        const resumo = registro.alertas
            ? `${registro.alertas} alertas ativos, ${registro.municipios} municípios em risco e pressão ${formatarPressao(registro.pressao)} nesta região.`
            : 'Nenhum alerta ativo localizado nesta região para o recorte atual.';

        definirFocoManual(registro.regiao, resumo);

        abrirModalTerritorio({
            kicker: 'Região',
            titulo: registro.regiao,
            resumo,
            cards: [
                { label: 'Municípios em risco', value: escapeHtml(registro.municipios || 0) },
                { label: 'Alertas ativos', value: escapeHtml(registro.quantidade_alertas_ativos || 0) },
                { label: 'Pressão territorial', value: escapeHtml(formatarPressao(registro.pressao)) },
                { label: 'Tipos de evento', value: renderizarChips(registro.tipos_evento || []) }
            ],
            detalhes: registro.detalhes || [],
            territorioLabel: 'Região',
            territorioValor: registro.regiao,
            exibirMunicipios: true
        });
    }

    function interacaoMunicipio(feature, layer) {
        const codIbge = String(feature.properties.cod_ibge);
        const nome = String(feature.properties.municipio || '');

        layer.bindTooltip(tooltipMunicipio(codIbge, nome), { sticky: true, opacity: 0.94 });

        layer.on('mouseover', () => {
            layer.setTooltipContent(tooltipMunicipio(codIbge, nome));
        });

        layer.on('click', () => {
            if (municipioSelecionado() !== codIbge) {
                destacarTemporariamente(layer, 'municipio');
            }

            mapa.fitBounds(layer.getBounds(), { padding: [28, 28], maxZoom: 10 });
            abrirModalMunicipio(codIbge, nome);
        });
    }

    function interacaoRegiao(feature, layer) {
        const nome = String(feature.properties.regiao_integracao || '');

        layer.bindTooltip(tooltipRegiao(nome), { sticky: true, opacity: 0.94 });

        layer.on('mouseover', () => {
            layer.setTooltipContent(tooltipRegiao(nome));
        });

        layer.on('click', () => {
            if (regiaoSelecionada() !== nome) {
                destacarTemporariamente(layer, 'regiao');
            }

            mapa.fitBounds(layer.getBounds(), { padding: [30, 30], maxZoom: 8 });
            abrirModalRegiao(nome);
        });
    }

    function interacaoAlerta(feature, layer) {
        const props = feature.properties || {};

        layer.bindPopup(`
            <div class="multirrisco-popup">
                <strong>Alerta ${escapeHtml(props.numero || '-')}</strong><br>
                Evento: ${escapeHtml(props.tipo_evento || '-')}<br>
                Gravidade: <strong>${escapeHtml(props.gravidade || props.nivel_gravidade || '-')}</strong><br>
                Data: ${escapeHtml(formatarData(props.data_alerta))}<br>
                Vigência: ${escapeHtml(formatarVigencia(props.inicio_alerta, props.fim_alerta))}
            </div>
        `);
    }

    camadaMunicipios.options.style = estiloMunicipio;
    camadaMunicipios.options.onEachFeature = interacaoMunicipio;
    camadaAlertas.options.style = estiloAlerta;
    camadaAlertas.options.onEachFeature = interacaoAlerta;
    camadaRegioes.options.style = estiloRegiao;
    camadaRegioes.options.onEachFeature = interacaoRegiao;

    function encontrarLayerMunicipio(codIbge) {
        let encontrado = null;
        camadaMunicipios.eachLayer((layer) => {
            if (String(layer.feature?.properties?.cod_ibge) === String(codIbge)) {
                encontrado = layer;
            }
        });
        return encontrado;
    }

    function encontrarLayerRegiao(nomeRegiao) {
        let encontrado = null;
        camadaRegioes.eachLayer((layer) => {
            if (String(layer.feature?.properties?.regiao_integracao) === String(nomeRegiao)) {
                encontrado = layer;
            }
        });
        return encontrado;
    }

    function zoomParaMunicipio(codIbge) {
        const layer = encontrarLayerMunicipio(codIbge);
        if (layer) mapa.fitBounds(layer.getBounds(), { padding: [28, 28], maxZoom: 10 });
    }

    function zoomParaRegiao(nomeRegiao) {
        const layer = encontrarLayerRegiao(nomeRegiao);
        if (layer) mapa.fitBounds(layer.getBounds(), { padding: [30, 30], maxZoom: 8 });
    }

    function setModoTerritorial(modo) {
        if (modo === 'regioes') {
            if (!mapa.hasLayer(camadaRegioes)) mapa.addLayer(camadaRegioes);
            if (mapa.hasLayer(camadaMunicipios)) mapa.removeLayer(camadaMunicipios);
        } else {
            if (!mapa.hasLayer(camadaMunicipios)) mapa.addLayer(camadaMunicipios);
            if (mapa.hasLayer(camadaRegioes)) mapa.removeLayer(camadaRegioes);
        }

        el.radiosModo.forEach((radio) => {
            radio.checked = radio.value === modo;
        });

        atualizarResumoFiltros();
        camadaMunicipios.setStyle(estiloMunicipio);
        camadaRegioes.setStyle(estiloRegiao);
        notificarContextoMapaIA();
    }

    function ajustarModoPorFiltro() {
        if (municipioSelecionado()) {
            setModoTerritorial('municipios');
        } else if (regiaoSelecionada()) {
            setModoTerritorial('regioes');
        }
    }

    function definirKPIsIndisponiveis() {
        el.kpiAtivos.textContent = '-';
        el.kpiMunicipios.textContent = '-';
        el.kpiRegioes.textContent = '-';
        el.heroAlertas.textContent = 'Dados indisponíveis';
    }

    function limparGraficoLinhaTempo() {
        if (!graficoIRP) return;

        graficoIRP.data.labels = [];
        graficoIRP.data.datasets[0].data = [];
        graficoIRP.update();
    }

    function cliqueGraficoLinhaTempo(dados) {
        return (_event, points) => {
            if (!points.length) return;
            const item = dados[points[0].index];
            if (!item || !item.dia) return;

            filtroDiaSelecionado = item.dia;
            el.diaTxt.textContent = formatarData(item.dia);
            el.filtroDiaAtivo.hidden = false;
            atualizarDashboard({ zoomSelection: Boolean(regiaoSelecionada() || municipioSelecionado()) });
        };
    }

    function atualizarGraficoLinhaTempo(dados) {
        if (!el.graficoLinhaTempo || typeof Chart === 'undefined') return;

        const anos = new Set(
            dados
                .map((item) => String(item?.dia || '').split('-')[0])
                .filter((ano) => /^\d{4}$/.test(ano))
        );
        const incluirAnoNoRotulo = anos.size > 1;
        const labels = dados.map((item) => formatarDataCurtaGrafico(item.dia, incluirAnoNoRotulo));
        const valores = dados.map((item) => Number(item.irp || 0));
        const tooltipTitulo = (contexto) => {
            const indice = contexto?.[0]?.dataIndex;
            if (!Number.isInteger(indice) || !dados[indice]) return '';
            return `Dia ${formatarData(dados[indice].dia)}`;
        };
        const tooltipValor = (contexto) => `${Number(contexto?.raw ?? 0)} pts IRP`;

        if (graficoIRP) {
            graficoIRP.data.labels = labels;
            graficoIRP.data.datasets[0].data = valores;
            graficoIRP.options.onClick = cliqueGraficoLinhaTempo(dados);
            if (graficoIRP.options?.plugins?.tooltip?.callbacks) {
                graficoIRP.options.plugins.tooltip.callbacks.title = tooltipTitulo;
                graficoIRP.options.plugins.tooltip.callbacks.label = tooltipValor;
            }
            graficoIRP.update();
            return;
        }

        graficoIRP = new Chart(el.graficoLinhaTempo, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'IRP diário',
                    data: valores,
                    borderColor: '#D94F04',
                    backgroundColor: 'rgba(217, 79, 4, 0.14)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: tooltipTitulo,
                            label: tooltipValor
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 8
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Pontos IRP'
                        }
                    }
                },
                onClick: cliqueGraficoLinhaTempo(dados)
            }
        });
    }

    function carregarMunicipiosBase() {
        return obterJson('/assets/geojson/municipios_pa.geojson')
            .then((geojson) => {
                camadaMunicipios.clearLayers();
                camadaMunicipios.addData(geojson);
            });
    }

    function carregarRegioesBase() {
        return obterJson('/assets/geojson/municipios_regioes_pa.geojson')
            .then((geojson) => {
                camadaRegioes.clearLayers();
                camadaRegioes.addData(geojson);
            });
    }

    function carregarPressaoMunicipios(query, signal) {
        return obterJson(`${mapaApiBase}/municipios_pressao.php?${query}`, signal)
            .then((dados) => {
                dadosMunicipios = {};
                (Array.isArray(dados) ? dados : []).forEach((item) => {
                    dadosMunicipios[String(item.cod_ibge)] = item;
                });
                camadaMunicipios.setStyle(estiloMunicipio);
            })
            .catch((error) => {
                if (erroAbortado(error)) throw error;
                dadosMunicipios = {};
                camadaMunicipios.setStyle(estiloMunicipio);
                throw error;
            });
    }

    function carregarPressaoRegioes(query, signal) {
        return obterJson(`${mapaApiBase}/regioes_pressao.php?${query}`, signal)
            .then((dados) => {
                dadosRegioes = {};
                (Array.isArray(dados) ? dados : []).forEach((item) => {
                    dadosRegioes[String(item.regiao)] = item;
                });
                camadaRegioes.setStyle(estiloRegiao);
            })
            .catch((error) => {
                if (erroAbortado(error)) throw error;
                dadosRegioes = {};
                camadaRegioes.setStyle(estiloRegiao);
                throw error;
            });
    }

    function carregarAlertas(query, signal) {
        return obterJson(`${mapaApiBase}/alertas_ativos.php?${query}`, signal)
            .then((geojson) => {
                camadaAlertas.clearLayers();
                if (geojson && Array.isArray(geojson.features) && geojson.features.length) {
                    camadaAlertas.addData(geojson);
                }
            })
            .catch((error) => {
                if (erroAbortado(error)) throw error;
                camadaAlertas.clearLayers();
                throw error;
            });
    }

    function carregarKPIs(query, signal) {
        return obterJson(`${mapaApiBase}/kpis.php?${query}`, signal)
            .then((dados) => {
                el.kpiAtivos.textContent = dados.alertas_ativos ?? 0;
                el.kpiMunicipios.textContent = dados.municipios ?? 0;
                el.kpiRegioes.textContent = dados.regioes ?? 0;
                el.heroAlertas.textContent = `${dados.alertas_ativos ?? 0} alertas`;
            })
            .catch((error) => {
                if (erroAbortado(error)) throw error;
                definirKPIsIndisponiveis();
                throw error;
            });
    }

    function renderizarPainelRegioes() {
        const lista = Object.values(dadosRegioes);

        if (!lista.length) {
            el.listaRegioes.innerHTML = '<div class="multirrisco-empty-box">Nenhuma região com alertas ativos para os filtros atuais.</div>';
            return;
        }

        lista.sort((a, b) => Number(b.pressao || 0) - Number(a.pressao || 0) || Number(b.alertas || 0) - Number(a.alertas || 0));
        el.listaRegioes.innerHTML = '';
        const pressaoMaxima = lista.reduce((maximo, item) => Math.max(maximo, Number(item.pressao || 0)), 0);

        lista.forEach((item) => {
            const pressaoAtual = Number(item.pressao || 0);
            const larguraBarra = pressaoMaxima > 0 && pressaoAtual > 0
                ? Math.max(6, Math.min((pressaoAtual / pressaoMaxima) * 100, 100))
                : 0;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `regiao-item${regiaoSelecionada() === item.regiao ? ' is-selected' : ''}`;
            button.innerHTML = `
                <div class="regiao-item-topo">
                    <div>
                        <strong>${escapeHtml(item.regiao)}</strong>
                        <span>${escapeHtml(item.municipios)} municípios</span>
                    </div>
                    <span class="regiao-item-pressao">${escapeHtml(formatarPressao(item.pressao))}</span>
                </div>
                <div class="regiao-item-meta">
                    <span>${escapeHtml(item.alertas)} alertas</span>
                    <span>${escapeHtml(item.gravidade || 'Sem gravidade')}</span>
                </div>
                <div class="regiao-barra">
                    <span style="width:${larguraBarra.toFixed(2)}%; background:${escapeHtml(corNivel(item.gravidade))};"></span>
                </div>
            `;

            button.addEventListener('click', () => {
                setModoTerritorial('regioes');
                const layer = encontrarLayerRegiao(item.regiao);
                if (layer && regiaoSelecionada() !== item.regiao) {
                    destacarTemporariamente(layer, 'regiao');
                }
                zoomParaRegiao(item.regiao);
                abrirModalRegiao(item.regiao);
            });

            el.listaRegioes.appendChild(button);
        });
    }

    function carregarLinhaDoTempo(query, signal) {
        if (!el.graficoLinhaTempo || typeof Chart === 'undefined') {
            return Promise.resolve();
        }

        return obterJson(`${mapaApiBase}/linha_tempo_pressao.php?${query}`, signal)
            .then((dados) => {
                atualizarGraficoLinhaTempo(Array.isArray(dados) ? dados : []);
            })
            .catch((error) => {
                if (erroAbortado(error)) throw error;
                limparGraficoLinhaTempo();
                console.error('Erro ao carregar a linha do tempo do IRP.', error);
                throw error;
            });
    }

    function limparFiltroDia() {
        filtroDiaSelecionado = null;
        el.filtroDiaAtivo.hidden = true;
        atualizarDashboard({ zoomSelection: Boolean(regiaoSelecionada() || municipioSelecionado()) });
    }

    function atualizarLegendaCompdec() {
        const simEl = document.getElementById('compdec-sim');
        const naoEl = document.getElementById('compdec-nao');
        if (simEl) simEl.textContent = totalCompdecSim;
        if (naoEl) naoEl.textContent = totalCompdecNao;
    }

    function abrirDrawerCompdec(dc) {
        if (!dc || !el.drawerCompdec || !el.overlayCompdec || !el.conteudoCompdec) {
            return;
        }

        const temCompdec = dc.tem_compdec === true || dc.tem_compdec === 'SIM';

        el.conteudoCompdec.innerHTML = `
            <div class="multirrisco-compdec-grid">
                <p><strong>Região de integração</strong>${escapeHtml(dc.regiao_integracao || '-')}</p>
                <p><strong>Município</strong>${escapeHtml(dc.municipio || '-')}</p>
                <p><strong>Prefeito</strong>${escapeHtml(dc.prefeito || '-')}</p>
                <p><strong>UBM</strong>${escapeHtml(dc.ubm || '-')}</p>
                <p><strong>Coordenador</strong>${escapeHtml(dc.coordenador || '-')}</p>
                <p><strong>Telefone</strong>${escapeHtml(dc.telefone || '-')}</p>
                <p><strong>Email</strong>${escapeHtml(dc.email || '-')}</p>
                <p><strong>Endereço</strong>${escapeHtml(dc.endereco || '-')}</p>
                <p><strong>COMPDEC</strong><span class="${temCompdec ? 'tag-sim' : 'tag-nao'}">${temCompdec ? 'SIM' : 'NÃO'}</span></p>
                <p><strong>Última atualização</strong>${escapeHtml(dc.data_atualizacao || '-')}</p>
            </div>
        `;

        el.drawerCompdec.classList.add('aberto');
        el.overlayCompdec.classList.add('ativo');
    }

    function fecharDrawerCompdec() {
        if (!el.drawerCompdec || !el.overlayCompdec) {
            return;
        }

        el.drawerCompdec.classList.remove('aberto');
        el.overlayCompdec.classList.remove('ativo');
    }

    function garantirEstadoInicialModais() {
        if (el.modalAjuda) {
            el.modalAjuda.classList.remove('ativo');
        }

        if (el.modalIRP) {
            el.modalIRP.classList.remove('is-open');
            el.modalIRP.setAttribute('aria-hidden', 'true');
        }

        if (el.modalTerritorio) {
            el.modalTerritorio.classList.remove('ativo');
            el.modalTerritorio.setAttribute('aria-hidden', 'true');
        }

        fecharDrawerCompdec();

        const modalIA = document.getElementById('modalIA');
        if (modalIA) {
            modalIA.style.display = 'none';
        }
    }

    function carregarCompdec() {
        if (!compdecHabilitado) {
            return Promise.resolve();
        }

        if (compdecCarregado) {
            return Promise.resolve();
        }

        if (carregamentoCompdec) {
            return carregamentoCompdec;
        }

        carregamentoCompdec = obterJson(`${mapaApiBase}/compdec.php`)
            .then((lista) => {
                totalCompdecSim = 0;
                totalCompdecNao = 0;
                camadaCompdec.clearLayers();

                (Array.isArray(lista) ? lista : []).forEach((dc) => {
                    const latRaw = dc.lat ?? dc.latitude ?? dc.Latitude ?? dc.LATITUDE;
                    const lngRaw = dc.lng ?? dc.lon ?? dc.longitude ?? dc.Longitude ?? dc.LONGITUDE;
                    const latitude = Number(String(latRaw ?? '').replace(',', '.'));
                    const longitude = Number(String(lngRaw ?? '').replace(',', '.'));

                    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;

                    const temCompdec = dc.tem_compdec === true || dc.tem_compdec === 'SIM';
                    if (temCompdec) totalCompdecSim++;
                    else totalCompdecNao++;

                    const marker = L.circleMarker([latitude, longitude], {
                        pane: 'paneCompdec',
                        radius: 6,
                        color: '#0B3C68',
                        fillColor: temCompdec ? '#2E7D32' : '#C62828',
                        fillOpacity: 0.85,
                        weight: 2
                    });

                    marker.bindTooltip(`<strong>${escapeHtml(dc.municipio || '-')}</strong><br>COMPDEC: ${temCompdec ? 'SIM' : 'NÃO'}`, { sticky: true });
                    marker.on('click', () => abrirDrawerCompdec(dc));
                    camadaCompdec.addLayer(marker);
                });

                atualizarLegendaCompdec();
                compdecCarregado = true;
                carregamentoCompdec = null;
            })
            .catch((error) => {
                carregamentoCompdec = null;
                console.error('Erro ao carregar a camada de COMPDEC.', error);
                throw error;
            });

        return carregamentoCompdec;
    }

    function alternarAlertas(checked) {
        if (checked) mapa.addLayer(camadaAlertas);
        else mapa.removeLayer(camadaAlertas);
        notificarContextoMapaIA();
    }

    function alternarCompdec(checked) {
        if (!compdecHabilitado) {
            return;
        }

        const pane = mapa.getPane('paneCompdec');

        if (checked) {
            atualizarStatus('Carregando camada de DC municipais...', true);

            carregarCompdec().then(() => {
                if (!el.toggleCompdec?.checked) {
                    atualizarStatus('Leitura territorial atualizada.', false);
                    notificarContextoMapaIA();
                    return;
                }

                mapa.addLayer(camadaCompdec);
                pane.style.pointerEvents = 'auto';
                atualizarStatus('Camada de DC municipais carregada.', false);
                notificarContextoMapaIA();
            }).catch(() => {
                if (el.toggleCompdec) el.toggleCompdec.checked = false;
                mapa.removeLayer(camadaCompdec);
                pane.style.pointerEvents = 'none';
                fecharDrawerCompdec();
                atualizarStatus('Não foi possível carregar a camada de DC municipais.', false);
                notificarContextoMapaIA();
            });
        } else {
            mapa.removeLayer(camadaCompdec);
            pane.style.pointerEvents = 'none';
            fecharDrawerCompdec();
            notificarContextoMapaIA();
        }
    }

    function atualizarDashboard(options = {}) {
        const zoomSelection = Boolean(options.zoomSelection);
        const query = filtrosQuery();
        const requestId = ++dashboardRequestId;

        if (dashboardController) {
            dashboardController.abort();
        }

        dashboardController = new AbortController();
        const { signal } = dashboardController;

        atualizarStatus('Atualizando mapa e indicadores...', true);
        restaurarDestaqueTemporario();
        limparFocoManual();
        atualizarResumoFiltros();

        return Promise.allSettled([
            carregarPressaoMunicipios(query, signal),
            carregarPressaoRegioes(query, signal),
            carregarAlertas(query, signal),
            carregarKPIs(query, signal),
            carregarLinhaDoTempo(query, signal)
        ]).then((resultados) => {
            if (signal.aborted || requestId !== dashboardRequestId) {
                return;
            }

            const houveFalha = resultados.some((resultado) => resultado.status === 'rejected' && !erroAbortado(resultado.reason));

            renderizarPainelRegioes();
            atualizarResumoFiltros();
            atualizarFocoPadrao();
            atualizarStatus(
                houveFalha ? 'Leitura territorial atualizada com avisos.' : 'Leitura territorial atualizada.',
                false
            );

            if (zoomSelection) {
                if (municipioSelecionado()) zoomParaMunicipio(municipioSelecionado());
                else if (regiaoSelecionada()) zoomParaRegiao(regiaoSelecionada());
            } else if (primeiraCarga && camadaMunicipios.getLayers().length) {
                mapa.fitBounds(camadaMunicipios.getBounds(), { padding: [20, 20], maxZoom: 7 });
            }

            mapa.invalidateSize();
            notificarContextoMapaIA();

            primeiraCarga = false;
        });
    }

    function aplicarFiltros(event) {
        if (event && typeof event.preventDefault === 'function') event.preventDefault();
        ajustarModoPorFiltro();
        return atualizarDashboard({ zoomSelection: Boolean(regiaoSelecionada() || municipioSelecionado()) });
    }

    function limparFiltros() {
        if (el.dataInicio) el.dataInicio.value = '';
        if (el.dataFim) el.dataFim.value = '';
        if (el.tipoEvento) el.tipoEvento.value = '';
        if (el.gravidade) el.gravidade.value = '';
        if (el.fonte) el.fonte.value = '';
        if (el.regiao) el.regiao.value = '';

        preencherMunicipios('', '');
        filtroDiaSelecionado = null;
        el.filtroDiaAtivo.hidden = true;
        setModoTerritorial('municipios');
        return atualizarDashboard({ zoomSelection: false });
    }

    function abrirModalIRP() {
        if (el.modalIRP) {
            el.modalIRP.classList.add('is-open');
            el.modalIRP.setAttribute('aria-hidden', 'false');
        }
    }

    function fecharModalIRP() {
        if (el.modalIRP) {
            el.modalIRP.classList.remove('is-open');
            el.modalIRP.setAttribute('aria-hidden', 'true');
        }
    }

    function abrirModalAjuda() {
        if (el.modalAjuda) el.modalAjuda.classList.add('ativo');
    }

    function fecharModalAjuda() {
        if (el.modalAjuda) el.modalAjuda.classList.remove('ativo');
    }

    function resolverMunicipioIA(referencia) {
        const valor = String(referencia || '').trim();

        if (!valor) return null;

        if (/^\d{7}$/.test(valor) && municipiosPorCodigo[valor]) {
            return municipiosPorCodigo[valor];
        }

        const alvo = normalizarTexto(valor);
        return municipiosCatalogo.find((item) => normalizarTexto(item.municipio) === alvo) || null;
    }

    function aplicarFocoRegiaoIA(regiao, abrirModal = false) {
        if (!regiao || !el.regiao) return Promise.resolve();

        el.regiao.value = regiao;
        preencherMunicipios(regiao, '');

        if (el.municipio) {
            el.municipio.value = '';
        }

        setModoTerritorial('regioes');

        return aplicarFiltros().then(() => {
            zoomParaRegiao(regiao);
            if (abrirModal) abrirModalRegiao(regiao);
        });
    }

    function aplicarFocoMunicipioIA(referencia, abrirModal = false) {
        const municipio = resolverMunicipioIA(referencia);

        if (!municipio || !el.regiao || !el.municipio) {
            return Promise.resolve();
        }

        el.regiao.value = municipio.regiao || '';
        preencherMunicipios(el.regiao.value, municipio.cod_ibge);
        el.municipio.value = municipio.cod_ibge;
        setModoTerritorial('municipios');

        return aplicarFiltros().then(() => {
            zoomParaMunicipio(municipio.cod_ibge);
            if (abrirModal) abrirModalMunicipio(municipio.cod_ibge, municipio.municipio);
        });
    }

    window.executarAcaoMapaIA = function (acao) {
        if (!acao || !acao.tipo) return;

        switch (acao.tipo) {
            case 'zoom_regiao':
            case 'filtrar_regiao':
                return aplicarFocoRegiaoIA(acao.regiao || acao.valor || '', Boolean(acao.abrir_modal));
            case 'zoom_municipio':
            case 'filtrar_municipio':
                return aplicarFocoMunicipioIA(
                    acao.cod_ibge || acao.municipio || acao.valor || '',
                    Boolean(acao.abrir_modal)
                );
            case 'abrir_modal_regiao':
                return aplicarFocoRegiaoIA(acao.regiao || acao.valor || '', true);
            case 'abrir_modal_municipio':
                return aplicarFocoMunicipioIA(acao.cod_ibge || acao.municipio || acao.valor || '', true);
            case 'limpar_filtros':
                return limparFiltros();
            case 'abrir_irp':
                abrirModalIRP();
                return Promise.resolve();
            case 'abrir_ajuda':
                abrirModalAjuda();
                return Promise.resolve();
            case 'alternar_camada':
                if (acao.camada === 'alertas' && el.toggleAlertas) {
                    el.toggleAlertas.checked = Boolean(acao.ativa);
                    alternarAlertas(el.toggleAlertas.checked);
                }

                if (acao.camada === 'compdec' && el.toggleCompdec) {
                    el.toggleCompdec.checked = Boolean(acao.ativa);
                    alternarCompdec(el.toggleCompdec.checked);
                }

                return Promise.resolve();
            default:
                return Promise.resolve();
        }
    };

    window.obterContextoMapaIA = obterContextoMapaIA;

    const legenda = L.control({ position: 'bottomright' });
    legenda.onAdd = function onAdd() {
        const div = L.DomUtil.create('div', 'legenda-mapa');
        div.innerHTML = `
            <strong>Pressão de risco</strong>
            <div class="legenda-item"><span class="legenda-cor" style="background:#F1F5F7"></span> Sem alertas</div>
            <div class="legenda-item"><span class="legenda-cor" style="background:#CCC9C7"></span> Baixo</div>
            <div class="legenda-item"><span class="legenda-cor" style="background:#FFE000"></span> Moderado</div>
            <div class="legenda-item"><span class="legenda-cor" style="background:#FF7B00"></span> Alto</div>
            <div class="legenda-item"><span class="legenda-cor" style="background:#FF1D08"></span> Muito alto</div>
            <div class="legenda-item"><span class="legenda-cor" style="background:#7A28C6"></span> Extremo</div>
        `;
        return div;
    };
    legenda.addTo(mapa);

    if (compdecHabilitado) {
        const legendaCompdec = L.control({ position: 'bottomleft' });
        legendaCompdec.onAdd = function onAdd() {
            const div = L.DomUtil.create('div', 'legenda-mapa');
            div.innerHTML = `
                <strong>Defesa Civil Municipal</strong>
                <div class="legenda-item"><span class="legenda-cor" style="background:#2E7D32"></span> Tem COMPDEC: <strong id="compdec-sim">0</strong></div>
                <div class="legenda-item"><span class="legenda-cor" style="background:#C62828"></span> Não tem COMPDEC: <strong id="compdec-nao">0</strong></div>
            `;
            return div;
        };
        legendaCompdec.addTo(mapa);
    }

    const norte = L.control({ position: 'topright' });
    norte.onAdd = function onAdd() {
        const div = L.DomUtil.create('div', 'rosa-ventos');
        div.innerHTML = '<img src="/assets/images/norte.png" alt="Norte">';
        return div;
    };
    norte.addTo(mapa);

    if (el.form) el.form.addEventListener('submit', aplicarFiltros);
    if (el.btnLimparFiltros) el.btnLimparFiltros.addEventListener('click', limparFiltros);
    if (el.btnAbrirAjuda) el.btnAbrirAjuda.addEventListener('click', abrirModalAjuda);
    if (el.btnAbrirIRP) el.btnAbrirIRP.addEventListener('click', abrirModalIRP);
    if (el.btnLimparFiltroDia) el.btnLimparFiltroDia.addEventListener('click', limparFiltroDia);

    if (el.regiao) {
        el.regiao.addEventListener('change', () => {
            const regiao = el.regiao.value || '';
            const municipioAtual = municipioSelecionado();

            preencherMunicipios(regiao, municipioAtual);

            if (municipioAtual) {
                const municipio = municipiosPorCodigo[municipioAtual];
                if (!municipio || municipio.regiao !== regiao) {
                    el.municipio.value = '';
                }
            }

            if (regiao && !municipioSelecionado()) {
                setModoTerritorial('regioes');
            }

            aplicarFiltros();
        });
    }

    if (el.municipio) {
        el.municipio.addEventListener('change', () => {
            const codIbge = municipioSelecionado();

            if (codIbge) {
                const municipio = municipiosPorCodigo[codIbge];

                if (municipio && el.regiao && el.regiao.value !== municipio.regiao) {
                    el.regiao.value = municipio.regiao;
                    preencherMunicipios(municipio.regiao, codIbge);
                }

                setModoTerritorial('municipios');
            }

            aplicarFiltros();
        });
    }

    el.radiosModo.forEach((radio) => {
        radio.addEventListener('change', () => setModoTerritorial(radio.value));
    });

    if (el.toggleAlertas) el.toggleAlertas.addEventListener('change', (event) => alternarAlertas(event.target.checked));
    if (el.toggleCompdec) el.toggleCompdec.addEventListener('change', (event) => alternarCompdec(event.target.checked));

    document.querySelectorAll('[data-close-irp]').forEach((button) => button.addEventListener('click', fecharModalIRP));
    document.querySelectorAll('[data-close-ajuda]').forEach((button) => button.addEventListener('click', fecharModalAjuda));
    document.querySelectorAll('[data-close-territorio]').forEach((button) => button.addEventListener('click', fecharModalTerritorio));
    document.querySelectorAll('[data-close-compdec]').forEach((button) => button.addEventListener('click', fecharDrawerCompdec));

    document.addEventListener('click', (event) => {
        if (event.target === el.modalAjuda) fecharModalAjuda();
        if (event.target === el.modalIRP) fecharModalIRP();
        if (event.target === el.modalTerritorio) fecharModalTerritorio();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        fecharModalAjuda();
        fecharModalIRP();
        fecharModalTerritorio();
        fecharDrawerCompdec();
    });

    window.addEventListener('load', () => mapa.invalidateSize());
    window.addEventListener('pageshow', () => {
        garantirEstadoInicialModais();
    });
    window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(() => mapa.invalidateSize(), 120);
    });

    garantirEstadoInicialModais();

    Promise.all([
        carregarMunicipiosBase(),
        carregarRegioesBase()
    ]).then(() => {
        mapa.addLayer(camadaMunicipios);
        mapa.addLayer(camadaAlertas);

        preencherMunicipios(regiaoSelecionada(), municipioSelecionado());
        atualizarResumoFiltros();
        atualizarFocoPadrao();
        notificarContextoMapaIA();
        mapa.invalidateSize();
        atualizarDashboard({ zoomSelection: false });
    }).catch((error) => {
        console.error('Erro ao carregar a base do mapa multirriscos.', error);
        atualizarStatus('Não foi possível carregar a base territorial do mapa.', false);
        renderizarFalhaMapa('Não foi possível carregar a base territorial do mapa. Tente atualizar a página.');
    });
})();

