# Pronote - Système de Gestion Scolaire

Bienvenue dans le projet Pronote, une application web complète de gestion scolaire inspirée du célèbre logiciel Pronote. Cette application permet de gérer les notes, absences, cahiers de textes, messagerie et agenda dans un établissement scolaire de manière sécurisée et centralisée.

## Table des matières

1. [Prérequis](#prérequis)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Structure du projet](#structure-du-projet)
5. [Sécurité](#sécurité)
6. [Modules](#modules)
7. [Utilisation](#utilisation)
8. [Maintenance](#maintenance)
9. [Dépannage](#dépannage)
10. [Contribution](#contribution)

## Prérequis

Pour installer et utiliser cette application, vous aurez besoin de :

- PHP 7.4 ou supérieur
- MySQL 5.7+ ou MariaDB 10.3+
- Serveur web (Apache, Nginx)
- Extensions PHP requises : pdo, pdo_mysql, json, mbstring, session
- Recommandé : Extension intl pour la gestion des dates/formats internationaux

## Installation

### Méthode 1 : Installation automatique (recommandée)

1. **Téléchargement** : Téléchargez l'archive du projet et décompressez-la dans le répertoire web de votre serveur.

2. **Préparation** : Assurez-vous que votre serveur web est correctement configuré et que PHP a les autorisations d'écriture sur les dossiers:
   - `API/logs`
   - `API/config`
   - `uploads`
   - `temp`

3. **Sécurité préliminaire** : Pour une installation sécurisée, créez un fichier `.env` à la racine avec le contenu suivant, en remplaçant `VOTRE_IP` par votre adresse IP:
   ```
   ALLOWED_INSTALL_IP=VOTRE_IP
   ```

4. **Accès à l'installation** : Accédez à `http://votre-serveur/pronote/install.php` depuis votre navigateur.

5. **Configuration** : Suivez les instructions pour configurer l'application :
   - Renseignez l'URL de base de l'application (par exemple, `/pronote` ou laisser vide si à la racine)
   - Sélectionnez l'environnement (`development`, `production` ou `test`)
   - Entrez les informations de connexion à votre base de données
   - Cliquez sur "Installer"

6. **Finalisation** : Une fois l'installation terminée, vous serez redirigé vers la page de connexion.

### Méthode 2 : Installation manuelle (pour utilisateurs avancés)

1. **Téléchargement et déploiement** : Décompressez l'archive dans le répertoire web de votre serveur.

2. **Configuration** : Créez un fichier `env.php` dans le répertoire `API/config/` avec le contenu suivant :
   ```php
   <?php
   // Environnement (development, production, test)
   if (!defined('APP_ENV')) define('APP_ENV', 'production');

   // Configuration de base
   if (!defined('APP_NAME')) define('APP_NAME', 'Pronote');
   if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');

   // Configuration des URLs et chemins
   if (!defined('BASE_URL')) define('BASE_URL', '/pronote'); // Ajustez selon votre installation
   if (!defined('APP_ROOT')) define('APP_ROOT', realpath(__DIR__ . '/../../'));

   // URLs communes construites avec BASE_URL
   if (!defined('LOGIN_URL')) define('LOGIN_URL', BASE_URL . '/login/public/index.php');
   if (!defined('LOGOUT_URL')) define('LOGOUT_URL', BASE_URL . '/login/public/logout.php');
   if (!defined('HOME_URL')) define('HOME_URL', BASE_URL . '/accueil/accueil.php');

   // Configuration de la base de données
   if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
   if (!defined('DB_NAME')) define('DB_NAME', 'votre_base_de_donnees');
   if (!defined('DB_USER')) define('DB_USER', 'votre_utilisateur');
   if (!defined('DB_PASS')) define('DB_PASS', 'votre_mot_de_passe');
   if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
   
   // Configuration des sessions
   if (!defined('SESSION_NAME')) define('SESSION_NAME', 'pronote_session');
   if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600); // 1 heure
   if (!defined('SESSION_PATH')) define('SESSION_PATH', '/');
   if (!defined('SESSION_SECURE')) define('SESSION_SECURE', false); // Mettre à true en production si HTTPS
   if (!defined('SESSION_HTTPONLY')) define('SESSION_HTTPONLY', true);
   if (!defined('SESSION_SAMESITE')) define('SESSION_SAMESITE', 'Lax'); // Options: Lax, Strict, None
   ```

3. **Création des répertoires** : Assurez-vous que ces répertoires existent et sont accessibles en écriture :
   - `API/logs`
   - `uploads`
   - `temp`
   
   ```bash
   mkdir -p API/logs uploads temp
   chmod 775 API/logs uploads temp
   ```

4. **Importation de la base de données** : Importez le fichier SQL `API/schema.sql` dans votre base de données.
   ```bash
   mysql -u votre_utilisateur -p votre_base_de_donnees < API/schema.sql
   ```

5. **Finition** : Créez un fichier `install.lock` à la racine du projet pour désactiver l'installation.
   ```bash
   echo "Installation completed on: $(date)" > install.lock
   ```

## Configuration

### Configuration du serveur web

#### Apache

Voici un exemple de configuration pour Apache avec un VirtualHost :

```apache
<VirtualHost *:80>
    ServerName pronote.example.com
    DocumentRoot /chemin/vers/pronote

    <Directory "/chemin/vers/pronote">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/pronote_error.log
    CustomLog ${APACHE_LOG_DIR}/pronote_access.log combined
</VirtualHost>
```

Pour une configuration sécurisée en HTTPS (recommandé) :

```apache
<VirtualHost *:443>
    ServerName pronote.example.com
    DocumentRoot /chemin/vers/pronote

    SSLEngine on
    SSLCertificateFile /chemin/vers/certificat.crt
    SSLCertificateKeyFile /chemin/vers/cle.key

    <Directory "/chemin/vers/pronote">
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/pronote_error.log
    CustomLog ${APACHE_LOG_DIR}/pronote_access.log combined
</VirtualHost>
```

#### Nginx

Pour Nginx, voici un exemple de configuration :

```nginx
server {
    listen 80;
    server_name pronote.example.com;
    return 301 https://$host$request_uri;  # Redirection vers HTTPS
}

server {
    listen 443 ssl;
    server_name pronote.example.com;
    root /chemin/vers/pronote;
    index index.php index.html;

    ssl_certificate /chemin/vers/certificat.crt;
    ssl_certificate_key /chemin/vers/cle.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;

    # Protection contre les attaques courants
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
    
    # Restreindre l'accès aux fichiers de configuration
    location ~ ^/(API/config|\.env) {
        deny all;
        return 404;
    }
}
```

### Configuration des permissions

Pour assurer un fonctionnement optimal et sécurisé :

```bash
# Permissions de base
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Dossiers nécessitant des permissions d'écriture
chmod -R 775 API/logs
chmod -R 775 uploads
chmod -R 775 temp

# Protéger les fichiers de configuration
chmod 640 API/config/env.php
chmod 640 API/config/*.php

# Si les fichiers sont gérés par un groupe spécifique (www-data par exemple)
chown -R :www-data API/logs uploads temp
```

### Configuration de la base de données

La base de données est au cœur du système. Sa configuration correcte est essentielle:

1. **Structure des tables principales**:
   - `administrateurs`: Comptes administratifs
   - `eleves`: Informations sur les élèves
   - `professeurs`: Informations sur les enseignants
   - `classes`: Liste des classes
   - `notes`: Stockage des notes
   - `absences`: Gestion des absences
   - `messages`: Système de messagerie
   - `evenements`: Événements d'agenda
   - `matieres`: Liste des matières enseignées

2. **Optimisation des performances**:
   - Ajoutez des index sur les colonnes fréquemment recherchées
   - Exemple: `ALTER TABLE notes ADD INDEX idx_eleve_matiere (id_eleve, id_matiere);`
   - Utilisez le moteur InnoDB pour la plupart des tables

3. **Maintenance de la base de données**:
   ```sql
   -- Optimisation périodique des tables
   OPTIMIZE TABLE notes, absences, messages;
   
   -- Analyse des tables pour optimiser les requêtes
   ANALYZE TABLE notes, absences, messages;
   ```

## Structure du projet

L'application est organisée en modules distincts suivant une architecture modulaire :

```
pronote/
├── accueil/           # Page d'accueil
├── API/               # API centralisée et système d'authentification
│   ├── auth_central.php  # Système d'authentification centralisé
│   ├── config/        # Configurations et variables d'environnement
│   ├── core/          # Fonctions essentielles du système
│   └── logs/          # Journaux d'événements et d'erreurs
├── notes/             # Module de gestion des notes
├── absences/          # Module de gestion des absences
├── cahierdetextes/    # Module de cahier de textes et devoirs
├── agenda/            # Module d'agenda et d'événements
├── messagerie/        # Module de messagerie interne
├── login/             # Système d'authentification
│   ├── public/        # Pages publiques (login, reset password)
│   └── data/          # Données de configuration 
├── uploads/           # Dossier pour les fichiers uploadés
├── temp/              # Dossier temporaire
├── assets/            # Ressources partagées (CSS, JS, images)
├── install.php        # Script d'installation
├── diagnostic.php     # Outil de diagnostic système
└── README.md          # Documentation
```

## Sécurité

La sécurité est une priorité dans le développement de cette application. Voici quelques-unes des mesures mises en place :

### Protection contre les attaques courantes

- **Injections SQL** : Toutes les requêtes utilisent des requêtes préparées (PDO)
- **XSS (Cross-Site Scripting)** : Échappement systématique des sorties avec `htmlspecialchars()`
- **CSRF (Cross-Site Request Forgery)** : Utilisation de jetons CSRF pour les formulaires
- **Fixation de session** : Régénération des identifiants de session après connexion

### Sécurité des mots de passe

- Les mots de passe sont hachés avec `password_hash()` utilisant l'algorithme par défaut
- Règles strictes de complexité: minimum 12 caractères, majuscules, minuscules, chiffres, caractères spéciaux
- Possibilité de réinitialisation sécurisée des mots de passe

### Protection des données

- Validation et assainissement des entrées utilisateur
- Protection des données sensibles lors des communications (HTTPS recommandé)
- Séparation des environnements (développement, test, production)
- Gestion des droits d'accès basée sur les rôles (RBAC)

### Bonnes pratiques supplémentaires

- Mise en œuvre d'un système de journalisation des événements importants
- Limitation des tentatives de connexion échouées
- Protection contre les attaques par force brute
- Analyse régulière des journaux de sécurité

### Audit et maintenance de sécurité

Pour maintenir un niveau de sécurité optimal:

1. **Audits réguliers**:
   ```bash
   # Vérifier les permissions des fichiers sensibles
   find . -name "*.php" -type f -perm /o+w -exec ls -l {} \;
   
   # Rechercher les fichiers de configuration mal protégés
   find . -name "config*.php" -o -name "*.env" | xargs ls -la
   ```

2. **Mises à jour de sécurité**:
   - Surveiller les mises à jour de PHP et des bibliothèques utilisées
   - Appliquer les correctifs de sécurité rapidement

## Modules

### Module Accueil

Le module d'accueil présente une vue d'ensemble des informations importantes pour l'utilisateur connecté :
- Pour les élèves : emploi du temps, dernières notes, devoirs à faire
- Pour les professeurs : emploi du temps, prochains cours, dernières actualités
- Pour les administrateurs : statistiques, alertes système

**Accès** : `/accueil/accueil.php`

### Module Notes

Ce module permet la gestion des notes des élèves :
- Ajout, modification et suppression de notes
- Consultation des moyennes
- Génération de bulletins
- Filtrage par classe, matière, trimestre

**Fonctionnalités principales** :
- **Ajout de notes** : `/notes/ajouter_note.php`
- **Consultation des notes** : `/notes/notes.php`
- **Modification d'une note** : `/notes/modifier_note.php?id=[ID_NOTE]`
- **Suppression d'une note** : `/notes/supprimer_note.php?id=[ID_NOTE]`
- **Interface des notes** : `/notes/interface_notes.php`

**Calcul des moyennes** :
- Moyennes par matière, par trimestre et générale
- Prise en compte des coefficients
- Génération automatique des rangs

### Module Absences

Ce module gère les absences et retards des élèves :
- Saisie des absences et retards
- Justification des absences
- Statistiques d'absences
- Filtrage par élève, classe, période

**Fonctionnalités principales** :
- **Saisie d'absences** : `/absences/ajouter_absence.php`
- **Faire l'appel** : `/absences/appel.php`
- **Consultation des absences** : `/absences/absences.php`
- **Gestion des justificatifs** : `/absences/justificatifs.php`
- **Détails d'une absence** : `/absences/details_absence.php?id=[ID_ABSENCE]`
- **Statistiques** : `/absences/statistiques.php`

### Module Cahier de textes

Ce module permet de gérer les devoirs et le contenu des cours :
- Ajout et modification de devoirs à faire
- Consultation du travail à faire
- Suivi du programme

**Fonctionnalités principales** :
- **Ajout de devoir** : `/cahierdetextes/ajouter_devoir.php`
- **Consultation des devoirs** : `/cahierdetextes/cahierdetextes.php`
- **Modification d'un devoir** : `/cahierdetextes/modifier_devoir.php?id=[ID_DEVOIR]`
- **Détails d'un devoir** : `/cahierdetextes/details_devoir.php?id=[ID_DEVOIR]`

### Module Agenda

Ce module gère les événements et l'agenda de l'établissement :
- Création d'événements
- Consultation de l'agenda
- Gestion des rendez-vous

**Fonctionnalités principales** :
- **Ajout d'événement** : `/agenda/ajouter_evenement.php`
- **Consultation de l'agenda** : `/agenda/agenda.php`
- **Modification d'un événement** : `/agenda/modifier_evenement.php?id=[ID_EVENEMENT]`
- **Détails d'un événement** : `/agenda/details_evenement.php?id=[ID_EVENEMENT]`

### Module Messagerie

Ce module permet la communication interne entre les différents acteurs :
- Envoi de messages
- Gestion des conversations
- Notifications

**Fonctionnalités principales** :
- **Consultation des messages** : `/messagerie/index.php`
- **Nouvelle conversation** : `/messagerie/nouvelle_conversation.php`
- **Lecture d'une conversation** : `/messagerie/conversation.php?id=[ID_CONVERSATION]`
- **API de statut de lecture** : `/messagerie/api/read_status.php`

## Utilisation

Après l'installation et la configuration, voici comment utiliser l'application :

1. **Connexion** : Accédez à la page de connexion à l'URL configurée (par exemple, `http://votre-serveur/pronote/login/public/index.php`).
2. **Tableau de bord** : Après connexion, vous serez redirigé vers le tableau de bord adapté à votre rôle (élève, professeur, administrateur, etc.).
3. **Navigation** : Utilisez le menu pour naviguer entre les différents modules (notes, absences, cahier de textes, agenda, messagerie).
4. **Déconnexion** : Pour vous déconnecter, cliquez sur le lien de déconnexion dans le menu.

### Workflows principaux par rôle

#### Professeurs
1. **Saisie des notes**:
   - Naviguer vers le module Notes
   - Choisir "Ajouter une note"
   - Sélectionner la classe et la matière
   - Entrer les notes des élèves

2. **Faire l'appel**:
   - Naviguer vers le module Absences
   - Sélectionner "Faire l'appel"
   - Choisir la classe et le créneau
   - Marquer les élèves absents ou en retard

#### Élèves
1. **Consulter les notes**:
   - Naviguer vers le module Notes
   - Visualiser les notes par matière ou par trimestre

2. **Justifier une absence**:
   - Naviguer vers le module Absences
   - Sélectionner l'absence à justifier
   - Fournir un justificatif numérique

#### Administrateurs
1. **Gestion des utilisateurs**:
   - Accéder au panneau d'administration
   - Sélectionner "Gestion des utilisateurs"
   - Ajouter, modifier ou désactiver des comptes

2. **Consultation des statistiques**:
   - Naviguer vers le module approprié
   - Sélectionner les rapports souhaités
   - Filtrer par période, classe ou matière

## Maintenance

Pour assurer le bon fonctionnement de l'application, voici quelques tâches de maintenance régulières :

### Sauvegardes

Il est crucial de mettre en place un système de sauvegarde régulier:

```bash
# Exemple de script de sauvegarde MySQL
#!/bin/bash
DATE=$(date +%Y-%m-%d)
BACKUP_DIR="/chemin/vers/backups"
DB_USER="votre_utilisateur"
DB_PASS="votre_mot_de_passe"
DB_NAME="pronote_db"

# Créer le répertoire de sauvegarde si nécessaire
mkdir -p $BACKUP_DIR

# Sauvegarde de la base de données
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/pronote_db_$DATE.sql.gz

# Sauvegarde des fichiers importants
tar -czf $BACKUP_DIR/pronote_files_$DATE.tar.gz /chemin/vers/pronote/uploads /chemin/vers/pronote/API/config

# Conserver seulement les 30 derniers jours de sauvegarde
find $BACKUP_DIR -name "pronote_*" -type f -mtime +30 -delete
```

### Nettoyage des fichiers temporaires

```bash
# Nettoyage des fichiers temporaires de plus de 7 jours
find /chemin/vers/pronote/temp -type f -mtime +7 -delete

# Rotation des journaux
find /chemin/vers/pronote/API/logs -name "*.log" -type f -mtime +30 -delete
```

### Optimisation des performances

Pour maintenir les performances optimales:

1. **Compression des ressources statiques**:
   - Activer la compression gzip dans votre serveur web
   - Utiliser des outils de minification pour CSS/JS

2. **Mise en cache**:
   - Configurer le cache navigateur pour les ressources statiques
   - Optimiser le cache des requêtes fréquentes

3. **Monitoring**:
   - Surveiller l'utilisation des ressources (CPU, mémoire)
   - Analyser les temps de réponse et optimiser les requêtes lentes

### Mises à jour

Les mises à jour de sécurité doivent être appliquées rapidement:

1. **Vérifier régulièrement les mises à jour**
2. **Effectuer une sauvegarde complète avant toute mise à jour**
3. **Tester les mises à jour dans un environnement de test avant déploiement en production**
4. **Documenter chaque mise à jour avec les changements effectués**

## Dépannage

### Problèmes courants

1. **Erreur de connexion à la base de données**
   - **Symptômes**: Message d'erreur "Could not connect to database" ou page blanche
   - **Causes possibles**:
     - Identifiants de connexion incorrects dans `API/config/env.php`
     - Base de données inaccessible ou arrêtée
     - Réseau entre le serveur web et la BD bloqué
   - **Solutions**:
     - Vérifier les paramètres de connexion (hôte, nom d'utilisateur, mot de passe)
     - S'assurer que le serveur MySQL est en cours d'exécution
     - Tester la connectivité avec la commande: `telnet [db_host] 3306`
     - Vérifier les journaux MySQL pour détecter d'éventuelles erreurs

2. **Erreur "Headers already sent"**
   - **Symptômes**: Erreur PHP mentionnant "headers already sent"
   - **Causes possibles**:
     - Caractères ou espaces avant `<?php`
     - Sortie HTML avant les redirections
     - BOM UTF-8 au début des fichiers PHP
   - **Solutions**:
     - Ajouter `ob_start();` au début des scripts
     - Vérifier l'absence de caractères avant `<?php`
     - Utiliser un éditeur qui sauvegarde sans BOM UTF-8
     - Convertir les fichiers avec: `sed -i '1s/^\xEF\xBB\xBF//' fichier.php`

3. **Problèmes d'authentification**
   - **Symptômes**: Impossibilité de se connecter, redirections en boucle
   - **Causes possibles**:
     - Sessions PHP mal configurées
     - Chemins de cookies incorrects
     - Problème de permissions sur le répertoire de session
   - **Solutions**:
     - Vérifier la configuration des sessions dans `php.ini`
     - Vérifier que `session.save_path` est accessible en écriture
     - Tester avec la fonction `session_save_path()`
     - Analyser avec le script de diagnostic (`/diagnostic.php`)

4. **Pages inaccessibles ou introuvables**
   - **Symptômes**: Erreurs 404, redirections incorrectes
   - **Causes possibles**:
     - Mauvaise configuration de `BASE_URL`
     - Problème de réécriture d'URL (.htaccess)
     - Permissions insuffisantes sur les fichiers
   - **Solutions**:
     - Vérifier la valeur de `BASE_URL` dans `API/config/env.php`
     - S'assurer que les fichiers `.htaccess` sont correctement configurés
     - Vérifier que mod_rewrite est activé sur Apache
     - Utiliser l'outil de diagnostic des chemins: `API/tools/path_debug.php`

5. **Affichage incorrect ou problèmes CSS/JS**
   - **Symptômes**: Interface graphique brisée, fonctionnalités JavaScript non fonctionnelles
   - **Causes possibles**:
     - Chemins relatifs incorrects vers les ressources
     - Conflits entre bibliothèques
     - Compression ou minification défectueuse
   - **Solutions**:
     - Utiliser les outils de développement du navigateur (F12)
     - Vérifier les chemins vers les ressources dans l'inspecteur réseau
     - Corriger les chemins dans les balises `<link>` et `<script>`
     - Tester en désactivant temporairement la minification

### Page de diagnostic

Une page de diagnostic complète est disponible pour les administrateurs à l'adresse suivante:
`/diagnostic.php`

Cette page vérifie:
- La configuration du système
- Les permissions des répertoires
- La connectivité à la base de données
- L'accessibilité des modules
- La sécurité et les vulnérabilités potentielles

Pour les problèmes plus spécifiques:

- **Problèmes d'authentification**: `/API/tools/function_inspector.php`
- **Problèmes de chemins**: `/API/tools/path_debug.php`
- **Débogage de session**: `/login/public/debug.php`

### Journaux d'erreur

Consultez les journaux pour identifier les problèmes:

- **Journal d'erreurs PHP**: Généralement dans `/var/log/apache2/error.log` ou défini dans `php.ini`
- **Journal d'erreurs de l'application**: `API/logs/error_YYYY-MM-DD.log`
- **Journal d'accès**: `API/logs/access_YYYY-MM-DD.log`
- **Journal des requêtes SQL lentes**: Configurable dans MySQL/MariaDB

## Contribution

Pour contribuer au projet :

1. Familiarisez-vous avec la structure du code et les conventions
2. Suivez les normes de codage PHP PSR-12
3. Documentez vos modifications
4. Testez vos modifications sur les différents rôles d'utilisateurs

### Directives de contribution

1. **Structure du code**:
   - Respectez l'architecture modulaire
   - Placez les classes partagées dans le répertoire `API/core/`
   - Les fonctions spécifiques à un module restent dans ce module

2. **Convention de nommage**:
   - Fichiers: `nom_de_fichier.php` (snake_case)
   - Classes: `NomDeClasse` (PascalCase)
   - Fonctions et méthodes: `nomDeFonction()` (camelCase)
   - Variables: `$nom_variable` (snake_case)
   - Constantes: `NOM_CONSTANTE` (UPPER_SNAKE_CASE)

3. **Documentation**:
   - Commentaires de fonction au format PHPDoc
   - READMEs pour chaque module principal
   - Documentation des API dans des fichiers Markdown

4. **Tests**:
   - Écrire des tests unitaires quand c'est possible
   - Tester sur différents navigateurs et environnements
   - Vérifier la compatibilité avec différentes versions de PHP

### Workflow de développement recommandé

1. Créer une branche dédiée pour chaque nouvelle fonctionnalité
2. Développer et tester la fonctionnalité
3. Soumettre une demande de fusion (pull request)
4. Après revue de code, la branche est fusionnée

### Rapporter des bugs

Pour signaler un problème:
1. Vérifiez d'abord si le problème n'a pas déjà été signalé
2. Utilisez le modèle de rapport de bug
3. Fournissez autant d'informations que possible (navigateur, version PHP, étapes pour reproduire)

---

## Support et contact

Pour toute question ou assistance:
- Consultez la documentation détaillée
- Contactez l'équipe technique à support@example.com

---

**Note sur la sécurité**: Si vous découvrez une vulnérabilité de sécurité, veuillez nous contacter directement par email à securite@example.com plutôt que de créer un rapport public.

---

© 2023 Projet Pronote - Tous droits réservés