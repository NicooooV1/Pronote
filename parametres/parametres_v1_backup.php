<?php
/**
 * M17 – Paramètres utilisateur — Page principale
 */
$pageTitle = 'Paramètres';
require_once __DIR__ . '/includes/header.php';

$userId   = getUserId();
$userType = getUserRole();
$settings = $settingsService->getSettings($userId, $userType);
$profile  = $settingsService->getProfile($userId, $userType);

$success = '';
$error   = '';
$section = $_GET['section'] ?? 'profil';

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Jeton de sécurité invalide. Veuillez recharger la page.';
    } else {
    $action = $_POST['action'] ?? '';

    if ($action === 'preferences') {
        $settingsService->save($userId, $userType, $_POST);
        $settings = $settingsService->getSettings($userId, $userType);
        $success = 'Préférences enregistrées.';
    } elseif ($action === 'avatar') {
        if (!empty($_FILES['avatar']['name'])) {
            try {
                $settingsService->uploadAvatar($userId, $userType, $_FILES['avatar']);
                $settings = $settingsService->getSettings($userId, $userType);
                $success = 'Photo de profil mise à jour.';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'password') {
        $ancien  = $_POST['ancien'] ?? '';
        $nouveau = $_POST['nouveau'] ?? '';
        $confirm = $_POST['confirmer'] ?? '';
        if ($nouveau !== $confirm) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (strlen($nouveau) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif (!$settingsService->changerMotDePasse($userId, $userType, $ancien, $nouveau)) {
            $error = 'Ancien mot de passe incorrect.';
        } else {
            $success = 'Mot de passe modifié avec succès.';
        }
    }
    }
}

$themes = SettingsService::themes();
$fontSizes = SettingsService::fontSizes();
$profilLabels = ['administrateur'=>'Administrateur','professeur'=>'Professeur','eleve'=>'Élève','parent'=>'Parent','vie_scolaire'=>'Vie scolaire'];
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-cog"></i> Paramètres</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="settings-layout">
        <!-- Sidebar de navigation interne -->
        <div class="settings-nav">
            <a href="?section=profil" class="settings-nav-item <?= $section === 'profil' ? 'active' : '' ?>"><i class="fas fa-user"></i> Profil</a>
            <a href="?section=preferences" class="settings-nav-item <?= $section === 'preferences' ? 'active' : '' ?>"><i class="fas fa-palette"></i> Préférences</a>
            <a href="?section=notifications" class="settings-nav-item <?= $section === 'notifications' ? 'active' : '' ?>"><i class="fas fa-bell"></i> Notifications</a>
            <a href="?section=securite" class="settings-nav-item <?= $section === 'securite' ? 'active' : '' ?>"><i class="fas fa-lock"></i> Sécurité</a>
        </div>

        <div class="settings-content">
            <?php if ($section === 'profil'): ?>
            <!-- === PROFIL === -->
            <div class="card">
                <div class="card-header"><h2>Mon profil</h2></div>
                <div class="card-body">
                    <div class="profile-header-settings">
                        <div class="profile-avatar-lg">
                            <?php if (!empty($settings['avatar_chemin'])): ?>
                                <img src="../<?= htmlspecialchars($settings['avatar_chemin']) ?>" alt="Avatar">
                            <?php else: ?>
                                <?= getUserInitials() ?>
                            <?php endif; ?>
                        </div>
                        <div class="profile-info-lg">
                            <h3><?= htmlspecialchars(getUserFullName()) ?></h3>
                            <span class="badge badge-primary"><?= $profilLabels[$userType] ?? ucfirst($userType) ?></span>
                            <?php if (!empty($profile['email'])): ?>
                                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($profile['email']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form method="post" enctype="multipart/form-data" class="mt-1">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="avatar">
                        <div class="form-group">
                            <label class="form-label">Changer la photo de profil</label>
                            <input type="file" name="avatar" accept="image/*" class="form-control">
                            <small class="form-text">JPG, PNG, GIF ou WebP. Max 2 Mo.</small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> Envoyer</button>
                    </form>

                    <hr>
                    <div class="form-group">
                        <label class="form-label">Bio</label>
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="preferences">
                            <input type="hidden" name="theme" value="<?= htmlspecialchars($settings['theme']) ?>">
                            <input type="hidden" name="langue" value="<?= htmlspecialchars($settings['langue']) ?>">
                            <input type="hidden" name="taille_police" value="<?= htmlspecialchars($settings['taille_police']) ?>">
                            <?php if ($settings['notifications_email']): ?><input type="hidden" name="notifications_email" value="1"><?php endif; ?>
                            <?php if ($settings['notifications_web']): ?><input type="hidden" name="notifications_web" value="1"><?php endif; ?>
                            <textarea name="bio" class="form-control" rows="3" placeholder="Décrivez-vous en quelques mots..."><?= htmlspecialchars($settings['bio'] ?? '') ?></textarea>
                            <button type="submit" class="btn btn-primary btn-sm mt-1"><i class="fas fa-save"></i> Enregistrer</button>
                        </form>
                    </div>
                </div>
            </div>

            <?php elseif ($section === 'preferences'): ?>
            <!-- === PRÉFÉRENCES === -->
            <div class="card">
                <div class="card-header"><h2>Préférences d'affichage</h2></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="preferences">
                        <input type="hidden" name="bio" value="<?= htmlspecialchars($settings['bio'] ?? '') ?>">
                        <?php if ($settings['notifications_email']): ?><input type="hidden" name="notifications_email" value="1"><?php endif; ?>
                        <?php if ($settings['notifications_web']): ?><input type="hidden" name="notifications_web" value="1"><?php endif; ?>

                        <div class="form-group">
                            <label class="form-label">Thème</label>
                            <div class="theme-selector">
                                <?php foreach ($themes as $k => $v): ?>
                                    <label class="theme-option <?= $settings['theme'] === $k ? 'selected' : '' ?>">
                                        <input type="radio" name="theme" value="<?= $k ?>" <?= $settings['theme'] === $k ? 'checked' : '' ?>>
                                        <i class="fas <?= $k === 'light' ? 'fa-sun' : ($k === 'dark' ? 'fa-moon' : 'fa-adjust') ?>"></i>
                                        <span><?= $v ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Taille de police</label>
                            <select name="taille_police" class="form-select">
                                <?php foreach ($fontSizes as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= $settings['taille_police'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="sidebar_collapsed" value="1" <?= $settings['sidebar_collapsed'] ? 'checked' : '' ?>>
                                Sidebar repliée par défaut
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                    </form>
                </div>
            </div>

            <?php elseif ($section === 'notifications'): ?>
            <!-- === NOTIFICATIONS === -->
            <div class="card">
                <div class="card-header"><h2>Notifications</h2></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="preferences">
                        <input type="hidden" name="theme" value="<?= htmlspecialchars($settings['theme']) ?>">
                        <input type="hidden" name="langue" value="<?= htmlspecialchars($settings['langue']) ?>">
                        <input type="hidden" name="taille_police" value="<?= htmlspecialchars($settings['taille_police']) ?>">
                        <input type="hidden" name="bio" value="<?= htmlspecialchars($settings['bio'] ?? '') ?>">

                        <div class="notif-options">
                            <div class="notif-option">
                                <div class="notif-info">
                                    <i class="fas fa-envelope"></i>
                                    <div>
                                        <strong>Notifications par email</strong>
                                        <p>Recevez les alertes importantes par email</p>
                                    </div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="notifications_email" value="1" <?= $settings['notifications_email'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="notif-option">
                                <div class="notif-info">
                                    <i class="fas fa-bell"></i>
                                    <div>
                                        <strong>Notifications web</strong>
                                        <p>Notifications en temps réel dans l'application</p>
                                    </div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="notifications_web" value="1" <?= $settings['notifications_web'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-1"><i class="fas fa-save"></i> Enregistrer</button>
                    </form>
                </div>
            </div>

            <?php elseif ($section === 'securite'): ?>
            <!-- === SÉCURITÉ === -->
            <div class="card" id="securite">
                <div class="card-header"><h2>Changer le mot de passe</h2></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="password">

                        <div class="form-group">
                            <label class="form-label">Mot de passe actuel</label>
                            <input type="password" name="ancien" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nouveau mot de passe</label>
                            <input type="password" name="nouveau" class="form-control" required minlength="8">
                            <small class="form-text">Au moins 8 caractères</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirmer le nouveau mot de passe</label>
                            <input type="password" name="confirmer" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-lock"></i> Modifier le mot de passe</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
