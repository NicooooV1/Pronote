/**
 * Notes module — Canvas API graphs (ES5 compatible).
 * Renders: histogram (distribution), evolution curve, box plot.
 */
(function () {
    'use strict';

    var COLORS = {
        primary: '#0f4c81',
        success: '#38a169',
        danger: '#e53e3e',
        info: '#667eea',
        muted: '#a0aec0',
        gridLine: '#edf2f7',
        text: '#4a5568',
        textLight: '#718096',
        bg: '#fff'
    };

    // ── Utility ─────────────────────────────────────────────

    function getPixelRatio(ctx) {
        return window.devicePixelRatio || 1;
    }

    function setupCanvas(canvas) {
        var dpr = getPixelRatio();
        var rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        var ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        return { ctx: ctx, w: rect.width, h: rect.height };
    }

    function hexToRgba(hex, alpha) {
        var r = parseInt(hex.slice(1, 3), 16);
        var g = parseInt(hex.slice(3, 5), 16);
        var b = parseInt(hex.slice(5, 7), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    // ── Histogram (note distribution) ───────────────────────

    function drawHistogram(canvasId, data) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || !data || !data.labels) return;

        var setup = setupCanvas(canvas);
        var ctx = setup.ctx, w = setup.w, h = setup.h;

        var padding = { top: 20, right: 20, bottom: 40, left: 45 };
        var chartW = w - padding.left - padding.right;
        var chartH = h - padding.top - padding.bottom;

        var maxVal = Math.max.apply(null, data.values) || 1;
        var barCount = data.labels.length;
        var barWidth = (chartW / barCount) * 0.7;
        var barGap = (chartW / barCount) * 0.3;

        // Clear
        ctx.clearRect(0, 0, w, h);

        // Grid lines
        ctx.strokeStyle = COLORS.gridLine;
        ctx.lineWidth = 1;
        var gridSteps = 5;
        for (var g = 0; g <= gridSteps; g++) {
            var y = padding.top + chartH - (chartH / gridSteps) * g;
            ctx.beginPath();
            ctx.moveTo(padding.left, y);
            ctx.lineTo(w - padding.right, y);
            ctx.stroke();

            // Y-axis label
            ctx.fillStyle = COLORS.textLight;
            ctx.font = '11px -apple-system, sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(Math.round(maxVal / gridSteps * g), padding.left - 6, y + 4);
        }

        // Bars
        for (var i = 0; i < barCount; i++) {
            var x = padding.left + (chartW / barCount) * i + barGap / 2;
            var barH = (data.values[i] / maxVal) * chartH;
            var y = padding.top + chartH - barH;

            // Color based on range
            var color;
            if (i < 3) color = COLORS.danger;
            else if (i < 5) color = '#ff9500';
            else if (i < 7) color = '#f0c000';
            else color = COLORS.success;

            // Bar fill
            ctx.fillStyle = hexToRgba(color, 0.75);
            ctx.beginPath();
            ctx.roundRect
                ? ctx.roundRect(x, y, barWidth, barH, [4, 4, 0, 0])
                : ctx.rect(x, y, barWidth, barH);
            ctx.fill();

            // Value on top of bar
            if (data.values[i] > 0) {
                ctx.fillStyle = COLORS.text;
                ctx.font = 'bold 11px -apple-system, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(data.values[i], x + barWidth / 2, y - 5);
            }

            // X-axis label
            ctx.fillStyle = COLORS.textLight;
            ctx.font = '10px -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(data.labels[i], x + barWidth / 2, h - padding.bottom + 16);
        }

        // X-axis title
        ctx.fillStyle = COLORS.text;
        ctx.font = '11px -apple-system, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Notes /20', w / 2, h - 4);
    }

    // ── Evolution curve ─────────────────────────────────────

    function drawEvolution(canvasId, data) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || !data) return;

        var setup = setupCanvas(canvas);
        var ctx = setup.ctx, w = setup.w, h = setup.h;

        var padding = { top: 20, right: 120, bottom: 40, left: 45 };
        var chartW = w - padding.left - padding.right;
        var chartH = h - padding.top - padding.bottom;

        var labels = ['T1', 'T2', 'T3'];
        var matieres = Object.keys(data);

        ctx.clearRect(0, 0, w, h);

        // Grid
        ctx.strokeStyle = COLORS.gridLine;
        ctx.lineWidth = 1;
        for (var g = 0; g <= 4; g++) {
            var y = padding.top + (chartH / 4) * g;
            var val = 20 - (20 / 4) * g;
            ctx.beginPath();
            ctx.moveTo(padding.left, y);
            ctx.lineTo(w - padding.right, y);
            ctx.stroke();

            ctx.fillStyle = COLORS.textLight;
            ctx.font = '11px -apple-system, sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(val.toFixed(0), padding.left - 6, y + 4);
        }

        // X-axis labels
        for (var i = 0; i < 3; i++) {
            var x = padding.left + (chartW / 2) * i;
            ctx.fillStyle = COLORS.text;
            ctx.font = '12px -apple-system, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(labels[i], x, h - padding.bottom + 20);
        }

        // Lines per subject
        var legendY = padding.top + 5;
        for (var m = 0; m < matieres.length; m++) {
            var matiere = matieres[m];
            var info = data[matiere];
            var color = info.couleur || COLORS.info;
            var points = info.trimestres;

            ctx.strokeStyle = color;
            ctx.lineWidth = 2.5;
            ctx.beginPath();

            var firstPoint = true;
            for (var t = 0; t < 3; t++) {
                if (points[t] === null) continue;
                var px = padding.left + (chartW / 2) * t;
                var py = padding.top + chartH - (points[t] / 20) * chartH;

                if (firstPoint) {
                    ctx.moveTo(px, py);
                    firstPoint = false;
                } else {
                    ctx.lineTo(px, py);
                }

                // Dot
                ctx.fillStyle = color;
                ctx.beginPath();
                ctx.arc(px, py, 4, 0, Math.PI * 2);
                ctx.fill();

                // Value
                ctx.fillStyle = COLORS.text;
                ctx.font = 'bold 10px -apple-system, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(points[t].toFixed(1), px, py - 10);
            }
            ctx.stroke();

            // Legend
            ctx.fillStyle = color;
            ctx.fillRect(w - padding.right + 10, legendY + m * 18, 12, 12);
            ctx.fillStyle = COLORS.text;
            ctx.font = '11px -apple-system, sans-serif';
            ctx.textAlign = 'left';
            var label = matiere.length > 12 ? matiere.substring(0, 12) + '...' : matiere;
            ctx.fillText(label, w - padding.right + 26, legendY + m * 18 + 10);
        }
    }

    // ── Box plot ─────────────────────────────────────────────

    function drawBoxPlot(canvasId, data) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || !data || !data.length) return;

        var setup = setupCanvas(canvas);
        var ctx = setup.ctx, w = setup.w, h = setup.h;

        var padding = { top: 20, right: 20, bottom: 60, left: 45 };
        var chartW = w - padding.left - padding.right;
        var chartH = h - padding.top - padding.bottom;

        var count = data.length;
        var boxWidth = Math.min(40, (chartW / count) * 0.6);
        var gap = chartW / count;

        ctx.clearRect(0, 0, w, h);

        // Grid
        ctx.strokeStyle = COLORS.gridLine;
        ctx.lineWidth = 1;
        for (var g = 0; g <= 4; g++) {
            var y = padding.top + (chartH / 4) * g;
            var val = 20 - (20 / 4) * g;
            ctx.beginPath();
            ctx.moveTo(padding.left, y);
            ctx.lineTo(w - padding.right, y);
            ctx.stroke();

            ctx.fillStyle = COLORS.textLight;
            ctx.font = '11px -apple-system, sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(val.toFixed(0), padding.left - 6, y + 4);
        }

        // Boxes
        for (var i = 0; i < count; i++) {
            var d = data[i];
            var cx = padding.left + gap * i + gap / 2;
            var color = d.couleur || COLORS.info;

            function yPos(val) {
                return padding.top + chartH - (val / 20) * chartH;
            }

            var yMin = yPos(d.min);
            var yQ1 = yPos(d.q1);
            var yMed = yPos(d.median);
            var yQ3 = yPos(d.q3);
            var yMax = yPos(d.max);

            // Whisker line
            ctx.strokeStyle = color;
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(cx, yMin);
            ctx.lineTo(cx, yQ1);
            ctx.moveTo(cx, yQ3);
            ctx.lineTo(cx, yMax);
            ctx.stroke();

            // Whisker caps
            ctx.beginPath();
            ctx.moveTo(cx - boxWidth / 4, yMin);
            ctx.lineTo(cx + boxWidth / 4, yMin);
            ctx.moveTo(cx - boxWidth / 4, yMax);
            ctx.lineTo(cx + boxWidth / 4, yMax);
            ctx.stroke();

            // Box Q1-Q3
            ctx.fillStyle = hexToRgba(color, 0.2);
            ctx.fillRect(cx - boxWidth / 2, yQ3, boxWidth, yQ1 - yQ3);
            ctx.strokeStyle = color;
            ctx.lineWidth = 2;
            ctx.strokeRect(cx - boxWidth / 2, yQ3, boxWidth, yQ1 - yQ3);

            // Median line
            ctx.strokeStyle = color;
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(cx - boxWidth / 2, yMed);
            ctx.lineTo(cx + boxWidth / 2, yMed);
            ctx.stroke();

            // X label (rotated)
            ctx.save();
            ctx.translate(cx, h - padding.bottom + 10);
            ctx.rotate(-Math.PI / 6);
            ctx.fillStyle = COLORS.text;
            ctx.font = '10px -apple-system, sans-serif';
            ctx.textAlign = 'right';
            var lbl = d.matiere.length > 14 ? d.matiere.substring(0, 14) + '...' : d.matiere;
            ctx.fillText(lbl, 0, 0);
            ctx.restore();
        }
    }

    // ── Public API ──────────────────────────────────────────

    window.FronoteGraphs = {
        histogram: drawHistogram,
        evolution: drawEvolution,
        boxPlot: drawBoxPlot
    };

    // ── Auto-load graphs from data attributes ───────────────

    document.addEventListener('DOMContentLoaded', function () {
        var graphContainers = document.querySelectorAll('[data-graph-type]');
        for (var i = 0; i < graphContainers.length; i++) {
            var el = graphContainers[i];
            var type = el.getAttribute('data-graph-type');
            var url = el.getAttribute('data-graph-url');
            var canvasId = el.getAttribute('data-graph-canvas');

            if (url && canvasId) {
                (function (t, u, cId) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', u, true);
                    xhr.onload = function () {
                        if (xhr.status === 200) {
                            try {
                                var data = JSON.parse(xhr.responseText);
                                if (t === 'histogram') FronoteGraphs.histogram(cId, data);
                                else if (t === 'evolution') FronoteGraphs.evolution(cId, data);
                                else if (t === 'boxplot') FronoteGraphs.boxPlot(cId, data);
                            } catch (e) {
                                console.warn('Graph parse error:', e);
                            }
                        }
                    };
                    xhr.send();
                })(type, url, canvasId);
            }
        }
    });
})();
