/**
 * Drag-and-drop handler for the EDT grid.
 * Makes .edt-cours draggable and .edt-cell droppable, then persists via AJAX.
 */
(function () {
    'use strict';

    let draggedEl = null;
    let sourceCell = null;
    let ghostEl = null;

    // Initialise draggable elements
    function init() {
        document.querySelectorAll('.edt-cours[draggable="true"]').forEach(function (el) {
            el.addEventListener('dragstart', onDragStart);
            el.addEventListener('dragend', onDragEnd);
        });

        document.querySelectorAll('.edt-cell').forEach(function (cell) {
            cell.addEventListener('dragover', onDragOver);
            cell.addEventListener('dragenter', onDragEnter);
            cell.addEventListener('dragleave', onDragLeave);
            cell.addEventListener('drop', onDrop);
        });
    }

    function onDragStart(e) {
        draggedEl = this;
        sourceCell = this.closest('.edt-cell');
        this.classList.add('edt-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.dataset.coursId);

        // Semi-transparent ghost
        ghostEl = this.cloneNode(true);
        ghostEl.style.opacity = '0.5';
        ghostEl.style.position = 'absolute';
        ghostEl.style.top = '-9999px';
        document.body.appendChild(ghostEl);
        e.dataTransfer.setDragImage(ghostEl, 40, 20);
    }

    function onDragEnd() {
        this.classList.remove('edt-dragging');
        document.querySelectorAll('.edt-cell').forEach(function (c) {
            c.classList.remove('edt-drop-target');
        });
        if (ghostEl) {
            ghostEl.remove();
            ghostEl = null;
        }
        draggedEl = null;
        sourceCell = null;
    }

    function onDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }

    function onDragEnter(e) {
        e.preventDefault();
        this.classList.add('edt-drop-target');
    }

    function onDragLeave() {
        this.classList.remove('edt-drop-target');
    }

    function onDrop(e) {
        e.preventDefault();
        this.classList.remove('edt-drop-target');

        if (!draggedEl) return;

        var targetCell = this;
        if (targetCell === sourceCell) return;

        var coursId = draggedEl.dataset.coursId;
        var newJour = targetCell.dataset.jour;
        var newCreneauId = targetCell.dataset.creneauId;

        if (!coursId || !newJour || !newCreneauId) return;

        // Optimistic move
        targetCell.appendChild(draggedEl);

        // Persist
        fetch('ajax_move_cours.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cours_id: parseInt(coursId, 10),
                new_jour: parseInt(newJour, 10),
                new_creneau_id: parseInt(newCreneauId, 10)
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                // Revert on conflict/error
                sourceCell.appendChild(draggedEl);
                showToast(data.message || 'Erreur lors du déplacement', 'error');
            } else {
                showToast('Cours déplacé avec succès', 'success');
            }
        })
        .catch(function () {
            sourceCell.appendChild(draggedEl);
            showToast('Erreur réseau', 'error');
        });
    }

    function showToast(message, type) {
        var toast = document.createElement('div');
        toast.className = 'edt-toast edt-toast-' + type;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function () { toast.classList.add('edt-toast-show'); }, 10);
        setTimeout(function () {
            toast.classList.remove('edt-toast-show');
            setTimeout(function () { toast.remove(); }, 300);
        }, 3000);
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
