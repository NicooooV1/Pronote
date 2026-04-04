# Fronote — Documentation Développeur

![PHP 8+](https://img.shields.io/badge/PHP-8%2B-blue) ![MySQL 5.7+](https://img.shields.io/badge/MySQL-5.7%2B-orange) ![Version](https://img.shields.io/badge/version-2.0.0-green) ![Licence](https://img.shields.io/badge/licence-MIT-lightgrey)

> **Deux documents disponibles :**
> - **README.md** (ce fichier) — Documentation technique pour les développeurs
> - **[INSTALL.md](INSTALL.md)** — Guide d'installation pour les utilisateurs finaux (établissements scolaires)

Fronote est un système de gestion scolaire en **PHP vanilla** (sans framework) : 47 modules, 140+ tables SQL, architecture IoC/PSR-4, API centralisée, WebSocket temps réel, design system tokens + thèmes (classic/glass).

---

## Table des matières

- [Architecture](#architecture)
- [Fonctionnalités — Modules](#fonctionnalités--modules)
- [Environnement de développement](#environnement-de-développement)
- [API centralisée](#api-centralisée)
- [Assets & Templates](#assets--templates)
- [Guide — Créer un module](#guide--créer-un-module)
- [Personnalisation](#personnalisation)
- [Sécurité](#sécurité)
- [Base de données](#base-de-données)
- [WebSocket](#websocket)
- [Configuration .env](#configuration-env)
- [Déploiement client](#déploiement-client)
- [Maintenance & Mises à jour](#maintenance--mises-à-jour)
- [Rôles utilisateurs](#rôles-utilisateurs)
- [Permissions par module](#permissions-par-module)
- [Accès technicien](#accès-technicien)
- [Import / Export](#import--export)
- [Design System](#design-system)
- [Dépannage développeur](#dépannage-développeur)

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Navigateur (client)                   │
└────────────┬──────────────────────┬─────────────────────┘
             │ HTTP/HTTPS           │ WebSocket (Socket.IO)
             ▼                     ▼
┌────────────────────┐   ┌──────────────────────────┐
│  Pages PHP (vues)  │   │  websocket-server/        │
│  + templates/      │   │  server.js (Node.js)      │
└────────┬───────────┘   └──────────────────────────┘
         │ require API/bootstrap.php
         ▼
┌─────────────────────────────────────────────────────────┐
│                    API/ (couche métier)                  │
│                                                         │
│  ┌──────────┐ ┌────────────┐ ┌────────┐ ┌──────────┐   │
│  │AuthManager│ │RateLimiter │ │  CSRF  │ │Container │   │
│  └──────────┘ └────────────┘ └────────┘ └──────────┘   │
│                                                         │
│  ┌──────────────────────────────────────────────────┐   │
│  │  Services: FileUploadService, ModuleService,     │   │
│  │  UserService, DashboardService, getPDO()…        │   │
│  └──────────────────────────────────────────────────┘   │
│                                                         │
│  ┌──────────────────────────────────────────────────┐   │
│  │  Facades: CSRF::, Auth::, DB::, Log::            │   │
│  └──────────────────────────────────────────────────┘   │
└────────────────────────┬────────────────────────────────┘
                         │ PDO (ERRMODE_EXCEPTION, utf8mb4)
                         ▼
                ┌──────────────────┐
                │   MySQL/MariaDB  │
                │  (pronote.sql)   │
                │   240+ tables    │
                └──────────────────┘
```

### Principes

| Principe | Implémentation |
|----------|---------------|
| **PHP vanilla** | Aucun framework — PDO, sessions natives, PSR-4 manuel |
| **IoC Container** | `API/Core/Container.php` — injection de dépendances, providers |
| **PSR-4 autoloading** | Namespace `API\` chargé via `API/bootstrap.php` |
| **Facades** | `API/Core/Facades/` — CSRF, Auth, DB, Log |
| **Templates partagés** | `templates/` — header (nonce CSP, CSRF), sidebar, topbar, footer |
| **Services centralisés** | Upload, auth, rate limiting, modules, WebSocket |

### Cycle de requête

```
Requête HTTP
  → Module PHP (ex: notes/notes.php)
    → require API/core.php          (charge bootstrap + helpers)
      → API/bootstrap.php           (autoloader + container IoC + session sécurisée)
    → requireAuth()                 (vérifie session, redirige si non connecté)
    → requireRole('professeur')     (optionnel — contrôle du rôle)
    → [logique métier via services PDO]
    → include templates/shared_header.php   (génère nonce CSP, token CSRF, thème DB)
    → include templates/shared_sidebar.php  (modules actifs filtrés par rôle)
    → include templates/shared_topbar.php   (avatar, notifications)
    → [HTML spécifique au module]
    → include templates/shared_footer.php   (scripts JS globaux, fermeture HTML)
```

---

## Fonctionnalités — Modules

### Navigation (core — non désactivables)

| Clé | Label | Description |
|-----|-------|-------------|
| `accueil` | Accueil | Tableau de bord avec widgets personnalisables (drag & drop) |
| `notifications` | Notifications | Centre de notifications multi-canal |
| `parametres` | Paramètres | Thème, police, avatar, bannière, citation, réseaux sociaux |

### Communication (désactivable — messagerie off par défaut)

| Clé | Label | Description |
|-----|-------|-------------|
| `messagerie` | Messagerie | Conversations, annonces, réactions, WebSocket — **désactivée par défaut**, activable par l'administrateur |

### Scolaire

| Clé | Label | Description |
|-----|-------|-------------|
| `notes` | Notes | Saisie/consultation, moyennes, export |
| `agenda` | Agenda | Calendrier, événements récurrents (rrule) |
| `cahierdetextes` | Cahier de textes | Devoirs, pièces jointes, suivi statuts |
| `emploi_du_temps` | Emploi du temps | Grille hebdomadaire, créneaux configurables |
| `bulletins` | Bulletins | Bulletins scolaires, export PDF |
| `competences` | Compétences | Évaluation par compétences (socle commun) |
| `devoirs` | Devoirs en ligne | Remise de rendus, date limite, suivi |
| `examens` | Examens | Organisation des épreuves |

### Vie scolaire

| Clé | Label | Description |
|-----|-------|-------------|
| `absences` | Absences | Suivi absences/retards, justificatifs |
| `appel` | Appel | Appel numérique en classe |
| `discipline` | Discipline | Incidents, sanctions, retenues |
| `vie_scolaire` | Vie scolaire | Dashboard vie scolaire |
| `reporting` | Reporting | Rapports et statistiques |
| `signalements` | Signalements | Signalements anonymes (harcèlement…) |
| `besoins` | Besoins particuliers | Suivi PAP, PPS |

### Communication

| Clé | Label | Description |
|-----|-------|-------------|
| `annonces` | Annonces | Annonces ciblées et sondages |
| `reunions` | Réunions | Réunions parents-profs, créneaux |
| `documents` | Documents | Documents administratifs |

### Établissement

| Clé | Label | Description |
|-----|-------|-------------|
| `trombinoscope` | Trombinoscope | Annuaire avec photos |
| `bibliotheque` | Bibliothèque | Catalogue et emprunts |
| `clubs` | Clubs | Clubs et activités parascolaires |
| `orientation` | Orientation | Fiches d'orientation et vœux |
| `inscriptions` | Inscriptions | Inscriptions en ligne |
| `infirmerie` | Infirmerie | Passages et fiches santé |
| `ressources` | Ressources | Ressources pédagogiques |
| `diplomes` | Diplômes | Gestion et délivrance |
| `vie_associative` | Vie associative | Associations (CRUD + export) |

### Logistique & Services

| Clé | Label | Description |
|-----|-------|-------------|
| `periscolaire` | Périscolaire | Cantine, garderie, activités |
| `cantine` | Cantine | Menus, réservations, pointage |
| `internat` | Internat | Chambres, affectations |
| `garderie` | Garderie | Accueil périscolaire |
| `stages` | Stages | Conventions de stage |
| `transports` | Transports | Lignes et inscriptions |
| `facturation` | Facturation | Factures et paiements |
| `salles` | Salles & Matériels | Réservation de salles |
| `personnel` | Gestion personnel | Absences et remplacements |

### Système

| Clé | Label | Description |
|-----|-------|-------------|
| `archivage` | Archivage | Archivage annuel |
| `rgpd` | RGPD & Audit | Conformité et journal d'audit |
| `support` | Aide & Support | FAQ et tickets |

---

## Environnement de développement

### Prérequis

| Outil | Version | Rôle |
|-------|---------|------|
| PHP | 8.0+ | Runtime principal |
| MySQL | 5.7+ / MariaDB 10.3+ | Base de données |
| Apache | 2.4+ (`mod_rewrite`) | Serveur web |
| Composer | 2.x | Dépendances PHP |
| Node.js | 16+ | Serveur WebSocket (optionnel) |
| Git | 2.x | Contrôle de version |

### Installation locale

```bash
git clone https://github.com/votre-org/fronote.git
cd fronote
composer install --optimize-autoloader
# Ouvrir http://localhost/fronote/install.php dans le navigateur
# L'assistant gère : création BDD, import SQL, configuration .env, compte admin
```

### Docker (développement rapide)

```yaml
# docker-compose.yml
version: '3.9'
services:
  app:
    image: php:8.2-apache
    volumes:
      - .:/var/www/html
    ports:
      - "8080:80"
    depends_on:
      - db

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: fronote
      MYSQL_USER: fronote_user
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: rootsecret
    volumes:
      - db_data:/var/lib/mysql

  websocket:
    image: node:18-alpine
    working_dir: /app
    volumes:
      - ./websocket-server:/app
    command: sh -c "npm install && node server.js"
    ports:
      - "3000:3000"

volumes:
  db_data:
```

```bash
docker compose up -d
# Accès : http://localhost:8080/install.php
```

---

## API centralisée

### Bootstrap

```php
require_once __DIR__ . '/../API/core.php';
// ou directement :
require_once __DIR__ . '/../API/bootstrap.php';
```

Charge l'autoloader PSR-4, initialise le conteneur IoC, démarre la session sécurisée, enregistre les providers.

### Endpoints REST

Tous les points d'entrée AJAX/REST centralisés sont dans `API/endpoints/` :

| Endpoint | Méthodes | Paramètres principaux | Réponse |
|----------|----------|----------------------|---------|
| `messagerie.php` | GET / POST / DELETE | `resource`, `action`, `id` | JSON |
| `agenda_persons.php` | GET | `visibility` | JSON `[{id, nom, prenom, type}]` |
| `notes_eleves.php` | GET | `classe` | JSON `[{id, nom, prenom}]` |

### Container IoC & Facades

```php
// Via container
$csrf    = app('csrf');      // API\Security\CSRF
$auth    = app('auth');      // API\Auth\AuthManager
$modules = app('modules');   // API\Services\ModuleService
$upload  = app('upload');    // API\Services\FileUploadService

// Via facades statiques
use API\Core\Facades\CSRF;
use API\Core\Facades\Auth;
use API\Core\Facades\DB;
use API\Core\Facades\Log;

$token = CSRF::generate();
$user  = Auth::user();
Log::info('Action effectuée', ['user' => $user['id']]);
```

### Services

| Service | Classe | Méthodes clés |
|---------|--------|---------------|
| Auth | `API\Auth\AuthManager` | `login()`, `logout()`, `user()`, `check()` |
| RBAC | `API\Security\RBAC` | `can()`, `canModule()`, `getModulePermissions()`, `setModulePermission()` |
| RateLimiter | `API\Security\RateLimiter` | `hit($key)`, `tooManyAttempts($key)`, `clear($key)` |
| CSRF | `API\Security\CSRF` | `generate()`, `validate($token)`, `field()`, `meta()` |
| FileUpload | `API\Services\FileUploadService` | `upload()`, `uploadMultiple()`, `serve()`, `delete()` |
| ModuleService | `API\Services\ModuleService` | `isEnabled()`, `getForSidebar()`, `updateConfig()`, `updateRolesAutorises()` |
| ProfileService | `API\Services\ProfileService` | `getProfile()`, `saveProfile()`, `uploadAvatar()`, `uploadBanner()` |
| ImportExportService | `ImportExportService` | `exportUsers()`, `importUsers()`, `exportConfig()`, `importConfig()` |
| Database | `API\Database\Database` | `getConnection()`, `table($name)` |

### FileUploadService

```php
$uploader = new \API\Services\FileUploadService('devoirs');
// Contextes disponibles : devoirs | messagerie | justificatifs

$results = $uploader->uploadMultiple($_FILES['fichiers']);
foreach ($results as $r) {
    if ($r['success']) {
        // $r['nom_original'], $r['chemin'], $r['type_mime'], $r['taille']
    }
}

$uploader->serve($relativePath, $originalName);  // Servir sécurisé
$uploader->delete($relativePath);

\API\Services\FileUploadService::formatBytes(1048576); // "1.00 MB"
```

| Contexte | Taille max | Fichiers max | Types |
|----------|-----------|-------------|-------|
| `messagerie` | 5 Mo | 10 | images, PDF, documents, archives |
| `devoirs` | 10 Mo | 5 | images, PDF, documents, archives |
| `justificatifs` | 5 Mo | 5 | images, PDF, documents |

### Helpers globaux

```php
requireAuth();                   // Redirige vers login si non authentifié
requireRole('professeur');       // Redirige si rôle insuffisant

$id   = getUserId();             // int — ID de l'utilisateur connecté
$role = getUserRole();           // 'administrateur'|'professeur'|'eleve'|'parent'|'personnel'
$name = getFullName();           // "Prénom Nom"
$pdo  = getPDO();                // \PDO — connexion singleton

logAudit('note.created', 'notes', $noteId, null, ['valeur' => 15]);
```

### ModuleService

```php
$modules = app('modules');

$modules->isEnabled('notes');
$modules->isVisibleForRole('absences', 'eleve');
$modules->getConfig('messagerie');           // array depuis config_json
$modules->updateConfig('notes', ['note_max' => 20]);
$modules->updateRolesAutorises('discipline', ['administrateur', 'professeur']);
$modules->getForSidebar('professeur');       // modules groupés par catégorie
```

---

## Assets & Templates

### Structure

```
assets/
│   css/
│   │   base.css                 ← Reset et fondations
│   │   tokens.css               ← Design tokens (couleurs, typo, spacing)
│   │   theme-classic.css        ← Thème principal (toujours chargé)
│   │   theme-glass.css          ← Surcouche glassmorphism (optionnel)
│   │   admin.css                ← Styles spécifiques admin
│   js/
│   │   pronote-theme.js         ← Gestion thème/dark mode/mobile
│   │   ws-global.js             ← Client WebSocket global

[module]/assets/                 ← CSS/JS spécifiques au module
    css/module.css
    js/module.js
```

**Règle :** si un fichier CSS/JS est utilisé sur plusieurs modules → `assets/` global. Si un seul module → `[module]/assets/`.

**Chargement CSS :** `base.css` → `tokens.css` → `theme-classic.css` → (optionnel) `theme-glass.css`. Le thème glass est une surcouche qui s'ajoute au thème classic, jamais chargé seul.

### Variables attendues par shared_header.php

| Variable | Type | Obligatoire | Description |
|----------|------|-------------|-------------|
| `$pageTitle` | string | Oui | Titre de la page (`<title>`) |
| `$rootPrefix` | string | Oui | Chemin relatif vers la racine (`'../'`, `'../../'`) |
| `$activePage` | string | Recommandé | Clé du module actif (colorie la sidebar) |
| `$extraCss` | array | Non | Chemins CSS supplémentaires |
| `$extraHeadHtml` | string | Non | HTML injecté dans `<head>` |
| `$headerExtraActions` | string | Non | Boutons supplémentaires dans la topbar |
| `$isAdmin` | bool | Non | Active le menu admin dans la sidebar |

Le header génère automatiquement : nonce CSP, token CSRF (via facade), thème depuis `user_settings`, config WebSocket JWT.

---

## Guide — Créer un module

### Structure type

```
mon_module/
├── mon_module.php      ← Page principale
├── assets/
│   ├── css/mon_module.css
│   └── js/mon_module.js
├── includes/
│   └── functions.php   ← Logique métier locale
└── api/                ← (optionnel) endpoints AJAX propres au module
    └── actions.php
```

### Page principale minimale

```php
<?php
// mon_module/mon_module.php
require_once __DIR__ . '/../API/core.php';

requireAuth();
// requireRole('professeur'); // décommenter si accès restreint

$pdo = getPDO();

// ─── Variables du template ────────────────────────────────
$pageTitle  = 'Mon Module';
$activePage = 'mon_module';
$rootPrefix = '../';
$extraCss   = ['assets/css/mon_module.css'];

include __DIR__ . '/../templates/shared_header.php';
include __DIR__ . '/../templates/shared_sidebar.php';
include __DIR__ . '/../templates/shared_topbar.php';
?>
<div class="main-content">
    <h1><?= htmlspecialchars($pageTitle) ?></h1>
    <!-- Contenu du module -->
</div>
<?php include __DIR__ . '/../templates/shared_footer.php'; ?>
```

### Enregistrer le module

**1. Dans `pronote.sql`** (ou via une requête SQL directe sur la base existante) :

```sql
INSERT INTO `modules_config`
  (`module_key`, `label`, `description`, `icon`, `category`, `enabled`, `sort_order`, `is_core`)
VALUES
  ('mon_module', 'Mon Module', 'Description courte', 'fas fa-star', 'scolaire', 1, 99, 0);
```

**2. Dans `API/Services/ModuleService.php`**, ajouter dans `$routeMap` :

```php
'mon_module' => 'mon_module/mon_module.php',
```

### Endpoint AJAX interne

```php
<?php
// mon_module/api/actions.php
require_once __DIR__ . '/../../API/core.php';
requireAuth();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\API\Core\Facades\CSRF::validate($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF invalide']);
        exit;
    }
}

match ($action) {
    'liste' => handleListe(),
    'creer' => handleCreer(),
    default => http_response_code(400),
};

function handleListe(): void {
    $pdo  = getPDO();
    $rows = $pdo->query("SELECT * FROM ma_table ORDER BY id DESC")->fetchAll();
    echo json_encode($rows);
}
```

### Bonnes pratiques

| Règle | Pourquoi |
|-------|---------|
| Toujours `requireAuth()` en début de page | Aucun module ne doit être accessible sans session |
| PDO + requêtes préparées uniquement | Prévient l'injection SQL |
| `htmlspecialchars()` sur toute sortie utilisateur | Prévient le XSS |
| Valider CSRF sur tous les POST | Prévient le CSRF |
| Utiliser `FileUploadService` pour tous les uploads | Validation MIME, nommage sécurisé, cohérence |
| `logAudit()` sur les actions sensibles | Traçabilité RGPD |

---

## Personnalisation

### Par utilisateur

| Fonctionnalité | Table | Accès |
|---------------|-------|-------|
| Thème (light/dark/auto) | `user_settings.theme` | `parametres/parametres.php?section=preferences` |
| Taille de police | `user_settings.taille_police` | idem |
| Sidebar réduite | `user_settings.sidebar_collapsed` | bouton sidebar (persisté en DB) |
| Préférences notifications | `notification_preferences` | `parametres/parametres.php?section=notifications` |
| Widgets dashboard | `user_settings.accueil_config` | `parametres/parametres.php?section=accueil` |
| Avatar | `user_settings.avatar_chemin` | `parametres/parametres.php?section=profil` |

`profil/index.php` redirige vers le module paramètres avec la bonne section.

### Par établissement (table `etablissement_info`)

| Colonne | Type | Défaut | Usage |
|---------|------|--------|-------|
| `couleur_primaire` | varchar(7) | `#003366` | Variable CSS `--color-primary` |
| `couleur_secondaire` | varchar(7) | `#0066cc` | Variable CSS `--color-secondary` |
| `css_personnalise` | text | null | CSS injecté en fin de `<head>` |
| `favicon` | varchar(255) | null | Favicon personnalisé |
| `pied_de_page` | text | null | Mentions légales du footer |

Ces valeurs sont lues par `shared_header.php` via `EtablissementService`.

### Visibilité des modules par rôle

La colonne `roles_autorises` (JSON) dans `modules_config` est prioritaire sur le tableau hardcodé `$roleVisibility` de `ModuleService`. Elle est éditable via `admin/modules/configure.php` → section "Rôles autorisés" sans redéploiement.

```php
// Programmatiquement :
$modules->updateRolesAutorises('discipline', ['administrateur', 'professeur', 'vie_scolaire']);
// null = tous les rôles
$modules->updateRolesAutorises('notes', null);
```

---

## Sécurité

### CSRF

Le système utilise un **token bucket rotatif** (`API\Security\CSRF`) stocké dans `$_SESSION['csrf_tokens']` (tableau, max 10 tokens simultanés, durée 1h). À ne pas confondre avec l'ancien `$_SESSION['csrf_token']` (maintenu pour rétrocompatibilité via `shared_header.php`).

```php
// Générer (automatique dans shared_header.php)
$token = \API\Core\Facades\CSRF::generate();

// Valider (traitement POST)
if (!\API\Core\Facades\CSRF::validate($_POST['csrf_token'] ?? '')) {
    http_response_code(403); exit;
}

// Champ HTML hidden
echo \API\Core\Facades\CSRF::field();  // <input type="hidden" name="csrf_token" value="...">
```

### Rate Limiting

`API\Security\RateLimiter` stocke les tentatives dans `api_rate_limits`. L'IP est résolue via `getClientIp()` :
- Sans `TRUSTED_PROXIES` dans `.env` → `REMOTE_ADDR` uniquement (résistant au spoofing)
- Avec `TRUSTED_PROXIES=1.2.3.4` → `X-Forwarded-For` accepté **uniquement si la requête vient d'un proxy de confiance**

### CSP avec nonce

Chaque page génère un nonce unique (`base64_encode(random_bytes(16))`). Les scripts inline du header portent l'attribut `nonce="..."`. Le header CSP utilise `'nonce-{nonce}'` au lieu de `'unsafe-inline'`.

### Headers HTTP envoyés

```
Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{nonce}' cdnjs.cloudflare.com ...
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
```

### Checklist production

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] HTTPS + `SESSION_SECURE=true`
- [ ] `TRUSTED_PROXIES` configuré si derrière un reverse proxy
- [ ] `pronote.sql` bloqué par `.htaccess` (déjà en place)
- [ ] Tâches cron configurées (worker.php + daily_maintenance.php)
- [ ] Backups automatiques (cron)
- [ ] Logs rotatifs (`logrotate`)

---

## Base de données

Schéma complet dans `pronote.sql`. Toujours modifier ce fichier directement (pas de système de migration séparé).

### Tables par groupe (140+ tables)

| Groupe | Exemples |
|--------|---------|
| **Référentielles** | `classes`, `matieres`, `periodes`, `professeur_classes` |
| **Utilisateurs** | `administrateurs`, `professeurs`, `eleves`, `parents`, `vie_scolaire`, `parent_eleve` |
| **Configuration** | `etablissement_info`, `modules_config`, `user_settings`, `feature_flags` |
| **Notes & Bulletins** | `notes`, `bulletins`, `bulletin_matieres`, `competences` |
| **Absences** | `absences`, `retards`, `justificatifs`, `justificatif_fichiers` |
| **Cahier de textes** | `devoirs`, `devoirs_fichiers`, `devoirs_statuts_eleve`, `devoirs_rendus` |
| **Agenda** | `evenements`, `evenement_exceptions` |
| **Messagerie** | `conversations`, `conversation_participants`, `messages`, `message_attachments`, `message_reactions` |
| **Notifications** | `notifications_globales`, `notification_preferences` |
| **Sécurité** | `api_rate_limits`, `audit_log`, `session_security`, `rbac_permissions` |
| **Emploi du temps** | `emploi_du_temps`, `creneaux_horaires`, `salles` |
| **Vie scolaire** | `incidents`, `sanctions`, `retenues` |
| **Logistique** | `menus_cantine`, `inscriptions_periscolaire`, `internat_chambres`, `lignes_transport` |
| **Facturation** | `factures`, `facture_lignes`, `paiements` |
| **RGPD** | `rgpd_demandes`, `rgpd_consentements` |
| **Système** | `job_queue`, `translations`, `api_tokens`, `webhooks`, `app_metrics` |

### Vue unifiée v_users

```sql
CREATE VIEW v_users AS
  SELECT id, nom, prenom, identifiant, mot_de_passe, mail, 'administrateur' AS type, actif FROM administrateurs
  UNION ALL SELECT id, nom, prenom, identifiant, mot_de_passe, mail, 'professeur', actif FROM professeurs
  UNION ALL SELECT id, nom, prenom, identifiant, mot_de_passe, mail, 'eleve', actif FROM eleves
  UNION ALL SELECT id, nom, prenom, identifiant, mot_de_passe, mail, 'parent', actif FROM parents
  UNION ALL SELECT id, nom, prenom, identifiant, mot_de_passe, mail, 'personnel', actif FROM personnel;
```

Utilisée par `AuthManager` pour résoudre les logins sans connaître le type d'utilisateur.

---

## WebSocket

### Vue d'ensemble

```
PHP (shared_header.php)
  → génère un JWT signé (JWT_SECRET) avec userId + userType
  → injecte window.FRONOTE_WS = { url, token, userId, userType }

Client JS (ws-global.js)
  → se connecte à Socket.IO avec le JWT
  → écoute les événements : newMessage, notification, newGrade, newAbsence…
  → si échec de connexion → bascule sur polling HTTP (fallback transparent)

PHP (API/endpoints/messagerie.php, etc.)
  → après chaque action → POST http://localhost:3000/notify/message { convId, message, secret: API_SECRET }
  → le serveur Node diffuse via io.to('conv_42').emit('newMessage', ...)
```

### Dépendances Node.js

Le serveur utilise trois packages. Comme `websocket-server/` ne contient pas de `package.json` versionné, il faut l'initialiser à chaque nouveau déploiement :

```bash
cd websocket-server
npm init -y
npm install express socket.io jsonwebtoken
```

### Variables d'environnement du serveur Node

| Variable | Description | Défaut |
|----------|-------------|--------|
| `PORT` | Port d'écoute | `3000` |
| `JWT_SECRET` | Même valeur que dans `.env` PHP | *(requis)* |
| `API_SECRET` | Secret partagé pour les routes `/notify/*` | *(requis)* |
| `ALLOWED_ORIGINS` | CORS — origines autorisées (virgule-séparé) | `*` |
| `NODE_ENV` | `production` ou `development` | `development` |

### Démarrage (développement)

```bash
cd websocket-server
JWT_SECRET=xxx API_SECRET=yyy node server.js
```

### Démarrage (production avec PM2)

```bash
npm install -g pm2
cd websocket-server
pm2 start server.js --name "fronote-ws" \
  --env-var JWT_SECRET=xxx \
  --env-var API_SECRET=yyy \
  --env-var ALLOWED_ORIGINS=https://mon-ecole.fr \
  --env-var NODE_ENV=production
pm2 save && pm2 startup
```

### Nginx reverse proxy (WebSocket en HTTPS)

```nginx
location /socket.io/ {
    proxy_pass         http://localhost:3000;
    proxy_http_version 1.1;
    proxy_set_header   Upgrade $http_upgrade;
    proxy_set_header   Connection "upgrade";
    proxy_set_header   Host $host;
    proxy_cache_bypass $http_upgrade;
}
```

### Routes HTTP internes (déclenchées par PHP)

Toutes les routes POST nécessitent `"secret": API_SECRET` dans le body JSON.

| Route | Payload | Diffuse vers |
|-------|---------|-------------|
| `POST /notify/message` | `{convId, message}` | `conv_{convId}` |
| `POST /notify/message-edited` | `{convId, messageId, newBody, editedAt}` | `conv_{convId}` |
| `POST /notify/message-deleted` | `{convId, messageId}` | `conv_{convId}` |
| `POST /notify/message-read` | `{convId, userId, messageId, readAt}` | `conv_{convId}` |
| `POST /notify/reaction` | `{convId, messageId, reactions}` | `conv_{convId}` |
| `POST /notify/notification` | `{userId, data}` | `user_{userId}` |
| `POST /notify/grade` | `{eleveId, gradeData}` | `user_{eleveId}` |
| `POST /notify/absence` | `{eleveId, absenceData}` | `user_{eleveId}` |
| `POST /notify/event` | `{targetType, targetId, eventData}` | `class_{id}` ou `user_{id}` |
| `GET /health` | — | `{status, connections, uptime}` |

---

## Configuration .env

```env
# ─── Base de données ───────────────────────────────────────
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=fronote
DB_USER=fronote_user
DB_PASS=secret
DB_CHARSET=utf8mb4

# ─── Application ───────────────────────────────────────────
APP_NAME=Fronote
APP_ENV=production          # production | development | test
APP_DEBUG=false
APP_URL=https://mon-ecole.fr/fronote
BASE_URL=/fronote
APP_TIMEZONE=Europe/Paris

# ─── Sécurité ──────────────────────────────────────────────
CSRF_LIFETIME=3600
CSRF_MAX_TOKENS=10
SESSION_NAME=fronote_session
SESSION_LIFETIME=7200
SESSION_SECURE=false        # true en HTTPS obligatoire
SESSION_HTTPONLY=true
SESSION_SAMESITE=Lax
MAX_LOGIN_ATTEMPTS=5
LOGIN_LOCKOUT_TIME=900
RATE_LIMIT_ATTEMPTS=5
RATE_LIMIT_DECAY=1
TRUSTED_PROXIES=            # IPs des reverse proxies de confiance (virgule-séparé)
ALLOWED_INSTALL_IP=         # IP externe autorisée pour install.php (vide = réseau local uniquement)

# ─── JWT & WebSocket ───────────────────────────────────────
JWT_SECRET=changez-ce-secret-en-production
WEBSOCKET_ENABLED=true
WEBSOCKET_URL=http://localhost:3000
WEBSOCKET_CLIENT_URL=http://localhost:3000
WEBSOCKET_API_SECRET=secret-partage-avec-le-serveur-node

# ─── Mises à jour automatiques ────────────────────────────
GITHUB_REPO=votre-org/fronote
GITHUB_BRANCH=main
GITHUB_WEBHOOK_SECRET=     # Unique par client — généré à la création du compte client
UPDATE_AUTO_CHECK=false

# ─── Chemins ───────────────────────────────────────────────
UPLOADS_PATH=/var/www/fronote/uploads
LOGS_PATH=/var/www/fronote/API/logs
TEMP_PATH=/var/www/fronote/temp

# ─── RGPD ─────────────────────────────────────────────────
AUDIT_ENABLED=true
AUDIT_RETENTION_DAYS=180

# ─── SMTP (configurable via admin/systeme/smtp.php) ────────
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="Fronote"
```

---

## Déploiement client

Chaque établissement client reçoit une **installation isolée** de Fronote avec sa propre base de données et sa propre clé webhook.

### Générer une clé webhook pour un nouveau client

```bash
# Générer une clé aléatoire sécurisée (32 octets = 64 caractères hex)
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
# ou
openssl rand -hex 32
```

Notez cette clé — c'est la valeur à placer dans `GITHUB_WEBHOOK_SECRET` dans le `.env` du client.

### Préparer l'archive de déploiement

L'archive envoyée au client ne doit pas contenir les fichiers de développement :

```bash
# Depuis la racine du projet
git archive --format=zip --output=fronote-v2.0.0.zip HEAD

# Ce que l'archive contient (fichiers trackés par git) :
# API/, templates/, assets/, [modules]/, websocket-server/server.js,
# .env.example, .htaccess, composer.json, composer.lock,
# install.php, install_guard.php, pronote.sql, version.json, scripts/

# Ce qu'elle NE contient PAS (géré par .gitignore) :
# .env, vendor/, uploads/, temp/, API/logs/, install.lock,
# websocket-server/node_modules/, login/data/etablissement.json
```

### Configurer le webhook GitHub pour le client

Dans les paramètres GitHub du dépôt (`Settings → Webhooks → Add webhook`) :

| Champ | Valeur |
|-------|--------|
| Payload URL | `https://domaine-du-client.fr/fronote/API/endpoints/webhook_update.php` |
| Content type | `application/json` |
| Secret | La clé générée pour ce client |
| Events | **Just the push event** |

### Suivi des clients déployés

Tenez un registre (fichier ou base de données interne) :

| Client | Domaine | `GITHUB_WEBHOOK_SECRET` | Version | Date déploiement |
|--------|---------|------------------------|---------|-----------------|
| Collège X | `college-x.fr/fronote` | `abc123…` | 2.0.0 | 2026-03-18 |
| Lycée Y | `lycee-y.fr/fronote` | `def456…` | 2.0.0 | 2026-03-20 |

### Config Nginx (référence)

```nginx
server {
    listen 443 ssl;
    server_name mon-ecole.fr;
    root /var/www/fronote;
    index index.php;

    # Bloquer fichiers sensibles
    location ~* \.(env|sql|bak|log)$ { deny all; }
    location = /install.lock          { deny all; }

    # WebSocket (si activé)
    location /socket.io/ {
        proxy_pass         http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade $http_upgrade;
        proxy_set_header   Connection "upgrade";
        proxy_set_header   Host $host;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location / { try_files $uri $uri/ =404; }
}
```

---

## Maintenance & Mises à jour

### Processus de mise à jour (côté développeur)

```
1. Développer + tester en local
2. git push origin main
3. GitHub déclenche les webhooks de tous les clients enregistrés
4. Chaque serveur client reçoit le signal → valide la signature HMAC-SHA256
   → exécute scripts/update.php (git pull + composer + bootstrap test)
5. En cas d'échec : .env restauré automatiquement, log disponible dans temp/update.log
```

### Mise à jour manuelle (SSH sur le serveur client)

```bash
cd /var/www/fronote
php scripts/update.php
```

### Logs

```
API/logs/          ← Logs applicatifs PHP
temp/update.log    ← Journal des mises à jour (50 dernières lignes dans l'admin)
audit_log (table)  ← Toutes les actions sensibles (login, CRUD sensible, etc.)
```

### Sauvegarde

```bash
# Base de données
mysqldump -u fronote_user -p fronote | gzip > /backups/fronote_$(date +%Y%m%d).sql.gz

# Fichiers uploadés
tar -czf /backups/uploads_$(date +%Y%m%d).tar.gz /var/www/fronote/uploads/

# Cron (2h du matin quotidiennement)
0 2 * * * mysqldump -u fronote_user -pSECRET fronote | gzip > /backups/fronote_$(date +\%Y\%m\%d).sql.gz
```

---

## Rôles utilisateurs

| Rôle | Clé session | Accès par défaut |
|------|-------------|-----------------|
| **Administrateur** | `administrateur` | Tout — admin panel, modules, utilisateurs, audit |
| **Professeur** | `professeur` | Notes (saisie), cahier de textes, agenda, messagerie, absences, appel |
| **Élève** | `eleve` | Notes (consultation), devoirs, agenda, messagerie, ressources |
| **Parent** | `parent` | Notes enfant(s), absences, messagerie, justificatifs, réunions |
| **Personnel** | `personnel` | Modules configurés via `roles_autorises` |
| **Vie scolaire** | `vie_scolaire` | Absences, discipline, reporting, infirmerie, internat |
| **Technicien** | `technicien` | Accès temporaire limité, configurable par l'administrateur |

La visibilité par rôle est configurable sans code via `admin/modules/configure.php`.

---

## Permissions par module

Le système RBAC supporte des permissions granulaires par module et par rôle, stockées dans `module_permissions`.

### Actions standard

| Action | Colonne | Description |
|--------|---------|-------------|
| `view` | `can_view` | Voir le module |
| `create` | `can_create` | Créer des entrées |
| `edit` | `can_edit` | Modifier |
| `delete` | `can_delete` | Supprimer |
| `export` | `can_export` | Exporter les données |
| `import` | `can_import` | Importer des données |

### Permissions personnalisées

Le champ `custom_permissions` (JSON) permet des actions spécifiques par module. Exemple pour la messagerie :

```json
{"send": true, "moderate": false, "broadcast": false}
```

### API

```php
// Vérifier une permission
canModule('messagerie', 'send');    // bool — via helper global
$rbac->canModule('notes', 'edit');  // via RBAC directement

// Configurer via l'admin
// admin/modules/permissions.php — interface matrice rôle × module × actions
```

---

## Accès technicien

Le système d'accès technicien permet de créer des comptes temporaires à durée limitée pour la maintenance.

| Fonctionnalité | Détails |
|---------------|---------|
| **Création** | Via `admin/systeme/technicien.php` |
| **Authentification** | Login `tech_XXXX` + mot de passe unique affiché une seule fois |
| **Expiration** | 1h à 7 jours (configurable) |
| **Restrictions** | Whitelist IP, modules restreints, niveau de permission (readonly/standard/full) |
| **Audit** | Toutes les actions logées dans `technicien_audit_log` |
| **Révocation** | Révocation manuelle immédiate possible |

Tables : `technicien_access`, `technicien_audit_log`.

---

## Import / Export

Le système d'import/export (accessible via `admin/systeme/import_export.php`) permet :

| Fonctionnalité | Format | Description |
|---------------|--------|-------------|
| Export utilisateurs | CSV | Par type (élèves, professeurs, parents…) |
| Export configuration | JSON | modules_config, module_permissions, settings |
| Import utilisateurs | CSV | Validation, détection de doublons par email |
| Import configuration | JSON | Restauration de configuration |
| Historique | — | Journal de tous les imports/exports avec statut |

---

## Design System

Fronote utilise un design system à tokens CSS avec deux thèmes : **classic** (défaut) et **glass** (glassmorphism).

### Architecture CSS

| Fichier | Rôle |
|---------|------|
| `base.css` | Reset, fondations |
| `tokens.css` | Design tokens (couleurs, typo fluide, spacing, ombres) |
| `theme-classic.css` | Thème principal — toujours chargé |
| `theme-glass.css` | Surcouche glassmorphism — chargée en overlay si l'utilisateur choisit le thème glass |

### Caractéristiques

- **Tokens partagés** : toutes les couleurs, tailles et espacements dans `tokens.css` via CSS custom properties
- **Dark mode** : `[data-theme="dark"]` avec overrides complets dans `tokens.css`
- **Typographie fluide** : `clamp()` pour des tailles qui s'adaptent de 320px à 1440px
- **Responsive** : sidebar drawer mobile (<768px), tables scrollables, filtres empilés
- **Glass mode** : `backdrop-filter: blur()`, backgrounds semi-transparents, chargé en overlay sur classic

### Composants

Cards, boutons, inputs, modals, tables, badges, alerts, tabs, pagination, stat cards — tous stylés via les tokens et supportant les deux thèmes + dark mode.

---

## Dépannage développeur

### Class not found

```bash
composer dump-autoload --optimize
```

### Erreur PDO silencieuse

`Database.php` force `ERRMODE_EXCEPTION` — si une requête ne lève pas d'exception, vérifiez que vous passez bien par `getPDO()` ou le container, pas une connexion manuelle sans options.

### Token CSRF rejeté

- Les tokens expirent après 1h (`CSRF_LIFETIME`)
- Ils sont à **usage unique** (supprimés après `validate()`)
- Si plusieurs AJAX parallèles utilisent le même token depuis la meta tag → utiliser `check()` (non destructif) à la place de `validate()` pour les tokens non-critiques

### Le webhook de mise à jour n'est pas déclenché

1. Vérifier que l'URL est accessible depuis l'extérieur
2. Vérifier `GITHUB_WEBHOOK_SECRET` dans `.env` (identique à celui configuré dans GitHub)
3. Consulter les livraisons du webhook dans GitHub (`Settings → Webhooks → Recent deliveries`)
4. Vérifier `temp/update.log`

### Module n'apparaît pas en sidebar

1. `enabled = 1` dans `modules_config`
2. Clé présente dans `ModuleService::$routeMap`
3. Rôle autorisé (`roles_autorises` dans DB ou `$roleVisibility` dans le code)
4. Clé pas dans la liste d'exclusion de `getForSidebar()` (`accueil`, `parametres`)

---

## Licence

MIT — voir [LICENSE](LICENSE)

---

*Fronote v2.0.0 — PHP vanilla · PSR-4 · IoC · 47 modules · 140+ tables · WebSocket · Design system tokens*
