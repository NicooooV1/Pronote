# Guide d'installation — Fronote

> Ce document est destiné aux **administrateurs système et responsables informatiques** des établissements scolaires.
> Il ne nécessite aucune connaissance du développement logiciel.

---

## Ce que vous avez reçu

Votre pack d'installation Fronote contient les éléments suivants :

| Élément | Forme | Description |
|---------|-------|-------------|
| **Archive du projet** | `fronote-vX.X.X.zip` | L'ensemble des fichiers de l'application |
| **Clé webhook** | Chaîne de caractères | Votre clé unique pour les mises à jour automatiques |
| **Identifiants de contact** | Email / ticket | Pour joindre le support |

> La clé webhook est **unique à votre installation**. Ne la partagez pas et conservez-la précieusement.

---

## Prérequis

Avant de commencer, assurez-vous que votre serveur dispose de :

| Logiciel | Version minimale | Vérification |
|----------|-----------------|--------------|
| PHP | 8.0+ | `php -v` |
| MySQL | 5.7+ ou MariaDB 10.3+ | `mysql --version` |
| Apache | 2.4+ | `apache2 -v` |
| Composer | 2.x | `composer --version` |
| Extension PHP pdo_mysql | — | `php -m \| grep pdo_mysql` |
| Extension PHP mbstring | — | `php -m \| grep mbstring` |

**Apache :** le module `mod_rewrite` doit être activé :
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

---

## Étape 1 — Déposer les fichiers

Extrayez l'archive dans le répertoire web de votre serveur :

```bash
# Exemple sur un serveur Linux avec Apache
unzip fronote-vX.X.X.zip -d /var/www/fronote
```

Définissez les permissions :
```bash
chown -R www-data:www-data /var/www/fronote
chmod -R 755 /var/www/fronote
chmod -R 775 /var/www/fronote/uploads  # Écriture nécessaire pour les fichiers
```

---

## Étape 2 — Installer les dépendances PHP

```bash
cd /var/www/fronote
composer install --no-dev --optimize-autoloader
```

> Si `composer` n'est pas installé :
> ```bash
> sudo apt install composer    # Debian/Ubuntu
> ```

---

## Étape 3 — Assistant d'installation

Ouvrez votre navigateur et accédez à :

```
http://votre-serveur/fronote/install.php
```

> Par défaut, l'assistant n'est accessible **que depuis le réseau local** (192.168.x.x, 10.x.x.x…).
> Si vous installez à distance, contactez le support pour obtenir la procédure d'accès temporaire.

L'assistant vous guide en **5 étapes** :

| Étape | Ce qui se passe |
|-------|-----------------|
| **1. Pré-requis** | Vérification automatique de PHP, extensions, répertoires |
| **2. Base de données** | Saisir l'hôte, le nom, l'utilisateur et le mot de passe MySQL |
| **3. Application** | Nom de l'établissement, URL du site, paramètres de session |
| **4. Administrateur** | Créer le premier compte administrateur |
| **5. Finalisation** | Création de la base de données, génération du fichier de configuration, tests |

> **Protection contre l'écrasement** : si la base de données existe déjà et contient des tables, l'assistant affiche un avertissement avec le nombre de tables détectées. Vous devrez cocher explicitement une case de confirmation avant de procéder, ou revenir en arrière pour choisir un autre nom de base.

À la fin de l'assistant, un fichier `install.lock` est créé automatiquement — il **bloque** toute réinstallation accidentelle.

---

## Étape 4 — Première connexion

Rendez-vous sur la page de connexion :

```
http://votre-serveur/fronote/login/index.php
```

Connectez-vous avec le compte **administrateur** créé à l'étape 3.

---

## Étape 5 — Configurer les mises à jour automatiques

Fronote peut se mettre à jour automatiquement dès qu'une nouvelle version est publiée, grâce à votre **clé webhook**.

### 5.1 Ouvrir la configuration

