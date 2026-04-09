/**
 * Topbar navigation — dropdowns, search modal, mobile panel, theme toggle.
 * ES5 compatible.
 */
(function () {
    'use strict';

    // ── Dropdown toggle ─────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('.topbar-dropdown__trigger, .topbar-user-avatar');
        var allDropdowns = document.querySelectorAll('.topbar-dropdown');

        if (trigger) {
            var dropdown = trigger.closest('.topbar-dropdown');
            var wasOpen = dropdown.classList.contains('open');

            // Close all
            for (var i = 0; i < allDropdowns.length; i++) {
                allDropdowns[i].classList.remove('open');
            }

            // Toggle current
            if (!wasOpen) {
                dropdown.classList.add('open');
            }
            e.stopPropagation();
            return;
        }

        // Click outside closes all dropdowns
        if (!e.target.closest('.topbar-dropdown__menu')) {
            for (var j = 0; j < allDropdowns.length; j++) {
                allDropdowns[j].classList.remove('open');
            }
        }
    });

    // ── Search modal (Ctrl+K) ───────────────────────────────────
    var searchModal = document.getElementById('search-modal');
    var searchInput = document.getElementById('search-modal-input');
    var searchResults = document.getElementById('search-modal-results');
    var searchBtn = document.getElementById('topbar-search-btn');

    function openSearch() {
        if (!searchModal) return;
        searchModal.classList.add('open');
        searchModal.setAttribute('aria-hidden', 'false');
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
        if (searchResults) searchResults.innerHTML = '';
    }

    function closeSearch() {
        if (!searchModal) return;
        searchModal.classList.remove('open');
        searchModal.setAttribute('aria-hidden', 'true');
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', openSearch);
    }

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            openSearch();
        }
        if (e.key === 'Escape' && searchModal && searchModal.classList.contains('open')) {
            closeSearch();
        }
    });

    if (searchModal) {
        var backdrop = searchModal.querySelector('.search-modal__backdrop');
        if (backdrop) {
            backdrop.addEventListener('click', closeSearch);
        }
    }

    // Search: filter modules from the topbar
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var query = this.value.toLowerCase().trim();
            if (!searchResults) return;

            if (!query) {
                searchResults.innerHTML = '';
                return;
            }

            var items = document.querySelectorAll('.topbar-dropdown__item');
            var matches = [];

            for (var i = 0; i < items.length; i++) {
                var text = items[i].textContent.trim().toLowerCase();
                if (text.indexOf(query) !== -1) {
                    matches.push(items[i]);
                }
            }

            // Also search mobile links
            var mobileLinks = document.querySelectorAll('.topbar-mobile-link');
            for (var m = 0; m < mobileLinks.length; m++) {
                var mText = mobileLinks[m].textContent.trim().toLowerCase();
                if (mText.indexOf(query) !== -1) {
                    var isDup = false;
                    var href = mobileLinks[m].getAttribute('href');
                    for (var d = 0; d < matches.length; d++) {
                        if (matches[d].getAttribute('href') === href) { isDup = true; break; }
                    }
                    if (!isDup) matches.push(mobileLinks[m]);
                }
            }

            var html = '';
            for (var r = 0; r < Math.min(matches.length, 10); r++) {
                var el = matches[r];
                var icon = el.querySelector('i');
                var iconClass = icon ? icon.className : 'fas fa-circle';
                var label = el.textContent.trim();
                var link = el.getAttribute('href') || '#';
                html += '<a class="search-result-item" href="' + link + '">'
                      + '<i class="' + iconClass + '"></i>'
                      + '<span>' + label + '</span></a>';
            }

            searchResults.innerHTML = html || '<div style="padding:1rem;color:#a0aec0;text-align:center;">Aucun resultat</div>';
        });
    }

    // ── Mobile panel ──────────────────────────────────────────��─
    var hamburger = document.getElementById('topbar-hamburger');
    var mobilePanel = document.getElementById('topbar-mobile-panel');
    var mobileClose = document.getElementById('topbar-mobile-close');

    if (hamburger && mobilePanel) {
        hamburger.addEventListener('click', function () {
            mobilePanel.classList.add('open');
        });
    }

    if (mobileClose && mobilePanel) {
        mobileClose.addEventListener('click', function () {
            mobilePanel.classList.remove('open');
        });
    }

    // Close mobile panel on outside click
    document.addEventListener('click', function (e) {
        if (mobilePanel && mobilePanel.classList.contains('open')) {
            if (!mobilePanel.contains(e.target) && e.target !== hamburger && !hamburger.contains(e.target)) {
                mobilePanel.classList.remove('open');
            }
        }
    });

    // ── Theme toggle ────────────────────────────────────────────
    var themeToggle = document.getElementById('topbar-theme-toggle');
    var iconLight = document.getElementById('theme-icon-light');
    var iconDark = document.getElementById('theme-icon-dark');

    function updateThemeIcons() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (iconLight) iconLight.style.display = isDark ? 'none' : '';
        if (iconDark) iconDark.style.display = isDark ? '' : 'none';
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            var current = document.documentElement.getAttribute('data-theme') || 'light';
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('fronote_dark_mode', next); } catch (e) {}
            updateThemeIcons();
        });
    }

    updateThemeIcons();

    // Watch for external theme changes (e.g., from sidebar toggle)
    var observer = new MutationObserver(updateThemeIcons);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
})();
