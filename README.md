# Fronote — Système de Gestion Scolaire

![PHP 8+](https://img.shields.io/badge/PHP-8%2B-blue) ![MySQL 5.7+](https://img.shields.io/badge/MySQL-5.7%2B-orange) ![Version](https://img.shields.io/badge/version-1.0.0-green) ![Licence](https://img.shields.io/badge/licence-MIT-lightgrey)

Fronote est une application web complète de gestion d'établissement scolaire, développée en **PHP vanilla** (sans framework). Elle couvre 40+ modules (notes, absences, emploi du temps, messagerie, vie scolaire, facturation…) avec une architecture IoC/PSR-4, une API centralisée et 233+ tables SQL.

---

## Table des matières

- [Quick Start](#quick-start)
- [Fonctionnalités](#fonctionnalités)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Architecture](#architecture)
- [API centralisée](#api-centralisée)
- [Assets & Templates](#assets--templates)
- [Guide Développeur — Créer un module](#guide-développeur--créer-un-module)
- [Personnalisation](#personnalisation)
- [Sécurité](#sécurité)
- [Base de données](#base-de-données)
- [WebSocket](#websocket)
- [Configuration](#configuration)
- [Maintenance](#maintenance)
- [Rôles utilisateurs](#rôles-utilisateurs)
- [Dépannage](#dépannage)
- [Licence](#licence)

---

## Quick Start

```bash
# 1. Cloner le dépôt
git clone https://github.com/votre-org/fronote.git /var/www/fronote

# 2. Installer les dépendances PHP
cd /var/www/fronote
composer install --no-dev --optimize-autoloader

# 3. Ouvrir le wizard dans le navigateur (réseau local uniquement par défaut)
http://votre-serveur/fronote/install.php
```

L'installateur configure automatiquement `.env`, crée la base de données (233+ tables), le compte admin, et verrouille l'accès à l'installateur.

---

## Fonctionnalités

### Navigation (modules système — toujours actifs)

| Module | Description |
|--------|-------------|
| **Accueil** | Tableau de bord personnalisé par rôle |
| **Messagerie** | Conversations privées, annonces, réactions, temps réel |
| **Notifications** | Centre de notifications multi-canal |
| **Paramètres** | Préférences utilisateur (thème, police, avatar, 2FA) |

### Scolaire

| Module | Description |
|--------|-------------|
| **Notes** | Saisie, consultation, moyennes par matière/classe/période, export |
| **Agenda** | Calendrier jour/semaine/liste, événements récurrents (rrule) |
| **Cahier de textes** | Devoirs avec pièces jointes, suivi statuts élève |
| **Emploi du temps** | Grille hebdomadaire, créneaux configurables |
| **Bulletins** | Bulletins scolaires par période, export PDF |
| **Compétences** | Évaluation par compétences (socle commun) |
| **Devoirs en ligne** | Remise de rendus, date limite, suivi |
| **Examens** | Organisation des épreuves et examens |

### Vie scolaire

| Module | Description |
|--------|-------------|
| **Absences** | Suivi absences/retards, justificatifs avec pièces jointes |
| **Appel** | Appel numérique en classe |
| **Discipline** | Incidents, sanctions, retenues |
| **Vie scolaire** | Dashboard vie scolaire centralisé |
| **Reporting** | Rapports et statistiques |
| **Signalements** | Signalements anonymes (harcèlement…) |
| **Besoins particuliers** | Suivi PAP, PPS, élèves à besoins spécifiques |

### Communication

| Module | Description |
|--------|-------------|
| **Annonces** | Annonces ciblées et sondages |
| **Réunions** | Organisation des réunions parents-profs, créneaux |
| **Documents** | Documents administratifs partagés |

### Établissement

| Module | Description |
|--------|-------------|
| **Trombinoscope** | Annuaire avec photos |
| **Bibliothèque** | Catalogue et gestion des emprunts |
| **Clubs** | Clubs et activités parascolaires |
| **Orientation** | Fiches d'orientation et vœux |
| **Inscriptions** | Inscriptions et réinscriptions en ligne |
| **Infirmerie** | Passages et fiches santé |
| **Ressources** | Ressources pédagogiques partagées |
| **Diplômes** | Gestion et délivrance |
| **Vie associative** | Associations de l'établissement (CRUD + export) |

### Logistique & Services

| Module | Description |
|--------|-------------|
| **Périscolaire** | Cantine, garderie, activités |
| **Cantine** | Menus, réservations, pointage |
| **Internat** | Chambres, affectations, vie en internat |
| **Garderie** | Accueil périscolaire matin/soir/mercredi |
| **Stages** | Conventions de stage (3e, lycée…) |
| **Transports** | Lignes et inscriptions |
| **Facturation** | Factures et paiements |
| **Salles & Matériels** | Réservation de salles, prêt de matériel |
| **Gestion personnel** | Absences et remplacements |

### Système & Administration

| Module | Description |
|--------|-------------|
| **Archivage** | Archivage annuel des données |
| **RGPD & Audit** | Conformité RGPD, journal d'audit |
| **Aide & Support** | FAQ et tickets de support |

---

## Prérequis

| Composant | Version minimale | Notes |
|-----------|-----------------|-------|
| PHP | 8.0+ | Extensions : `pdo`, `pdo_mysql`, `json`, `mbstring`, `session`, `fileinfo`, `openssl` |
| MySQL | 5.7+ | ou MariaDB 10.3+ |
| Apache | 2.4+ | `mod_rewrite` requis |
| Composer | 2.x | Pour les dépendances PHP |
| Node.js | 16+ | *Optionnel*, uniquement pour le serveur WebSocket |

---

## Installation

### Cloner & Composer

```bash
git clone https://github.com/votre-org/fronote.git /var/www/fronote
cd /var/www/fronote

# Définir les permissions
chown -R www-data:www-data /var/www/fronote
chmod -R 755 /var/www/fronote

# Installer les dépendances PHP
composer install --no-dev --optimize-autoloader
```

### Wizard en 5 étapes

Ouvrez `http://votre-serveur/fronote/install.php` dans votre navigateur (réseau local uniquement par défaut).

| Étape | Description |
|-------|-------------|
| **1. Pré-requis** | Vérification PHP, extensions, répertoires |
| **2. Base de données** | Test de connexion MySQL avec diagnostic d'erreur |
| **3. Application** | Configuration (nom, URL, sessions) et établissement |
| **4. Administrateur** | Création du compte admin avec validation de sécurité |
| **5. Installation** | Exécution SQL, génération `.env`, tests API, `install.lock` |

L'installateur crée automatiquement :
- `.env` avec tous les paramètres
- La base de données complète (233+ tables)
- Les répertoires `uploads/`, `temp/`, `API/logs/`
- Les fichiers `.htaccess` de protection
- `install.lock` (bloque toute réinstallation)

### Checklist post-installation

- [ ] Vérifier que `APP_DEBUG=false` dans `.env`
- [ ] Configurer HTTPS et forcer `cookie_secure=true`
- [ ] Restreindre `diagnostic.php` par IP ou le supprimer
- [ ] Configurer les sauvegardes automatiques (`uploads/` + BDD)
- [ ] Optionnel : démarrer le serveur WebSocket Node.js

### Configuration Nginx

```nginx
server {
    listen 80;
    server_name mon-ecole.fr;
    root /var/www/fronote;
    index index.php;

    # Bloquer fichiers sensibles
    location ~* \.(env|sql|bak|log)$ { deny all; }
    location = /install.lock           { deny all; }
    location = /diagnostic.php         { deny all; }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # API routing (équivalent du RewriteRule .htaccess)
    location /api/ {
        try_files $uri $uri/ /API/index.php?$query_string;
    }

    location / {
        try_files $uri $uri/ =404;
    }
}
```

### Docker (docker-compose.yml)

```yaml
version: '3.9'
services:
  app:
    image: php:8.2-apache
    volumes:
      - .:/var/www/html
    environment:
      - APACHE_DOCUMENT_ROOT=/var/www/html
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

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Navigateur (client)                   │
└────────────┬──────────────────────┬─────────────────────┘
             │ HTTP/HTTPS           │ WebSocket
             ▼                     ▼
┌────────────────────┐   ┌──────────────────┐
│  Pages PHP (vues)  │   │  websocket-server│
│  + templates/      │   │  (Node.js)       │
└────────┬───────────┘   └──────────────────┘
         │ require API/bootstrap.php
         ▼
┌─────────────────────────────────────────────────────────┐
│                    API/ (couche métier)                  │
│                                                         │
│  ┌──────────┐ ┌────────────┐ ┌──────────┐ ┌─────────┐  │
│  │AuthManager│ │RateLimiter │ │   CSRF   │ │Container│  │
│  └──────────┘ └────────────┘ └──────────┘ └─────────┘  │
│                                                         │
│  ┌──────────────────────────────────────────────────┐   │
│  │  Services: FileUploadService, ModuleService,     │   │
│  │  UserService, DashboardService, getPDO()…        │   │
│  └──────────────────────────────────────────────────┘   │
└────────────────────────┬────────────────────────────────┘
                         │ PDO (ERRMODE_EXCEPTION)
                         ▼
                ┌──────────────────┐
                │   MySQL/MariaDB  │
                │  (pronote.sql)   │
                │   233+ tables    │
                └──────────────────┘
```

### Principes

- **PHP vanilla** : aucun framework, PDO pour la base de données
- **PSR-4 autoloading** : namespace `API\` chargé via `API/bootstrap.php`
- **IoC Container** : `API/Core/Container.php` avec injection de dépendances
- **Facades** : `CSRF::generate()`, `Auth::user()`, `DB::query()`, `Log::info()`
- **Templates partagés** : header, sidebar, topbar, footer dans `templates/`
- **Services centralisés** : upload, auth, rate limiting, modules

### Cycle de requête

```
Requête HTTP
  → Module PHP (ex: notes/notes.php)
    → require API/bootstrap.php  (autoloader + container + session)
    → requireAuth()              (vérifie session, redirige si non connecté)
    → [logique métier via services]
    → include templates/shared_header.php  (nonce CSP, CSRF, thème)
    → include templates/shared_sidebar.php (modules actifs par rôle)
    → [HTML spécifique au module]
    → include templates/shared_footer.php  (scripts JS, fermeture)
```

---

## API centralisée

### Bootstrap

```php
require_once __DIR__ . '/../API/bootstrap.php';
```

Charge l'autoloader PSR-4, initialise le conteneur IoC, démarre la session sécurisée, enregistre les providers (Auth, CSRF, RateLimiter, Modules, FileUpload…).

### Endpoints REST

Tous les points d'entrée AJAX/REST sont dans `API/endpoints/` :

| Endpoint | Méthodes | Paramètres principaux | Réponse |
|----------|----------|----------------------|---------|
| `messagerie.php` | GET/POST/DELETE | `resource`, `action`, `id` | JSON |
| `agenda_persons.php` | GET | `visibility` | JSON `[{id, nom, prenom, type}]` |
| `notes_eleves.php` | GET | `classe` | JSON `[{id, nom, prenom}]` |

### Services

| Service | Classe | Méthodes clés |
|---------|--------|---------------|
| Auth | `API\Auth\AuthManager` | `login()`, `logout()`, `user()`, `check()` |
| Rate Limiter | `API\Security\RateLimiter` | `hit($key)`, `tooManyAttempts($key)`, `clear($key)` |
| CSRF | `API\Security\CSRF` | `generate()`, `validate($token)`, `field()` |
| File Upload | `API\Services\FileUploadService` | `upload()`, `uploadMultiple()`, `serve()`, `delete()` |
| Modules | `API\Services\ModuleService` | `isEnabled()`, `getForSidebar()`, `updateConfig()` |
| Database | `API\Database\Database` | `getConnection()`, `table($name)` |

### FileUploadService

```php
use API\Services\FileUploadService;

$uploader = new FileUploadService('devoirs');  // devoirs | messagerie | justificatifs

// Upload multiple
$results = $uploader->uploadMultiple($_FILES['fichiers']);
foreach ($results as $r) {
    if ($r['success']) {
        // $r['nom_original'], $r['chemin'], $r['type_mime'], $r['taille']
    }
}

// Servir un fichier de manière sécurisée
$uploader->serve($relativePath, $originalName);

// Supprimer
$uploader->delete($relativePath);

// Utilitaires statiques
FileUploadService::formatBytes(1048576);      // "1.00 MB"
FileUploadService::getFileIcon('image/png'); // "file-image"
```

| Contexte | Taille max | Fichiers max | Types autorisés |
|----------|-----------|-------------|-----------------|
| `messagerie` | 5 Mo | 10 | images, PDF, documents, archives |
| `devoirs` | 10 Mo | 5 | images, PDF, documents, archives |
| `justificatifs` | 5 Mo | 5 | images, PDF, documents |

### AuthManager

```php
// Dans un module après bootstrap
requireAuth();              // Redirige vers login si non connecté
requireRole('professeur');  // Redirige si mauvais rôle

$userId   = getUserId();    // int
$userRole = getUserRole();  // string : administrateur|professeur|eleve|parent|personnel
$userName = getFullName();  // "Prénom Nom"
```

### ModuleService

```php
$modules = app('modules');

$modules->isEnabled('notes');                    // bool
$modules->isVisibleForRole('absences', 'eleve'); // bool
$modules->getConfig('messagerie');               // array de config_json
$modules->updateConfig('notes', ['note_max' => 20]);
$modules->updateRolesAutorises('discipline', ['administrateur', 'professeur']);
```

---

## Assets & Templates

### Structure globale

```
assets/
├── css/
│   └── pronote-unified.css   ← CSS global (toute l'appli)
└── js/
    ├── ws-global.js          ← Client WebSocket global
    └── ...

[module]/
└── assets/
    ├── css/
    │   └── module.css        ← CSS spécifique au module
    └── js/
        └── module.js         ← JS spécifique au module
```

**Règle :** CSS/JS utilisé sur plusieurs modules → `assets/` global. CSS/JS utilisé sur un seul module → `[module]/assets/`.

### Templates partagés

| Fichier | Rôle | Variables attendues |
|---------|------|---------------------|
| `shared_header.php` | `<head>`, CSS, meta CSRF, nonce CSP, thème | `$pageTitle`, `$extraCss`, `$rootPrefix` |
| `shared_sidebar.php` | Navigation latérale dynamique (rôle + modules actifs) | `$activePage` |
| `shared_topbar.php` | Barre supérieure (avatar, notifications) | `$user_initials`, `$user_fullname` |
| `shared_footer.php` | Fermeture HTML, scripts JS, WebSocket | `$extraJs` |

### Inclure CSS/JS d'un module

```php
// Dans le module, avant d'inclure shared_header.php
$extraCss = ['assets/css/mon-module.css'];
$extraJs  = ['assets/js/mon-module.js'];

include __DIR__ . '/../templates/shared_header.php';
```

---

## Guide Développeur — Créer un module

### Structure type

```
mon_module/
├── mon_module.php          ← Page principale
├── assets/
│   ├── css/mon_module.css
│   └── js/mon_module.js
├── includes/
│   └── functions.php       ← Fonctions métier
└── api/                    ← (optionnel) endpoints AJAX locaux
    └── actions.php
```

### Étapes de création

**1. Créer le fichier principal :**

```php
<?php
// mon_module/mon_module.php
require_once __DIR__ . '/../API/core.php';

requireAuth();
// requireRole('professeur');  // si module réservé

$pdo = getPDO();
$pageTitle   = 'Mon Module';
$activePage  = 'mon_module';
$rootPrefix  = '../';
$extraCss    = ['assets/css/mon_module.css'];

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

**2. Enregistrer dans `modules_config` (pronote.sql) :**

```sql
INSERT INTO `modules_config` (`module_key`, `label`, `description`, `icon`, `category`, `enabled`, `sort_order`, `is_core`)
VALUES ('mon_module', 'Mon Module', 'Description du module', 'fas fa-star', 'scolaire', 1, 99, 0);
```

**3. Ajouter la route dans `ModuleService::$routeMap` :**

```php
'mon_module' => 'mon_module/mon_module.php',
```

### Bonnes pratiques

- **PDO** : toujours utiliser `getPDO()` + requêtes préparées (`prepare` + `execute`)
- **CSRF** : `\API\Core\Facades\CSRF::validate($_POST['csrf_token'])` sur tous les POST
- **Upload** : utiliser `FileUploadService` avec un contexte défini
- **Rôles** : appeler `requireRole()` en début de page
- **Sortie HTML** : toujours `htmlspecialchars()` sur les données utilisateur

---

## Personnalisation

### Par utilisateur

| Fonctionnalité | Table DB | Comment accéder |
|---------------|----------|-----------------|
| Thème clair/sombre/auto | `user_settings.theme` | `parametres/parametres.php?section=preferences` |
| Taille de police | `user_settings.taille_police` | idem |
| Sidebar collapsed | `user_settings.sidebar_collapsed` | bouton sidebar (persisté en DB) |
| Préférences notifications | `notification_preferences` | `parametres/parametres.php?section=notifications` |
| Widgets dashboard | `user_settings.accueil_config` | `parametres/parametres.php?section=accueil` |
| Avatar | `user_settings.avatar_chemin` | `parametres/parametres.php?section=profil` |

Accès direct via `profil/index.php?section=preferences` (redirige vers paramètres).

### Par établissement

La table `etablissement_info` expose :

| Colonne | Défaut | Usage |
|---------|--------|-------|
| `nom` | 'Établissement Scolaire' | Affiché en sidebar et PDF |
| `logo` | null | Logo dans l'en-tête |
| `couleur_primaire` | `#003366` | Variable CSS `--color-primary` |
| `couleur_secondaire` | `#0066cc` | Variable CSS `--color-secondary` |
| `css_personnalise` | null | CSS injecté dans `<head>` |
| `favicon` | null | Favicon personnalisé |
| `pied_de_page` | null | Mentions légales en bas de page |

**`shared_header.php`** lit ces valeurs via `EtablissementService` et les injecte automatiquement.

### Activer/désactiver des modules

`admin/modules/index.php` → toggle ON/OFF par module.

Les modules `core` (accueil, messagerie, paramètres, notifications) ne peuvent pas être désactivés.

### Configurer un module

`admin/modules/configure.php?module=notes` permet de modifier :
- Label, description, icône, ordre d'affichage
- Configuration JSON spécifique (ex: note_max, décimales, notifications)
- **Rôles autorisés** : restreindre la visibilité du module à certains rôles sans toucher au code

---

## Sécurité

### Authentification & sessions

- Mots de passe hashés **BCRYPT** (cost 12)
- Sessions : `cookie_httponly`, `cookie_secure`, `SameSite=Lax`
- `session_regenerate_id()` après authentification
- Durée de session configurable (défaut : 2h)

### CSRF

Tokens générés par `API\Security\CSRF` (bucket rotatif, durée 1h, max 10 tokens simultanés). Utilisez toujours la façade :

```php
// Générer (dans le template header, automatique)
$token = \API\Core\Facades\CSRF::generate();

// Valider (dans le traitement POST)
if (!\API\Core\Facades\CSRF::validate($_POST['csrf_token'])) {
    http_response_code(403); exit;
}

// Champ HTML
echo \API\Core\Facades\CSRF::field();
```

### Rate Limiting

`API\Security\RateLimiter` stocke les tentatives en base (`api_rate_limits`). L'IP est détectée via `getClientIp()` : accepte `X-Forwarded-For` **uniquement si `TRUSTED_PROXIES` est défini** dans `.env`.

### Upload de fichiers

- Validation MIME réelle via `finfo`
- Allowlist d'extensions par contexte
- Nommage aléatoire `random_bytes(16)` en hex
- Stockage dans `uploads/{contexte}/YYYY/MM/`
- Servi uniquement via PHP (`.htaccess` Deny from all sur `uploads/`)

### Headers HTTP (CSP avec nonce)

```
Content-Security-Policy: default-src 'self';
  script-src 'self' 'nonce-{nonce}' cdnjs.cloudflare.com ...;
  style-src 'self' 'unsafe-inline' cdnjs.cloudflare.com;
  frame-ancestors 'none';
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
```

Le nonce est généré par requête dans `shared_header.php` et appliqué sur les scripts inline.

### Checklist sécurité production

- [ ] `APP_DEBUG=false`
- [ ] HTTPS + `cookie_secure=true`
- [ ] `pronote.sql` → accès bloqué par `.htaccess`
- [ ] `diagnostic.php` → bloqué ou supprimé
- [ ] `TRUSTED_PROXIES` configuré si derrière un reverse proxy
- [ ] Backups automatiques (cron)

---

## Base de données

Le schéma complet est dans `pronote.sql` (233+ tables, 1 vue).

### Tables par groupe

| Groupe | Exemples de tables |
|--------|--------------------|
| **Référentielles** | `classes`, `matieres`, `periodes`, `professeur_classes` |
| **Utilisateurs** | `administrateurs`, `professeurs`, `eleves`, `parents`, `personnel`, `parent_eleve` |
| **Configuration** | `etablissement_info`, `modules_config`, `smtp_config`, `user_settings` |
| **Notes & Bulletins** | `notes`, `bulletins`, `appreciations`, `competences` |
| **Absences** | `absences`, `retards`, `justificatifs`, `justificatif_fichiers` |
| **Cahier de textes** | `devoirs`, `devoirs_fichiers`, `devoirs_statuts_eleve` |
| **Agenda** | `evenements`, `evenements_recurrents` |
| **Messagerie** | `conversations`, `conversation_participants`, `messages`, `message_attachments`, `message_reactions`, `announcements` |
| **Notifications** | `notifications`, `notification_preferences` |
| **Sécurité** | `api_rate_limits`, `audit_log`, `session_security`, `demandes_reinitialisation` |
| **Emploi du temps** | `emploi_du_temps`, `creneaux`, `salles` |
| **Vie scolaire** | `vie_scolaire`, `incidents`, `sanctions` |
| **Logistique** | `cantine_menus`, `reservations_cantine`, `chambres_internat`, `lignes_transport` |
| **Facturation** | `factures`, `lignes_facture`, `paiements` |
| **RGPD** | `rgpd_demandes`, `rgpd_traitements` |

### Vue unifiée

```sql
CREATE VIEW v_users AS
  SELECT id, nom, prenom, identifiant, mot_de_passe, mail, 'administrateur' AS type, actif FROM administrateurs
  UNION ALL SELECT id, nom, prenom, identifiant, mot_de_passe, mail, 'professeur', actif FROM professeurs
  UNION ALL SELECT id, nom, prenom, identifiant, mot_de_passe, mail, 'eleve', actif FROM eleves
  UNION ALL SELECT id, nom, prenom, identifiant, mot_de_passe, mail, 'parent', actif FROM parents
  UNION ALL SELECT id, nom, prenom, identifiant, mot_de_passe, mail, 'personnel', actif FROM personnel;
```

Utilisée par `AuthManager` pour résoudre les logins sans connaître le type d'utilisateur à l'avance.

---

## WebSocket

**Chemin :** `websocket-server/`

Serveur Node.js pour les notifications temps réel de la messagerie.

```bash
cd websocket-server
npm install
node server.js
```

Le client tente une connexion WebSocket au démarrage. En cas d'échec, il bascule automatiquement sur du **polling HTTP** via l'API REST (fallback transparent).

Variables `.env` liées :

```env
WEBSOCKET_ENABLED=true
WEBSOCKET_CLIENT_URL=http://localhost:3000
WEBSOCKET_SERVER_URL=http://localhost:3000
JWT_SECRET=votre-secret-jwt-ici
```

---

## Configuration

Toute la configuration est dans `.env` à la racine, généré par l'installateur.

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

# ─── Sécurité ──────────────────────────────────────────────
SESSION_NAME=fronote_session
SESSION_LIFETIME=7200       # secondes (2h par défaut)
CSRF_LIFETIME=3600
MAX_LOGIN_ATTEMPTS=5
RATE_LIMIT_ATTEMPTS=5
RATE_LIMIT_DECAY=1          # minutes
TRUSTED_PROXIES=            # IP des reverse proxies de confiance (virgule-séparé)

# ─── JWT (WebSocket) ───────────────────────────────────────
JWT_SECRET=changez-ce-secret-en-production
JWT_LIFETIME=3600

# ─── WebSocket ─────────────────────────────────────────────
WEBSOCKET_ENABLED=true
WEBSOCKET_CLIENT_URL=http://localhost:3000
WEBSOCKET_SERVER_URL=http://localhost:3000

# ─── Chemins ───────────────────────────────────────────────
UPLOADS_PATH=/var/www/fronote/uploads
LOGS_PATH=/var/www/fronote/API/logs
TEMP_PATH=/var/www/fronote/temp

# ─── Mises à jour automatiques ────────────────────────────
GITHUB_REPO=votre-org/fronote
GITHUB_BRANCH=main
GITHUB_WEBHOOK_SECRET=       # Secret du webhook GitHub

# ─── SMTP (optionnel) ──────────────────────────────────────
# Configurable via admin/systeme/smtp.php
```

---

## Maintenance

### Diagnostic

`diagnostic.php` expose l'état de PHP, des extensions, de la connexion DB et des répertoires. **Accès bloqué par `.htaccess` en production** — accorder temporairement via IP ou supprimer après vérification.

### Sauvegarde

```bash
# Base de données
mysqldump -u fronote_user -p fronote > backup_$(date +%Y%m%d).sql

# Fichiers uploadés
tar -czf uploads_$(date +%Y%m%d).tar.gz uploads/

# Cron quotidien (crontab)
0 2 * * * /usr/bin/mysqldump -u fronote_user -pSECRET fronote | gzip > /backups/fronote_$(date +\%Y\%m\%d).sql.gz
```

### Mise à jour automatique

`admin/systeme/update.php` permet une mise à jour en 1 clic via GitHub. Fonctionne via `git pull` + rechargement de l'autoloader. Un webhook GitHub peut déclencher la mise à jour automatiquement (`scripts/update.php`).

### Logs

- **Logs applicatifs** : `API/logs/` (niveau configurable)
- **Audit log** : table `audit_log` en base de données (actions sensibles : login, création utilisateur, modification notes, etc.)

### Réinstallation

```bash
rm install.lock
# Puis accéder à install.php (la BDD sera recréée)
```

---

## Rôles utilisateurs

| Rôle | Accès |
|------|-------|
| **Administrateur** | Tout (admin panel, modules, utilisateurs, audit, config) |
| **Professeur** | Notes (saisie), cahier de textes, agenda, messagerie, absences (saisie), appel |
| **Élève** | Notes (consultation), devoirs, agenda, messagerie, ressources |
| **Parent** | Notes enfant(s), absences, messagerie, justificatifs, réunions |
| **Personnel** | Selon configuration (`roles_autorises` par module) |
| **Vie scolaire** | Absences, discipline, signalements, reporting, infirmerie, internat |

La visibilité des modules par rôle est configurable depuis `admin/modules/configure.php` sans déploiement.

---

## Dépannage

### L'installateur refuse de s'ouvrir

Vérifiez que votre IP est dans le réseau local (127.0.0.1, 192.168.x.x, 10.x.x.x). Pour autoriser une IP externe, ajoutez `ALLOWED_INSTALL_IP=votre.ip` dans un `.env` minimal avant d'ouvrir l'installateur.

### Erreur "Class not found"

```bash
composer dump-autoload --optimize
```

### Erreur PDO "Could not find driver"

L'extension `pdo_mysql` n'est pas activée. Sur Debian/Ubuntu :
```bash
sudo apt install php8.x-mysql && sudo systemctl restart apache2
```

### La sidebar n'affiche pas certains modules

1. Vérifiez que le module est activé dans `admin/modules/index.php`
2. Vérifiez que votre rôle est autorisé (`admin/modules/configure.php` → onglet Rôles)
3. Vérifiez que `module_key` est dans `ModuleService::$routeMap`

### Les notifications WebSocket ne fonctionnent pas

Le fallback polling est automatique. Si vous voulez activer WebSocket :
1. Assurez-vous que Node.js est installé
2. `cd websocket-server && npm install && node server.js`
3. Vérifiez `WEBSOCKET_ENABLED=true` et `WEBSOCKET_CLIENT_URL` dans `.env`

### Erreur CSRF "Token invalide"

Les tokens CSRF expirent après 1h (`CSRF_LIFETIME`) et sont à usage unique. Si plusieurs onglets sont ouverts simultanément ou si la session a expiré, ouvrez une nouvelle page pour obtenir un token frais.

### Upload de fichiers échoue silencieusement

- Vérifiez `upload_max_filesize` et `post_max_size` dans `php.ini`
- Vérifiez les permissions du répertoire `uploads/` (`chmod 775`)
- Consultez les logs dans `API/logs/`

---

## Licence

MIT — voir [LICENSE](LICENSE)

---

*Fronote v1.0.0 — Développé avec PHP vanilla, PSR-4, IoC Container, 40+ modules, 233+ tables*
