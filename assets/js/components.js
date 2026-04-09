/**
 * Fronote UI Components — JS interactions
 * Handles: tabs, dropdown, modal, toast, alert dismiss, card collapse
 */
(function() {
    'use strict';

    // ─── Tabs ──────────────────────────────────────────────────────
    document.addEventListener('click', function(e) {
        var tab = e.target.closest('.ui-tabs__tab');
        if (!tab) return;
        var tabs = tab.closest('.ui-tabs');
        var key = tab.dataset.tab;

        tabs.querySelectorAll('.ui-tabs__tab').forEach(function(t) { t.classList.remove('ui-tabs__tab--active'); });
        tabs.querySelectorAll('.ui-tabs__panel').forEach(function(p) { p.classList.remove('ui-tabs__panel--active'); });
        tab.classList.add('ui-tabs__tab--active');
        var panel = tabs.querySelector('[data-tab-panel="' + key + '"]');
        if (panel) panel.classList.add('ui-tabs__panel--active');
    });

    // ─── Dropdown ──────────────────────────────────────────────────
    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('[data-dropdown]');
        if (trigger) {
            e.stopPropagation();
            var dd = document.getElementById(trigger.dataset.dropdown);
            if (dd) {
                var wasOpen = dd.classList.contains('is-open');
                closeAllDropdowns();
                if (!wasOpen) dd.classList.add('is-open');
            }
            return;
        }
        if (!e.target.closest('.ui-dropdown__menu')) {
            closeAllDropdowns();
        }
    });

    function closeAllDropdowns() {
        document.querySelectorAll('.ui-dropdown.is-open').forEach(function(d) { d.classList.remove('is-open'); });
    }

    // ─── Modal ───��─────────────────────────────────────────────────
    document.addEventListener('click', function(e) {
        // Open modal
        var opener = e.target.closest('[data-modal]');
        if (opener) {
            e.preventDefault();
            var modal = document.getElementById(opener.dataset.modal);
            if (modal) modal.classList.add('is-visible');
            return;
        }
        // Close modal
        var closer = e.target.closest('[data-dismiss="modal"]');
        if (closer) {
            var overlay = closer.closest('.ui-modal-overlay');
            if (overlay) overlay.classList.remove('is-visible');
            return;
        }
        // Close on overlay click
        if (e.target.classList.contains('ui-modal-overlay')) {
            e.target.classList.remove('is-visible');
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var modal = document.querySelector('.ui-modal-overlay.is-visible');
            if (modal) modal.classList.remove('is-visible');
        }
    });

    // ─── Alert dismiss ─────────────────────────────────────────────
    document.addEventListener('click', function(e) {
        var close = e.target.closest('.ui-alert__close');
        if (close) {
            var alert = close.closest('.ui-alert');
            if (alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-8px)';
                setTimeout(function() { alert.remove(); }, 200);
            }
        }
    });

    // ─── Card collapse ────────────��────────────────────────────────
    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.ui-card__toggle');
        if (!toggle) return;
        var card = toggle.closest('.ui-card');
        var body = card.querySelector('.ui-card__body');
        if (body) {
            var hidden = body.style.display === 'none';
            body.style.display = hidden ? '' : 'none';
            toggle.querySelector('i').style.transform = hidden ? '' : 'rotate(-90deg)';
        }
    });

    // ─── Toast API ─────────────────────────────────────────────────
    window.FronoteToast = {
        show: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 4000;
            var container = document.getElementById('ui-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'ui-toast-container';
                container.className = 'ui-toast-container';
                container.setAttribute('aria-live', 'polite');
                document.body.appendChild(container);
            }
            var toast = document.createElement('div');
            toast.className = 'ui-toast ui-toast--' + type;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(function() {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                toast.style.transition = 'all 0.2s';
                setTimeout(function() { toast.remove(); }, 200);
            }, duration);
        },
        success: function(msg, dur) { this.show(msg, 'success', dur); },
        error: function(msg, dur) { this.show(msg, 'error', dur); },
        warning: function(msg, dur) { this.show(msg, 'warning', dur); },
        info: function(msg, dur) { this.show(msg, 'info', dur); }
    };
})();
