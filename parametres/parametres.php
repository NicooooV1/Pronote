<?php
/**
 * M17 – Paramètres utilisateur — Page principale (v2)
 * 
 * Sections disponibles :
 *   - profil       : Photo, bio, informations
 *   - preferences  : Thème (dark/light/auto), taille police, sidebar
 *   - notifications: Email & web
 *   - securite     : Mot de passe + 2FA (TOTP)
 *   - accueil      : Personnalisation du tableau de bord
 *   - confidentialite : Déconnexion sessions, export données
 */
$pageTitle = 'Paramètres';
require_once __DIR__ . '/includes/header.php';

// ─── Services ────────────────────────────────────────────────────
$userId   = getUserId();
$userType = getUserRole();
$settings = $settingsService->getSettings($userId, $userType);
$profile  = $settingsService->getProfile($userId, $userType);

// 2FA service
require_once dirname(__DIR__) . '/API/Services/TwoFactorService.php';
$twoFactor = new \API\Services\TwoFactorService(getPDO());
$twoFAEnabled = $twoFactor->isEnabled($userId, $userType);

// Modules service for accueil config
$moduleService = null;
try { $moduleService = app('modules'); } catch (Exception $e) {}

$success = '';
$error   = '';
$section = $_GET['section'] ?? 'profil';

// ─── Traitement POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();
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
        } elseif (!preg_match('/[A-Z]/', $nouveau) || !preg_match('/[a-z]/', $nouveau) || !preg_match('/[0-9]/', $nouveau) || !preg_match('/[^A-Za-z0-9]/', $nouveau)) {
            $error = 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.';
        } elseif (!$settingsService->changerMotDePasse($userId, $userType, $ancien, $nouveau)) {
            $error = 'Ancien mot de passe incorrect.';
        } else {
            $success = 'Mot de passe modifié avec succès.';
        }
    } elseif ($action === '2fa_setup') {
        // Step 1: User confirms setup with a code
        $secret = $_POST['secret'] ?? '';
        $code   = $_POST['code'] ?? '';
        if ($twoFactor->enable($userId, $userType, $secret, $code)) {
            $twoFAEnabled = true;
            $success = 'Authentification à deux facteurs activée avec succès.';
        } else {
            $error = 'Code incorrect. Veuillez réessayer avec un code valide de votre application.';
            $section = 'securite';
        }
    } elseif ($action === '2fa_disable') {
        $code = $_POST['code'] ?? '';
        if ($twoFactor->validateLogin($userId, $userType, $code)) {
            $twoFactor->disable($userId, $userType);
            $twoFAEnabled = false;
            $success = 'Authentification à deux facteurs désactivée.';
        } else {
            $error = 'Code incorrect. La 2FA n\'a pas été désactivée.';
            $section = 'securite';
        }
    } elseif ($action === 'accueil_config') {
        $widgetConfig = $_POST['widgets'] ?? [];
        $settingsService->saveAccueilConfig($userId, $userType, $widgetConfig);
        $success = 'Configuration du tableau de bord enregistrée.';
    }
}

// ─── Data for templates ──────────────────────────────────────────
$themes = SettingsService::themes();
$fontSizes = SettingsService::fontSizes();
$profilLabels = ['administrateur'=>'Administrateur','professeur'=>'Professeur','eleve'=>'Élève','parent'=>'Parent','vie_scolaire'=>'Vie scolaire'];

// 2FA setup data (only needed if section is securite and 2FA not yet enabled)
$twoFA_secret = null;
$twoFA_qrUrl = null;
if ($section === 'securite' && !$twoFAEnabled) {
    $twoFA_secret = $twoFactor->generateSecret();
    $accountName = ($profile['prenom'] ?? '') . ' ' . ($profile['nom'] ?? '') . ' (' . $userType . ')';
    $otpUri = $twoFactor->getOtpauthUri($twoFA_secret, trim($accountName));
    $twoFA_qrUrl = $twoFactor->getQrCodeUrl($otpUri);
}