Dans Fronote, connectez-vous en tant qu'administrateur puis allez dans :
**Administration → Système → Mises à jour**

### 5.2 Saisir votre clé webhook

Dans l'onglet **Configuration** :

- **Webhook secret** : collez votre clé webhook reçue dans le pack
- **Branche** : laissez `main` (valeur par défaut)
- Cliquez **Enregistrer**

### 5.3 Communiquer votre URL de mise à jour

Transmettez au support l'**URL de mise à jour** de votre serveur, qui a la forme suivante :

```
https://votre-domaine.fr/fronote/API/endpoints/webhook_update.php
```

Le support enregistrera cette URL dans le système de déploiement, ce qui permettra à votre serveur de recevoir automatiquement les mises à jour lors de chaque nouvelle version.

### Comment fonctionnent les mises à jour

```
Nouvelle version publiée par les développeurs
  → Votre serveur reçoit un signal de mise à jour (webhook)
  → Fronote vérifie l'authenticité du signal avec votre clé
  → La mise à jour est téléchargée et appliquée
  → Votre fichier de configuration (.env) est préservé
  → Un test automatique vérifie que tout fonctionne
```

> En cas d'échec d'une mise à jour, tout est automatiquement restauré dans l'état précédent.

### Déclencher une mise à jour manuellement

Si vous souhaitez appliquer une mise à jour sans attendre le déclencheur automatique :

**Administration → Système → Mises à jour → Statut → Déclencher une mise à jour**

Vous pouvez suivre l'avancement dans l'onglet **Journal**.

---

## Étape 6 — WebSocket (optionnel)

Le WebSocket permet les **notifications en temps réel** dans la messagerie (voir un nouveau message sans recharger la page, indicateur de frappe…).

> **Sans WebSocket**, tout fonctionne normalement. L'application vérifie automatiquement les nouveaux messages en arrière-plan (toutes les 30 secondes environ).

### Installer Node.js

```bash
sudo apt install nodejs npm   # Debian/Ubuntu
node -v                       # vérifier : v16+ requis
```

### Installer les dépendances du serveur WebSocket

```bash
cd /var/www/fronote/websocket-server
npm init -y
npm install express socket.io jsonwebtoken
```

### Démarrer le serveur

```bash
# Installation de PM2 (gestionnaire de processus)
sudo npm install -g pm2

# Démarrage
cd /var/www/fronote/websocket-server
pm2 start server.js --name "fronote-ws"
pm2 save       # conserver après redémarrage serveur
pm2 startup    # activer au démarrage automatique (suivre les instructions affichées)
```

### Configurer Fronote

