<?php
/**
 * Détails d'un événement — Module Agenda
 * Nettoyé : EventRepository, canViewEvent/canEditEvent/canDeleteEvent, CSRF centralisé,
 *           suppression via POST (au lieu de GET), pas d'inline styles/JS superflus.
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
$pdo = getPDO();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/EventRepository.php';

requireAuth();

$user          = getCurrentUser();
$user_fullname = getUserFullName();
$user_role     = getUserRole();
$user_initials = getUserInitials();
$repo          = new EventRepository($pdo);

// Vérifier l'ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlashMessage('error', "Identifiant d'événement invalide.");
    header('Location: agenda.php');
    exit;
}

// Récupérer l'événement
$evenement = $repo->findById($id);
if (!$evenement) {
    setFlashMessage('error', "L'événement demandé n'existe pas.");
    header('Location: agenda.php');
    exit;
}

// Vérifier la visibilité (canViewEvent de auth.php)
if (!canViewEvent($evenement)) {
    setFlashMessage('error', "Vous n'avez pas l'autorisation de consulter cet événement.");
    header('Location: agenda.php');
    exit;
}

// Permissions (canEditEvent / canDeleteEvent de auth.php)
$can_edit   = canEditEvent($evenement);
$can_delete = canDeleteEvent($evenement);

// Formater les dates
try {
    $date_debut  = new DateTime($evenement['date_debut']);
    $date_fin    = new DateTime($evenement['date_fin']);
} catch (Exception $e) {
    $date_debut = new DateTime();
    $date_fin   = new DateTime('+1 hour');
}
$format_date  = 'd/m/Y';
$format_heure = 'H:i';

$now        = new DateTime();
$is_today   = $date_debut->format('Y-m-d') === $now->format('Y-m-d');
$is_tomorrow = $date_debut->format('Y-m-d') === (new DateTime('tomorrow'))->format('Y-m-d');
$is_past    = $date_fin < $now;
$is_future  = $date_debut > $now;
$days_until = $is_future ? $date_debut->diff($now)->days : 0;

// Type (depuis EventRepository au lieu de la copie locale)
$type_info = EventRepository::getTypeInfo($evenement['type_evenement']);

// Visibilité (depuis EventRepository au lieu du switch/case dupliqué)
$vis_info      = EventRepository::getVisibilityLabel($evenement['visibilite']);
$visibilite_texte = $vis_info['label'];
$visibilite_icone = $vis_info['icone'];

// Classes et personnes
$classes_array   = !empty($evenement['classes']) ? explode(',', $evenement['classes']) : [];
$personnes_array = !empty($evenement['personnes_concernees']) ? explode(',', $evenement['personnes_concernees']) : [];

// CSRF pour suppression POST (système centralisé)
$csrf_token = csrf_token();

// Lien iCal (simplifié, pas de token CSRF — la session suffit)
$ical_filename = urlencode(preg_replace('/[^a-z0-9]+/i', '_', $evenement['titre'])) . '.ics';
$ical_link = "export_ical.php?id=" . (int) $id . "&filename=" . $ical_filename;

// Lien de partage (URL propre, pas de token)
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$share_link = $proto . '://' . $_SERVER['HTTP_HOST'] .
              dirname($_SERVER['PHP_SELF']) . '/details_evenement.php?id=' . (int) $id;

/* ── Page ── */
$pageTitle = htmlspecialchars($evenement['titre']);
$extraCss  = [];

include 'includes/header.php';
?>

