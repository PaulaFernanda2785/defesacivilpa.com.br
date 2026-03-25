/* =====================================================
   ASSISTENTE MULTIRRISCOS - EXPERIENCIA OPERACIONAL
===================================================== */
(function () {
    function $(id) {
        return document.getElementById(id);
    }

    const state = {
        carregando: false,
        historico: [],
        contexto: null,
        controller: null,
        requestToken: 0
    };

    function criarElemento(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function obterContexto() {
        if (typeof window.obterContextoMapaIA === 'function') {
            return window.obterContextoMapaIA();
        }

        return {
            filtros: {},
            indicadores: {},
            camadas: {},
            ranking_regioes: []
        };
    }

    function contextoAtual() {
        state.contexto = obterContexto();
        return state.contexto;
    }

    function temFiltrosAtivos(contexto) {
        const filtros = contexto?.filtros || {};

        return Boolean(
            filtros.data_inicio ||
            filtros.data_fim ||
            filtros.tipo_evento ||
            filtros.gravidade ||
            filtros.fonte ||
            filtros.regiao ||
            filtros.municipio ||
            filtros.dia
        );
    }

    function resumoRecorte(contexto) {
        const filtros = contexto?.filtros || {};

        if (filtros.municipio_nome) {
            return `Municipio ${filtros.municipio_nome}`;
        }

        if (filtros.regiao) {
            return `Regiao ${filtros.regiao}`;
        }

        return 'Panorama estadual';
    }

    function resumoCamadas(contexto) {
        const camadas = contexto?.camadas || {};
        const partes = [];

        if (camadas.alertas_ativos) partes.push('Alertas');
        if (camadas.compdec) partes.push('COMPDEC');

        return partes.length ? partes.join(' + ') : 'Sem camadas auxiliares';
    }

    function registrarHistorico(role, texto) {
        state.historico.push({ role, texto, ts: Date.now() });
        state.historico = state.historico.slice(-8);
    }

    function renderizarContexto() {
        const box = $('iaContextoResumo');
        if (!box) return;

        const contexto = contextoAtual();
        const indicadores = contexto.indicadores || {};
        const filtros = contexto.filtros || {};

        const cards = [
            { label: 'Recorte', value: resumoRecorte(contexto) },
            { label: 'Modo', value: filtros.modo === 'regioes' ? 'Regioes' : 'Municipios' },
            { label: 'Alertas', value: `${Number(indicadores.alertas_ativos || 0)} ativos` },
            { label: 'Camadas', value: resumoCamadas(contexto) }
        ];

        box.innerHTML = '';

        cards.forEach((card) => {
            const article = criarElemento('article', 'ia-contexto-card');
            article.appendChild(criarElemento('span', 'ia-contexto-label', card.label));
            article.appendChild(criarElemento('strong', 'ia-contexto-value', card.value));
            box.appendChild(article);
        });
    }

    function sugestoesContextuais() {
        const contexto = contextoAtual();
        const ranking = Array.isArray(contexto.ranking_regioes) ? contexto.ranking_regioes : [];
        const topo = ranking[0] || null;
        const filtros = contexto.filtros || {};
        const sugestoes = [];

        sugestoes.push({
            label: 'Resumo operacional',
            prompt: 'Faca um resumo operacional do recorte atual.'
        });

        if (filtros.regiao) {
            sugestoes.push({
                label: `Analisar ${filtros.regiao}`,
                prompt: `Faca um resumo operacional da regiao ${filtros.regiao}.`
            });
            sugestoes.push({
                label: 'Abrir detalhe da regiao',
                prompt: `Abra o detalhamento da regiao ${filtros.regiao}.`
            });
        } else if (topo?.regiao) {
            sugestoes.push({
                label: `Destacar ${topo.regiao}`,
                prompt: `Mostre a regiao ${topo.regiao} no mapa e resuma o cenario atual.`
            });
        } else {
            sugestoes.push({
                label: 'Regiao lider',
                prompt: 'Qual regiao esta com maior pressao de risco agora?'
            });
        }

        if (filtros.municipio_nome) {
            sugestoes.push({
                label: `Analisar ${filtros.municipio_nome}`,
                prompt: `Analise o municipio ${filtros.municipio_nome} com base no recorte atual.`
            });
            sugestoes.push({
                label: 'Abrir detalhe do municipio',
                prompt: `Abra o detalhamento do municipio ${filtros.municipio_nome}.`
            });
        } else {
            sugestoes.push({
                label: 'Municipios extremos',
                prompt: 'Quais municipios estao em alerta extremo no recorte atual?'
            });
        }

        sugestoes.push({
            label: 'Evento dominante',
            prompt: 'Qual evento predomina no recorte atual?'
        });

        sugestoes.push({
            label: 'IRP atual',
            prompt: 'Como esta a pressao de risco atual no recorte do mapa?'
        });

        if (temFiltrosAtivos(contexto)) {
            sugestoes.push({
                label: 'Limpar recorte',
                prompt: 'Limpe os filtros e volte para a visao geral do estado.'
            });
        }

        return sugestoes.slice(0, 6);
    }

    function cancelarConsultaAtiva() {
        if (state.controller) {
            state.controller.abort();
            state.controller = null;
        }
    }

    function renderizarSugestoes() {
        const box = $('iaSugestoes');
        if (!box) return;

        const sugestoes = sugestoesContextuais();
        box.innerHTML = '';

        const titulo = criarElemento('div', 'ia-sugestoes-head');
        titulo.appendChild(criarElemento('strong', '', 'Sugestoes dinamicas'));
        titulo.appendChild(criarElemento('span', '', 'Baseadas no recorte atual do mapa'));
        box.appendChild(titulo);

        const grid = criarElemento('div', 'ia-sugestoes-grid');

        sugestoes.forEach((sugestao) => {
            const button = criarElemento('button', 'ia-sugestao-chip', sugestao.label);
            button.type = 'button';
            button.addEventListener('click', () => window.usarSugestao(sugestao.prompt));
            grid.appendChild(button);
        });

        box.appendChild(grid);
    }

    function criarMensagemTexto(classe, titulo, subtitulo, texto) {
        const article = criarElemento('article', `ia-msg ${classe}`);
        const head = criarElemento('div', 'ia-msg-head');
        head.appendChild(criarElemento('strong', '', titulo));

        if (subtitulo) {
            head.appendChild(criarElemento('span', '', subtitulo));
        }

        article.appendChild(head);

        const body = criarElemento('div', 'ia-msg-body');
        body.appendChild(criarElemento('p', '', texto));
        article.appendChild(body);

        return article;
    }

    function adicionarMensagemUsuario(texto) {
        const box = $('iaMensagens');
        if (!box) return;

        box.appendChild(criarMensagemTexto('ia-usuario', 'Voce', 'Pergunta enviada', texto));
        box.scrollTop = box.scrollHeight;
    }

    function adicionarMensagemCarregando() {
        const box = $('iaMensagens');
        if (!box) return null;

        const node = criarMensagemTexto('ia-sistema ia-msg-loading', 'Assistente', 'Analisando o mapa', 'Preparando a resposta...');
        box.appendChild(node);
        box.scrollTop = box.scrollHeight;
        return node;
    }

    function renderizarCardsResumo(container, cards) {
        if (!Array.isArray(cards) || !cards.length) return;

        const grid = criarElemento('div', 'ia-cards-grid');

        cards.forEach((card) => {
            const article = criarElemento('article', 'ia-card');
            article.appendChild(criarElemento('span', 'ia-card-label', card.label || 'Indicador'));
            article.appendChild(criarElemento('strong', 'ia-card-value', String(card.value ?? '-')));
            grid.appendChild(article);
        });

        container.appendChild(grid);
    }

    function renderizarAcoes(container, acoes) {
        if (!Array.isArray(acoes) || !acoes.length) return;

        const wrap = criarElemento('div', 'ia-acoes');

        acoes.forEach((acao) => {
            const button = criarElemento('button', 'ia-acao-btn', acao.label || acao.descricao || 'Aplicar no mapa');
            button.type = 'button';
            button.addEventListener('click', () => {
                if (typeof window.executarAcaoMapaIA === 'function') {
                    window.executarAcaoMapaIA(acao);
                }
            });
            wrap.appendChild(button);
        });

        container.appendChild(wrap);
    }

    function renderizarFollowUps(container, followUps) {
        if (!Array.isArray(followUps) || !followUps.length) return;

        const wrap = criarElemento('div', 'ia-followups');
        wrap.appendChild(criarElemento('span', 'ia-followups-label', 'Continuar a conversa'));

        const list = criarElemento('div', 'ia-followups-list');

        followUps.forEach((item) => {
            const prompt = typeof item === 'string' ? item : String(item?.prompt || '');
            const label = typeof item === 'string' ? item : String(item?.label || item?.prompt || '');

            if (!prompt) return;

            const button = criarElemento('button', 'ia-followup-chip', label);
            button.type = 'button';
            button.addEventListener('click', () => window.usarSugestao(prompt));
            list.appendChild(button);
        });

        wrap.appendChild(list);
        container.appendChild(wrap);
    }

    function adicionarMensagemAssistente(payload) {
        const box = $('iaMensagens');
        if (!box) return;

        const resposta = payload?.resposta || 'Sem resposta disponivel.';
        const dados = payload?.dados || {};

        const article = criarElemento('article', 'ia-msg ia-resposta');
        const head = criarElemento('div', 'ia-msg-head');
        head.appendChild(criarElemento('strong', '', 'Assistente operacional'));
        head.appendChild(criarElemento('span', '', dados?.escopo_label || 'Resposta contextualizada pelo mapa'));
        article.appendChild(head);

        const body = criarElemento('div', 'ia-msg-body');
        body.appendChild(criarElemento('p', '', resposta));
        renderizarCardsResumo(body, dados.resumo_cards || []);
        renderizarAcoes(body, dados.acoes_operacionais || []);
        renderizarFollowUps(body, dados.follow_ups || []);

        article.appendChild(body);
        box.appendChild(article);
        box.scrollTop = box.scrollHeight;
    }

    function definirCarregando(ativo) {
        state.carregando = ativo;

        const input = $('iaPergunta');
        const botaoEnviar = document.querySelector('.btn-ia-enviar');

        if (input) input.disabled = ativo;
        if (botaoEnviar) botaoEnviar.disabled = ativo;
    }

    function limparConversa() {
        const box = $('iaMensagens');
        if (!box) return;

        cancelarConsultaAtiva();
        definirCarregando(false);
        state.historico = [];
        box.innerHTML = '';
        if ($('iaPergunta')) $('iaPergunta').value = '';

        const inicial = criarMensagemTexto(
            'ia-resposta ia-msg-apresentacao',
            'Assistente operacional',
            'Pronto para apoiar a leitura do mapa',
            'Posso resumir o recorte atual, identificar prioridades e aplicar acoes diretamente no mapa.'
        );

        box.appendChild(inicial);
        renderizarContexto();
        renderizarSugestoes();
    }

    function executarAcoesAutomaticas(acoes) {
        if (!Array.isArray(acoes)) return;

        acoes.forEach((acao) => {
            if (acao.executar === true && typeof window.executarAcaoMapaIA === 'function') {
                window.executarAcaoMapaIA(acao);
            }
        });
    }

    window.abrirIA = function () {
        const modal = $('modalIA');
        if (!modal) return;

        modal.style.display = 'block';
        renderizarContexto();
        renderizarSugestoes();
        $('iaPergunta')?.focus();
    };

    window.fecharIA = function () {
        const modal = $('modalIA');
        if (!modal) return;

        modal.style.display = 'none';
    };

    window.enviarPerguntaIA = function (textoManual = null) {
        const input = $('iaPergunta');
        const pergunta = String(textoManual ?? input?.value ?? '').trim();

        if (!pergunta || state.carregando) return;

        const contexto = contextoAtual();
        const token = Date.now();
        const controller = new AbortController();

        adicionarMensagemUsuario(pergunta);
        const loadingNode = adicionarMensagemCarregando();
        registrarHistorico('user', pergunta);

        if (input) input.value = '';
        state.requestToken = token;
        state.controller = controller;
        definirCarregando(true);

        fetch('/api/ia/consultar.php', {
            method: 'POST',
            credentials: 'same-origin',
            signal: controller.signal,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.APP_CSRF_TOKEN || ''
            },
            body: JSON.stringify({
                pergunta,
                contexto,
                historico: state.historico
            })
        })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Erro HTTP');
            }

            return response.json();
        })
        .then((data) => {
            if (state.requestToken !== token) return;
            loadingNode?.remove();
            adicionarMensagemAssistente(data);
            registrarHistorico('assistant', data?.resposta || '');
            executarAcoesAutomaticas(data?.dados?.acoes_operacionais || []);
            renderizarContexto();
            renderizarSugestoes();
        })
        .catch((error) => {
            if (error?.name === 'AbortError') {
                loadingNode?.remove();
                return;
            }

            if (state.requestToken !== token) return;
            console.error('[IA]', error);
            loadingNode?.remove();
            adicionarMensagemAssistente({
                resposta: 'Nao foi possivel consultar o assistente agora. Tente novamente em instantes.',
                dados: {
                    escopo_label: 'Falha temporaria',
                    follow_ups: ['Faca um resumo operacional do recorte atual.']
                }
            });
        })
        .finally(() => {
            if (state.requestToken !== token) return;
            state.controller = null;
            definirCarregando(false);
            $('iaPergunta')?.focus();
        });
    };

    window.usarSugestao = function (texto) {
        window.enviarPerguntaIA(texto);
    };

    document.addEventListener('click', (event) => {
        const modal = $('modalIA');
        const conteudo = modal?.querySelector('.modal-ia-conteudo');

        if (!modal || !conteudo) return;

        if (event.target === modal) {
            window.fecharIA();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            window.fecharIA();
        }
    });

    $('iaPergunta')?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            window.enviarPerguntaIA();
        }
    });

    $('btnIANovaConversa')?.addEventListener('click', limparConversa);

    window.addEventListener('multirrisco:contexto-atualizado', () => {
        renderizarContexto();
        renderizarSugestoes();
    });

    limparConversa();
})();
