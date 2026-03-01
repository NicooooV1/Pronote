# 🎓 Fronote — Système de Gestion Scolaire

Fronote est une application web complète de gestion d'établissement scolaire, développée en **PHP vanilla** (sans framework). Elle couvre la gestion des notes, absences, cahier de textes, agenda, messagerie interne et administration, avec une architecture API centralisée et un système d'authentification unifié.

---

## Table des matières

- [Fonctionnalités](#fonctionnalités)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Architecture](#architecture)
- [Modules](#modules)
  - [Accueil](#accueil)
  - [Notes](#notes)
  - [Cahier de textes](#cahier-de-textes)
  - [Agenda](#agenda)
  - [Messagerie](#messagerie)
  - [Absences](#absences)
  - [Administration](#administration)
  - [Authentification](#authentification)
- [API centralisée](#api-centralisée)
- [Sécurité](#sécurité)
- [Base de données](#base-de-données)
- [WebSocket](#websocket)
- [Configuration](#configuration)
- [Maintenance](#maintenance)
- [Structure du projet](#structure-du-projet)

---

## Fonctionnalités

| Module | Description |
|--------|-------------|
| **Notes** | Saisie, modification, consultation des notes par matière/classe/période avec calcul de moyennes |
| **Absences** | Suivi des absences et retards, soumission et traitement de justificatifs avec pièces jointes |
| **Cahier de textes** | Création de devoirs avec fichiers joints, suivi des statuts élève (à faire, rendu…) |
| **Agenda** | Calendrier avec vues jour/semaine/liste, gestion d'événements récurrents |
| **Messagerie** | Conversations privées, annonces, messages de classe, pièces jointes, notifications temps réel |
| **Administration** | Gestion des utilisateurs, classes, matières, périodes, audit, statistiques |
| **Dashboard** | Tableau de bord personnalisé selon le rôle (élève, parent, professeur, admin) |

---

## Prérequis

- **PHP** 7.4+ (8.x recommandé)
- **MySQL** 5.7+ ou **MariaDB** 10.3+
- **Serveur web** : Apache (avec `mod_rewrite`) ou Nginx
- **Extensions PHP** : `pdo`, `pdo_mysql`, `json`, `mbstring`, `session`, `fileinfo`
- **Node.js** 16+ *(optionnel, pour le serveur WebSocket)*

---

## Installation

Fronote dispose d'un **assistant d'installation en 5 étapes** accessible via le navigateur.

### 1. Déploiement des fichiers

Clonez ou décompressez le projet dans le répertoire web de votre serveur :

```bash
git clone https://github.com/votre-repo/fronote.git /var/www/fronote
```

### 2. Permissions

```bash
chown -R www-data:www-data /var/www/fronote
chmod -R 755 /var/www/fronote
```

### 3. Restriction d'accès à l'installateur *(optionnel)*

Créez un fichier `.env` à la racine avec votre IP :

```
ALLOWED_INSTALL_IP=203.0.113.42
```

Par défaut, seul le réseau local (127.0.0.1, 192.168.x.x, 10.x.x.x, 172.16-31.x.x) est autorisé.

### 4. Lancer l'assistant

Ouvrez dans votre navigateur :

```
http://votre-serveur/fronote/install.php
```

L'assistant guide à travers :

| Étape | Description |
|-------|-------------|
| **1. Pré-requis** | Vérification PHP, extensions, répertoires et fichiers |
| **2. Base de données** | Test de connexion MySQL avec diagnostic d'erreur détaillé |
| **3. Application** | Configuration (nom, URL, sécurité, sessions) et établissement (nom, type, périodes) |
| **4. Administrateur** | Création du compte admin avec validation de la force du mot de passe |
| **5. Installation** | Récapitulatif → exécution (SQL, .env, protections, données initiales, test API) |

L'installateur crée automatiquement :
- Le fichier `.env` avec tous les paramètres
- La base de données et toutes les tables (~30 tables)
- Les répertoires protégés (`uploads/`, `temp/`, `API/logs/`)
- Les sous-dossiers d'upload (`messagerie/`, `devoirs/`, `justificatifs/`)
- Les fichiers `.htaccess` de sécurité
- Le fichier `etablissement.json` avec classes et matières par défaut
- Les périodes scolaires (trimestres ou semestres)
- Un fichier `install.lock` empêchant toute réinstallation

### 5. Connexion

Connectez-vous via `login/index.php` avec l'identifiant `admin` et le mot de passe choisi.

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Navigateur (client)                   │
└────────────┬──────────────────────┬─────────────────────┘
             │ HTTP                 │ WebSocket
             ▼                     ▼
┌────────────────────┐   ┌──────────────────┐
│  Pages PHP (vues)  │   │  websocket-server│
│  + templates/      │   │  (Node.js)       │
└────────┬───────────┘   └──────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│                    API/ (couche métier)                  │
│                                                         │
│  ┌──────────┐ ┌──────────┐ ┌────────────┐ ┌─────────┐  │
│  │AuthManager│ │RateLimiter│ │CsrfManager│ │Container│  │
│  └──────────┘ └──────────┘ └────────────┘ └─────────┘  │
│                                                         │
│  ┌──────────────────────────────────────────────────┐   │
│  │  Services : FileUploadService, getPDO(), etc.    │   │
│  └──────────────────────────────────────────────────┘   │
└────────────────────────┬────────────────────────────────┘
                         │ PDO
                         ▼
                ┌──────────────────┐
                │   MySQL / MariaDB│
                │   (pronote.sql)  │
                └──────────────────┘
```

### Principes

- **PHP vanilla** : aucun framework, PDO pour la base de données
- **PSR-4 autoloading** : namespace `API\` chargé via `API/bootstrap.php`
- **IoC Container** : `API/Core/Container.php` avec injection de dépendances
- **Templates partagés** : header, sidebar, topbar, footer dans `templates/`
- **Services centralisés** : upload, authentification, rate limiting via l'API
- **Séparation MVC légère** : chaque module a ses vues, includes et assets

---

## Modules

### Accueil

**Chemin** : `accueil/`

Tableau de bord personnalisé selon le rôle de l'utilisateur connecté :
- **Élève** : notes récentes, devoirs à venir, absences, messages non lus
- **Parent** : vue enfant(s), absences, notes, convocations
- **Professeur** : classes, dernières notes saisies, devoirs, agenda
- **Administrateur** : statistiques globales, alertes système

### Notes

**Chemin** : `notes/`

- Saisie et modification de notes par le professeur
- Interface de consultation pour les élèves et parents
- Calcul de moyennes par matière, classe et période
- Export et statistiques
- Filtrage par classe, matière, période scolaire

### Cahier de textes

**Chemin** : `cahierdetextes/`

- Création de devoirs (contenu, date d'échéance, pièces jointes)
- Upload de fichiers via `FileUploadService` (contexte `devoirs`, max 10 Mo, 5 fichiers)
- Suivi des statuts élève : non lu → à faire → en cours → terminé
- Téléchargement sécurisé des pièces jointes (via `telecharger.php`)
- **Service** : `DevoirService` pour la logique métier

### Agenda

**Chemin** : `agenda/`

- Vues : jour, semaine, liste
- Création, modification, suppression d'événements
- Support des événements récurrents (quotidien, hebdomadaire, mensuel, annuel)
- Modèle `Evenement` avec helpers de calendrier
- Filtrage par type et période

### Messagerie

**Chemin** : `messagerie/`

- **Conversations privées** : messages individuels entre utilisateurs
- **Annonces** : diffusion à un ou plusieurs groupes (classe, tous les profs…)
- **Messages de classe** : diffusion ciblée par classe
- **Pièces jointes** : upload via `FileUploadService` (contexte `messagerie`, max 5 Mo, 10 fichiers)
- **Réactions** : emojis sur les messages
- **Notifications temps réel** : via WebSocket (avec fallback polling)
- **API REST** : `API/endpoints/messagerie.php` (point d'entrée centralisé)
- **Anti-abus** : rate limiting sur l'envoi de messages
- **Templates** : header, sidebar, modals dans `messagerie/templates/`

### Absences

**Chemin** : `absences/`

- Enregistrement des absences et retards
- Soumission de justificatifs avec pièces jointes (contexte `justificatifs`, max 5 Mo)
- Workflow de traitement : soumis → accepté / refusé
- Statistiques et vue calendrier
- Vue liste et vue calendrier
- Téléchargement sécurisé des pièces jointes

### Administration

**Chemin** : `admin/`

Module complet de gestion de l'établissement :

| Sous-module | Fonctionnalités |
|-------------|-----------------|
| **Utilisateurs** (`users/`) | CRUD admins/profs/élèves/parents, reset mot de passe, sessions actives, profils AJAX |
| **Classes** (`classes/`) | Gestion des classes, affectation élèves-professeurs |
| **Établissement** (`etablissement/`) | Infos, matières, périodes scolaires, événements |
| **Scolaire** (`scolaire/`) | Vue admin sur notes, absences, devoirs, justificatifs |
| **Messagerie** (`messagerie/`) | Annonces système, conversations, modération |
| **Système** (`systeme/`) | Audit log, statistiques globales |

### Authentification

**Chemin** : `login/`

- Formulaire de connexion par identifiant + mot de passe + type de compte
- 5 types d'utilisateurs : `administrateur`, `professeur`, `eleve`, `parent`, `personnel`
- Vue unifiée `v_users` (UNION des 5 tables) pour la résolution de login
- Protection brute-force via `RateLimiter` (centralisé dans `API/Services/UserService.php`)
- Sessions sécurisées avec `session_regenerate_id()`
- Jeton CSRF sur tous les formulaires
- Réinitialisation de mot de passe en 3 étapes (demande → code → changement)
- Page d'inscription admin-only pour créer des comptes utilisateur

---

## API centralisée

Le dossier `API/` contient la couche métier partagée par tous les modules.

### Point d'entrée

```php
require_once __DIR__ . '/../API/bootstrap.php';
```

Charge l'autoloader PSR-4, initialise le conteneur IoC et rend disponibles les services.

### Endpoints centralisés

Tous les points d'entrée AJAX/REST sont regroupés dans `API/endpoints/` :

| Endpoint | Description | Paramètres |
|----------|-------------|------------|
| `messagerie.php` | API REST complète de la messagerie | `?resource=conversations\|messages\|participants\|notifications\|search\|reactions&action=...` |
| `agenda_persons.php` | Recherche de personnes pour les événements | `?visibility=eleves\|professeurs\|parents\|classes:NomClasse` |
| `notes_eleves.php` | Liste des élèves par classe (notes) | `?classe=NomClasse` |

Les modules JavaScript appellent directement ces endpoints au lieu d'avoir chacun leur propre dossier `api/`.

### Services principaux

| Service | Classe | Description |
|---------|--------|-------------|
| **Auth** | `API\Auth\AuthManager` | Authentification unifiée multi-tables |
| **Rate Limiter** | `API\Security\RateLimiter` | Limitation de débit par IP (base de données) |
| **CSRF** | `API\Security\CsrfManager` | Tokens CSRF avec rotation automatique |
| **File Upload** | `API\Services\FileUploadService` | Upload centralisé avec validation MIME et contextes |
| **Database** | `API\Database\Connection` | Singleton PDO via `getPDO()` |

### FileUploadService

Service centralisé remplaçant les 3 anciens gestionnaires d'upload (messagerie, cahier de textes, absences).

```php
use API\Services\FileUploadService;

$uploader = new FileUploadService('devoirs');  // contexte : devoirs|messagerie|justificatifs

// Upload multiple
$results = $uploader->uploadMultiple($_FILES['fichiers']);
foreach ($results as $r) {
    if ($r['success']) {
        // $r['nom_original'], $r['chemin'], $r['type_mime'], $r['taille']
    }
}

// Téléchargement sécurisé (headers + readfile)
$uploader->serve($relativePath, $originalName);

// Suppression
$uploader->delete($relativePath);

// Utilitaires statiques
FileUploadService::formatBytes(1048576);     // "1.00 MB"
FileUploadService::getFileIcon('image/png'); // "file-image"
```

**Contextes configurés** :

| Contexte | Taille max | Fichiers max | Types autorisés |
|----------|-----------|-------------|-----------------|
| `messagerie` | 5 Mo | 10 | images, PDF, documents, archives |
| `devoirs` | 10 Mo | 5 | images, PDF, documents, archives |
| `justificatifs` | 5 Mo | 5 | images, PDF, documents |

Tous les fichiers sont stockés dans `uploads/{contexte}/YYYY/MM/` avec noms aléatoires et validation MIME via `finfo`.

---

## Sécurité

### Authentification & sessions

- Mots de passe hashés **BCRYPT** (cost 12)
- Sessions avec `cookie_httponly`, `cookie_secure`, `SameSite=Lax`
- `session_regenerate_id()` après authentification
- Durée de session configurable (défaut : 2h)

### Protection des accès

- **Rate limiting** : limitation par IP en base de données (table `rate_limits`)
- **CSRF** : tokens rotatifs avec durée de vie configurable
- **Brute-force** : verrouillage temporaire après N tentatives de login
- **Audit** : toutes les actions sensibles sont journalisées dans `audit_log`

### Protection des fichiers

- `.htaccess` racine : bloque l'accès direct à `.env`, `install.php`, `install.lock`, `*.sql`, `*.bak`
- `uploads/.htaccess` : `Deny from all` — les fichiers sont servis uniquement via PHP
- `temp/.htaccess` et `API/logs/.htaccess` : `Deny from all`
- Upload : validation MIME réelle (`finfo`), allowlist d'extensions, nommage aléatoire (`random_bytes`)
- L'installateur est verrouillé par IP (réseau local uniquement par défaut)

---

## Base de données

Le schéma complet est dans `pronote.sql` (~580 lignes, ~30 tables + 1 vue).

### Tables principales

| Section | Tables |
|---------|--------|
| **Référentielles** | `classes`, `matieres`, `periodes`, `professeur_classes` |
| **Utilisateurs** | `administrateurs`, `professeurs`, `eleves`, `parents`, `personnel`, `parent_eleve` |
| **Notes** | `notes` |
| **Absences** | `absences`, `retards`, `justificatifs`, `justificatif_fichiers` |
| **Cahier de textes** | `devoirs`, `devoirs_fichiers`, `devoirs_statuts_eleve` |
| **Agenda** | `evenements`, `evenements_recurrents` |
| **Messagerie** | `conversations`, `conversation_participants`, `messages`, `message_attachments`, `message_reactions`, `announcements`, `announcement_recipients`, `notification_preferences` |
| **Sécurité** | `rate_limits`, `audit_log` |

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

**Chemin** : `websocket-server/`

Serveur Node.js pour les notifications temps réel de la messagerie.

```bash
cd websocket-server
npm install
node server.js
```

Le client JavaScript tente d'abord une connexion WebSocket. En cas d'échec, il bascule automatiquement sur du **polling HTTP** via l'API REST.

---

## Configuration

Toute la configuration est dans le fichier `.env` à la racine, généré par l'installateur.

### Variables principales

```env
# Base de données
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=pronote
DB_USER=pronote_user
DB_PASS=secret
DB_CHARSET=utf8mb4

# Application
APP_NAME=Fronote
APP_ENV=production        # production | development | test
APP_DEBUG=false
APP_URL=https://mon-ecole.fr/fronote
BASE_URL=/fronote

# Sécurité
SESSION_NAME=pronote_session
SESSION_LIFETIME=7200     # 2 heures
CSRF_LIFETIME=3600
MAX_LOGIN_ATTEMPTS=5
RATE_LIMIT_ATTEMPTS=5
RATE_LIMIT_DECAY=1        # minutes

# Chemins
UPLOADS_PATH=/var/www/fronote/uploads
LOGS_PATH=/var/www/fronote/API/logs
TEMP_PATH=/var/www/fronote/temp
```

---

## Maintenance

### Diagnostic

Accédez à `diagnostic.php` pour vérifier l'état du système (PHP, extensions, base de données, fichiers).

### Réinstallation

Pour réinstaller :

1. Supprimez `install.lock` à la racine
2. Accédez à `install.php`
3. L'assistant relancera la procédure complète (la base sera recréée)

### Sauvegarde

```bash
# Base de données
mysqldump -u pronote_user -p pronote > backup_$(date +%Y%m%d).sql

# Fichiers uploadés
tar -czf uploads_$(date +%Y%m%d).tar.gz uploads/
```

### Logs

Les logs applicatifs sont dans `API/logs/`. Les logs d'audit sont en base dans la table `audit_log`.

---

## Structure du projet

```
fronote/
├── .env                        # Configuration (généré par install.php)
├── install.php                 # Assistant d'installation 5 étapes
├── install.lock                # Verrou post-installation
├── install_guard.php           # Protection de l'installateur
├── diagnostic.php              # Diagnostic système
├── pronote.sql                 # Schéma complet (~30 tables + vue)
│
├── API/                        # Couche métier centralisée (namespace API\)
│   ├── bootstrap.php           # Autoloader PSR-4 + init container
│   ├── core.php                # Fonctions utilitaires (getPDO, etc.)
│   ├── endpoints/              # Points d'entrée AJAX/REST centralisés
│   │   ├── messagerie.php      # API REST messagerie (conversations, messages…)
│   │   ├── agenda_persons.php  # Recherche de personnes (agenda)
│   │   └── notes_eleves.php    # Liste élèves par classe (notes)
│   ├── Auth/                   # AuthManager, SessionGuard
│   ├── Core/                   # Container IoC, helpers
│   ├── Database/               # Connection PDO singleton
│   ├── Security/               # RateLimiter, CsrfManager
│   ├── Services/               # FileUploadService
│   ├── Providers/              # Service providers
│   ├── config/                 # Config runtime
│   └── logs/                   # Logs applicatifs
│
├── templates/                  # Templates HTML partagés
│   ├── shared_header.php       # <head>, CSS, meta
│   ├── shared_sidebar.php      # Navigation latérale
│   ├── shared_topbar.php       # Barre supérieure
│   └── shared_footer.php       # Scripts, fermeture HTML
│
├── uploads/                    # Fichiers utilisateur (protégé par .htaccess)
│   ├── messagerie/             # Pièces jointes messagerie
│   ├── devoirs/                # Fichiers cahier de textes
│   └── justificatifs/          # Pièces jointes justificatifs
│
├── accueil/                    # Module dashboard
├── notes/                      # Module notes
├── cahierdetextes/             # Module cahier de textes
├── agenda/                     # Module agenda
├── messagerie/                 # Module messagerie (API dans API/endpoints/)
├── absences/                   # Module absences & justificatifs
├── admin/                      # Module administration
├── login/                      # Module authentification
│   ├── index.php               # Formulaire de connexion
│   ├── register.php            # Inscription (admin-only)
│   ├── reset_password.php      # Demande de réinitialisation
│   └── assets/                 # CSS/JS du module login
│
├── assets/                     # CSS/JS globaux
├── temp/                       # Fichiers temporaires
└── websocket-server/           # Serveur WebSocket Node.js
    └── server.js
```

---

## Rôles utilisateurs

| Rôle | Accès |
|------|-------|
| **Administrateur** | Tout (admin panel, gestion utilisateurs, audit, config) |
| **Professeur** | Notes (saisie), cahier de textes, agenda, messagerie, absences (saisie) |
| **Élève** | Notes (consultation), cahier de textes, agenda, messagerie |
| **Parent** | Notes enfant(s), absences enfant(s), messagerie, justificatifs |
| **Personnel** | Accès limité selon configuration |

---

## Licence

© 2026 Projet Fronote — Tous droits réservés.
