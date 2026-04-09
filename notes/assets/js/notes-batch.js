/**
 * Notes module — Batch entry with auto-save every 30s (ES5 compatible).
 */
(function () {
    'use strict';

    var AUTOSAVE_INTERVAL = 30000; // 30 seconds
    var saveTimer = null;
    var isDirty = false;
    var lastSaveTime = null;

    var form = document.getElementById('batch-entry-form');
    if (!form) return;

    var statusEl = document.getElementById('autosave-status');
    var noteInputs = form.querySelectorAll('input[name^="notes["]');
    var commentInputs = form.querySelectorAll('input[name^="commentaire["]');

    // Mark dirty on any input change
    function markDirty() {
        isDirty = true;
        if (statusEl) {
            statusEl.textContent = 'Modifications non sauvegardées';
            statusEl.className = 'autosave-status autosave-dirty';
        }
    }

    for (var i = 0; i < noteInputs.length; i++) {
        noteInputs[i].addEventListener('input', markDirty);
    }
    for (var j = 0; j < commentInputs.length; j++) {
        commentInputs[j].addEventListener('input', markDirty);
    }

    // Collect form data
    function collectData() {
        var common = {
            id_matiere: form.querySelector('[name="id_matiere"]').value,
            type_evaluation: form.querySelector('[name="type_evaluation"]').value,
            date_note: form.querySelector('[name="date_note"]').value,
            trimestre: form.querySelector('[name="trimestre"]').value,
            coefficient: form.querySelector('[name="coefficient"]').value,
            note_sur: form.querySelector('[name="note_sur"]').value
        };

        var notes = [];
        for (var k = 0; k < noteInputs.length; k++) {
            var input = noteInputs[k];
            var match = input.name.match(/notes\[(\d+)\]/);
            if (!match) continue;
            var eleveId = match[1];
            var val = input.value.trim();
            if (val === '') continue;

            var commentInput = form.querySelector('input[name="commentaire[' + eleveId + ']"]');
            notes.push({
                id_eleve: parseInt(eleveId, 10),
                note: parseFloat(val),
                commentaire: commentInput ? commentInput.value : ''
            });
        }

        return { common: common, notes: notes };
    }

    // Auto-save via AJAX
    function autoSave() {
        if (!isDirty) return;

        var data = collectData();
        if (data.notes.length === 0) return;

        // Get CSRF token
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfInput = form.querySelector('[name="csrf_token"]');
        data.csrf_token = csrfInput ? csrfInput.value : (csrfMeta ? csrfMeta.content : '');

        if (statusEl) {
            statusEl.textContent = 'Sauvegarde en cours...';
            statusEl.className = 'autosave-status autosave-saving';
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'includes/ajax_batch_save.php', true);
        xhr.setRequestHeader('Content-Type', 'application/json');

        xhr.onload = function () {
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success) {
                        isDirty = false;
                        lastSaveTime = resp.saved_at;
                        if (statusEl) {
                            statusEl.textContent = 'Sauvegardé à ' + resp.saved_at +
                                ' (' + (resp.updated + resp.inserted) + ' notes)';
                            statusEl.className = 'autosave-status autosave-saved';
                        }
                    } else {
                        showSaveError(resp.error || 'Erreur inconnue');
                    }
                } catch (e) {
                    showSaveError('Réponse invalide');
                }
            } else {
                showSaveError('Erreur HTTP ' + xhr.status);
            }
        };

        xhr.onerror = function () {
            showSaveError('Erreur réseau');
        };

        xhr.send(JSON.stringify(data));
    }

    function showSaveError(msg) {
        if (statusEl) {
            statusEl.textContent = 'Erreur: ' + msg;
            statusEl.className = 'autosave-status autosave-error';
        }
    }

    // Start auto-save timer
    saveTimer = setInterval(autoSave, AUTOSAVE_INTERVAL);

    // Save on Ctrl+S
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            autoSave();
        }
    });

    // Warn on page leave if dirty
    window.addEventListener('beforeunload', function (e) {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Save before form submit (so we don't lose data)
    form.addEventListener('submit', function () {
        clearInterval(saveTimer);
        isDirty = false;
    });
})();
