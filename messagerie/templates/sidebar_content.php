<?php
/**
 * Contenu spécifique de la sidebar pour la messagerie
 * Inclus dans $sidebarExtraContent via le header
 */

// Liste des dossiers pour le menu
$folders = [
    'information' => 'Informations',
    'reception' => 'Boîte de réception',
    'envoyes' => 'Messages envoyés',
    'archives' => 'Archives',
    'corbeille' => 'Corbeille'
];

// S'assurer que user est défini et que le type est présent
if (!isset($user)) {
    $user = $_SESSION['user'] ?? [];
}
if (!isset($user['type']) && isset($user['profil'])) {
    $user['type'] = $user['profil'];
} elseif (!isset($user['type'])) {
    $user['type'] = 'eleve';
}

$canSendAnnouncement = isset($user) && in_array($user['type'], ['vie_scolaire', 'administrateur']);
$isProfesseur = isset($user) && $user['type'] === 'professeur';
$currentFolder = $currentFolder ?? 'reception';
?>

        <!-- Dossiers messagerie -->
        <div class="sidebar-section">
            <div class="sidebar-section-header">Dossiers</div>
            <div class="sidebar-nav">
                <?php foreach ($folders as $key => $name): ?>
                <a href="<?= $rootPrefix ?>messagerie/index.php?folder=<?= $key ?>" class="sidebar-nav-item <?= $currentFolder === $key ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-<?= getFolderIcon($key) ?>"></i></span>
                    <span><?= htmlspecialchars($name) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Actions messagerie -->
        <div class="sidebar-section">
            <div class="sidebar-section-header">Actions</div>
            <div class="sidebar-nav">
                <a href="<?= $rootPrefix ?>messagerie/new_message.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-pen"></i></span>
                    <span>Nouveau message</span>
                </a>
                
                <?php if ($isProfesseur): ?>
                <a href="<?= $rootPrefix ?>messagerie/class_message.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-graduation-cap"></i></span>
                    <span>Message à la classe</span>
                </a>
                <?php endif; ?>
                
                <?php if ($canSendAnnouncement): ?>
                <a href="<?= $rootPrefix ?>messagerie/new_announcement.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-bullhorn"></i></span>
                    <span>Nouvelle annonce</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
