/**
 * Agenda — Event Form (ajouter / modifier)
 * Gère : synchronisation dates, validation, toggles type/visibilité/classes,
 *         chargement AJAX personnes via API/endpoints/agenda_persons.php.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form          = document.getElementById('event-form');
        var typeSelect    = document.getElementById('type_evenement');
        var visSelect     = document.getElementById('visibilite');
        var dateDebut     = document.getElementById('date_debut');
        var dateFin       = document.getElementById('date_fin');
        var typePersoCtr  = document.getElementById('type_personnalise_container');
        var sectionCls    = document.getElementById('section_classes');
        var persContainer = document.getElementById('personnesContainer');
        var personsList   = document.getElementById('personsList');
        var searchInput   = document.getElementById('searchPersons');
        var personsCount  = document.getElementById('personsCount');

        /* ── Synchronisation dates ── */
        if (dateDebut && dateFin) {
            dateDebut.addEventListener('change', function () {
                if (!dateFin.value || dateFin.value < this.value) {
                    dateFin.value = this.value;
                }
            });
        }

        /* ── Validation formulaire ── */
        if (form) {
            form.addEventListener('submit', function (e) {
                var heureDebut = document.getElementById('heure_debut');
                var heureFin   = document.getElementById('heure_fin');
                if (!dateDebut || !dateFin || !heureDebut || !heureFin) return;

                var d1 = new Date(dateDebut.value + 'T' + heureDebut.value);
                var d2 = new Date(dateFin.value + 'T' + heureFin.value);

                if (isNaN(d1.getTime()) || isNaN(d2.getTime())) {
                    e.preventDefault();
                    alert('Format de date ou heure invalide.');
                    return;
                }
                if (d2 <= d1) {
                    e.preventDefault();
                    alert('La date/heure de fin doit être après la date/heure de début.');
                }
            });
        }

        /* ── Toggle type personnalisé ── */
        function toggleTypePerso() {
            if (!typeSelect || !typePersoCtr) return;
            typePersoCtr.hidden = (typeSelect.value !== 'autre');
        }
        if (typeSelect) {
            typeSelect.addEventListener('change', toggleTypePerso);
            toggleTypePerso();
        }

        /* ── Toggle section classes ── */
        function toggleClasses() {
            if (!visSelect || !sectionCls) return;
            sectionCls.hidden = (visSelect.value !== 'classes_specifiques');
        }
        if (visSelect) {
            visSelect.addEventListener('change', function () {
                toggleClasses();
                loadPersons(this.value);
            });
            toggleClasses();
        }

        /* ── Recherche classes ── */
        var classesSearch = document.getElementById('classes_search');
        if (classesSearch) {
            classesSearch.addEventListener('input', function () {
                var term = this.value.toLowerCase();
                document.querySelectorAll('.class-option').forEach(function (opt) {
                    opt.style.display = opt.textContent.toLowerCase().indexOf(term) !== -1 ? '' : 'none';
                });
            });
        }

        /* ── Select / Deselect all (data-action) ── */
        document.querySelectorAll('[data-action="select-all"]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelectorAll('.' + this.dataset.target).forEach(function (cb) { cb.checked = true; });
                updatePersonsCount();
            });
        });
        document.querySelectorAll('[data-action="deselect-all"]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelectorAll('.' + this.dataset.target).forEach(function (cb) { cb.checked = false; });
                updatePersonsCount();
            });
        });

        /* ── Chargement AJAX des personnes ── */
        function loadPersons(visibility) {
            if (!personsList) return;

            // Masquer le conteneur pour public / personnel
            if (visibility === 'public' || visibility === 'personnel') {
                if (persContainer) persContainer.hidden = true;
                return;
            }
            if (persContainer) persContainer.hidden = false;

            personsList.innerHTML = '<div class="loading-indicator">Chargement…</div>';

            fetch('../API/endpoints/agenda_persons.php?visibility=' + encodeURIComponent(visibility))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success && data.persons && data.persons.length > 0) {
                        renderPersons(data.persons);
                    } else {
                        personsList.innerHTML = '<div class="no-persons">Aucune personne trouvée pour cette visibilité.</div>';
                    }
                })
                .catch(function () {
                    personsList.innerHTML = '<div class="no-persons">Erreur lors du chargement.</div>';
                });
        }

        function renderPersons(persons) {
            personsList.innerHTML = '';
            persons.forEach(function (p) {
                var div  = document.createElement('div');
                div.className = 'person-item';

                var cb   = document.createElement('input');
                cb.type  = 'checkbox';
                cb.className = 'person-checkbox';
                cb.name  = 'personnes_concernees[]';
                cb.value = p.type + ':' + p.id;
                cb.id    = 'person-' + p.type + '-' + p.id;
                cb.addEventListener('change', updatePersonsCount);

                var lbl  = document.createElement('label');
                lbl.htmlFor   = cb.id;
                lbl.className = 'person-label';

                var name  = document.createElement('span');
                name.className   = 'person-name';
                name.textContent = p.name;

                var info  = document.createElement('span');
                info.className   = 'person-info';
                info.textContent = p.info || '';

                lbl.appendChild(name);
                lbl.appendChild(info);
                div.appendChild(cb);
                div.appendChild(lbl);
                personsList.appendChild(div);
            });
            updatePersonsCount();
        }

        function updatePersonsCount() {
            if (!personsCount) return;
            var n = document.querySelectorAll('.person-checkbox:checked').length;
            personsCount.textContent = n + ' personne(s) sélectionnée(s)';
        }

        /* ── Recherche personnes ── */
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var term = this.value.toLowerCase();
                document.querySelectorAll('.person-item').forEach(function (item) {
                    var nm = (item.querySelector('.person-name') || {}).textContent || '';
                    var inf = (item.querySelector('.person-info') || {}).textContent || '';
                    item.style.display = (nm.toLowerCase().indexOf(term) !== -1 || inf.toLowerCase().indexOf(term) !== -1) ? '' : 'none';
                });
            });
        }

        /* ── Init : charger les personnes selon la visibilité par défaut ── */
        if (visSelect) {
            loadPersons(visSelect.value);
        }
    });
})();
