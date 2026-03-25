(function (global) {
    'use strict';

    var registeredPlugins = [];

    function toArray(value) {
        return Array.isArray(value) ? value : [];
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function getColor(source, index, fallback) {
        if (Array.isArray(source)) {
            return source[index % source.length] || fallback;
        }

        return source || fallback;
    }

    function isDatasetVisible(dataset) {
        return Boolean(dataset) && dataset.hidden !== true;
    }

    function toNumber(value) {
        var parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function labelToText(label) {
        if (Array.isArray(label)) {
            return label
                .map(function (item) { return String(item || ''); })
                .join(' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        return String(label || '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function wrapLabelText(label, maxCharsPerLine) {
        var text = labelToText(label);
        var limit = Math.max(8, Math.round(toNumber(maxCharsPerLine) || 22));

        if (!text) {
            return [''];
        }

        if (text.length <= limit) {
            return [text];
        }

        var words = text.split(' ');
        var lines = [];
        var current = '';

        words.forEach(function (word) {
            var candidate = current ? (current + ' ' + word) : word;

            if (candidate.length <= limit) {
                current = candidate;
                return;
            }

            if (current) {
                lines.push(current);
            }

            if (word.length <= limit) {
                current = word;
                return;
            }

            for (var i = 0; i < word.length; i += limit) {
                lines.push(word.slice(i, i + limit));
            }

            current = '';
        });

        if (current) {
            lines.push(current);
        }

        return lines.length ? lines : [text];
    }

    function maxLabelLineCount(labels, maxCharsPerLine) {
        var maxLines = 1;

        labels.forEach(function (label) {
            maxLines = Math.max(maxLines, wrapLabelText(label, maxCharsPerLine).length);
        });

        return maxLines;
    }

    function maxLabelLineWidth(labels, maxCharsPerLine, charPx) {
        var width = 0;
        var perChar = Math.max(5, toNumber(charPx) || 6.2);

        labels.forEach(function (label) {
            wrapLabelText(label, maxCharsPerLine).forEach(function (line) {
                width = Math.max(width, Math.round(line.length * perChar));
            });
        });

        return width;
    }

    function parseHexColor(color) {
        if (typeof color !== 'string') {
            return null;
        }

        var normalized = color.trim();

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

        return null;
    }

    function brightenColor(color, amount) {
        var rgb = parseHexColor(color);
        var delta = Number.isFinite(amount) ? amount : 22;

        if (!rgb) {
            return color;
        }

        return 'rgb('
            + clamp(rgb.r + delta, 0, 255) + ','
            + clamp(rgb.g + delta, 0, 255) + ','
            + clamp(rgb.b + delta, 0, 255) + ')';
    }

    function maxValueForBarChart(datasets, labelsCount, stacked) {
        if (stacked) {
            var totals = new Array(labelsCount).fill(0);

            datasets.forEach(function (dataset) {
                if (!isDatasetVisible(dataset)) {
                    return;
                }

                toArray(dataset.data).forEach(function (value, index) {
                    totals[index] += Number(value || 0);
                });
            });

            return Math.max(0, Math.max.apply(null, totals));
        }

        var max = 0;
        datasets.forEach(function (dataset) {
            if (!isDatasetVisible(dataset)) {
                return;
            }

            toArray(dataset.data).forEach(function (value) {
                max = Math.max(max, Number(value || 0));
            });
        });

        return max;
    }

    function createLegendLayout(ctx, area, datasets) {
        if (!datasets.length) {
            return { rows: [], height: 0 };
        }

        ctx.save();
        ctx.font = '12px Segoe UI, Arial, sans-serif';
        var maxWidth = Math.max(120, area.width);
        var rows = [[]];
        var currentWidth = 0;

        datasets.forEach(function (dataset, index) {
            var label = String(dataset.label || ('Serie ' + (index + 1)));
            var itemWidth = Math.min(maxWidth, ctx.measureText(label).width + 34);

            if (currentWidth + itemWidth > maxWidth && rows[rows.length - 1].length) {
                rows.push([]);
                currentWidth = 0;
            }

            rows[rows.length - 1].push({
                label: label,
                color: getColor(dataset.borderColor || dataset.backgroundColor, index, '#0f3d57'),
                datasetIndex: index,
                hidden: dataset.hidden === true,
                width: itemWidth
            });
            currentWidth += itemWidth;
        });

        ctx.restore();

        return {
            rows: rows,
            height: rows.length * 18 + 10
        };
    }

    function Chart(canvas, config) {
        if (!(this instanceof Chart)) {
            return new Chart(canvas, config);
        }

        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.config = config || {};
        this.type = this.config.type || 'bar';
        this.data = this.config.data || { labels: [], datasets: [] };
        this.options = this.config.options || {};
        this._plotArea = null;
        this.chartArea = null;
        this._hitRegions = [];
        this._legendRegions = [];
        this._activeHit = null;
        this._destroyed = false;
        this._boundResize = this.resize.bind(this);
        this._boundClick = this.handleClick.bind(this);
        this._boundPointerMove = this.handlePointerMove.bind(this);
        this._boundPointerLeave = this.handlePointerLeave.bind(this);
        this._numberFormatter = new Intl.NumberFormat('pt-BR', {
            maximumFractionDigits: 2
        });
        this.tooltip = {
            getActiveElements: this.getActiveElements.bind(this)
        };
        this._categoryLabelConfig = null;

        this.canvas.style.width = this.canvas.style.width || '100%';
        this.canvas.style.height = this.canvas.style.height || '100%';
        this.canvas.addEventListener('click', this._boundClick);
        this.canvas.addEventListener('mousemove', this._boundPointerMove);
        this.canvas.addEventListener('touchmove', this._boundPointerMove);
        this.canvas.addEventListener('mouseleave', this._boundPointerLeave);
        this.canvas.addEventListener('touchend', this._boundPointerLeave);
        this.canvas.addEventListener('touchcancel', this._boundPointerLeave);
        global.addEventListener('resize', this._boundResize);

        this.resize();
    }

    Chart.prototype.destroy = function destroy() {
        if (this._destroyed) {
            return;
        }

        this._destroyed = true;
        this.canvas.removeEventListener('click', this._boundClick);
        this.canvas.removeEventListener('mousemove', this._boundPointerMove);
        this.canvas.removeEventListener('touchmove', this._boundPointerMove);
        this.canvas.removeEventListener('mouseleave', this._boundPointerLeave);
        this.canvas.removeEventListener('touchend', this._boundPointerLeave);
        this.canvas.removeEventListener('touchcancel', this._boundPointerLeave);
        global.removeEventListener('resize', this._boundResize);
        this.clear();
    };

    Chart.prototype.clear = function clear() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    };

    Chart.prototype.resize = function resize(explicitWidth, explicitHeight) {
        if (this._destroyed) {
            return;
        }

        var rect = this.canvas.getBoundingClientRect();
        var ratio = global.devicePixelRatio || 1;
        var width = Math.max(280, Math.round((toNumber(explicitWidth) || rect.width || this.canvas.parentElement?.clientWidth || 640)));
        var height = Math.max(220, Math.round((toNumber(explicitHeight) || rect.height || this.canvas.parentElement?.clientHeight || 320)));

        this.canvas.width = Math.round(width * ratio);
        this.canvas.height = Math.round(height * ratio);
        this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        this.render();
    };

    Chart.prototype.update = function update() {
        if (!this._destroyed) {
            this.render();
        }
    };

    Chart.prototype.render = function render() {
        var labels = toArray(this.data.labels);
        var datasets = toArray(this.data.datasets);
        var ctx = this.ctx;
        var width = this.canvas.width / (global.devicePixelRatio || 1);
        var height = this.canvas.height / (global.devicePixelRatio || 1);

        this.clear();
        this._hitRegions = [];
        this._legendRegions = [];

        ctx.save();
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);
        ctx.restore();

        if (!labels.length || !datasets.length) {
            this.drawEmptyState(width, height);
            return;
        }

        var legendEnabled = (this.options.plugins?.legend?.display) !== false;
        var legend = legendEnabled ? createLegendLayout(ctx, { width: width - 72 }, datasets) : { rows: [], height: 0 };
        var isHorizontalBar = this.type === 'bar' && this.options.indexAxis === 'y';
        var yTicksOptions = this.options.scales?.y?.ticks || {};
        var xTicksOptions = this.options.scales?.x?.ticks || {};
        var yMaxCharsPerLine = Math.max(10, Math.round(toNumber(yTicksOptions.maxCharsPerLine) || 28));
        var xMaxCharsPerLine = Math.max(8, Math.round(toNumber(xTicksOptions.maxCharsPerLine) || 14));
        var xLineHeight = Math.max(11, Math.round(toNumber(xTicksOptions.lineHeight) || 12));
        var yLineHeight = Math.max(11, Math.round(toNumber(yTicksOptions.lineHeight) || 12));
        var reservedLeft = 56;
        var reservedBottom = 36;

        if (isHorizontalBar) {
            var yLabelMaxWidth = maxLabelLineWidth(labels, yMaxCharsPerLine, 6.2);
            reservedLeft = clamp(yLabelMaxWidth + 26, 56, Math.max(56, width - 140));
        } else {
            var xLineCount = maxLabelLineCount(labels, xMaxCharsPerLine);
            reservedBottom = clamp((xLineCount * xLineHeight) + 18, 36, 120);
        }

        var plot = {
            left: reservedLeft,
            right: width - 18,
            top: 18,
            bottom: height - reservedBottom - legend.height
        };

        if (plot.right - plot.left < 120) {
            plot.left = Math.max(20, plot.right - 120);
        }

        if (plot.bottom <= plot.top + 40) {
            plot.bottom = height - 26;
        }

        this._categoryLabelConfig = {
            isHorizontalBar: isHorizontalBar,
            xMaxCharsPerLine: xMaxCharsPerLine,
            yMaxCharsPerLine: yMaxCharsPerLine,
            xLineHeight: xLineHeight,
            yLineHeight: yLineHeight
        };

        this._plotArea = plot;
        this.chartArea = {
            left: plot.left,
            right: plot.right,
            top: plot.top,
            bottom: plot.bottom
        };

        if (this.type === 'line') {
            this.drawLineChart(labels, datasets, plot);
        } else if (this.type === 'doughnut') {
            this.drawDoughnutChart(labels, datasets, plot);
        } else {
            this.drawBarChart(labels, datasets, plot);
        }

        if (this.type !== 'doughnut') {
            this.drawAxes(plot);
        }

        this.callPlugins('afterDatasetsDraw');

        if (legendEnabled) {
            this.drawLegend(legend, width, height - legend.height + 4);
        }

        if (this._activeHit) {
            var nextActive = this._hitRegions.find(function (region) {
                return region.index === this._activeHit.index;
            }, this) || null;

            this._activeHit = nextActive;
        }

        this.drawTooltip(labels, datasets, plot);
    };

    Chart.prototype.drawEmptyState = function drawEmptyState(width, height) {
        var ctx = this.ctx;
        ctx.save();
        ctx.fillStyle = '#587080';
        ctx.font = '600 14px Segoe UI, Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('Sem dados para exibir no grafico.', width / 2, height / 2);
        ctx.restore();
    };

    Chart.prototype.drawAxes = function drawAxes(plot) {
        var ctx = this.ctx;

        ctx.save();
        ctx.strokeStyle = 'rgba(15, 61, 87, 0.16)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(plot.left, plot.top);
        ctx.lineTo(plot.left, plot.bottom);
        ctx.lineTo(plot.right, plot.bottom);
        ctx.stroke();
        ctx.restore();
    };

    Chart.prototype.drawLegend = function drawLegend(legend, width, startY) {
        var ctx = this.ctx;
        var self = this;

        ctx.save();
        ctx.font = '12px Segoe UI, Arial, sans-serif';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#173042';

        legend.rows.forEach(function (row, rowIndex) {
            var x = 24;
            var y = startY + rowIndex * 18 + 8;

            row.forEach(function (item) {
                var itemWidth = Math.min(240, Number(item.width) || (ctx.measureText(item.label).width + 34));
                var hidden = item.hidden === true;

                ctx.globalAlpha = hidden ? 0.38 : 1;
                ctx.fillStyle = item.color;
                ctx.fillRect(x, y - 5, 12, 12);
                ctx.fillStyle = '#173042';
                ctx.fillText(item.label, x + 18, y + 1);

                self._legendRegions.push({
                    datasetIndex: item.datasetIndex,
                    x1: x,
                    x2: x + itemWidth,
                    y1: y - 10,
                    y2: y + 10
                });

                ctx.globalAlpha = 1;
                x += itemWidth;
            });
        });

        ctx.restore();
    };

    Chart.prototype.drawLineChart = function drawLineChart(labels, datasets, plot) {
        var ctx = this.ctx;
        var allValues = [];
        var activeIndex = this._activeHit ? this._activeHit.index : -1;
        var visibleDatasets = datasets
            .map(function (dataset, datasetIndex) {
                return { dataset: dataset, datasetIndex: datasetIndex };
            })
            .filter(function (entry) {
                return isDatasetVisible(entry.dataset);
            });

        visibleDatasets.forEach(function (entry) {
            var dataset = entry.dataset;
            allValues = allValues.concat(toArray(dataset.data).map(function (value) {
                return Number(value || 0);
            }));
        });

        var max = Math.max(1, Math.max.apply(null, allValues.concat([0])));
        var stepX = labels.length > 1 ? (plot.right - plot.left) / (labels.length - 1) : 0;
        var categoryWidth = labels.length ? (plot.right - plot.left) / labels.length : (plot.right - plot.left);
        var self = this;

        visibleDatasets.forEach(function (entry, visibleIndex) {
            var dataset = entry.dataset;
            var datasetIndex = entry.datasetIndex;
            var values = toArray(dataset.data).map(function (value) { return Number(value || 0); });
            var stroke = getColor(dataset.borderColor || dataset.backgroundColor, visibleIndex, '#0f3d57');

            ctx.save();
            ctx.strokeStyle = stroke;
            ctx.lineWidth = 2.5;
            ctx.beginPath();

            values.forEach(function (value, index) {
                var x = plot.left + stepX * index;
                var y = plot.bottom - (value / max) * (plot.bottom - plot.top);

                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });

            ctx.stroke();

            values.forEach(function (value, index) {
                var x = plot.left + stepX * index;
                var y = plot.bottom - (value / max) * (plot.bottom - plot.top);
                var isActivePoint = activeIndex === index;
                var pointRadius = Number(isActivePoint ? (dataset.pointHoverRadius || dataset.pointRadius || 3) : (dataset.pointRadius || 3));

                ctx.fillStyle = stroke;
                ctx.beginPath();
                ctx.arc(x, y, pointRadius, 0, Math.PI * 2);
                ctx.fill();

                if (visibleIndex === 0) {
                    self._hitRegions.push({
                        index: index,
                        datasetIndex: datasetIndex,
                        x1: plot.left + categoryWidth * index,
                        x2: plot.left + categoryWidth * (index + 1),
                        y1: plot.top,
                        y2: plot.bottom,
                        cx: x,
                        cy: y
                    });
                }
            });

            ctx.restore();
        });

        this.drawCategoryLabels(labels, plot, false);
        this.drawValueTicks(max, plot, false);
    };

    Chart.prototype.drawDoughnutChart = function drawDoughnutChart(labels, datasets, plot) {
        var ctx = this.ctx;
        var dataset = datasets[0] || { data: [] };
        var values = toArray(dataset.data).map(function (value) { return Number(value || 0); });
        var total = values.reduce(function (sum, value) { return sum + value; }, 0);
        var self = this;

        if (!total) {
            this.drawEmptyState(this.canvas.width / (global.devicePixelRatio || 1), this.canvas.height / (global.devicePixelRatio || 1));
            return;
        }

        var centerX = plot.left + (plot.right - plot.left) / 2;
        var centerY = plot.top + (plot.bottom - plot.top) / 2;
        var radius = Math.max(42, Math.min(plot.right - plot.left, plot.bottom - plot.top) / 2 - 10);
        var innerRadius = radius * 0.58;
        var startAngle = -Math.PI / 2;

        values.forEach(function (value, index) {
            if (value <= 0) {
                return;
            }

            var angle = (value / total) * Math.PI * 2;
            var endAngle = startAngle + angle;
            var middleAngle = startAngle + (angle / 2);

            ctx.save();
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, startAngle, endAngle);
            ctx.closePath();
            ctx.fillStyle = getColor(dataset.backgroundColor, index, '#1f7a8c');
            ctx.fill();
            ctx.restore();

            self._hitRegions.push({
                type: 'arc',
                index: index,
                datasetIndex: 0,
                x1: centerX - radius,
                x2: centerX + radius,
                y1: centerY - radius,
                y2: centerY + radius,
                cx: centerX + Math.cos(middleAngle) * ((radius + innerRadius) / 2),
                cy: centerY + Math.sin(middleAngle) * ((radius + innerRadius) / 2),
                centerX: centerX,
                centerY: centerY,
                innerRadius: innerRadius,
                outerRadius: radius,
                startAngle: startAngle,
                endAngle: endAngle
            });

            startAngle = endAngle;
        });

        ctx.save();
        ctx.beginPath();
        ctx.fillStyle = '#ffffff';
        ctx.arc(centerX, centerY, innerRadius, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();

        ctx.save();
        ctx.fillStyle = '#173042';
        ctx.font = '600 14px Segoe UI, Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(String(total) + ' alertas', centerX, centerY);
        ctx.restore();
    };

    Chart.prototype.drawBarChart = function drawBarChart(labels, datasets, plot) {
        var horizontal = this.options.indexAxis === 'y';
        var stacked = Boolean(horizontal ? this.options.scales?.x?.stacked : this.options.scales?.y?.stacked);
        var max = Math.max(1, maxValueForBarChart(datasets, labels.length, stacked));

        if (horizontal) {
            this.drawHorizontalBars(labels, datasets, plot, max, stacked);
            this.drawCategoryLabels(labels, plot, true);
            this.drawValueTicks(max, plot, true);
            return;
        }

        this.drawVerticalBars(labels, datasets, plot, max, stacked);
        this.drawCategoryLabels(labels, plot, false);
        this.drawValueTicks(max, plot, false);
    };

    Chart.prototype.drawVerticalBars = function drawVerticalBars(labels, datasets, plot, max, stacked) {
        var ctx = this.ctx;
        var slotWidth = (plot.right - plot.left) / Math.max(labels.length, 1);
        var barPadding = slotWidth * 0.18;
        var innerWidth = slotWidth - barPadding;
        var self = this;
        var activeIndex = this._activeHit ? this._activeHit.index : -1;
        var visibleDatasets = datasets
            .map(function (dataset, datasetIndex) {
                return { dataset: dataset, datasetIndex: datasetIndex };
            })
            .filter(function (entry) {
                return isDatasetVisible(entry.dataset);
            });
        var seriesCount = Math.max(visibleDatasets.length, 1);

        labels.forEach(function (_label, index) {
            var baseX = plot.left + slotWidth * index + barPadding / 2;
            var cumulative = 0;

            visibleDatasets.forEach(function (entry, visibleIndex) {
                var dataset = entry.dataset;
                var datasetIndex = entry.datasetIndex;
                var value = Number(toArray(dataset.data)[index] || 0);
                var color = getColor(dataset.backgroundColor || dataset.borderColor, index + visibleIndex, '#1f7a8c');
                var barWidth = stacked ? innerWidth : innerWidth / seriesCount;
                var barX = stacked ? baseX : baseX + visibleIndex * barWidth;
                var barHeight = max > 0 ? (value / max) * (plot.bottom - plot.top) : 0;
                var barY = stacked
                    ? plot.bottom - cumulative - barHeight
                    : plot.bottom - barHeight;
                var isActiveBar = activeIndex === index;
                var drawColor = isActiveBar ? brightenColor(color, 24) : color;

                cumulative += stacked ? barHeight : 0;

                ctx.save();
                ctx.fillStyle = drawColor;
                ctx.fillRect(barX, barY, Math.max(6, barWidth - 4), barHeight);
                ctx.restore();

                if (visibleIndex === 0) {
                    self._hitRegions[index] = {
                        index: index,
                        datasetIndex: datasetIndex,
                        x1: plot.left + slotWidth * index,
                        x2: plot.left + slotWidth * (index + 1),
                        y1: plot.top,
                        y2: plot.bottom,
                        cx: plot.left + slotWidth * index + slotWidth / 2,
                        cy: barY
                    };
                }
            });
        });
    };

    Chart.prototype.drawHorizontalBars = function drawHorizontalBars(labels, datasets, plot, max, stacked) {
        var ctx = this.ctx;
        var slotHeight = (plot.bottom - plot.top) / Math.max(labels.length, 1);
        var barPadding = slotHeight * 0.22;
        var innerHeight = slotHeight - barPadding;
        var self = this;
        var activeIndex = this._activeHit ? this._activeHit.index : -1;
        var visibleDatasets = datasets
            .map(function (dataset, datasetIndex) {
                return { dataset: dataset, datasetIndex: datasetIndex };
            })
            .filter(function (entry) {
                return isDatasetVisible(entry.dataset);
            });
        var seriesCount = Math.max(visibleDatasets.length, 1);

        labels.forEach(function (_label, index) {
            var baseY = plot.top + slotHeight * index + barPadding / 2;
            var cumulative = 0;

            visibleDatasets.forEach(function (entry, visibleIndex) {
                var dataset = entry.dataset;
                var datasetIndex = entry.datasetIndex;
                var value = Number(toArray(dataset.data)[index] || 0);
                var color = getColor(dataset.backgroundColor || dataset.borderColor, index + visibleIndex, '#1f7a8c');
                var barHeight = stacked ? innerHeight : innerHeight / seriesCount;
                var barY = stacked ? baseY : baseY + visibleIndex * barHeight;
                var barWidth = max > 0 ? (value / max) * (plot.right - plot.left) : 0;
                var barStartX = plot.left + cumulative;
                var barEndX = barStartX + barWidth;
                var isActiveBar = activeIndex === index;
                var drawColor = isActiveBar ? brightenColor(color, 24) : color;

                ctx.save();
                ctx.fillStyle = drawColor;
                ctx.fillRect(barStartX, barY, barWidth, Math.max(6, barHeight - 4));
                ctx.restore();

                cumulative += stacked ? barWidth : 0;

                if (visibleIndex === 0) {
                    self._hitRegions[index] = {
                        index: index,
                        datasetIndex: datasetIndex,
                        x1: plot.left,
                        x2: plot.right,
                        y1: plot.top + slotHeight * index,
                        y2: plot.top + slotHeight * (index + 1),
                        cx: barEndX,
                        cy: plot.top + slotHeight * index + slotHeight / 2
                    };
                }
            });
        });
    };

    Chart.prototype.drawCategoryLabels = function drawCategoryLabels(labels, plot, horizontal) {
        var ctx = this.ctx;
        var labelConfig = this._categoryLabelConfig || {};

        ctx.save();
        ctx.fillStyle = '#587080';
        ctx.font = '11px Segoe UI, Arial, sans-serif';
        ctx.textBaseline = 'middle';

        if (horizontal) {
            var slotHeight = (plot.bottom - plot.top) / Math.max(labels.length, 1);
            var yMaxChars = Math.max(10, Math.round(toNumber(labelConfig.yMaxCharsPerLine) || 28));
            var yLineHeight = Math.max(11, Math.round(toNumber(labelConfig.yLineHeight) || 12));

            labels.forEach(function (label, index) {
                var y = plot.top + slotHeight * index + slotHeight / 2;
                var lines = wrapLabelText(label, yMaxChars);
                var startY = y - ((lines.length - 1) * yLineHeight / 2);
                ctx.textAlign = 'right';

                lines.slice(0, 5).forEach(function (line, lineIndex) {
                    ctx.fillText(line, plot.left - 8, startY + (lineIndex * yLineHeight));
                });
            });
        } else {
            var slotWidth = (plot.right - plot.left) / Math.max(labels.length, 1);
            var xMaxChars = Math.max(8, Math.round(toNumber(labelConfig.xMaxCharsPerLine) || 14));
            var xLineHeight = Math.max(11, Math.round(toNumber(labelConfig.xLineHeight) || 12));
            var maxVisible = Math.max(1, Math.floor((plot.right - plot.left) / 86));
            var skipStep = labels.length > maxVisible ? Math.ceil(labels.length / maxVisible) : 1;

            labels.forEach(function (label, index) {
                if (skipStep > 1 && (index % skipStep !== 0) && index !== labels.length - 1) {
                    return;
                }

                var lines = wrapLabelText(label, xMaxChars).slice(0, 3);
                var x = plot.left + slotWidth * index + slotWidth / 2;
                ctx.textAlign = 'center';
                lines.forEach(function (line, lineIndex) {
                    ctx.fillText(line, x, plot.bottom + 14 + (lineIndex * xLineHeight));
                });
            });
        }

        ctx.restore();
    };

    Chart.prototype.drawValueTicks = function drawValueTicks(max, plot, horizontal) {
        var ctx = this.ctx;
        var ticks = 4;

        ctx.save();
        ctx.strokeStyle = 'rgba(15, 61, 87, 0.08)';
        ctx.fillStyle = '#7a90a0';
        ctx.font = '11px Segoe UI, Arial, sans-serif';

        for (var index = 0; index <= ticks; index += 1) {
            var ratio = index / ticks;
            var value = Math.round(max * ratio * 10) / 10;

            if (horizontal) {
                var x = plot.left + ratio * (plot.right - plot.left);
                ctx.beginPath();
                ctx.moveTo(x, plot.top);
                ctx.lineTo(x, plot.bottom);
                ctx.stroke();
                ctx.textAlign = 'center';
                ctx.fillText(String(value), x, plot.bottom + 16);
            } else {
                var y = plot.bottom - ratio * (plot.bottom - plot.top);
                ctx.beginPath();
                ctx.moveTo(plot.left, y);
                ctx.lineTo(plot.right, y);
                ctx.stroke();
                ctx.textAlign = 'right';
                ctx.fillText(String(value), plot.left - 8, y + 4);
            }
        }

        ctx.restore();
    };

    Chart.prototype.getTooltipOptions = function getTooltipOptions() {
        return this.options && this.options.plugins && this.options.plugins.tooltip
            ? this.options.plugins.tooltip
            : {};
    };

    Chart.prototype.formatNumber = function formatNumber(value) {
        return this._numberFormatter.format(toNumber(value));
    };

    Chart.prototype.getHitPayload = function getHitPayload(hit) {
        return hit ? [{ index: hit.index, datasetIndex: hit.datasetIndex }] : [];
    };

    Chart.prototype.getActiveElements = function getActiveElements() {
        if (!this._activeHit) {
            return [];
        }

        return [{
            index: this._activeHit.index,
            datasetIndex: this._activeHit.datasetIndex || 0,
            element: {
                x: toNumber(this._activeHit.cx) || ((this._activeHit.x1 + this._activeHit.x2) / 2),
                y: toNumber(this._activeHit.cy) || ((this._activeHit.y1 + this._activeHit.y2) / 2)
            }
        }];
    };

    Chart.prototype.findHitRegion = function findHitRegion(x, y) {
        if (!Number.isFinite(x) || !Number.isFinite(y)) {
            return null;
        }

        function normalizeAngle(angle) {
            var full = Math.PI * 2;
            var normalized = angle % full;
            return normalized < 0 ? normalized + full : normalized;
        }

        return this._hitRegions.find(function (item) {
            if (item && item.type === 'arc') {
                var dx = x - item.centerX;
                var dy = y - item.centerY;
                var distance = Math.sqrt((dx * dx) + (dy * dy));

                if (!Number.isFinite(distance) || distance < item.innerRadius || distance > item.outerRadius) {
                    return false;
                }

                var angle = normalizeAngle(Math.atan2(dy, dx));
                var start = normalizeAngle(item.startAngle);
                var end = normalizeAngle(item.endAngle);

                if (end < start) {
                    return angle >= start || angle <= end;
                }

                return angle >= start && angle <= end;
            }

            return x >= item.x1 && x <= item.x2 && y >= item.y1 && y <= item.y2;
        }) || null;
    };

    Chart.prototype.getEventPoint = function getEventPoint(event) {
        if (!event) {
            return null;
        }

        var source = event;

        if (event.touches && event.touches.length) {
            source = event.touches[0];
        } else if (event.changedTouches && event.changedTouches.length) {
            source = event.changedTouches[0];
        }

        if (!source || !Number.isFinite(source.clientX) || !Number.isFinite(source.clientY)) {
            return null;
        }

        var rect = this.canvas.getBoundingClientRect();
        return {
            x: source.clientX - rect.left,
            y: source.clientY - rect.top
        };
    };

    Chart.prototype.findLegendRegion = function findLegendRegion(x, y) {
        if (!Number.isFinite(x) || !Number.isFinite(y)) {
            return null;
        }

        return this._legendRegions.find(function (region) {
            return x >= region.x1 && x <= region.x2 && y >= region.y1 && y <= region.y2;
        }) || null;
    };

    Chart.prototype.toggleDatasetVisibility = function toggleDatasetVisibility(datasetIndex) {
        var datasets = toArray(this.data.datasets);
        var target = datasets[datasetIndex];

        if (!target) {
            return;
        }

        target.hidden = target.hidden !== true ? true : false;
        this._activeHit = null;
        this.render();
    };

    Chart.prototype.callPlugins = function callPlugins(hookName) {
        if (!hookName || !registeredPlugins.length) {
            return;
        }

        registeredPlugins.forEach(function (plugin) {
            if (!plugin || typeof plugin[hookName] !== 'function') {
                return;
            }

            try {
                plugin[hookName](this);
            } catch (_error) {
                // Plugin errors should not break chart rendering.
            }
        }, this);
    };

    Chart.prototype.drawTooltip = function drawTooltip(labels, datasets, plot) {
        var tooltipOptions = this.getTooltipOptions();
        var activeHit = this._activeHit;

        if (!activeHit || tooltipOptions.enabled === false || !plot) {
            return;
        }

        var index = activeHit.index;
        if (!Number.isInteger(index) || index < 0 || index >= labels.length) {
            return;
        }

        var callbacks = tooltipOptions.callbacks || {};
        var bodyLines = [];
        var markerColors = [];
        var contexts = [];
        var self = this;

        datasets.forEach(function (dataset, datasetIndex) {
            if (!isDatasetVisible(dataset)) {
                return;
            }

            var value = toNumber(toArray(dataset.data)[index]);
            var parsedValue = null;

            if (self.type === 'doughnut' || self.type === 'pie' || self.type === 'polarArea') {
                parsedValue = value;
            } else if (self.options && self.options.indexAxis === 'y') {
                parsedValue = { x: value };
            } else {
                parsedValue = { y: value };
            }

            var context = {
                chart: self,
                dataset: dataset,
                datasetIndex: datasetIndex,
                dataIndex: index,
                label: labels[index],
                parsed: parsedValue,
                raw: value
            };
            var labelText = null;

            if (typeof callbacks.label === 'function') {
                try {
                    labelText = callbacks.label(context);
                } catch (_error) {
                    labelText = null;
                }
            }

            if (Array.isArray(labelText)) {
                labelText = labelText.join(' ');
            }

            if (labelText === null || typeof labelText === 'undefined' || labelText === '') {
                var baseLabel = dataset && dataset.label ? (dataset.label + ': ') : '';
                labelText = baseLabel + self.formatNumber(value);
            }

            bodyLines.push(String(labelText));
            markerColors.push(getColor(dataset.borderColor || dataset.backgroundColor, datasetIndex, '#0f3d57'));
            contexts.push(context);
        });

        if (!bodyLines.length) {
            return;
        }

        var footerLine = '';
        if (typeof callbacks.footer === 'function') {
            try {
                var footerValue = callbacks.footer(contexts);
                if (Array.isArray(footerValue)) {
                    footerLine = footerValue.join(' ');
                } else if (footerValue) {
                    footerLine = String(footerValue);
                }
            } catch (_error) {
                footerLine = '';
            }
        }

        var title = String(labels[index] ?? '');
        var ctx = this.ctx;
        var canvasWidth = this.canvas.width / (global.devicePixelRatio || 1);
        var canvasHeight = this.canvas.height / (global.devicePixelRatio || 1);
        var padding = Math.max(6, toNumber(tooltipOptions.padding) || 12);
        var cornerRadius = Math.max(4, toNumber(tooltipOptions.cornerRadius) || 10);
        var borderWidth = Math.max(0, toNumber(tooltipOptions.borderWidth) || 1);
        var displayColors = tooltipOptions.displayColors !== false;
        var markerSize = displayColors ? 8 : 0;
        var markerGap = displayColors ? 8 : 0;
        var contentWidth = 0;

        ctx.save();
        ctx.font = '600 12px Segoe UI, Arial, sans-serif';
        contentWidth = Math.max(contentWidth, ctx.measureText(title).width);

        ctx.font = '12px Segoe UI, Arial, sans-serif';
        bodyLines.forEach(function (line) {
            contentWidth = Math.max(contentWidth, ctx.measureText(line).width + markerSize + markerGap);
        });

        if (footerLine) {
            ctx.font = '600 11px Segoe UI, Arial, sans-serif';
            contentWidth = Math.max(contentWidth, ctx.measureText(footerLine).width);
        }

        var lineHeight = 17;
        var titleHeight = title ? lineHeight : 0;
        var footerHeight = footerLine ? (lineHeight + 2) : 0;
        var boxWidth = contentWidth + padding * 2;
        var boxHeight = padding * 2 + titleHeight + bodyLines.length * lineHeight + footerHeight;
        var anchorX = toNumber(activeHit.cx) || ((activeHit.x1 + activeHit.x2) / 2);
        var anchorY = toNumber(activeHit.cy) || ((activeHit.y1 + activeHit.y2) / 2);
        var x = anchorX + 14;
        var y = anchorY - boxHeight - 14;

        if (x + boxWidth > canvasWidth - 8) {
            x = anchorX - boxWidth - 14;
        }

        if (y < 8) {
            y = anchorY + 14;
        }

        x = clamp(x, 8, canvasWidth - boxWidth - 8);
        y = clamp(y, 8, canvasHeight - boxHeight - 8);

        var backgroundColor = tooltipOptions.backgroundColor || 'rgba(7, 27, 43, 0.92)';
        var titleColor = tooltipOptions.titleColor || '#ffffff';
        var bodyColor = tooltipOptions.bodyColor || '#eef4f8';
        var borderColor = tooltipOptions.borderColor || 'rgba(255, 255, 255, 0.16)';

        ctx.beginPath();
        ctx.moveTo(x + cornerRadius, y);
        ctx.lineTo(x + boxWidth - cornerRadius, y);
        ctx.arcTo(x + boxWidth, y, x + boxWidth, y + cornerRadius, cornerRadius);
        ctx.lineTo(x + boxWidth, y + boxHeight - cornerRadius);
        ctx.arcTo(x + boxWidth, y + boxHeight, x + boxWidth - cornerRadius, y + boxHeight, cornerRadius);
        ctx.lineTo(x + cornerRadius, y + boxHeight);
        ctx.arcTo(x, y + boxHeight, x, y + boxHeight - cornerRadius, cornerRadius);
        ctx.lineTo(x, y + cornerRadius);
        ctx.arcTo(x, y, x + cornerRadius, y, cornerRadius);
        ctx.closePath();
        ctx.fillStyle = backgroundColor;
        ctx.fill();

        if (borderWidth > 0) {
            ctx.strokeStyle = borderColor;
            ctx.lineWidth = borderWidth;
            ctx.stroke();
        }

        var textX = x + padding;
        var lineY = y + padding + 2;

        if (title) {
            ctx.font = '600 12px Segoe UI, Arial, sans-serif';
            ctx.fillStyle = titleColor;
            ctx.textAlign = 'left';
            ctx.textBaseline = 'top';
            ctx.fillText(title, textX, lineY);
            lineY += titleHeight;
        }

        ctx.font = '12px Segoe UI, Arial, sans-serif';
        ctx.fillStyle = bodyColor;

        bodyLines.forEach(function (line, bodyIndex) {
            var lineX = textX;

            if (displayColors) {
                ctx.fillStyle = markerColors[bodyIndex];
                ctx.fillRect(lineX, lineY + 4, markerSize, markerSize);
                lineX += markerSize + markerGap;
                ctx.fillStyle = bodyColor;
            }

            ctx.fillText(line, lineX, lineY);
            lineY += lineHeight;
        });

        if (footerLine) {
            ctx.font = '600 11px Segoe UI, Arial, sans-serif';
            ctx.fillStyle = bodyColor;
            ctx.fillText(footerLine, textX, lineY + 1);
        }

        ctx.restore();
    };

    Chart.prototype.handlePointerMove = function handlePointerMove(event) {
        if (this._destroyed) {
            return;
        }

        var point = this.getEventPoint(event);
        if (!point) {
            return;
        }

        var legendHit = this.findLegendRegion(point.x, point.y);
        var hit = this.findHitRegion(point.x, point.y);

        if (!hit && this.type === 'line' && this._plotArea && point.x >= this._plotArea.left && point.x <= this._plotArea.right && point.y >= this._plotArea.top && point.y <= this._plotArea.bottom) {
            hit = this._hitRegions.reduce(function (nearest, item) {
                var center = toNumber(item.cx) || ((item.x1 + item.x2) / 2);
                var distance = Math.abs(center - point.x);

                if (!nearest || distance < nearest.distance) {
                    return { item: item, distance: distance };
                }

                return nearest;
            }, null);

            hit = hit ? hit.item : null;
        }

        var previousIndex = this._activeHit ? this._activeHit.index : -1;
        var nextIndex = hit ? hit.index : -1;
        this._activeHit = hit ? Object.assign({}, hit) : null;
        this.canvas.style.cursor = (legendHit || hit) ? 'pointer' : 'default';

        if (typeof this.options?.onHover === 'function') {
            this.options.onHover(event, this.getHitPayload(hit), this);
        }

        if (previousIndex !== nextIndex) {
            this.render();
        }
    };

    Chart.prototype.handlePointerLeave = function handlePointerLeave(event) {
        var hadActive = Boolean(this._activeHit);
        this._activeHit = null;
        this.canvas.style.cursor = 'default';

        if (typeof this.options?.onHover === 'function') {
            this.options.onHover(event, [], this);
        }

        if (hadActive) {
            this.render();
        }
    };

    Chart.prototype.handleClick = function handleClick(event) {
        var point = this.getEventPoint(event);
        var legendHit = point ? this.findLegendRegion(point.x, point.y) : null;

        if (legendHit) {
            this.toggleDatasetVisibility(legendHit.datasetIndex);
            return;
        }

        var callback = this.options?.onClick;

        if (typeof callback !== 'function') {
            return;
        }

        var hit = point ? this.findHitRegion(point.x, point.y) : null;
        callback(event, this.getHitPayload(hit), this);
    };

    Chart.registry = {
        plugins: {
            get: function getPlugin(id) {
                return registeredPlugins.find(function (plugin) {
                    return plugin && plugin.id === id;
                });
            }
        }
    };

    Chart.register = function register() {
        Array.prototype.slice.call(arguments).forEach(function (plugin) {
            if (!plugin || !plugin.id) {
                return;
            }

            if (!Chart.registry.plugins.get(plugin.id)) {
                registeredPlugins.push(plugin);
            }
        });
    };

    global.Chart = Chart;
})(window);
