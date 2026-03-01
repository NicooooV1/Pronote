<?php
/**
 * M12 – Notifications — Préférences de notification
 */
$pageTitle = 'Préférences de notification';
require_once __DIR__ . '/includes/header.php';

$userId = getUserId();
$userType = getUserRole();

// Sauvegarde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $prefs = [];
    foreach (NotificationService::typesNotification() as $type => $label) {
        $prefs[$type] = [
            'canal_email' => isset($_POST['email_' . $type]) ? 1 : 0,
            'canal_web'   => isset($_POST['web_' . $type]) ? 1 : 0,
            'canal_push'  => isset($_POST['push_' . $type]) ? 1 : 0,
            'actif'       => isset($_POST['actif_' . $type]) ? 1 : 0,
        ];
    }
    $notifService->sauvegarderPreferences($userId, $userType, $prefs);
    $_SESSION['success_message'] = 'Préférences sauvegardées avec succès.';
    header('Location: preferences.php');
    exit;
}

$preferences = $notifService->getPreferences($userId, $userType);
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-sliders-h"></i> Préférences de notification</h1>
        <a href="notifications.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <p class="pref-intro">Choisissez comment et quand vous souhaitez être notifié pour chaque type d'événement.</p>
            
            <form method="post">
                <?= csrfField() ?>
                <table class="pref-table">
                    <thead>
                        <tr>
                            <th>Type de notification</th>
                            <th class="text-center">Activé</th>
                            <th class="text-center"><i class="fas fa-globe"></i> Web</th>
                            <th class="text-center"><i class="fas fa-envelope"></i> Email</th>
                            <th class="text-center"><i class="fas fa-mobile-alt"></i> Push</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (NotificationService::typesNotification() as $type => $label): ?>
                        <?php $pref = $preferences[$type]; ?>
                        <tr>
                            <td>
                                <div class="pref-type">
                                    <i class="fas <?= NotificationService::iconeParType($type) ?>"></i>
                                    <span><?= htmlspecialchars($label) ?></span>
                                </div>
                            </td>
                            <td class="text-center">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="actif_<?= $type ?>" <?= $pref['actif'] ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td class="text-center">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="web_<?= $type ?>" <?= $pref['canal_web'] ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td class="text-center">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="email_<?= $type ?>" <?= $pref['canal_email'] ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td class="text-center">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="push_<?= $type ?>" <?= $pref['canal_push'] ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Sauvegarder</button>
                    <a href="notifications.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