<div class="calendar-container">
    <div class="event-details-container">
        <!-- En-tête -->
        <div class="event-header">
            <div class="event-header-top">
                <div class="event-title-container">
                    <h1 class="event-title"><?= htmlspecialchars($evenement['titre']) ?></h1>
                    <div class="event-subtitle">Créé par <?= htmlspecialchars($evenement['createur']) ?></div>
                </div>

                <?php if (($evenement['statut'] ?? 'actif') !== 'actif'): ?>
                    <div class="event-status <?= $evenement['statut'] === 'annulé' ? 'cancelled' : 'postponed' ?>">
                        <i class="fas fa-<?= $evenement['statut'] === 'annulé' ? 'ban' : 'clock' ?>"></i>
                        <?= htmlspecialchars(ucfirst($evenement['statut'])) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="event-type" style="background-color: <?= htmlspecialchars($type_info['couleur']) ?>">
                <i class="fas fa-<?= htmlspecialchars($type_info['icone']) ?>"></i>
                <?= htmlspecialchars($type_info['nom']) ?>
            </div>

            <div class="event-timing">
                <div class="event-date-display">
                    <i class="far fa-calendar-alt"></i>
                    <?php if ($date_debut->format('Y-m-d') === $date_fin->format('Y-m-d')): ?>
                        <?= $date_debut->format($format_date) ?>
                    <?php else: ?>
                        Du <?= $date_debut->format($format_date) ?> au <?= $date_fin->format($format_date) ?>
                    <?php endif; ?>

                    <?php if ($is_today): ?>
                        <span class="event-badge today">Aujourd'hui</span>
                    <?php elseif ($is_tomorrow): ?>
                        <span class="event-badge tomorrow">Demain</span>
                    <?php elseif ($is_future): ?>
                        <span class="event-badge future">Dans <?= $days_until ?> jour<?= $days_until > 1 ? 's' : '' ?></span>
                    <?php elseif ($is_past): ?>
                        <span class="event-badge past">Passé</span>
                    <?php endif; ?>
                </div>

                <div class="event-date-display">
                    <i class="far fa-clock"></i>
                    <?php if ($date_debut->format('Y-m-d') === $date_fin->format('Y-m-d')): ?>
                        De <?= $date_debut->format($format_heure) ?> à <?= $date_fin->format($format_heure) ?>
                    <?php else: ?>
                        De <?= $date_debut->format($format_date . ' à ' . $format_heure) ?>
                        à <?= $date_fin->format($format_date . ' à ' . $format_heure) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Corps -->
        <div class="event-body">
            <?php if (!empty($evenement['description'])): ?>
            <div class="event-section">
                <h3 class="section-title"><i class="fas fa-align-left"></i> Description</h3>
                <div class="section-content description">
                    <?= nl2br(htmlspecialchars($evenement['description'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="event-section">
                <h3 class="section-title"><i class="fas fa-info-circle"></i> Informations</h3>
                <div class="info-grid">
                    <?php if (!empty($evenement['lieu'])): ?>
                    <div class="info-item">
                        <div class="info-label">Lieu</div>
                        <div class="info-value"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($evenement['lieu']) ?></div>
                    </div>
                    <?php endif; ?>

                    <div class="info-item">
                        <div class="info-label">Visibilité</div>
                        <div class="info-value"><i class="fas fa-<?= htmlspecialchars($visibilite_icone) ?>"></i> <?= htmlspecialchars($visibilite_texte) ?></div>
                    </div>

                    <?php if (!empty($evenement['matieres'])): ?>
                    <div class="info-item">
                        <div class="info-label">Matière</div>
                        <div class="info-value"><i class="fas fa-book"></i> <?= htmlspecialchars($evenement['matieres']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($evenement['date_modification'])): ?>
                    <div class="info-item">
                        <div class="info-label">Dernière modification</div>
                        <div class="info-value">
                            <i class="fas fa-edit"></i>
                            <?= (new DateTime($evenement['date_modification']))->format('d/m/Y à H:i') ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($classes_array)): ?>
                <div class="tags-container">
                    <?php foreach ($classes_array as $classe): ?>
                        <span class="tag"><?= htmlspecialchars($classe) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($personnes_array)): ?>
            <div class="event-section">
                <h3 class="section-title"><i class="fas fa-users"></i> Personnes concernées</h3>
                <div class="tags-container">
                    <?php foreach ($personnes_array as $personne): ?>
                        <span class="tag"><?= htmlspecialchars(trim($personne)) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="event-actions">
                <a href="agenda.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>

                <a href="<?= htmlspecialchars($ical_link) ?>" class="btn btn-secondary" download>
                    <i class="fas fa-calendar-plus"></i> Exporter (iCal)
                </a>

                <button type="button" class="btn btn-secondary" id="shareBtn"
                        data-url="<?= htmlspecialchars($share_link) ?>"
                        data-title="<?= htmlspecialchars($evenement['titre']) ?>">
                    <i class="fas fa-share-alt"></i> Partager
                </button>

                <?php if ($can_edit): ?>
                <a href="modifier_evenement.php?id=<?= (int) $id ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Modifier
                </a>
                <?php endif; ?>

                <?php if ($can_delete): ?>
                <button type="button" class="btn btn-danger" id="openDeleteModal">
                    <i class="fas fa-trash-alt"></i> Supprimer
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($can_delete): ?>
<!-- Modal de suppression — POST au lieu de GET (fix sécurité) -->
<div id="confirmationModal" class="modal" role="dialog" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Confirmation de suppression</h3>
            <button class="close" id="closeModal" aria-label="Fermer">&times;</button>
        </div>
        <div class="modal-body">
            <p>Êtes-vous sûr de vouloir supprimer cet événement ?</p>
            <p>Cette action est irréversible.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="cancelDelete">Annuler</button>
            <form method="post" action="supprimer_evenement.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <button type="submit" class="btn btn-danger">Supprimer</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── Modal de suppression ──
    var modal     = document.getElementById('confirmationModal');
    var openBtn   = document.getElementById('openDeleteModal');
    var closeBtn  = document.getElementById('closeModal');
    var cancelBtn = document.getElementById('cancelDelete');

    function openModal()  { if (modal) { modal.style.display = 'block'; modal.setAttribute('aria-hidden', 'false'); } }
    function closeModal() { if (modal) { modal.style.display = 'none';  modal.setAttribute('aria-hidden', 'true');  } }

    if (openBtn)   openBtn.addEventListener('click', openModal);
    if (closeBtn)  closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (modal)     modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.style.display === 'block') closeModal();
    });

    // ── Partage ──
    var shareBtn = document.getElementById('shareBtn');
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            var url   = this.dataset.url;
            var title = this.dataset.title;

            if (navigator.share) {
                navigator.share({ title: title, text: 'Événement : ' + title, url: url }).catch(function() {});
            } else if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    alert('Lien copié dans le presse-papier.');
                });
            } else {
                prompt('Copiez ce lien :', url);
            }
        });
    }
});
</script>

<?php
include 'includes/footer.php';
ob_end_flush();
?>
