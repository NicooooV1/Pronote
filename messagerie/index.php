<?php
/**
 * Interface principale de messagerie
 */

// Charger la configuration (qui charge l'API)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/utils.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/csrf.php';
require_once __DIR__ . '/core/validator.php';

// Vérifier l'authentification via l'API
$user = requireAuth();

// La connexion PDO est déjà disponible via config.php
require_once __DIR__ . '/models/conversation.php';
require_once __DIR__ . '/models/notification.php';

// Définir le titre de la page
$pageTitle = 'Pronote - Messagerie';

// Traitement des actions POST (avec CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['conv_id'])) {
    csrf_verify();
    $convId = Validator::id($_POST['conv_id']);
    
    if ($convId) {
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
}

// Récupérer le dossier courant
$currentFolder = Validator::folder($_GET['folder'] ?? null);

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

// Pagination
$page = Validator::positiveInt($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Recherche
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$isSearch = !empty($searchQuery) && strlen($searchQuery) >= 2;

// Récupérer les conversations
if ($isSearch) {
    $result = searchConversations($user['id'], $user['type'], $searchQuery, $limit, $offset);
    $conversations = $result;
    $totalConversations = count($result);
    $hasMore = false;
} else {
    $result = getConversations($user['id'], $user['type'], $currentFolder, $limit, $offset);
    $conversations = $result['conversations'];
    $totalConversations = $result['total'];
    $hasMore = $result['has_more'];
}

$totalPages = max(1, ceil($totalConversations / $limit));

// Si requête AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    echo json_encode([
        'conversations' => $conversations,
        'total' => $totalConversations,
        'has_more' => $hasMore,
        'page' => $page,
        'csrf_token' => csrf_token()
    ]);
    exit;
}

// Inclure l'en-tête
include 'templates/header.php';
?>

<!-- Barre de recherche -->
<div class="search-bar">
    <form method="get" action="index.php" class="search-form">
        <input type="hidden" name="folder" value="<?= htmlspecialchars($currentFolder) ?>">
        <div class="search-input-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" name="q" placeholder="Rechercher dans les conversations..." 
                   value="<?= htmlspecialchars($searchQuery) ?>" minlength="2" autocomplete="off">
            <?php if ($isSearch): ?>
            <a href="index.php?folder=<?= htmlspecialchars($currentFolder) ?>" class="search-clear" title="Effacer la recherche">
                <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn primary btn-sm">Rechercher</button>
    </form>
</div>

<?php if ($isSearch): ?>
<div class="search-results-info">
    <p><i class="fas fa-info-circle"></i> <?= $totalConversations ?> résultat(s) pour « <?= htmlspecialchars($searchQuery) ?> »</p>
</div>
<?php endif; ?>

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

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
    <a href="?folder=<?= htmlspecialchars($currentFolder) ?>&page=<?= $page - 1 ?><?= $isSearch ? '&q=' . urlencode($searchQuery) : '' ?>" class="pagination-btn">
        <i class="fas fa-chevron-left"></i> Précédent
    </a>
    <?php endif; ?>
    
    <span class="pagination-info">Page <?= $page ?> / <?= $totalPages ?> (<?= $totalConversations ?> conversations)</span>
    
    <?php if ($hasMore): ?>
    <a href="?folder=<?= htmlspecialchars($currentFolder) ?>&page=<?= $page + 1 ?><?= $isSearch ? '&q=' . urlencode($searchQuery) : '' ?>" class="pagination-btn">
        Suivant <i class="fas fa-chevron-right"></i>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include 'templates/footer.php'; ?>