Dans le fichier `.env` à la racine du projet, vérifiez ces lignes (normalement déjà renseignées par l'installateur) :

```env
WEBSOCKET_ENABLED=true
WEBSOCKET_CLIENT_URL=http://votre-serveur:3000
```

Vérifiez que le serveur WebSocket répond :
```bash
curl http://localhost:3000/health
# → {"status":"ok","connections":0,"uptime":...}
```

---

## Étape 7 — Tâches planifiées (cron)

Fronote utilise des tâches de fond pour le traitement asynchrone et la maintenance.

### Tâches obligatoires

```bash
crontab -e
# Ajouter ces lignes :

# Traitement de la file de tâches (emails, notifications…) — toutes les minutes
* * * * * php /var/www/fronote/scripts/worker.php >> /dev/null 2>&1

# Maintenance quotidienne (nettoyage logs, purge cache, sauvegardes) — chaque nuit à 2h
0 2 * * * php /var/www/fronote/cron/daily_maintenance.php >> /dev/null 2>&1
```

### Tâche optionnelle (mises à jour automatiques)

```bash
# Vérification de mises à jour — toutes les 6 heures
0 */6 * * * php /var/www/fronote/scripts/check_update.php >> /dev/null 2>&1
```

> **Note :** Les mises à jour automatiques nécessitent que **Git** soit installé sur le serveur (`sudo apt install git`). Si vous avez installé Fronote depuis une archive ZIP, installez Git et initialisez le dépôt :
> ```bash
> cd /var/www/fronote
> git init && git remote add origin https://github.com/VOTRE-REPO/fronote.git
> git fetch origin && git reset --hard origin/main
> ```

---

## Checklist post-installation

- [ ] Connexion administrateur fonctionnelle
- [ ] Création des comptes enseignants, élèves et parents (Administration → Utilisateurs)
- [ ] Configuration de l'établissement (Administration → Établissement)
- [ ] Configuration des **permissions par module** (Administration → Modules → Permissions)
- [ ] Activation de la **messagerie** si souhaitée (désactivée par défaut pour des raisons de sécurité)
- [ ] Personnalisation des **widgets du tableau de bord** (page d'accueil → Personnaliser)
- [ ] Tâches cron configurées (worker.php + daily_maintenance.php)
- [ ] Clé webhook saisie et URL transmise au support
- [ ] `APP_DEBUG=false` dans la configuration (vérifiable dans Administration → Système)
- [ ] HTTPS configuré si accès depuis Internet
- [ ] Sauvegardes automatiques planifiées (voir ci-dessous)

> **Import d'utilisateurs** : vous pouvez importer des listes d'élèves, professeurs et parents depuis des fichiers CSV via **Administration → Système → Import/Export**.

---

## Sauvegardes

Il est fortement recommandé de planifier des sauvegardes automatiques quotidiennes.

### Sauvegarder la base de données

```bash
mysqldump -u utilisateur -p nom_base > sauvegarde_$(date +%Y%m%d).sql
```

### Sauvegarder les fichiers uploadés

```bash
tar -czf uploads_$(date +%Y%m%d).tar.gz /var/www/fronote/uploads/
```

### Automatiser avec cron

```bash
crontab -e
# Ajouter cette ligne (sauvegarde quotidienne à 2h du matin) :
0 2 * * * mysqldump -u fronote_user -pMOT_DE_PASSE fronote | gzip > /sauvegardes/fronote_$(date +\%Y\%m\%d).sql.gz
```

---

## Problèmes courants

### L'assistant d'installation ne s'ouvre pas

→ Vérifiez que vous accédez depuis le réseau local. Contactez le support si vous êtes à distance.

### Erreur de connexion à la base de données

→ Vérifiez que MySQL est démarré (`sudo systemctl status mysql`).
→ Vérifiez les identifiants MySQL saisis.
→ Vérifiez que l'utilisateur MySQL a les droits sur la base.

### Les pages s'affichent en blanc ou avec des erreurs

→ Activez temporairement le mode debug dans `.env` : `APP_DEBUG=true`
→ Consultez les logs dans `API/logs/` et les logs Apache (`/var/log/apache2/error.log`).
→ Repassez ensuite à `APP_DEBUG=false`.

### Les notifications ne s'affichent pas en temps réel

→ Le WebSocket n'est pas démarré. Les notifications fonctionnent toujours mais avec un délai.
→ Vérifiez l'état : `pm2 status` puis `pm2 logs fronote-ws`.

### Une mise à jour a échoué

→ Allez dans **Administration → Système → Mises à jour → Journal** pour voir les détails.
→ Votre configuration a été préservée automatiquement.
→ Contactez le support avec le contenu du journal.

### Je dois réinstaller complètement

1. Supprimez le fichier `install.lock` à la racine
2. Retournez sur `install.php`
3. ⚠️ La base de données sera **entièrement recréée** (toutes les données perdues)

---

## Contact support

En cas de problème non résolu, contactez le support en précisant :
- La version de Fronote (visible dans Administration → Système → Mises à jour)
- Le message d'erreur exact
- Le contenu du journal de mise à jour si applicable
