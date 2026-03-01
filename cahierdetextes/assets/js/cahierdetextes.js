/**
 * FRONOTE — Cahier de Textes · JS unifié (REF-4)
 *
 * Regroupe toutes les interactions JS du module :
 *   - Auto-close des alertes
 *   - Validation dates (formulaire)
 *   - Synchro professeur ↔ matière
 *   - Filtrage sidebar (urgent/bientôt/tous)
 *   - Zone d'upload drag & drop
 *   - Popover calendrier
 *   - Toggle « devoir fait »
 */
const CahierTextes = {

    init() {
        this.initAlerts();
        this.initDateValidation();
        this.initProfFilter();
        this.initSidebarFilter();
        this.initUploadZone();
        this.initCalendarPopover();
        this.initDevoirFait();
    },

    /* ── Auto-close alertes ── */
    initAlerts() {
        document.querySelectorAll('.alert-banner').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 300);
            }, 5000);
        });
        document.querySelectorAll('.alert-close').forEach(btn => {
            btn.addEventListener('click', function () {
                const a = this.parentElement;
                a.style.opacity = '0';
                setTimeout(() => a.style.display = 'none', 300);
            });
        });
    },

    /* ── Validation dates du formulaire ── */
    initDateValidation() {
        const form      = document.getElementById('devoir-form');
        const dateAjout = document.getElementById('date_ajout');
        const dateRendu = document.getElementById('date_rendu');
        const indicator = document.getElementById('jours-restants');

        if (!form || !dateAjout || !dateRendu) return;

        const update = () => {
            const da = new Date(dateAjout.value);
            const dr = new Date(dateRendu.value);
            if (isNaN(dr.getTime()) || isNaN(da.getTime())) { indicator.textContent = ''; return; }

            if (dr <= da) {
                indicator.textContent = 'La date de rendu doit être postérieure à la date d\'ajout';
                indicator.style.color = 'var(--urgent-color)';
                return;
            }

            const diff = Math.ceil((dr - new Date()) / 86400000);
            if (diff < 0) {
                indicator.textContent = 'Expiré (depuis ' + Math.abs(diff) + ' jours)';
                indicator.style.color = 'var(--expired-color)';
            } else if (diff === 0) {
                indicator.textContent = 'À rendre aujourd\'hui !';
                indicator.style.color = 'var(--urgent-color)';
            } else {
                indicator.textContent = `À rendre dans ${diff} jour${diff > 1 ? 's' : ''}`;
                indicator.style.color = diff <= 3 ? 'var(--urgent-color)' : diff <= 7 ? 'var(--deadline-soon)' : 'var(--module-color)';
            }
        };

        dateAjout.addEventListener('change', update);
        dateRendu.addEventListener('change', update);
        update(); // initial

        form.addEventListener('submit', e => {
            const da = new Date(dateAjout.value);
            const dr = new Date(dateRendu.value);
            if (dr <= da) { e.preventDefault(); alert("La date de rendu doit être ultérieure à la date d'ajout."); }
        });
    },

    /* ── Synchro professeur ↔ matière (admin/vie scolaire) ── */
    initProfFilter() {
        const profSel    = document.getElementById('nom_professeur');
        const matiereSel = document.getElementById('nom_matiere');
        if (!profSel || !matiereSel || profSel.tagName !== 'SELECT') return;

        profSel.addEventListener('change', function () {
            if (this.selectedIndex > 0) {
                const mat = this.options[this.selectedIndex].dataset.matiere;
                for (let i = 0; i < matiereSel.options.length; i++) {
                    if (matiereSel.options[i].value === mat) { matiereSel.selectedIndex = i; break; }
                }
            }
        });

        matiereSel.addEventListener('change', function () {
            const val = this.value;
            for (let i = 1; i < profSel.options.length; i++) {
                profSel.options[i].style.display = (!val || profSel.options[i].dataset.matiere === val) ? '' : 'none';
            }
        });
    },

    /* ── Filtrage sidebar (urgent/bientôt/tous) ── */
    initSidebarFilter() {
        document.querySelectorAll('.filter-link').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                const filter = link.dataset.filter;
                document.querySelectorAll('.filter-link').forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                document.querySelectorAll('.devoir-card[data-date]').forEach(card => {
                    if (filter === 'all') { card.style.display = ''; return; }
                    if (filter === 'urgent') { card.style.display = card.classList.contains('urgent') ? '' : 'none'; return; }
                    if (filter === 'soon')   { card.style.display = (card.classList.contains('soon') || card.classList.contains('urgent')) ? '' : 'none'; }
                });
            });
        });
    },

    /* ── Zone upload drag & drop (PJ-4) ── */
    initUploadZone() {
        const zone    = document.getElementById('upload-zone');
        const input   = document.getElementById('fichiers');
        const preview = document.getElementById('fichiers-preview');
        if (!zone || !input || !preview) return;

        zone.addEventListener('click', () => input.click());
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('dragover');
            input.files = e.dataTransfer.files;
            this._renderPreview(input.files, preview);
        });
        input.addEventListener('change', () => this._renderPreview(input.files, preview));
    },

    _renderPreview(files, container) {
        container.innerHTML = '';
        for (const f of files) {
            const div = document.createElement('div');
            div.className = 'fichier-item';
            div.innerHTML = `<i class="fas fa-file"></i><span>${f.name}</span><span class="fichier-taille">${this._formatSize(f.size)}</span>`;
            container.appendChild(div);
        }
    },

    _formatSize(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' Mo';
        if (bytes >= 1024)    return (bytes / 1024).toFixed(1) + ' Ko';
        return bytes + ' o';
    },

    /* ── Popover calendrier (UX-4) ── */
    initCalendarPopover() {
        let popover = null;
        document.querySelectorAll('.calendar-event').forEach(ev => {
            ev.addEventListener('click', function (e) {
                e.stopPropagation();
                if (popover) popover.remove();
                const rect  = this.getBoundingClientRect();
                popover = document.createElement('div');
                popover.className = 'calendar-popover';
                popover.innerHTML = `
                    <div class="popover-header">${this.dataset.titre || ''}</div>
                    <div class="popover-body">
                        <div><strong>Matière :</strong> ${this.dataset.matiere || ''}</div>
                        <div><strong>Professeur :</strong> ${this.dataset.prof || ''}</div>
                        ${this.dataset.desc ? '<div class="popover-desc">' + this.dataset.desc + '</div>' : ''}
                    </div>
                    <div class="popover-footer"><a href="form_devoir.php?id=${this.dataset.id}" class="btn btn-sm btn-primary">Voir / Modifier</a></div>
                `;
                popover.style.position = 'fixed';
                popover.style.top  = (rect.bottom + 5) + 'px';
                popover.style.left = rect.left + 'px';
                document.body.appendChild(popover);
            });
        });
        document.addEventListener('click', () => { if (popover) { popover.remove(); popover = null; } });
    },

    /* ── Toggle « devoir fait » (UX-3) ── */
    initDevoirFait() {
        document.querySelectorAll('.devoir-fait-toggle').forEach(btn => {
            btn.addEventListener('click', function () {
                const devoirId = this.dataset.devoirId;
                const card     = this.closest('.devoir-card');
                fetch('?ajax=toggle_fait&devoir_id=' + devoirId)
                    .then(r => r.json())
                    .then(data => {
                        if (data.fait) {
                            card.classList.add('devoir-fait');
                            this.innerHTML = '<i class="fas fa-check-circle"></i> Fait';
                        } else {
                            card.classList.remove('devoir-fait');
                            this.innerHTML = '<i class="far fa-circle"></i> À faire';
                        }
                    })
                    .catch(() => {});
            });
        });
    }
};

document.addEventListener('DOMContentLoaded', () => CahierTextes.init());
