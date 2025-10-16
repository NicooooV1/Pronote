<?php
/**
 * Liste des participants d'une conversation
 * 
 * @param array $participants Les participants à afficher
 * @param array $user L'utilisateur connecté
 * @param bool $isAdmin Si l'utilisateur est administrateur
 * @param bool $isModerator Si l'utilisateur est modérateur
 * @param bool $isDeleted Si la conversation est supprimée
 */
?>

<!-- Titre de la section avec bouton d'ajout -->
<h3>
    <span>Participants</span>
    <?php if (!$isDeleted && $isModerator): ?>
    <button id="add-participant-btn" class="btn-icon"><i class="fas fa-plus-circle"></i></button>
    <?php endif; ?>
</h3>

<?php
// S'assurer que la variable $participants est définie
$participants = $participants ?? [];
?>

<ul class="participants-list">
    <?php if (empty($participants)): ?>
    <li class="no-participants">Aucun participant</li>
    <?php else: ?>
    <?php 
    // Regrouper les participants par statut
    $admins = [];
    $moderators = [];
    $normal = [];
    $left = [];
    
    foreach ($participants as $p) {
        if ($p['a_quitte']) {
            $left[] = $p;
        } elseif ($p['est_administrateur']) {
            $admins[] = $p;
        } elseif ($p['est_moderateur']) {
            $moderators[] = $p;
        } else {
            $normal[] = $p;
        }
    }
    
    // Fonction d'affichage d'un participant
    function displayParticipant($p, $user, $isAdmin, $isModerator, $isDeleted) {
        $isCurrentUser = ($p['utilisateur_id'] == $user['id'] && $p['utilisateur_type'] == $user['type']);
        $canManage = ($isAdmin || ($isModerator && !$p['est_administrateur'])) && !$isDeleted && !$isCurrentUser;
        ?>
        <li class="participant-item<?= $isCurrentUser ? ' current' : '' ?><?= $p['a_quitte'] ? ' left' : '' ?>">
            <div class="participant-info">
                <div class="participant-avatar">
                    <?= strtoupper(substr(htmlspecialchars($p['nom_complet']), 0, 2)) ?>
                </div>
                <div class="participant-details">
                    <div class="participant-name">
                        <?= h(htmlspecialchars($p['nom_complet'])) ?>
                        <?php if ($isCurrentUser): ?>
                        <span class="badge current">Vous</span>
                        <?php endif; ?>
                    </div>
                    <div class="participant-type">
                        <?= getParticipantType($p['utilisateur_type']) ?>
                        <?php if ($p['est_administrateur']): ?>
                        <span class="badge admin">Admin</span>
                        <?php elseif ($p['est_moderateur']): ?>
                        <span class="badge moderator">Modérateur</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($canManage): ?>
            <div class="participant-actions">
                <button class="btn-icon" onclick="toggleParticipantActions(<?= $p['id'] ?>)">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div class="participant-actions-menu" id="participant-actions-<?= $p['id'] ?>">
                    <?php if ($isAdmin): ?>
                        <?php if (!$isModerator): ?>
                        <a href="#" onclick="promoteToModerator(<?= $p['id'] ?>); return false;">
                            <i class="fas fa-user-shield"></i> Promouvoir modérateur
                        </a>
                        <?php else: ?>
                        <a href="#" onclick="demoteFromModerator(<?= $p['id'] ?>); return false;">
                            <i class="fas fa-user"></i> Rétrograder
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a href="#" onclick="removeParticipant(<?= $p['id'] ?>); return false;" class="danger">
                        <i class="fas fa-user-minus"></i> Retirer
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </li>
        <?php
    }
    
    // Afficher les administrateurs en premier
    foreach ($admins as $p) {
        displayParticipant($p, $user, $isAdmin, $isModerator, $isDeleted);
    }
    
    // Puis les modérateurs
    foreach ($moderators as $p) {
        displayParticipant($p, $user, $isAdmin, $isModerator, $isDeleted);
    }
    
    // Puis les participants normaux
    foreach ($normal as $p) {
        displayParticipant($p, $user, $isAdmin, $isModerator, $isDeleted);
    }
    
    // Enfin, les participants qui ont quitté (si on les affiche)
    if (!empty($left)) {
        echo '<li class="participant-divider">Participants ayant quitté</li>';
        foreach ($left as $p) {
            displayParticipant($p, $user, $isAdmin, $isModerator, $isDeleted);
        }
    }
    ?>
    <?php endif; ?>
</ul>