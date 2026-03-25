(function () {
    const dataNode = document.getElementById('analise-severidade-data');

    if (!dataNode || typeof Chart === 'undefined') {
        return;
    }

    let pageData;

    try {
        pageData = JSON.parse(dataNode.textContent);
    } catch (error) {
        console.error('Falha ao carregar os dados da analise de severidade.', error);
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

    function getDatasetNumericValue(context, axis) {
        if (!context) {
            return 0;
        }

        const datasetData = context.dataset && Array.isArray(context.dataset.data)
            ? context.dataset.data
            : [];
        const rawDataPoint = datasetData[context.dataIndex];

        if (rawDataPoint !== null && typeof rawDataPoint === 'object' && typeof rawDataPoint[axis] !== 'undefined') {
            return toNumber(rawDataPoint[axis]);
        }

        if (typeof rawDataPoint !== 'undefined') {
            return toNumber(rawDataPoint);
        }

        if (context.parsed && typeof context.parsed === 'object' && typeof context.parsed[axis] !== 'undefined') {
            return toNumber(context.parsed[axis]);
        }

        if (typeof context.raw !== 'undefined') {
            return toNumber(context.raw);
        }

        return 0;
    }

    function resolveOriginalLabel(labels, fallback, dataIndex) {
        const source = Array.isArray(labels) ? labels : [];
        const fromSource = source[dataIndex];

        if (typeof fromSource === 'string' && fromSource !== '') {
            return fromSource;
        }

        if (Array.isArray(fallback)) {
            return fallback.join(' ');
        }

        return normalizeLabel(fallback);
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
        id: 'severidadeHoverGuide',
        afterDatasetsDraw: function (chart) {
            if (!chart || !chart.tooltip || typeof chart.tooltip.getActiveElements !== 'function' || !chart.chartArea) {
                return;
            }

            const activeElements = chart.tooltip.getActiveElements();

            if (!Array.isArray(activeElements) || !activeElements.length) {
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
            ctx.strokeStyle = 'rgba(15, 61, 87, 0.26)';

            if (chart.config && chart.config.options && chart.config.options.indexAxis === 'y') {
                ctx.moveTo(chartArea.left, activeElement.y);
                ctx.lineTo(chartArea.right, activeElement.y);
            } else {
                ctx.moveTo(activeElement.x, chartArea.top);
                ctx.lineTo(activeElement.x, chartArea.bottom);
            }

            ctx.stroke();
            ctx.restore();
        }
    };

    const customYAxisLabelsPlugin = {
        id: 'severidadeCustomYAxisLabels',
        afterDraw: function (chart) {
            if (!chart || !chart.options || !chart.options.plugins) {
                return;
            }

            const customOptions = chart.options.plugins.customYAxisLabels || {};

            if (!customOptions.enabled) {
                return;
            }

            const yScale = chart.scales && chart.scales.y;

            if (!yScale) {
                return;
            }

            const labels = Array.isArray(customOptions.labels) ? customOptions.labels : [];
            const fontSize = Math.max(9, toNumber(customOptions.fontSize) || 11);
            const lineHeight = Math.max(fontSize + 1, toNumber(customOptions.lineHeight) || 13);
            const x = Math.max(6, toNumber(customOptions.x) || 12);
            const color = customOptions.color || '#526b7f';
            const fontFamily = customOptions.fontFamily || 'system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif';
            const fontWeight = customOptions.fontWeight || '500';

            const ctx = chart.ctx;
            const chartWidth = Math.max(0, Math.round(toNumber(chart.width)));
            const chartHeight = Math.max(0, Math.round(toNumber(chart.height)));

            if (chartWidth <= 0 || chartHeight <= 0) {
                return;
            }

            ctx.save();
            // Garante que os labels possam ser desenhados em toda a area do canvas,
            // evitando truncamento no limite esquerdo.
            ctx.beginPath();
            ctx.rect(0, 0, chartWidth, chartHeight);
            ctx.clip();
            ctx.fillStyle = color;
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.font = fontWeight + ' ' + fontSize + 'px ' + fontFamily;

            labels.forEach(function (label, index) {
                const y = yScale.getPixelForTick(index);
                const lines = getLabelLines(label);
                const totalHeight = (lines.length - 1) * lineHeight;
                const startY = y - (totalHeight / 2);

                lines.forEach(function (line, lineIndex) {
                    ctx.fillText(line, x, startY + (lineIndex * lineHeight));
                });
            });

            ctx.restore();
        }
    };

    const pluginRegistry = Chart.registry && Chart.registry.plugins;
    const hoverGuideRegistered = pluginRegistry
        && typeof pluginRegistry.get === 'function'
        && pluginRegistry.get('severidadeHoverGuide');

    if (typeof Chart.register === 'function' && !hoverGuideRegistered) {
        Chart.register(hoverGuidePlugin);
    }

    const customYAxisRegistered = pluginRegistry
        && typeof pluginRegistry.get === 'function'
        && pluginRegistry.get('severidadeCustomYAxisLabels');

    if (typeof Chart.register === 'function' && !customYAxisRegistered) {
        Chart.register(customYAxisLabelsPlugin);
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

    function applyDatasetInteractivity(config) {
        if (!config || !config.data || !Array.isArray(config.data.datasets)) {
            return;
        }

        const chartType = String(config.type || '');

        config.data.datasets = config.data.datasets.map(function (dataset) {
            const next = Object.assign({}, dataset);

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
        }

        const chart = new Chart(element, config);
        syncChartWithStage(chart);
        return chart;
    }

    const coresSeveridade = {
        BAIXO: '#CCC9C7',
        MODERADO: '#FFE000',
        ALTO: '#FF7B00',
        'MUITO ALTO': '#FF1D08',
        EXTREMO: '#7A28C6'
    };

    const labelsSeveridade = normalizeLabels(pageData.labelsSeveridade || []);
    const valoresSeveridade = pageData.valoresSeveridade || [];
    const labelsDuracao = normalizeLabels(pageData.labelsDuracao || []);
    const labelsMunicipiosImpactados = normalizeLabels(pageData.labelsMunicipios || []);
    const labelsAlertasEvento = normalizeLabels(pageData.labelsAlertasEvento || []);
    const viewportWidth = Math.max(
        toNumber(window.innerWidth),
        toNumber(document.documentElement && document.documentElement.clientWidth)
    );
    const viewportHeight = Math.max(
        toNumber(window.innerHeight),
        toNumber(document.documentElement && document.documentElement.clientHeight)
    );
    const isCompactViewport = viewportWidth > 0 && viewportWidth <= 900;
    const isMediumViewport = viewportWidth > 0 && viewportWidth <= 1280;
    const isAlertasEventoHorizontal = viewportWidth > 0 && viewportWidth <= 1440;
    const isFixedOffsetDesktop1920x945 = viewportWidth >= 1880
        && viewportWidth <= 1960
        && viewportHeight >= 900
        && viewportHeight <= 980;
    const fixedOffsetDuracao1920x945 = isFixedOffsetDesktop1920x945 ? 180 : 0;
    const fixedOffsetMunicipios1920x945 = isFixedOffsetDesktop1920x945 ? 260 : 0;

    const labelsDuracaoWrapped = buildWrappedLabels(labelsDuracao, isCompactViewport ? 14 : (isMediumViewport ? 16 : 22));
    const labelsMunicipiosWrapped = buildWrappedLabels(labelsMunicipiosImpactados, isCompactViewport ? 16 : (isMediumViewport ? 18 : 30));
    const labelsAlertasEventoWrapped = buildWrappedLabels(labelsAlertasEvento, isAlertasEventoHorizontal ? 12 : 18);

    const totalAlertas = valoresSeveridade.reduce(function (accumulator, value) {
        return accumulator + toNumber(value);
    }, 0);

    const percentuais = valoresSeveridade.map(function (valor) {
        return totalAlertas ? Number(((toNumber(valor) / totalAlertas) * 100).toFixed(1)) : 0;
    });

    const coresGrafico = labelsSeveridade.map(function (nivel) {
        const nivelNormalizado = normalizeLabel(nivel).toUpperCase();
        return coresSeveridade[nivelNormalizado] || '#90a4ae';
    });

    const yAxisDuracaoWidthEstimated = computeYAxisReservedWidth(labelsDuracaoWrapped, {
        minWidth: isMediumViewport ? 320 : 420,
        maxWidth: isMediumViewport ? 620 : 860,
        charPx: 8.2,
        padding: 42
    });
    const yAxisDuracaoWidth = capReservedWidthByStage('graficoDuracao', yAxisDuracaoWidthEstimated, {
        minWidth: isCompactViewport ? 260 : (isMediumViewport ? 320 : 420),
        maxRatio: isCompactViewport ? 0.92 : 0.96
    });

    const yAxisMunicipiosWidthEstimated = computeYAxisReservedWidth(labelsMunicipiosWrapped, {
        minWidth: isMediumViewport ? 420 : 620,
        maxWidth: isMediumViewport ? 920 : 1280,
        charPx: 8.3,
        padding: 48
    });
    const yAxisMunicipiosWidth = capReservedWidthByStage('graficoMunicipiosImpactados', yAxisMunicipiosWidthEstimated, {
        minWidth: isCompactViewport ? 340 : (isMediumViewport ? 420 : 620),
        maxRatio: isCompactViewport ? 0.95 : 0.96
    });

    const yAxisAlertasEventoWidthEstimated = computeYAxisReservedWidth(labelsAlertasEventoWrapped, {
        minWidth: 260,
        maxWidth: 580,
        charPx: 8,
        padding: 44
    });
    const yAxisAlertasEventoWidth = capReservedWidthByStage('graficoAlertasEvento', yAxisAlertasEventoWidthEstimated, {
        minWidth: isCompactViewport ? 260 : (isAlertasEventoHorizontal ? 280 : 220),
        maxRatio: isCompactViewport ? 0.92 : (isAlertasEventoHorizontal ? 0.9 : 0.78)
    });

    setStageHeightByCount('graficoDuracao', countLabelLines(labelsDuracaoWrapped), {
        base: isFixedOffsetDesktop1920x945 ? 340 : 280,
        perItem: isCompactViewport ? 44 : 38,
        minHeight: isCompactViewport ? 620 : (isFixedOffsetDesktop1920x945 ? 620 : 520),
        maxHeight: 2600
    });

    setStageHeightByCount('graficoMunicipiosImpactados', countLabelLines(labelsMunicipiosWrapped), {
        base: isFixedOffsetDesktop1920x945 ? 440 : 360,
        perItem: isCompactViewport ? 52 : 42,
        minHeight: isCompactViewport ? 980 : (isFixedOffsetDesktop1920x945 ? 980 : 760),
        maxHeight: 7200
    });

    if (isAlertasEventoHorizontal) {
        setStageHeightByCount('graficoAlertasEvento', countLabelLines(labelsAlertasEventoWrapped), {
            base: 220,
            perItem: 40,
            minHeight: 500,
            maxHeight: 1400
        });
    }

    renderChart('graficoProporcao', {
        type: 'bar',
        data: {
            labels: labelsSeveridade,
            datasets: [{
                label: 'Percentual por severidade',
                data: percentuais,
                backgroundColor: coresGrafico
            }]
        },
        options: {
            layout: {
                padding: {
                    left: 18,
                    right: 8
                }
            },
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
                        text: 'Percentual de alertas'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Grau de severidade'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const percentual = toNumber(context.parsed && context.parsed.y);
                            const indice = context.dataIndex;
                            const quantidade = toNumber(valoresSeveridade[indice]);
                            return context.label + ': ' + formatNumber(percentual) + '% (' + formatNumber(quantidade) + ' alertas)';
                        }
                    }
                }
            }
        }
    });

    renderChart('graficoDuracao', {
        type: 'bar',
        data: {
            labels: labelsDuracao,
            datasets: [{
                label: 'Duracao media (horas)',
                data: pageData.valoresDuracao || [],
                backgroundColor: '#0b3c68'
            }]
        },
        options: {
            indexAxis: 'y',
            layout: {
                padding: {
                    left: isFixedOffsetDesktop1920x945 ? 24 : 10,
                    right: 8
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Horas'
                    }
                },
                y: {
                    ticks: {
                        display: true,
                        autoSkip: false,
                        padding: 8,
                        mirror: false,
                        crossAlign: 'far',
                        maxCharsPerLine: isCompactViewport ? 16 : 24,
                        lineHeight: 12,
                        color: '#526b7f',
                        font: {
                            size: 10,
                            weight: '500'
                        },
                        callback: function (value, index) {
                            return labelsDuracaoWrapped[index] || this.getLabelForValue(value);
                        },
                        maxRotation: 0,
                        minRotation: 0
                    },
                    afterFit: function (scaleInstance) {
                        scaleInstance.width = Math.max(
                            scaleInstance.width,
                            yAxisDuracaoWidth + fixedOffsetDuracao1920x945
                        );
                    },
                    title: {
                        display: false,
                        text: 'Tipo de evento'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const nomeCompleto = resolveOriginalLabel(labelsDuracao, context.label, context.dataIndex);
                            const horas = getDatasetNumericValue(context, 'x');
                            return nomeCompleto + ': ' + formatNumber(horas) + ' horas';
                        }
                    }
                }
            }
        }
    });

    renderChart('graficoDistribuicao', {
        type: 'bar',
        data: {
            labels: labelsSeveridade,
            datasets: [{
                label: 'Alertas por severidade',
                data: valoresSeveridade,
                backgroundColor: coresGrafico
            }]
        },
        options: {
            layout: {
                padding: {
                    left: 18,
                    right: 8
                }
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
                        text: 'Grau de severidade'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const quantidade = toNumber(context.parsed && context.parsed.y);
                            const percentual = totalAlertas ? (quantidade / totalAlertas) * 100 : 0;
                            return context.label + ': ' + formatNumber(quantidade) + ' alertas (' + formatNumber(percentual.toFixed(1)) + '%)';
                        }
                    }
                }
            }
        }
    });

    renderChart('graficoAlertasEvento', {
        type: 'bar',
        data: {
            labels: labelsAlertasEvento,
            datasets: [{
                label: 'Alertas por evento',
                data: pageData.valoresAlertasEvento || [],
                backgroundColor: '#0b3c68'
            }]
        },
        options: {
            indexAxis: isAlertasEventoHorizontal ? 'y' : 'x',
            layout: {
                padding: {
                    left: isAlertasEventoHorizontal ? 10 : 18,
                    right: 8
                }
            },
            scales: {
                x: isAlertasEventoHorizontal
                    ? {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Quantidade de alertas'
                        }
                    }
                    : {
                        title: {
                            display: true,
                            text: 'Tipo de evento'
                        },
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 7,
                            maxRotation: 0,
                            minRotation: 0,
                            padding: 10,
                            maxCharsPerLine: 18,
                            lineHeight: 12,
                            callback: function (value, index) {
                                return labelsAlertasEventoWrapped[index] || this.getLabelForValue(value);
                            }
                        }
                    },
                y: isAlertasEventoHorizontal
                    ? {
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
                                return labelsAlertasEventoWrapped[index] || this.getLabelForValue(value);
                            },
                            maxRotation: 0,
                            minRotation: 0
                        },
                        afterFit: function (scaleInstance) {
                            scaleInstance.width = Math.max(scaleInstance.width, yAxisAlertasEventoWidth);
                        },
                        title: {
                            display: false,
                            text: 'Tipo de evento'
                        }
                    }
                    : {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Quantidade de alertas'
                        }
                    }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const nomeCompleto = resolveOriginalLabel(labelsAlertasEvento, context.label, context.dataIndex);
                            const eixoValor = isAlertasEventoHorizontal ? 'x' : 'y';
                            const totalAlertas = getDatasetNumericValue(context, eixoValor);
                            return nomeCompleto + ': ' + formatNumber(totalAlertas) + ' alertas';
                        }
                    }
                }
            }
        }
    });

    renderChart('graficoMunicipiosImpactados', {
        type: 'bar',
        data: {
            labels: labelsMunicipiosImpactados,
            datasets: [{
                label: 'Alertas por municipio',
                data: pageData.valoresMunicipios || [],
                backgroundColor: '#c62828'
            }]
        },
        options: {
            indexAxis: 'y',
            layout: {
                padding: {
                    left: isFixedOffsetDesktop1920x945 ? 24 : 10,
                    right: 8
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantidade de alertas'
                    }
                },
                y: {
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
                        scaleInstance.width = Math.max(
                            scaleInstance.width,
                            yAxisMunicipiosWidth + fixedOffsetMunicipios1920x945
                        );
                    },
                    title: {
                        display: false,
                        text: 'Municipio'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const nomeCompleto = resolveOriginalLabel(labelsMunicipiosImpactados, context.label, context.dataIndex);
                            return nomeCompleto + ': ' + formatNumber(getDatasetNumericValue(context, 'x')) + ' alertas';
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