// Accueil widget list
$accueilConfig = $settingsService->getAccueilConfig($userId, $userType);
$availableWidgets = [
    'evenements' => ['label' => 'Événements à venir',   'icon' => 'fas fa-calendar-alt'],
    'devoirs'    => ['label' => 'Devoirs à faire',       'icon' => 'fas fa-tasks'],
    'notes'      => ['label' => 'Dernières notes',       'icon' => 'fas fa-chart-bar'],
    'enfants'    => ['label' => 'Mes enfants (parents)', 'icon' => 'fas fa-child'],
    'absences'   => ['label' => 'Absences du jour',      'icon' => 'fas fa-user-times'],
];
// Only show relevant widgets per role
$roleWidgets = match ($userType) {
    'eleve'         => ['evenements', 'devoirs', 'notes'],
    'parent'        => ['enfants', 'evenements', 'devoirs'],
    'professeur'    => ['notes', 'devoirs', 'evenements'],
    'vie_scolaire'  => ['absences', 'evenements', 'devoirs'],
    'administrateur'=> ['evenements', 'devoirs', 'notes', 'absences'],
    default         => ['evenements', 'devoirs', 'notes'],
};
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
            <a href="?section=preferences" class="settings-nav-item <?= $section === 'preferences' ? 'active' : '' ?>"><i class="fas fa-palette"></i> Apparence</a>
            <a href="?section=accueil" class="settings-nav-item <?= $section === 'accueil' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> Tableau de bord</a>
            <a href="?section=notifications" class="settings-nav-item <?= $section === 'notifications' ? 'active' : '' ?>"><i class="fas fa-bell"></i> Notifications</a>
            <a href="?section=securite" class="settings-nav-item <?= $section === 'securite' ? 'active' : '' ?>"><i class="fas fa-lock"></i> Sécurité</a>
            <a href="?section=confidentialite" class="settings-nav-item <?= $section === 'confidentialite' ? 'active' : '' ?>"><i class="fas fa-shield-alt"></i> Confidentialité</a>
        </div>

        <div class="settings-content">

            <?php if ($section === 'profil'): ?>
            <!-- ═══════ PROFIL ═══════ -->
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
                            <?php if (!empty($profile['mail'])): ?>
                                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($profile['mail']) ?></p>
                            <?php endif; ?>
                            <?php if ($twoFAEnabled): ?>
                                <p style="color:var(--success-color);font-size:.85em;margin-top:4px"><i class="fas fa-shield-alt"></i> 2FA activée</p>
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
            <!-- ═══════ APPARENCE ═══════ -->
            <div class="card">
                <div class="card-header"><h2>Apparence & Affichage</h2></div>
                <div class="card-body">
                    <form method="post" id="preferencesForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="preferences">
                        <input type="hidden" name="bio" value="<?= htmlspecialchars($settings['bio'] ?? '') ?>">
                        <?php if ($settings['notifications_email']): ?><input type="hidden" name="notifications_email" value="1"><?php endif; ?>
                        <?php if ($settings['notifications_web']): ?><input type="hidden" name="notifications_web" value="1"><?php endif; ?>

                        <div class="form-group">
                            <label class="form-label">Thème de couleur</label>
                            <p class="form-text" style="margin-bottom:8px">Le thème sombre réduit la fatigue oculaire. Le mode automatique suit les préférences de votre système.</p>
                            <div class="theme-selector">
                                <?php foreach ($themes as $k => $v): ?>
                                    <label class="theme-option <?= $settings['theme'] === $k ? 'selected' : '' ?>" onclick="previewTheme('<?= $k ?>')">
                                        <input type="radio" name="theme" value="<?= $k ?>" <?= $settings['theme'] === $k ? 'checked' : '' ?>>
                                        <i class="fas <?= $k === 'light' ? 'fa-sun' : ($k === 'dark' ? 'fa-moon' : 'fa-adjust') ?>"></i>
                                        <span><?= $v ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Taille de police</label>
                            <select name="taille_police" class="form-select" onchange="previewFontSize(this.value)">
                                <?php foreach ($fontSizes as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= $settings['taille_police'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Langue</label>
                            <select name="langue" class="form-select">
                                <option value="fr" <?= ($settings['langue'] ?? 'fr') === 'fr' ? 'selected' : '' ?>>Français</option>
                                <option value="en" <?= ($settings['langue'] ?? 'fr') === 'en' ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="sidebar_collapsed" value="1" <?= ($settings['sidebar_collapsed'] ?? 0) ? 'checked' : '' ?>>
                                Sidebar repliée par défaut
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer les préférences</button>
                    </form>
                </div>
            </div>

            <script>
            function previewTheme(theme) {
                if (theme === 'auto') {
                    var dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
                } else {
                    document.documentElement.setAttribute('data-theme', theme);
                }
                document.documentElement.setAttribute('data-theme-pref', theme);
                // Update sidebar icons
                var d = document.getElementById('themeIconDark');
                var l = document.getElementById('themeIconLight');
                var a = document.getElementById('themeIconAuto');
                if(d) d.style.display = theme==='light'?'inline':'none';
                if(l) l.style.display = theme==='dark'?'inline':'none';
                if(a) a.style.display = theme==='auto'?'inline':'none';
            }
            function previewFontSize(size) {
                var sizes = {small:'14px', normal:'16px', large:'18px', xlarge:'20px'};
                document.documentElement.style.fontSize = sizes[size] || '16px';
            }
            </script>

            <?php elseif ($section === 'accueil'): ?>
            <!-- ═══════ TABLEAU DE BORD ═══════ -->
            <div class="card">
                <div class="card-header"><h2>Personnaliser le tableau de bord</h2></div>
                <div class="card-body">
                    <p class="form-text" style="margin-bottom:16px">Choisissez les widgets à afficher sur votre page d'accueil. Décochez ceux que vous ne souhaitez pas voir.</p>

                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="accueil_config">

                        <div class="widget-config-list">
                            <?php foreach ($roleWidgets as $wKey): ?>
                            <?php $w = $availableWidgets[$wKey] ?? null; if (!$w) continue; ?>
                            <div class="notif-option">
                                <div class="notif-info">
                                    <i class="<?= $w['icon'] ?>" style="font-size:1.3em;color:var(--primary-color)"></i>
                                    <div>
                                        <strong><?= htmlspecialchars($w['label']) ?></strong>
                                        <p>Afficher ce widget sur le tableau de bord</p>
                                    </div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="widgets[]" value="<?= $wKey ?>"
                                        <?= (empty($accueilConfig) || in_array($wKey, $accueilConfig)) ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn btn-primary mt-1"><i class="fas fa-save"></i> Enregistrer</button>
                    </form>
                </div>
            </div>

            <!-- Module visibility info (read-only for non-admins) -->
            <?php if ($moduleService): ?>
            <div class="card" style="margin-top:16px">
                <div class="card-header"><h2>Modules disponibles</h2></div>
                <div class="card-body">
                    <p class="form-text" style="margin-bottom:12px">Modules actuellement activés pour votre profil. Contactez l'administrateur pour modifier la visibilité.</p>
                    <?php
                    $sidebarMods = $moduleService->getForSidebar($userType);
                    $catMeta = ModuleService::categoryMeta();
                    ?>
                    <?php foreach ($sidebarMods as $catKey => $mods): ?>
                    <div style="margin-bottom:12px">
                        <strong style="font-size:.85em;color:var(--text-muted)"><?= htmlspecialchars($catMeta[$catKey]['label'] ?? ucfirst($catKey)) ?></strong>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px">
                            <?php foreach ($mods as $m): ?>
                            <span style="background:var(--pastel-agenda,#e0f2fe);padding:4px 10px;border-radius:16px;font-size:.82em;font-weight:500;color:var(--primary-color)">
                                <i class="<?= htmlspecialchars($m['icon']) ?>"></i> <?= htmlspecialchars($m['label']) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php elseif ($section === 'notifications'): ?>
            <!-- ═══════ NOTIFICATIONS ═══════ -->
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

                            <div class="notif-option">
                                <div class="notif-info">
                                    <i class="fas fa-comment-dots"></i>
                                    <div>
                                        <strong>Nouveaux messages</strong>
                                        <p>Notification à chaque nouveau message reçu</p>
                                    </div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="notif_messages" value="1" <?= ($settings['notif_messages'] ?? 1) ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="notif-option">
                                <div class="notif-info">
                                    <i class="fas fa-chart-bar"></i>
                                    <div>
                                        <strong>Nouvelles notes</strong>
                                        <p>Notification à chaque nouvelle note publiée</p>
                                    </div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="notif_notes" value="1" <?= ($settings['notif_notes'] ?? 1) ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-1"><i class="fas fa-save"></i> Enregistrer</button>
                    </form>
                </div>
            </div>

            <?php elseif ($section === 'securite'): ?>
            <!-- ═══════ SÉCURITÉ ═══════ -->

            <!-- Mot de passe -->
            <div class="card">
                <div class="card-header"><h2><i class="fas fa-key"></i> Changer le mot de passe</h2></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="password">

                        <div class="form-group">
                            <label class="form-label">Mot de passe actuel</label>
                            <input type="password" name="ancien" class="form-control" required autocomplete="current-password">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nouveau mot de passe</label>
                            <input type="password" name="nouveau" class="form-control" required minlength="8" autocomplete="new-password" id="newPwd">
                            <small class="form-text">Min. 8 caractères, 1 majuscule, 1 minuscule, 1 chiffre, 1 caractère spécial</small>
                            <div id="pwdStrength" style="margin-top:6px;height:4px;border-radius:2px;background:#e2e8f0;overflow:hidden">
                                <div id="pwdStrengthBar" style="height:100%;width:0;transition:width .3s,background .3s"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirmer le nouveau mot de passe</label>
                            <input type="password" name="confirmer" class="form-control" required autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-lock"></i> Modifier le mot de passe</button>
                    </form>
                </div>
            </div>

            <!-- 2FA -->
            <div class="card" style="margin-top:16px">
                <div class="card-header">
                    <h2><i class="fas fa-shield-alt"></i> Authentification à deux facteurs (2FA)</h2>
                </div>
                <div class="card-body">

                    <?php if ($twoFAEnabled): ?>
                    <!-- 2FA is ENABLED -->
                    <div class="twofa-status twofa-enabled">
                        <div class="twofa-status-icon"><i class="fas fa-check-circle"></i></div>
                        <div>
                            <strong>2FA activée</strong>
                            <p>Votre compte est protégé par l'authentification à deux facteurs.</p>
                        </div>
                    </div>

                    <form method="post" style="margin-top:16px">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="2fa_disable">
                        <p class="form-text">Pour désactiver la 2FA, saisissez un code de votre application d'authentification :</p>
                        <div class="form-group" style="max-width:280px">
                            <input type="text" name="code" class="form-control" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required
                                   inputmode="numeric" autocomplete="one-time-code" style="font-size:1.4em;letter-spacing:8px;text-align:center;font-weight:700">
                        </div>
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times-circle"></i> Désactiver la 2FA</button>
                    </form>

                    <?php else: ?>
                    <!-- 2FA is DISABLED — Setup flow -->
                    <div class="twofa-status twofa-disabled">
                        <div class="twofa-status-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div>
                            <strong>2FA non activée</strong>
                            <p>Ajoutez une couche de sécurité supplémentaire à votre compte.</p>
                        </div>
                    </div>

                    <div class="twofa-setup" style="margin-top:20px">
                        <h3 style="font-size:1em;margin-bottom:12px">Configuration en 3 étapes :</h3>

                        <div class="twofa-steps">
                            <div class="twofa-step">
                                <div class="twofa-step-num">1</div>
                                <div>
                                    <strong>Installer une application</strong>
                                    <p>Téléchargez Google Authenticator, Authy ou Microsoft Authenticator sur votre téléphone.</p>
                                </div>
                            </div>

                            <div class="twofa-step">
                                <div class="twofa-step-num">2</div>
                                <div>
                                    <strong>Scanner le QR code</strong>
                                    <p>Scannez ce QR code avec votre application :</p>
                                    <div class="twofa-qr">
                                        <img src="<?= htmlspecialchars($twoFA_qrUrl) ?>" alt="QR Code 2FA" width="200" height="200">
                                    </div>
                                    <details style="margin-top:8px">
                                        <summary style="cursor:pointer;font-size:.85em;color:var(--text-muted)">Saisie manuelle</summary>
                                        <code style="display:block;margin-top:4px;padding:8px 12px;background:var(--border-color,#f0f0f0);border-radius:6px;font-size:.9em;word-break:break-all;letter-spacing:2px">
                                            <?= htmlspecialchars($twoFA_secret) ?>
                                        </code>
                                    </details>
                                </div>
                            </div>

                            <div class="twofa-step">
                                <div class="twofa-step-num">3</div>
                                <div>
                                    <strong>Confirmer avec un code</strong>
                                    <p>Entrez le code à 6 chiffres affiché dans votre application :</p>
                                    <form method="post" style="margin-top:8px">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="2fa_setup">
                                        <input type="hidden" name="secret" value="<?= htmlspecialchars($twoFA_secret) ?>">
                                        <div class="form-group" style="max-width:280px">
                                            <input type="text" name="code" class="form-control" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required
                                                   inputmode="numeric" autocomplete="one-time-code" style="font-size:1.4em;letter-spacing:8px;text-align:center;font-weight:700">
                                        </div>
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Activer la 2FA</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Password strength meter script -->
            <script>
            (function() {
                var pwd = document.getElementById('newPwd');
                var bar = document.getElementById('pwdStrengthBar');
                if (!pwd || !bar) return;
                pwd.addEventListener('input', function() {
                    var v = pwd.value, s = 0;
                    if (v.length >= 8) s++;
                    if (v.length >= 12) s++;
                    if (/[A-Z]/.test(v)) s++;
                    if (/[a-z]/.test(v)) s++;
                    if (/[0-9]/.test(v)) s++;
                    if (/[^A-Za-z0-9]/.test(v)) s++;
                    var pct = Math.min(s / 6 * 100, 100);
                    bar.style.width = pct + '%';
                    bar.style.background = pct < 40 ? '#ef4444' : pct < 70 ? '#f59e0b' : '#22c55e';
                });
            })();
            </script>

            <?php elseif ($section === 'confidentialite'): ?>
            <!-- ═══════ CONFIDENTIALITÉ ═══════ -->
            <div class="card">
                <div class="card-header"><h2><i class="fas fa-shield-alt"></i> Confidentialité & Données</h2></div>
                <div class="card-body">
                    <div class="notif-options">
                        <div class="notif-option">
                            <div class="notif-info">
                                <i class="fas fa-sign-out-alt" style="color:#ef4444"></i>
                                <div>
                                    <strong>Déconnecter toutes les sessions</strong>
                                    <p>Ferme toutes vos sessions actives sauf celle-ci</p>
                                </div>
                            </div>
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="logout_all">
                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Déconnecter</button>
                            </form>
                        </div>

                        <div class="notif-option">
                            <div class="notif-info">
                                <i class="fas fa-download" style="color:var(--primary-color)"></i>
                                <div>
                                    <strong>Exporter mes données</strong>
                                    <p>Téléchargez une copie de vos données personnelles (RGPD)</p>
                                </div>
                            </div>
                            <a href="../rgpd/export.php" class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Exporter</a>
                        </div>

                        <div class="notif-option">
                            <div class="notif-info">
                                <i class="fas fa-trash-alt" style="color:#ef4444"></i>
                                <div>
                                    <strong>Supprimer mes préférences</strong>
                                    <p>Réinitialise tous vos paramètres aux valeurs par défaut</p>
                                </div>
                            </div>
                            <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir réinitialiser tous vos paramètres ?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="reset_settings">
                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-undo"></i> Réinitialiser</button>
                            </form>
                        </div>
                    </div>

                    <hr>
                    <div style="padding:10px;background:var(--border-color,#f8f9fa);border-radius:8px;font-size:.85em;color:var(--text-muted)">
                        <i class="fas fa-info-circle"></i>
                        Conformément au RGPD, vous disposez d'un droit d'accès, de rectification et de suppression de vos données.
                        Pour toute demande, contactez l'administrateur de l'établissement.
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* 2FA specific styles */
.twofa-status {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 18px; border-radius: 10px;
}
.twofa-enabled { background: #f0fdf4; border: 1px solid #86efac; }
.twofa-enabled .twofa-status-icon { color: #22c55e; font-size: 1.6em; }
.twofa-enabled strong { color: #166534; }
.twofa-enabled p { color: #4ade80; font-size: .85em; margin: 2px 0 0; }
.twofa-disabled { background: #fefce8; border: 1px solid #fde047; }
.twofa-disabled .twofa-status-icon { color: #f59e0b; font-size: 1.6em; }
.twofa-disabled strong { color: #92400e; }
.twofa-disabled p { color: #ca8a04; font-size: .85em; margin: 2px 0 0; }
.twofa-steps { display: flex; flex-direction: column; gap: 20px; }
.twofa-step { display: flex; gap: 14px; align-items: flex-start; }
.twofa-step-num {
    width: 30px; height: 30px; flex-shrink: 0;
    background: var(--primary-color, #0f4c81); color: white;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .9em;
}
.twofa-step strong { display: block; margin-bottom: 4px; }
.twofa-step p { font-size: .88em; color: var(--text-muted, #666); margin: 0; }
.twofa-qr {
    display: inline-block; padding: 12px; background: white;
    border: 2px solid var(--border-color, #e2e8f0); border-radius: 12px; margin-top: 8px;
}
.btn-danger {
    background: #ef4444; color: white; border: none; padding: 8px 16px;
    border-radius: 6px; font-weight: 600; cursor: pointer; font-size: .88em;
}
.btn-danger:hover { background: #dc2626; }
.widget-config-list { display: flex; flex-direction: column; gap: .75rem; }

/* Dark mode overrides for 2FA */
[data-theme="dark"] .twofa-enabled { background: #052e16; border-color: #166534; }
[data-theme="dark"] .twofa-enabled strong { color: #86efac; }
[data-theme="dark"] .twofa-enabled p { color: #4ade80; }
[data-theme="dark"] .twofa-disabled { background: #451a03; border-color: #92400e; }
[data-theme="dark"] .twofa-disabled strong { color: #fcd34d; }
[data-theme="dark"] .twofa-disabled p { color: #ca8a04; }
[data-theme="dark"] .twofa-qr { background: white; } /* QR must stay white */
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
