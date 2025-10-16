<?php
/**
 * Interface principale de messagerie
 */

// Charger la configuration (qui charge l'API)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/utils.php';
require_once __DIR__ . '/core/auth.php';

// Vérifier l'authentification via l'API
$user = requireAuth();

// La connexion PDO est déjà disponible via config.php
require_once __DIR__ . '/models/conversation.php';
require_once __DIR__ . '/models/notification.php';

// Définir le titre de la page
$pageTitle = 'Pronote - Messagerie';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['conv_id'])) {
    $convId = (int)$_POST['conv_id'];
    
    switch ($_POST['action']) {
        case 'archive':
            archiveConversation($convId, $user['id'], $user['type']);
            redirect('index.php?folder=archives');
            break;
            
        case 'delete':
            deleteConversation($convId, $user['id'], $user['type']);
            redirect('index.php?folder=corbeille');
            break;
            
        case 'restore':
            restoreConversation($convId, $user['id'], $user['type']);
            redirect('index.php');
            break;
    }
}

// Récupérer le dossier courant
$currentFolder = isset($_GET['folder']) ? $_GET['folder'] : 'reception';

// Titre du dossier
$folderTitles = [
    'archives' => 'Archives',
    'envoyes' => 'Messages envoyés',
    'corbeille' => 'Corbeille',
    'information' => 'Informations & Annonces',
    'reception' => 'Boîte de réception'
];

$folderTitle = $folderTitles[$currentFolder] ?? 'Boîte de réception';
$pageTitle = 'Pronote - Messagerie - ' . $folderTitle;

// Récupérer les conversations
$conversations = getConversations($user['id'], $user['type'], $currentFolder);

// Si requête AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    foreach ($conversations as $conversation) {
        include 'templates/components/conversation-item.php';
    }
    exit;
}

// Inclure l'en-tête
include 'templates/header.php';
?>

<!-- Contenu principal -->
<div class="conversation-list-header">
    <div class="bulk-actions">
        <label class="checkbox-container select-all">
            <input type="checkbox" id="select-all-conversations">
            <span class="checkmark"></span>
            <span class="label-text">Tout sélectionner</span>
        </label>
        
        <?php if ($currentFolder !== 'corbeille'): ?>
        <button class="bulk-action-btn" data-action="mark_read" data-action-text="Marquer comme lu" data-icon="envelope-open" disabled>
            <i class="fas fa-envelope-open"></i> Marquer comme lu (0)
        </button>
        <button class="bulk-action-btn" data-action="mark_unread" data-action-text="Marquer comme non lu" data-icon="envelope" disabled>
            <i class="fas fa-envelope"></i> Marquer comme non lu (0)
        </button>
        <?php endif; ?>
        
        <button class="bulk-action-btn" data-action="archive" data-action-text="Archiver" data-icon="archive" disabled>
            <i class="fas fa-archive"></i> Archiver (0)
        </button>
        
        <?php if ($currentFolder !== 'corbeille'): ?>
        <button class="bulk-action-btn danger" data-action="delete" data-action-text="Supprimer" data-icon="trash-alt" disabled>
            <i class="fas fa-trash-alt"></i> Supprimer (0)
        </button>
        <?php else: ?>
        <button class="bulk-action-btn" data-action="restore" data-action-text="Restaurer" data-icon="undo" disabled>
            <i class="fas fa-undo"></i> Restaurer (0)
        </button>
        <button class="bulk-action-btn danger" data-action="delete_permanently" data-action-text="Suppr. définitivement" data-icon="trash-alt" disabled>
            <i class="fas fa-trash-alt"></i> Suppr. définitivement (0)
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Liste des conversations -->
<div class="conversation-list">
    <?php if (empty($conversations)): ?>
    <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <p>Aucune conversation dans ce dossier</p>
    </div>
    <?php else: ?>
    <?php foreach ($conversations as $conversation): ?>
        <?php include 'templates/components/conversation-item.php'; ?>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'templates/footer.php'; ?>