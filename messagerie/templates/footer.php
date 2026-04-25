<?php
/**
 * Pied de page HTML commun - Messagerie
 * Utilise le template partagé Fronote
 */
?>
    </div><!-- Fin content-container -->
            
<?php
// JS supplémentaires (shared.js toujours chargé en premier)
$extraJs = ['assets/js/shared.js', 'assets/js/inbox.js'];

if (basename($_SERVER['PHP_SELF']) === 'conversation.php') {
    $extraJs[] = 'assets/js/conversation.js';
}
if (in_array(basename($_SERVER['PHP_SELF']), ['new_message.php', 'new_announcement.php', 'class_message.php'])) {
    $extraJs[] = 'assets/js/forms.js';
}

// jQuery + scripts spécifiques messagerie
ob_start();
?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    // ── CSRF Token ──
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ── Helper: créer un formulaire avec CSRF ──
    function createCsrfForm(action, extraFields) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        let fields = `<input type="hidden" name="_csrf_token" value="${csrfToken}">`;
        for (const [name, value] of Object.entries(extraFields)) {
            fields += `<input type="hidden" name="${name}" value="${value}">`;
        }
        form.innerHTML = fields;
        document.body.appendChild(form);
        return form;
    }
    
    function confirmDelete(id) {
        if (confirm('Êtes-vous sûr de vouloir supprimer cette conversation ?')) {
            createCsrfForm('delete', { action: 'delete', conv_id: id }).submit();
        }
    }
    
    function confirmDeletePermanently(id) {
        if (confirm('Êtes-vous sûr de vouloir supprimer définitivement cette conversation ? Cette action est irréversible.')) {
            createCsrfForm('delete_permanently', { action: 'delete_permanently', conv_id: id }).submit();
        }
    }
    
    function archiveConversation(id) {
        createCsrfForm('archive', { action: 'archive', conv_id: id }).submit();
    }
    
    function unarchiveConversation(id) {
        createCsrfForm('unarchive', { action: 'unarchive', conv_id: id }).submit();
    }
    
    function restoreConversation(id) {
        createCsrfForm('restore', { action: 'restore', conv_id: id }).submit();
    }
    </script>
<?php
$extraScriptHtml = ob_get_clean();

include __DIR__ . '/../../templates/shared_footer.php';
?>