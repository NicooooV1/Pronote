<?php
/**
 * /notification_preferences.php - Gestion des préférences de notification
 */

// Inclure les fichiers nécessaires
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/utils.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/models/notification.php';
require_once __DIR__ . '/controllers/notification.php';

// Vérifier l'authentification
$user = requireAuth();

// Définir le titre de la page
$pageTitle = 'Préférences de notification';

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les valeurs du formulaire
    $preferences = [
        'email_notifications' => isset($_POST['email_notifications']),
        'browser_notifications' => isset($_POST['browser_notifications']),
        'notification_sound' => isset($_POST['notification_sound']),
        'mention_notifications' => isset($_POST['mention_notifications']),
        'reply_notifications' => isset($_POST['reply_notifications']),
        'important_notifications' => isset($_POST['important_notifications']),
        'digest_frequency' => isset($_POST['digest_frequency']) ? $_POST['digest_frequency'] : 'never'
    ];
    
    // Mettre à jour les préférences
    $result = handleUpdateNotificationPreferences($user['id'], $user['type'], $preferences);
    
    if ($result['success']) {
        $success = 'Vos préférences de notification ont été mises à jour avec succès.';
    } else {
        $error = 'Une erreur est survenue lors de la mise à jour de vos préférences.';
    }
}

// Récupérer les préférences actuelles
$preferences = getUserNotificationPreferences($user['id'], $user['type']);

// Inclure l'en-tête
include 'templates/header.php';
?>

    <main>
        <h2>Préférences de notification</h2>
        
        <?php if (!empty($success)): ?>
        <div class="alert success">
            <p><?= htmlspecialchars($success) ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="alert error">
            <p><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="post" action="">
                <div class="form-group">
                    <h3>Types de notifications</h3>
                    <p class="text-muted">Choisissez les types de notifications que vous souhaitez recevoir.</p>
                    
                    <label class="checkbox-container">
                        <input type="checkbox" name="important_notifications" <?= $preferences['important_notifications'] ? 'checked' : '' ?>>
                        <span class="checkmark"></span>
                        Messages importants et urgents
                    </label>
                    
                    <label class="checkbox-container">
                        <input type="checkbox" name="reply_notifications" <?= $preferences['reply_notifications'] ? 'checked' : '' ?>>
                        <span class="checkmark"></span>
                        Réponses à vos messages
                    </label>
                    
                    <label class="checkbox-container">
                        <input type="checkbox" name="mention_notifications" <?= $preferences['mention_notifications'] ? 'checked' : '' ?>>
                        <span class="checkmark"></span>
                        Mentions (@votre_nom)
                    </label>
                </div>
                
                <div class="form-group">
                    <h3>Méthodes de notification</h3>
                    <p class="text-muted">Choisissez comment vous souhaitez être notifié.</p>
                    
                    <label class="checkbox-container">
                        <input type="checkbox" name="browser_notifications" <?= $preferences['browser_notifications'] ? 'checked' : '' ?>>
                        <span class="checkmark"></span>
                        Notifications dans le navigateur
                    </label>
                    
                    <label class="checkbox-container">
                        <input type="checkbox" name="notification_sound" <?= $preferences['notification_sound'] ? 'checked' : '' ?>>
                        <span class="checkmark"></span>
                        Sons de notification
                    </label>
                    
                    <label class="checkbox-container">
                        <input type="checkbox" name="email_notifications" <?= $preferences['email_notifications'] ? 'checked' : '' ?>>
                        <span class="checkmark"></span>
                        Notifications par email
                    </label>
                </div>
                
                <div class="form-group">
                    <h3>Résumé des notifications</h3>
                    <p class="text-muted">Recevez un résumé périodique de vos notifications par email.</p>
                    
                    <select name="digest_frequency" class="form-control">
                        <option value="never" <?= $preferences['digest_frequency'] === 'never' ? 'selected' : '' ?>>Jamais</option>
                        <option value="daily" <?= $preferences['digest_frequency'] === 'daily' ? 'selected' : '' ?>>Quotidien</option>
                        <option value="weekly" <?= $preferences['digest_frequency'] === 'weekly' ? 'selected' : '' ?>>Hebdomadaire</option>
                    </select>
                </div>
                
                <div class="form-footer">
                    <button type="submit" class="btn primary">Enregistrer les préférences</button>
                </div>
            </form>
        </div>
    </main>

<!-- Script pour la gestion des notifications du navigateur -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const browserNotificationsCheckbox = document.querySelector('input[name="browser_notifications"]');
    
    if (browserNotificationsCheckbox) {
        // Vérifier si le navigateur supporte les notifications
        if ('Notification' in window) {
            // Si l'utilisateur a activé les notifications du navigateur
            if (browserNotificationsCheckbox.checked) {
                // Demander la permission si ce n'est pas encore fait
                if (Notification.permission !== 'granted' && Notification.permission !== 'denied') {
                    browserNotificationsCheckbox.addEventListener('click', function(e) {
                        // Si l'utilisateur coche la case
                        if (this.checked) {
                            Notification.requestPermission().then(function(permission) {
                                // Si l'utilisateur refuse, décocher la case
                                if (permission !== 'granted') {
                                    browserNotificationsCheckbox.checked = false;
                                }
                            });
                        }
                    });
                }
            }
        } else {
            // Si le navigateur ne supporte pas les notifications, désactiver l'option
            browserNotificationsCheckbox.disabled = true;
            browserNotificationsCheckbox.parentNode.style.opacity = '0.5';
            
            // Ajouter un message d'information
            const infoMessage = document.createElement('div');
            infoMessage.className = 'alert info';
            infoMessage.innerHTML = 'Votre navigateur ne supporte pas les notifications.';
            browserNotificationsCheckbox.parentNode.parentNode.insertBefore(infoMessage, browserNotificationsCheckbox.parentNode.nextSibling);
        }
    }
});
</script>

<?php
// Inclure le pied de page
include 'templates/footer.php';
?>