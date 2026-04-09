/**
 * Competences module — Radar chart (Canvas API, ES5 compatible).
 * Renders a radar/spider chart showing competence levels per domain.
 */
(function () {
    'use strict';

    var COLORS = {
        primary: 'rgba(15, 76, 129, 0.6)',
        primaryBorder: '#0f4c81',
        classeOverlay: 'rgba(102, 126, 234, 0.3)',
        classeBorder: '#667eea',
        grid: '#e2e8f0',
        text: '#4a5568',
        textLight: '#718096',
        bg: '#fff'
    };

    function setupCanvas(canvas) {
        var dpr = window.devicePixelRatio || 1;
        var rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        var ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        return { ctx: ctx, w: rect.width, h: rect.height };
    }

    /**
     * Draw a radar chart.
     * @param {string} canvasId
     * @param {object} data — { labels: string[], values: number[], max: number }
     * @param {object} [overlay] — optional second dataset (class average) { values: number[] }
     */
    function drawRadar(canvasId, data, overlay) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || !data || !data.labels || data.labels.length < 3) return;

        var setup = setupCanvas(canvas);
        var ctx = setup.ctx, w = setup.w, h = setup.h;

        var cx = w / 2;
        var cy = h / 2;
        var radius = Math.min(cx, cy) - 50;
        var n = data.labels.length;
        var maxVal = data.max || 4;
        var step = (2 * Math.PI) / n;
        var startAngle = -Math.PI / 2; // Start from top

        ctx.clearRect(0, 0, w, h);

        // Grid circles (levels 1-4)
        var levels = 4;
        for (var lv = 1; lv <= levels; lv++) {
            var r = (radius / levels) * lv;
            ctx.beginPath();
            ctx.strokeStyle = COLORS.grid;
            ctx.lineWidth = 1;
            for (var i = 0; i <= n; i++) {
                var angle = startAngle + step * (i % n);
                var px = cx + r * Math.cos(angle);
                var py = cy + r * Math.sin(angle);
                if (i === 0) ctx.moveTo(px, py);
                else ctx.lineTo(px, py);
            }
            ctx.stroke();

            // Level label
            ctx.fillStyle = COLORS.textLight;
            ctx.font = '10px -apple-system, sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText(lv.toString(), cx + 4, cy - r + 12);
        }

        // Axis lines and labels
        for (var i = 0; i < n; i++) {
            var angle = startAngle + step * i;
            var px = cx + radius * Math.cos(angle);
            var py = cy + radius * Math.sin(angle);

            ctx.beginPath();
            ctx.strokeStyle = COLORS.grid;
            ctx.lineWidth = 1;
            ctx.moveTo(cx, cy);
            ctx.lineTo(px, py);
            ctx.stroke();

            // Label
            var labelR = radius + 18;
            var lx = cx + labelR * Math.cos(angle);
            var ly = cy + labelR * Math.sin(angle);

            ctx.fillStyle = COLORS.text;
            ctx.font = '11px -apple-system, sans-serif';
            ctx.textAlign = Math.abs(angle) < 0.1 || Math.abs(angle - Math.PI) < 0.1 ? 'center' :
                angle > -Math.PI / 2 && angle < Math.PI / 2 ? 'left' : 'right';
            ctx.textBaseline = 'middle';

            var label = data.labels[i];
            if (label.length > 18) label = label.substring(0, 16) + '...';
            ctx.fillText(label, lx, ly);
        }

        // Draw overlay (class average) first if present
        if (overlay && overlay.values) {
            drawDataPoly(ctx, cx, cy, radius, maxVal, n, step, startAngle, overlay.values, COLORS.classeOverlay, COLORS.classeBorder);
        }

        // Draw main data
        drawDataPoly(ctx, cx, cy, radius, maxVal, n, step, startAngle, data.values, COLORS.primary, COLORS.primaryBorder);

        // Legend if overlay
        if (overlay) {
            var legendY = h - 20;
            ctx.fillStyle = COLORS.primaryBorder;
            ctx.fillRect(w / 2 - 90, legendY, 12, 12);
            ctx.fillStyle = COLORS.text;
            ctx.font = '11px -apple-system, sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText('Élève', w / 2 - 74, legendY + 10);

            ctx.fillStyle = COLORS.classeBorder;
            ctx.fillRect(w / 2 + 10, legendY, 12, 12);
            ctx.fillStyle = COLORS.text;
            ctx.fillText('Classe', w / 2 + 26, legendY + 10);
        }
    }

    function drawDataPoly(ctx, cx, cy, radius, maxVal, n, step, startAngle, values, fillColor, strokeColor) {
        ctx.beginPath();
        for (var i = 0; i <= n; i++) {
            var idx = i % n;
            var val = Math.min(values[idx] || 0, maxVal);
            var r = (val / maxVal) * radius;
            var angle = startAngle + step * idx;
            var px = cx + r * Math.cos(angle);
            var py = cy + r * Math.sin(angle);
            if (i === 0) ctx.moveTo(px, py);
            else ctx.lineTo(px, py);
        }
        ctx.closePath();
        ctx.fillStyle = fillColor;
        ctx.fill();
        ctx.strokeStyle = strokeColor;
        ctx.lineWidth = 2;
        ctx.stroke();

        // Dots
        for (var i = 0; i < n; i++) {
            var val = Math.min(values[i] || 0, maxVal);
            var r = (val / maxVal) * radius;
            var angle = startAngle + step * i;
            var px = cx + r * Math.cos(angle);
            var py = cy + r * Math.sin(angle);

            ctx.beginPath();
            ctx.arc(px, py, 4, 0, Math.PI * 2);
            ctx.fillStyle = strokeColor;
            ctx.fill();
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 1.5;
            ctx.stroke();
        }
    }

    // Public API
    window.FronoteRadar = {
        draw: drawRadar
    };

    // Auto-load from data attributes
    document.addEventListener('DOMContentLoaded', function () {
        var containers = document.querySelectorAll('[data-radar-url]');
        for (var i = 0; i < containers.length; i++) {
            (function (el) {
                var url = el.getAttribute('data-radar-url');
                var canvasId = el.getAttribute('data-radar-canvas');
                var overlayUrl = el.getAttribute('data-radar-overlay-url');

                if (!url || !canvasId) return;

                var xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.onload = function () {
                    if (xhr.status !== 200) return;
                    var data = JSON.parse(xhr.responseText);

                    if (overlayUrl) {
                        var xhr2 = new XMLHttpRequest();
                        xhr2.open('GET', overlayUrl, true);
                        xhr2.onload = function () {
                            var overlay = xhr2.status === 200 ? JSON.parse(xhr2.responseText) : null;
                            FronoteRadar.draw(canvasId, data, overlay);
                        };
                        xhr2.send();
                    } else {
                        FronoteRadar.draw(canvasId, data, null);
                    }
                };
                xhr.send();
            })(containers[i]);
        }
    });
})();
