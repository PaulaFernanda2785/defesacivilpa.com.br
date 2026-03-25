(function () {
    const dataNode = document.getElementById('analise-tipologia-data');

    if (!dataNode || typeof Chart === 'undefined') {
        return;
    }

    let pageData;

    try {
        pageData = JSON.parse(dataNode.textContent);
    } catch (error) {
        console.error('Falha ao carregar os dados da analise de tipologia.', error);
        return;
    }

    window.limparFiltros = function limparFiltros() {
        const destino = new URL(window.location.href);
        const manterEmbedPublico = destino.searchParams.get('embed') === '1';

        destino.search = '';

        if (manterEmbedPublico) {
            destino.searchParams.set('embed', '1');
        }

        window.location.href = destino.pathname + destino.search;
    };

    const numberFormatter = new Intl.NumberFormat('pt-BR', {
        maximumFractionDigits: 2
    });

    function toNumber(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function formatNumber(value) {
        return numberFormatter.format(toNumber(value));
    }

    function normalizeLabel(value) {
        return String(value == null ? '' : value)
            .replace(/\s+/g, ' ')
            .trim();
    }

    function normalizeLabels(values) {
        return (Array.isArray(values) ? values : []).map(normalizeLabel);
    }

    function wrapLabelByWords(label, maxCharsPerLine) {
        const text = normalizeLabel(label);
        const maxChars = Math.max(8, toNumber(maxCharsPerLine) || 18);

        if (!text || text.length <= maxChars) {
            return text;
        }

        const words = text.split(' ');
        const lines = [];
        let current = '';

        words.forEach(function (word) {
            const candidate = current ? current + ' ' + word : word;

            if (candidate.length <= maxChars) {
                current = candidate;
                return;
            }

            if (current) {
                lines.push(current);
            }

            if (word.length <= maxChars) {
                current = word;
                return;
            }

            for (let i = 0; i < word.length; i += maxChars) {
                lines.push(word.slice(i, i + maxChars));
            }
            current = '';
        });

        if (current) {
            lines.push(current);
        }

        return lines.length > 1 ? lines : (lines[0] || text);
    }

    function buildWrappedLabels(labels, maxCharsPerLine) {
        return (Array.isArray(labels) ? labels : []).map(function (label) {
            return wrapLabelByWords(label, maxCharsPerLine);
        });
    }

    function getLabelLines(label) {
        if (Array.isArray(label)) {
            return label.map(normalizeLabel).filter(Boolean);
        }

        const single = normalizeLabel(label);
        return single ? [single] : [];
    }

    function countLabelLines(labels) {
        return (Array.isArray(labels) ? labels : []).reduce(function (sum, label) {
            return sum + Math.max(1, getLabelLines(label).length);
        }, 0);
    }

    function getTooltipNumericValue(context) {
        if (!context) {
            return 0;
        }

        if (typeof context.raw !== 'undefined') {
            return toNumber(context.raw);
        }

        if (context.parsed && typeof context.parsed === 'object') {
            if (typeof context.parsed.y !== 'undefined') {
                return toNumber(context.parsed.y);
            }

            if (typeof context.parsed.x !== 'undefined') {
                return toNumber(context.parsed.x);
            }
        }

        return 0;
    }

    function computeYAxisReservedWidth(labels, options) {
        const safeLabels = Array.isArray(labels) ? labels : [];
        const config = options || {};
        const minWidth = Math.max(180, toNumber(config.minWidth) || 280);
        const maxWidth = Math.max(minWidth, toNumber(config.maxWidth) || 760);
        const charPx = Math.max(6, toNumber(config.charPx) || 8.2);
        const padding = Math.max(20, toNumber(config.padding) || 46);

        let maxLen = 0;
        safeLabels.forEach(function (label) {
            getLabelLines(label).forEach(function (line) {
                maxLen = Math.max(maxLen, line.length);
            });
        });

        const estimated = Math.round((maxLen * charPx) + padding);
        return Math.min(maxWidth, Math.max(minWidth, estimated));
    }

    function capReservedWidthByStage(canvasId, desiredWidth, options) {
        const config = options || {};
        const fallbackWidth = Math.max(140, Math.round(toNumber(desiredWidth)));
        const canvas = document.getElementById(canvasId);

        if (!canvas || !canvas.parentElement) {
            return fallbackWidth;
        }

        const stageWidth = Math.round(canvas.parentElement.getBoundingClientRect().width);

        if (stageWidth <= 0) {
            return fallbackWidth;
        }

        const minWidth = Math.max(140, Math.round(toNumber(config.minWidth) || 180));
        const maxRatio = Math.min(0.72, Math.max(0.35, toNumber(config.maxRatio) || 0.52));
        const cappedByStage = Math.max(minWidth, Math.round(stageWidth * maxRatio));

        return Math.max(minWidth, Math.min(fallbackWidth, cappedByStage));
    }

    function setStageHeightByCount(canvasId, labelCount, options) {
        const canvas = document.getElementById(canvasId);

        if (!canvas || !canvas.parentElement) {
            return;
        }

        const config = options || {};
        const count = Math.max(1, toNumber(labelCount));
        const base = Math.max(140, toNumber(config.base) || 220);
        const perItem = Math.max(20, toNumber(config.perItem) || 40);
        const minHeight = Math.max(280, toNumber(config.minHeight) || 420);
        const maxHeight = Math.max(minHeight, toNumber(config.maxHeight) || 2200);

        const computed = Math.min(maxHeight, Math.max(minHeight, Math.round(base + (count * perItem))));
        const stage = canvas.parentElement;

        stage.style.height = computed + 'px';
        stage.style.minHeight = computed + 'px';
        stage.style.maxHeight = computed + 'px';
    }

    function mergeChartOptions(customOptions) {
        const options = customOptions || {};
        const pluginOptions = options.plugins || {};
        const legendOptions = pluginOptions.legend || {};
        const tooltipOptions = pluginOptions.tooltip || {};

        return Object.assign({
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 120
            },
            transitions: {
                active: {
                    animation: {
                        duration: 70
                    }
                }
            },
            interaction: {
                mode: 'index',
                intersect: false
            },
            hover: {
                mode: 'index',
                intersect: false
            },
            onHover: function (event, activeElements, chart) {
                chart.canvas.style.cursor = activeElements.length ? 'pointer' : 'default';
            }
        }, options, {
            interaction: Object.assign({
                mode: 'index',
                intersect: false
            }, options.interaction || {}),
            hover: Object.assign({
                mode: 'index',
                intersect: false
            }, options.hover || {}),
            plugins: Object.assign({
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(7, 27, 43, 0.92)',
                    titleColor: '#ffffff',
                    bodyColor: '#eef4f8',
                    borderColor: 'rgba(255, 255, 255, 0.16)',
                    borderWidth: 1,
                    cornerRadius: 10,
                    padding: 12,
                    displayColors: true,
                    boxPadding: 5
                }
            }, pluginOptions, {
                legend: Object.assign({
                    display: true,
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }, legendOptions, {
                    labels: Object.assign({
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }, legendOptions.labels || {})
                }),
                tooltip: Object.assign({
                    enabled: true
                }, tooltipOptions)
            })
        });
    }

    function syncChartWithStage(chart) {
        if (!chart || !chart.canvas || !chart.canvas.parentElement) {
            return;
        }

        const stage = chart.canvas.parentElement;
        let scheduled = false;
        let lastWidth = 0;
        let lastHeight = 0;

        function applyResize() {
            scheduled = false;

            const rect = stage.getBoundingClientRect();
            const width = Math.round(rect.width);
            const height = Math.round(rect.height);

            if (width <= 0 || height <= 0) {
                return;
            }

            if (width === lastWidth && height === lastHeight) {
                return;
            }

            lastWidth = width;
            lastHeight = height;
            chart.resize(width, height);
        }

        function scheduleResize() {
            if (scheduled) {
                return;
            }

            scheduled = true;

            if (typeof window.requestAnimationFrame === 'function') {
                window.requestAnimationFrame(applyResize);
                return;
            }

            window.setTimeout(applyResize, 0);
        }

        scheduleResize();
        window.setTimeout(scheduleResize, 80);

        if (typeof ResizeObserver !== 'undefined') {
            const observer = new ResizeObserver(function () {
                scheduleResize();
            });
            observer.observe(stage);
        }

        window.addEventListener('orientationchange', scheduleResize);
    }

    function renderChart(elementId, config) {
        const element = document.getElementById(elementId);
        if (!element) {
            return null;
        }

        if (config) {
            config.options = mergeChartOptions(config.options || {});
        }

        const chart = new Chart(element, config);
        syncChartWithStage(chart);
        return chart;
    }

    const viewportWidth = Math.max(
        toNumber(window.innerWidth),
        toNumber(document.documentElement && document.documentElement.clientWidth)
    );
    const isCompactViewport = viewportWidth > 0 && viewportWidth <= 900;
    const isMediumViewport = viewportWidth > 0 && viewportWidth <= 1280;

    const labelsEventos = normalizeLabels(pageData.labelsEventos || []);
    const labelsEventosCorrelacao = normalizeLabels(pageData.labelsEventosCorrelacao || pageData.labelsEventos || []);
    const labelsRegioes = normalizeLabels(pageData.labelsRegioes || []);
    const labelsMunicipios = normalizeLabels(pageData.labelsMunicipios || []);

    const labelsRegioesWrapped = buildWrappedLabels(labelsRegioes, isCompactViewport ? 14 : 20);
    const labelsMunicipiosWrapped = buildWrappedLabels(labelsMunicipios, isCompactViewport ? 16 : 28);

    const yAxisRegioesWidthEstimated = computeYAxisReservedWidth(labelsRegioesWrapped, {
        minWidth: 260,
        maxWidth: 620,
        charPx: 8,
        padding: 44
    });
    const yAxisRegioesWidth = capReservedWidthByStage('graficoTipologiaRegiao', yAxisRegioesWidthEstimated, {
        minWidth: isCompactViewport ? 240 : 280,
        maxRatio: isCompactViewport ? 0.92 : 0.9
    });

    const yAxisMunicipiosWidthEstimated = computeYAxisReservedWidth(labelsMunicipiosWrapped, {
        minWidth: isMediumViewport ? 360 : 520,
        maxWidth: isMediumViewport ? 780 : 1080,
        charPx: 8.2,
        padding: 46
    });
    const yAxisMunicipiosWidth = capReservedWidthByStage('graficoTipologiaMunicipio', yAxisMunicipiosWidthEstimated, {
        minWidth: isCompactViewport ? 300 : (isMediumViewport ? 360 : 520),
        maxRatio: isCompactViewport ? 0.95 : 0.96
    });

    setStageHeightByCount('graficoTipologiaRegiao', countLabelLines(labelsRegioesWrapped), {
        base: 260,
        perItem: isCompactViewport ? 48 : 40,
        minHeight: isCompactViewport ? 540 : 480,
        maxHeight: 2200
    });

    setStageHeightByCount('graficoTipologiaMunicipio', countLabelLines(labelsMunicipiosWrapped), {
        base: 380,
        perItem: isCompactViewport ? 54 : 44,
        minHeight: isCompactViewport ? 980 : 760,
        maxHeight: 7200
    });

    renderChart('graficoTipologiaEvento', {
        type: 'bar',
        data: {
            labels: labelsEventos,
            datasets: [{
                label: 'Quantidade de alertas',
                data: pageData.valoresEventos || [],
                backgroundColor: '#0b3c68'
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantidade de alertas'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Tipo de evento'
                    },
                    ticks: {
                        maxRotation: 0,
                        minRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 8
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return formatNumber(context.parsed && context.parsed.y) + ' alertas';
                        }
                    }
                }
            }
        }
    });

    renderChart('graficoTipologiaPercentual', {
        type: 'bar',
        data: {
            labels: labelsEventos,
            datasets: [{
                label: 'Percentual (%)',
                data: pageData.percentuaisEventos || [],
                backgroundColor: '#0277bd'
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return formatNumber(value) + '%';
                        }
                    },
                    title: {
                        display: true,
                        text: 'Percentual (%)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Tipo de evento'
                    },
                    ticks: {
                        maxRotation: 0,
                        minRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 8
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return formatNumber(context.parsed && context.parsed.y) + '% dos alertas';
                        }
                    }
                }
            }
        }
    });

    renderChart('graficoTipologiaSeveridade', {
        type: 'bar',
        data: {
            labels: labelsEventosCorrelacao,
            datasets: pageData.datasetsSeveridade || []
        },
        options: {
            scales: {
                x: {
                    stacked: true,
                    title: {
                        display: true,
                        text: 'Tipo de evento'
                    },
                    ticks: {
                        maxRotation: 0,
                        minRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 10
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantidade de alertas'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return context.dataset.label + ': ' + formatNumber(context.parsed && context.parsed.y) + ' alertas';
                        }
                    }
                }
            }
        }
    });

    renderChart('graficoTipologiaRegiao', {
        type: 'bar',
        data: {
            labels: labelsRegioes,
            datasets: pageData.datasetsRegiao || []
        },
        options: {
            indexAxis: 'y',
            scales: {
                x: {
                    stacked: true,
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantidade de alertas'
                    }
                },
                y: {
                    stacked: true,
                    ticks: {
                        display: true,
                        autoSkip: false,
                        padding: 8,
                        mirror: false,
                        crossAlign: 'far',
                        maxCharsPerLine: isCompactViewport ? 14 : 20,
                        lineHeight: 12,
                        color: '#526b7f',
                        font: {
                            size: 10,
                            weight: '500'
                        },
                        callback: function (value, index) {
                            return labelsRegioesWrapped[index] || this.getLabelForValue(value);
                        },
                        maxRotation: 0,
                        minRotation: 0
                    },
                    afterFit: function (scaleInstance) {
                        scaleInstance.width = Math.max(scaleInstance.width, yAxisRegioesWidth);
                    },
                    title: {
                        display: false,
                        text: 'Regiao de integracao'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return context.dataset.label + ': ' + formatNumber(getTooltipNumericValue(context)) + ' alertas';
                        }
                    }
                }
            }
        }
    });

    renderChart('graficoTipologiaMunicipio', {
        type: 'bar',
        data: {
            labels: labelsMunicipios,
            datasets: pageData.datasetsMunicipio || []
        },
        options: {
            indexAxis: 'y',
            scales: {
                x: {
                    stacked: true,
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantidade de alertas'
                    }
                },
                y: {
                    stacked: true,
                    ticks: {
                        display: true,
                        autoSkip: isCompactViewport,
                        maxTicksLimit: isCompactViewport ? 28 : undefined,
                        padding: 8,
                        mirror: false,
                        crossAlign: 'far',
                        maxCharsPerLine: isCompactViewport ? 18 : 30,
                        lineHeight: 12,
                        color: '#526b7f',
                        font: {
                            size: 10,
                            weight: '500'
                        },
                        callback: function (value, index) {
                            return labelsMunicipiosWrapped[index] || this.getLabelForValue(value);
                        },
                        maxRotation: 0,
                        minRotation: 0
                    },
                    afterFit: function (scaleInstance) {
                        scaleInstance.width = Math.max(scaleInstance.width, yAxisMunicipiosWidth);
                    },
                    title: {
                        display: false,
                        text: 'Municipio'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return context.dataset.label + ': ' + formatNumber(getTooltipNumericValue(context)) + ' alertas';
                        }
                    }
                }
            }
        }
    });

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
            municipioSelect.innerHTML = '<option value="">Selecione uma regiao</option>';
            municipioSelect.disabled = true;
            return;
        }

        carregarMunicipios(regiao, null);
    });

    function carregarMunicipios(regiao, municipioSelecionado) {
        municipioSelect.disabled = true;
        municipioSelect.innerHTML = '<option value="">Carregando municipios...</option>';

        fetch('/pages/analises/ajax_municipios.php?regiao=' + encodeURIComponent(regiao))
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Falha HTTP ao buscar municipios: ' + response.status);
                }

                return response.json();
            })
            .then(function (lista) {
                if (!Array.isArray(lista)) {
                    throw new Error('Resposta invalida para lista de municipios.');
                }

                municipioSelect.innerHTML = '<option value="">Todos</option>';

                lista.forEach(function (nome) {
                    const nomeNormalizado = normalizeLabel(nome);

                    if (!nomeNormalizado) {
                        return;
                    }

                    const option = document.createElement('option');
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
                municipioSelect.innerHTML = '<option value="">Erro ao carregar municipios</option>';
                municipioSelect.disabled = true;
            });
    }
})();
