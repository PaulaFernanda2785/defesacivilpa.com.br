document.addEventListener('DOMContentLoaded', function () {
    const config = Object.assign({
        filtrosBaseUrl: '/pages/analises/api/filtros_base.php',
        analiseUrl: '/pages/analises/api/analise_global.php',
        pdfUrl: '/pages/analises/pdf/relatorio_analitico.php',
        pdfEnabled: true
    }, window.ANALISE_GLOBAL_CONFIG || {});

    const el = {
        ano: document.getElementById('filtroAno'),
        mes: document.getElementById('filtroMes'),
        regiao: document.getElementById('filtroRegiao'),
        municipio: document.getElementById('filtroMunicipio'),
        modal: document.getElementById('modalRelatorio'),
        fecharModal: document.getElementById('fecharModal'),
        btnGerar: document.getElementById('btnGerarRelatorio'),
        btnBaixarPDF: document.getElementById('btnBaixarPDF'),
        conteudo: document.getElementById('conteudoRelatorio')
    };

    if (!el.ano || !el.mes || !el.regiao || !el.municipio || !el.modal || !el.btnGerar || !el.conteudo) {
        return;
    }

    const meses = [
        { value: '', label: 'Todos' },
        { value: '1', label: 'Janeiro' },
        { value: '2', label: 'Fevereiro' },
        { value: '3', label: 'Marco' },
        { value: '4', label: 'Abril' },
        { value: '5', label: 'Maio' },
        { value: '6', label: 'Junho' },
        { value: '7', label: 'Julho' },
        { value: '8', label: 'Agosto' },
        { value: '9', label: 'Setembro' },
        { value: '10', label: 'Outubro' },
        { value: '11', label: 'Novembro' },
        { value: '12', label: 'Dezembro' }
    ];

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function abrirModal() {
        el.modal.style.display = 'flex';
    }

    function fecharModal() {
        el.modal.style.display = 'none';
    }

    function filtrosAtuais() {
        return {
            ano: el.ano.value || '',
            mes: el.mes.value || '',
            regiao: el.regiao.value || '',
            municipio: el.municipio.value || ''
        };
    }

    function queryString(params) {
        const query = new URLSearchParams();

        Object.entries(params).forEach(function ([key, value]) {
            if (value) {
                query.set(key, value);
            }
        });

        return query.toString();
    }

    function opcoesNoSelect(select, items, placeholder) {
        select.innerHTML = '';

        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);

        items.forEach(function (item) {
            const node = document.createElement('option');
            node.value = String(item);
            node.textContent = String(item);
            select.appendChild(node);
        });
    }

    function carregarJson(url) {
        return fetch(url, { headers: { Accept: 'application/json' } }).then(function (response) {
            if (!response.ok) {
                throw new Error('Falha ao carregar dados.');
            }

            return response.json();
        });
    }

    function mostrarCarregando() {
        el.conteudo.innerHTML = '<p style="text-align:center;padding:30px;">Gerando analise...</p>';
    }

    function mostrarErro() {
        el.conteudo.innerHTML = '<p style="color:#bf3434;text-align:center;padding:30px;">Nao foi possivel gerar o relatorio agora.</p>';
    }

    function tabelaVazia(texto) {
        return '<p>' + escapeHtml(texto) + '</p>';
    }

    function wrapTabela(html) {
        return '<div class="analises-tabela-wrap">' + html + '</div>';
    }

    function tabelaObjeto(obj) {
        const entries = Object.entries(obj || {});

        if (!entries.length) {
            return tabelaVazia('Sem dados para o recorte selecionado.');
        }

        const linhas = entries.map(function ([label, value]) {
            return '<tr><td>' + escapeHtml(label) + '</td><td>' + escapeHtml(value) + '</td></tr>';
        }).join('');

        return wrapTabela([
            '<table class="tabela-relatorio duas-colunas">',
            '<thead><tr><th>Descricao</th><th>Valor</th></tr></thead>',
            '<tbody>', linhas, '</tbody>',
            '</table>'
        ].join(''));
    }

    function tabelaLista(lista, campoDescricao, campoValor) {
        if (!Array.isArray(lista) || !lista.length) {
            return tabelaVazia('Sem dados para o recorte selecionado.');
        }

        const linhas = lista.map(function (item) {
            return '<tr><td>' + escapeHtml(item[campoDescricao]) + '</td><td>' + escapeHtml(item[campoValor]) + '</td></tr>';
        }).join('');

        return wrapTabela([
            '<table class="tabela-relatorio duas-colunas">',
            '<thead><tr><th>Descricao</th><th>Valor</th></tr></thead>',
            '<tbody>', linhas, '</tbody>',
            '</table>'
        ].join(''));
    }

    function tabelaListaOrdenada(lista, campoDescricao, campoValor) {
        if (!Array.isArray(lista) || !lista.length) {
            return tabelaVazia('Sem dados para o recorte selecionado.');
        }

        const linhas = lista.map(function (item, index) {
            return '<tr><td>' + (index + 1) + '</td><td>' + escapeHtml(item[campoDescricao]) + '</td><td>' + escapeHtml(item[campoValor]) + '</td></tr>';
        }).join('');

        return wrapTabela([
            '<table class="tabela-relatorio">',
            '<thead><tr><th>Ordem</th><th>Descricao</th><th>Valor</th></tr></thead>',
            '<tbody>', linhas, '</tbody>',
            '</table>'
        ].join(''));
    }

    function tabelaMultiEvento(obj) {
        const eventos = Object.keys(obj || {});

        if (!eventos.length) {
            return tabelaVazia('Sem comparativo de eventos para o recorte selecionado.');
        }

        const mesesComparados = Object.keys(obj[eventos[0]] || {});
        const header = ['<tr><th>Evento</th>']
            .concat(mesesComparados.map(function (mes) {
                return '<th>' + escapeHtml(mes) + '</th>';
            }))
            .concat(['</tr>'])
            .join('');

        const linhas = eventos.map(function (evento) {
            const valores = mesesComparados.map(function (mes) {
                return '<td>' + escapeHtml((obj[evento] || {})[mes] ?? 0) + '</td>';
            }).join('');

            return '<tr><td>' + escapeHtml(evento) + '</td>' + valores + '</tr>';
        }).join('');

        return wrapTabela('<table class="tabela-relatorio"><thead>' + header + '</thead><tbody>' + linhas + '</tbody></table>');
    }

    function tabelaCorrelacaoTipologia(lista) {
        if (!Array.isArray(lista) || !lista.length) {
            return tabelaVazia('Sem dados de correlacao no recorte selecionado.');
        }

        const severidades = ['BAIXO', 'MODERADO', 'ALTO', 'MUITO ALTO', 'EXTREMO'];
        const eventos = Array.from(new Set(lista.map(function (item) {
            return String(item.tipo_evento || '');
        }).filter(Boolean)));
        const mapa = {};

        lista.forEach(function (item) {
            const evento = String(item.tipo_evento || '');
            const gravidade = String(item.nivel_gravidade || '');

            if (!mapa[evento]) {
                mapa[evento] = {};
            }

            mapa[evento][gravidade] = item.total ?? 0;
        });

        const header = ['<tr><th>Evento</th>']
            .concat(severidades.map(function (item) {
                return '<th>' + escapeHtml(item) + '</th>';
            }))
            .concat(['</tr>'])
            .join('');

        const linhas = eventos.map(function (evento) {
            const colunas = severidades.map(function (gravidade) {
                return '<td>' + escapeHtml((mapa[evento] || {})[gravidade] ?? 0) + '</td>';
            }).join('');

            return '<tr><td>' + escapeHtml(evento) + '</td>' + colunas + '</tr>';
        }).join('');

        return wrapTabela('<table class="tabela-relatorio"><thead>' + header + '</thead><tbody>' + linhas + '</tbody></table>');
    }

    function tabelaTipologiaRegiao(lista) {
        if (!Array.isArray(lista) || !lista.length) {
            return tabelaVazia('Sem dados de tipologia por regiao no recorte selecionado.');
        }

        const regioes = Array.from(new Set(lista.map(function (item) {
            return String(item.regiao_integracao || '');
        }).filter(Boolean)));
        const eventos = Array.from(new Set(lista.map(function (item) {
            return String(item.tipo_evento || '');
        }).filter(Boolean)));
        const mapa = {};

        lista.forEach(function (item) {
            const evento = String(item.tipo_evento || '');
            const regiao = String(item.regiao_integracao || '');

            if (!mapa[evento]) {
                mapa[evento] = {};
            }

            mapa[evento][regiao] = item.total ?? 0;
        });

        const header = ['<tr><th>Evento</th>']
            .concat(regioes.map(function (regiao) {
                return '<th>' + escapeHtml(regiao) + '</th>';
            }))
            .concat(['</tr>'])
            .join('');

        const linhas = eventos.map(function (evento) {
            const colunas = regioes.map(function (regiao) {
                return '<td>' + escapeHtml((mapa[evento] || {})[regiao] ?? 0) + '</td>';
            }).join('');

            return '<tr><td>' + escapeHtml(evento) + '</td>' + colunas + '</tr>';
        }).join('');

        return wrapTabela('<table class="tabela-relatorio"><thead>' + header + '</thead><tbody>' + linhas + '</tbody></table>');
    }

    function blocoRelatorio(titulo, conteudo) {
        return '<section class="analises-relatorio-bloco"><h3>' + escapeHtml(titulo) + '</h3>' + conteudo + '</section>';
    }

    function montarRelatorio(dados, filtros) {
        const resumoFiltros = [
            ['Ano', filtros.ano || 'Todos'],
            ['Mes', (meses.find(function (item) { return item.value === filtros.mes; }) || {}).label || 'Todos'],
            ['Regiao', filtros.regiao || 'Todas'],
            ['Municipio', filtros.municipio || 'Todos']
        ];

        const linhasFiltro = resumoFiltros.map(function (item) {
            return '<tr><td><strong>' + escapeHtml(item[0]) + ':</strong></td><td>' + escapeHtml(item[1]) + '</td></tr>';
        }).join('');

        return [
            blocoRelatorio('Parametros do relatorio', '<table class="tabela-filtros">' + linhasFiltro + '</table>'),
            blocoRelatorio('Evolucao anual de alertas', tabelaObjeto(dados?.temporal?.evolucao_anual || {})),
            blocoRelatorio('Alertas cancelados por ano', tabelaObjeto(dados?.temporal?.cancelados_por_ano || {})),
            blocoRelatorio('Distribuicao por severidade', tabelaLista(dados?.severidade || [], 'nivel_gravidade', 'total')),
            blocoRelatorio('Municipios mais impactados', tabelaListaOrdenada(dados?.municipios || [], 'municipio', 'total_alertas')),
            blocoRelatorio('Quantidade de alertas por evento', tabelaLista(dados?.eventos_qtd || [], 'tipo_evento', 'total')),
            blocoRelatorio('Duracao media por evento', tabelaLista(dados?.duracao_media || [], 'tipo_evento', 'duracao_media_horas')),
            blocoRelatorio('Sazonalidade mensal', tabelaObjeto(dados?.temporal?.sazonalidade || {})),
            blocoRelatorio('Sazonalidade mensal por evento', tabelaMultiEvento(dados?.temporal?.multi_evento || {})),
            blocoRelatorio('Frequencia por periodo do dia', tabelaObjeto(dados?.temporal?.frequencia_hora || {})),
            blocoRelatorio('Correlacao entre evento e severidade', tabelaCorrelacaoTipologia(dados?.tipologia?.correlacao || [])),
            blocoRelatorio('Tipologia por regiao de integracao', tabelaTipologiaRegiao(dados?.tipologia?.por_regiao || [])),
            blocoRelatorio('Indice regional de pressao', tabelaListaOrdenada(dados?.indice_risco?.ranking_irp || [], 'regiao_integracao', 'irp')),
            blocoRelatorio('Indice de pressao territorial', tabelaListaOrdenada(dados?.indice_risco?.ranking_ipt || [], 'municipio', 'ipt'))
        ].join('');
    }

    function carregarAnos() {
        return carregarJson(config.filtrosBaseUrl + '?tipo=anos').then(function (data) {
            opcoesNoSelect(el.ano, Array.isArray(data) ? data : [], 'Todos');
        });
    }

    function carregarRegioes() {
        return carregarJson(config.filtrosBaseUrl + '?tipo=regioes').then(function (data) {
            opcoesNoSelect(el.regiao, Array.isArray(data) ? data : [], 'Todas');
        });
    }

    function carregarMunicipios() {
        const regiao = el.regiao.value || '';

        if (!regiao) {
            opcoesNoSelect(el.municipio, [], 'Todos');
            return Promise.resolve();
        }

        return carregarJson(config.filtrosBaseUrl + '?tipo=municipios&regiao=' + encodeURIComponent(regiao)).then(function (data) {
            opcoesNoSelect(el.municipio, Array.isArray(data) ? data : [], 'Todos');
        });
    }

    function inicializarMeses() {
        opcoesNoSelect(el.mes, meses.filter(function (item) {
            return item.value !== '';
        }).map(function (item) {
            return { value: item.value, label: item.label };
        }).map(function (item) {
            return item.value;
        }), 'Todos');

        const options = Array.from(el.mes.options);
        options.forEach(function (option) {
            const mes = meses.find(function (item) {
                return item.value === option.value;
            });

            if (mes) {
                option.textContent = mes.label;
            }
        });
    }

    function abrirPdf() {
        const query = queryString(filtrosAtuais());
        const url = query ? config.pdfUrl + '?' + query : config.pdfUrl;
        window.open(url, '_blank');
    }

    function gerarRelatorio() {
        const filtros = filtrosAtuais();
        const query = queryString(filtros);
        const url = query ? config.analiseUrl + '?' + query : config.analiseUrl;

        abrirModal();
        mostrarCarregando();

        carregarJson(url)
            .then(function (dados) {
                if (dados?.erro) {
                    throw new Error(String(dados.msg || 'Falha ao gerar relatorio.'));
                }

                el.conteudo.innerHTML = montarRelatorio(dados, filtros);
            })
            .catch(function () {
                mostrarErro();
            });
    }

    window.fecharModal = fecharModal;

    inicializarMeses();
    carregarAnos();
    carregarRegioes();
    opcoesNoSelect(el.municipio, [], 'Todos');

    el.regiao.addEventListener('change', carregarMunicipios);
    el.btnGerar.addEventListener('click', gerarRelatorio);
    el.fecharModal?.addEventListener('click', fecharModal);

    if (el.btnBaixarPDF) {
        if (config.pdfEnabled === false) {
            el.btnBaixarPDF.hidden = true;
        } else {
            el.btnBaixarPDF.addEventListener('click', abrirPdf);
        }
    }

    window.addEventListener('click', function (event) {
        if (event.target === el.modal) {
            fecharModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && el.modal.style.display !== 'none') {
            fecharModal();
        }
    });
});
