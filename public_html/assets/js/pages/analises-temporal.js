(function () {
    const dataNode = document.getElementById('analise-temporal-data');

    if (!dataNode || typeof Chart === 'undefined') {
        return;
    }

    let pageData;

    try {
        pageData = JSON.parse(dataNode.textContent);
    } catch (error) {
        console.error('Falha ao carregar os dados da análise temporal.', error);
        return;
    }

    window.limparFiltros = function limparFiltros() {
        window.location.href = window.location.pathname;
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

    function formatPercent(value) {
        return (toNumber(value) * 100).toFixed(1).replace('.', ',') + '%';
    }

    function parseColor(color) {
        if (typeof color !== 'string') {
            return null;
        }

        const normalized = color.trim();

        if (/^#[\da-f]{3}$/i.test(normalized)) {
            return {
                r: parseInt(normalized[1] + normalized[1], 16),
                g: parseInt(normalized[2] + normalized[2], 16),
                b: parseInt(normalized[3] + normalized[3], 16)
            };
        }

        if (/^#[\da-f]{6}$/i.test(normalized)) {
            return {
                r: parseInt(normalized.slice(1, 3), 16),
                g: parseInt(normalized.slice(3, 5), 16),
                b: parseInt(normalized.slice(5, 7), 16)
            };
        }

        const matchRgb = normalized.match(/^rgba?\(([^)]+)\)$/i);

        if (!matchRgb) {
            return null;
        }

        const channels = matchRgb[1]
            .split(',')
            .map(function (item) {
                return item.trim();
            })
            .map(Number);

        if (!Number.isFinite(channels[0]) || !Number.isFinite(channels[1]) || !Number.isFinite(channels[2])) {
            return null;
        }

        return {
            r: Math.max(0, Math.min(255, channels[0])),
            g: Math.max(0, Math.min(255, channels[1])),
            b: Math.max(0, Math.min(255, channels[2]))
        };
    }

    function createHoverColor(color) {
        const rgb = parseColor(color);

        if (!rgb) {
            return color;
        }

        const offset = 24;
        return 'rgb('
            + Math.min(255, rgb.r + offset) + ','
            + Math.min(255, rgb.g + offset) + ','
            + Math.min(255, rgb.b + offset) + ')';
    }

    const hoverGuidePlugin = {
        id: 'temporalHoverGuide',
        afterDatasetsDraw: function (chart) {
            if (!chart || !chart.tooltip || typeof chart.tooltip.getActiveElements !== 'function') {
                return;
            }

            const activeElements = chart.tooltip.getActiveElements();

            if (!Array.isArray(activeElements) || !activeElements.length || !chart.chartArea) {
                return;
            }

            const activeElement = activeElements[0].element;

            if (!activeElement) {
                return;
            }

            const ctx = chart.ctx;
            const chartArea = chart.chartArea;

            ctx.save();
            ctx.beginPath();
            ctx.setLineDash([4, 4]);
            ctx.lineWidth = 1;
            ctx.strokeStyle = 'rgba(15, 61, 87, 0.28)';
            ctx.moveTo(activeElement.x, chartArea.top);
            ctx.lineTo(activeElement.x, chartArea.bottom);
            ctx.stroke();
            ctx.restore();
        }
    };

    const pluginRegistry = Chart.registry && Chart.registry.plugins;
    const hoverGuideRegistered = pluginRegistry
        && typeof pluginRegistry.get === 'function'
        && pluginRegistry.get('temporalHoverGuide');

    if (typeof Chart.register === 'function' && !hoverGuideRegistered) {
        Chart.register(hoverGuidePlugin);
    }

    function tooltipLabelCallback(context) {
        const value = toNumber(context && context.parsed && typeof context.parsed === 'object'
            ? context.parsed.y
            : context && context.parsed);
        const datasetLabel = context && context.dataset && context.dataset.label
            ? context.dataset.label + ': '
            : '';
        const datasets = context && context.chart && context.chart.data && Array.isArray(context.chart.data.datasets)
            ? context.chart.data.datasets.filter(function (dataset) {
                return dataset && dataset.hidden !== true;
            })
            : [];
        const datasetCount = datasets.length;
        const dataIndex = context ? context.dataIndex : 0;
        let totalAtPoint = 0;

        if (datasets.length) {
            totalAtPoint = datasets.reduce(function (sum, dataset) {
                const item = dataset && Array.isArray(dataset.data) ? dataset.data[dataIndex] : 0;
                return sum + toNumber(item);
            }, 0);
        }

        if (datasetCount > 1 && totalAtPoint > 0) {
            return datasetLabel + formatNumber(value) + ' (' + formatPercent(value / totalAtPoint) + ')';
        }

        return datasetLabel + formatNumber(value);
    }

    function tooltipFooterCallback(items) {
        if (!Array.isArray(items) || items.length <= 1) {
            return '';
        }

        const total = items.reduce(function (sum, item) {
            const value = item && item.parsed && typeof item.parsed === 'object'
                ? item.parsed.y
                : item && item.parsed;
            return sum + toNumber(value);
        }, 0);

        return 'Total no ponto: ' + formatNumber(total);
    }

    function mergeChartOptions(customOptions) {
        const options = customOptions || {};
        const pluginOptions = options.plugins || {};
        const legendOptions = pluginOptions.legend || {};
        const tooltipOptions = pluginOptions.tooltip || {};
        const tooltipCallbacks = tooltipOptions.callbacks || {};

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
                    boxPadding: 5,
                    callbacks: {
                        label: tooltipLabelCallback,
                        footer: tooltipFooterCallback
                    }
                }
            }, pluginOptions, {
                legend: Object.assign({
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
                    enabled: true,
                    callbacks: {
                        label: tooltipLabelCallback,
                        footer: tooltipFooterCallback
                    }
                }, tooltipOptions, {
                    callbacks: Object.assign({
                        label: tooltipLabelCallback,
                        footer: tooltipFooterCallback
                    }, tooltipCallbacks)
                })
            })
        });
    }

    function applyDatasetInteractivity(config) {
        if (!config || !config.data || !Array.isArray(config.data.datasets)) {
            return;
        }

        const chartType = String(config.type || '');

        config.data.datasets = config.data.datasets.map(function (dataset) {
            const next = Object.assign({}, dataset);

            if (chartType === 'line') {
                if (typeof next.pointRadius !== 'number') {
                    next.pointRadius = 3;
                }

                if (typeof next.pointHoverRadius !== 'number') {
                    next.pointHoverRadius = 7;
                }

                if (typeof next.pointHitRadius !== 'number') {
                    next.pointHitRadius = 16;
                }

                if (typeof next.pointHoverBorderWidth !== 'number') {
                    next.pointHoverBorderWidth = 2;
                }

                if (typeof next.borderWidth !== 'number') {
                    next.borderWidth = 2.4;
                }

                if (typeof next.pointBackgroundColor === 'undefined' && typeof next.borderColor === 'string') {
                    next.pointBackgroundColor = next.borderColor;
                }

                if (typeof next.pointHoverBackgroundColor === 'undefined' && typeof next.borderColor === 'string') {
                    next.pointHoverBackgroundColor = createHoverColor(next.borderColor);
                }
            }

            if (chartType === 'bar') {
                if (typeof next.borderRadius === 'undefined') {
                    next.borderRadius = 10;
                }

                if (typeof next.borderWidth === 'undefined') {
                    next.borderWidth = 1;
                }

                if (typeof next.backgroundColor === 'string') {
                    if (typeof next.hoverBackgroundColor === 'undefined') {
                        next.hoverBackgroundColor = createHoverColor(next.backgroundColor);
                    }

                    if (typeof next.borderColor === 'undefined') {
                        next.borderColor = createHoverColor(next.backgroundColor);
                    }

                    if (typeof next.hoverBorderColor === 'undefined') {
                        next.hoverBorderColor = '#0f3d57';
                    }
                }
            }

            return next;
        });
    }

    function applyAxisFormatting(config) {
        if (!config || !config.options || !config.options.scales || !config.options.scales.y) {
            return;
        }

        const yScale = config.options.scales.y;
        const yTicks = yScale.ticks || {};

        if (typeof yTicks.callback === 'function') {
            return;
        }

        yScale.ticks = Object.assign({}, yTicks, {
            callback: function (value) {
                return formatNumber(value);
            }
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
            applyDatasetInteractivity(config);
            applyAxisFormatting(config);
        }

        const chart = new Chart(element, config);
        syncChartWithStage(chart);
        return chart;
    }

    const palette = [
        '#2e7d32',
        '#f9a825',
        '#ef6c00',
        '#7a28c6',
        '#0277bd',
        '#c62828',
        '#6d4c41'
    ];

    renderChart('graficoSazonalidade', {
        type: 'line',
        data: {
            labels: pageData.sazonalidadeLabels || [],
            datasets: [{
                label: 'Alertas',
                data: pageData.sazonalidadeValores || [],
                borderColor: '#0b3c68',
                backgroundColor: 'rgba(11, 60, 104, 0.12)',
                fill: true,
                tension: 0.3
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
                        text: 'Mes'
                    }
                }
            },
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    renderChart('graficoEvolucao', {
        type: 'line',
        data: {
            labels: pageData.evolucaoLabels || [],
            datasets: [{
                label: 'Alertas',
                data: pageData.evolucaoValores || [],
                borderColor: '#2e7d32',
                backgroundColor: 'rgba(46, 125, 50, 0.12)',
                fill: true,
                tension: 0.3
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
                        text: 'Ano'
                    }
                }
            },
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    renderChart('graficoHora', {
        type: 'bar',
        data: {
            labels: pageData.horaLabels || [],
            datasets: [{
                label: 'Alertas',
                data: pageData.horaValores || [],
                backgroundColor: '#f9a825'
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
                        text: 'Periodo do dia'
                    }
                }
            },
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    if ((pageData.eventoMensalLabels || []).length) {
        renderChart('graficoEventoMensal', {
            type: 'line',
            data: {
                labels: pageData.eventoMensalLabels || [],
                datasets: [{
                    label: 'Alertas',
                    data: pageData.eventoMensalValores || [],
                    borderColor: '#ef6c00',
                    backgroundColor: 'rgba(239, 108, 0, 0.12)',
                    fill: true,
                    tension: 0.35
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
                            text: 'Mes'
                        }
                    }
                },
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    const dadosMultiEvento = pageData.dadosMultiEvento || {};
    renderChart('graficoMultiEvento', {
        type: 'line',
        data: {
            labels: Object.keys(Object.values(dadosMultiEvento)[0] || {}),
            datasets: Object.keys(dadosMultiEvento).map(function (evento, index) {
                return {
                    label: evento,
                    data: Object.values(dadosMultiEvento[evento] || {}),
                    borderColor: palette[index % palette.length],
                    backgroundColor: palette[index % palette.length],
                    fill: false,
                    tension: 0.35
                };
            })
        },
        options: {
            plugins: {
                legend: { position: 'bottom' }
            },
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
                        text: 'Mes'
                    }
                }
            }
        }
    });

    const dadosEvolucao = pageData.dadosEvolucaoAnual || {};
    const anos = Array.from(new Set(
        Object.values(dadosEvolucao).flatMap(function (item) {
            return Object.keys(item || {});
        })
    )).sort();

    renderChart('graficoEvolucaoAnual', {
        type: 'line',
        data: {
            labels: anos,
            datasets: Object.keys(dadosEvolucao).map(function (evento, index) {
                return {
                    label: evento,
                    data: anos.map(function (ano) {
                        return dadosEvolucao[evento][ano] || 0;
                    }),
                    borderColor: palette[index % palette.length],
                    backgroundColor: palette[index % palette.length],
                    fill: false,
                    tension: 0.3
                };
            })
        },
        options: {
            plugins: {
                legend: { position: 'bottom' }
            },
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
                        text: 'Ano'
                    }
                }
            }
        }
    });

    renderChart('graficoCancelados', {
        type: 'bar',
        data: {
            labels: pageData.canceladosLabels || [],
            datasets: [{
                label: 'Cancelados',
                data: pageData.canceladosValores || [],
                backgroundColor: '#c62828'
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantidade de cancelamentos'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Ano'
                    }
                }
            },
            plugins: {
                legend: { position: 'bottom' }
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
            municipioSelect.innerHTML = '<option value="">Selecione uma região</option>';
            municipioSelect.disabled = true;
            return;
        }

        carregarMunicipios(regiao, null);
    });

    function carregarMunicipios(regiao, municipioSelecionado) {
        municipioSelect.disabled = true;
        municipioSelect.innerHTML = '<option value="">Carregando municípios...</option>';

        fetch('/pages/analises/ajax_municipios.php?regiao=' + encodeURIComponent(regiao))
            .then(function (response) {
                return response.json();
            })
            .then(function (lista) {
                municipioSelect.innerHTML = '<option value="">Todos</option>';

                lista.forEach(function (nome) {
                    const option = document.createElement('option');
                    option.value = nome;
                    option.textContent = nome;

                    if (nome === municipioSelecionado) {
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
            });
    }
})();
