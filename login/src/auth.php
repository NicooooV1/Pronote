<?php
/**
 * Classe d'authentification pour Fronote
 * Délègue à l'API centralisée (AuthManager + UserProvider)
 */

class Auth {
    private $errorMessage = '';

    /**
     * Authentifie un utilisateur via l'API centralisée
     *
     * @param string $profil   Type de profil (eleve, parent, professeur, administrateur, vie_scolaire)
     * @param string $identifiant Identifiant de l'utilisateur
     * @param string $password Mot de passe en clair
     * @return array ['success' => bool, 'message' => string, 'user' => array|null]
     */
    public function login($profil, $identifiant, $password) {
        $validProfiles = ['eleve', 'parent', 'professeur', 'administrateur', 'vie_scolaire'];
        if (!in_array($profil, $validProfiles)) {
            $this->errorMessage = "Type de profil non valide.";
            return ['success' => false, 'message' => $this->errorMessage, 'user' => null];
        }

        try {
            // Déléguer l'authentification à l'API
            $auth = app('auth');
            $result = $auth->attempt([
                'type'     => $profil,
                'login'    => $identifiant,
                'password' => $password
            ]);

            if ($result) {
                $user = $auth->user();

                // Enrichir les données utilisateur depuis la table complète
                $userData = $this->enrichUserData($user, $profil);

                // Journaliser la connexion
                $this->logLogin($user['id'] ?? 0, $profil, true);

                return [
                    'success' => true,
                    'message' => 'Connexion réussie',
                    'user'    => $userData
                ];
            }

            $this->logLogin(0, $profil, false, $identifiant);
            $this->errorMessage = "Identifiant ou mot de passe incorrect.";
            return ['success' => false, 'message' => $this->errorMessage, 'user' => null];

        } catch (\Exception $e) {
            error_log("Erreur d'authentification: " . $e->getMessage());
            $this->errorMessage = "Une erreur est survenue lors de l'authentification.";
            return ['success' => false, 'message' => $this->errorMessage, 'user' => null];
        }
    }

    /**
     * Enrichit les données utilisateur avec les colonnes spécifiques au profil
     */
    private function enrichUserData($user, $profil) {
        $userData = [
            'id'         => $user['id'],
            'nom'        => $user['nom'] ?? '',
            'prenom'     => $user['prenom'] ?? '',
            'profil'     => $profil,
            'type'       => $profil,
            'mail'       => $user['email'] ?? $user['mail'] ?? '',
            'identifiant'=> $user['identifiant'] ?? ''
        ];

        // Colonnes spécifiques : on charge depuis la BDD
        try {
            $pdo = getPDO();
            $table = $this->getTableName($profil);
            if ($table) {
                $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
                $stmt->execute([$user['id']]);
                $full = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($full) {
                    if ($profil === 'eleve') {
                        $userData['classe'] = $full['classe'] ?? '';
                        $userData['date_naissance'] = $full['date_naissance'] ?? '';
                    } elseif ($profil === 'professeur') {
                        $userData['matiere'] = $full['matiere'] ?? '';
                        $userData['est_pp'] = $full['professeur_principal'] ?? 0;
                    }
                    $userData['identifiant'] = $full['identifiant'] ?? $userData['identifiant'];
                }
            }
        } catch (\Exception $e) {
            error_log("Enrichissement user data: " . $e->getMessage());
        }

        return $userData;
    }

    /**
     * Obtient le nom de la table correspondant au profil
     */
    private function getTableName($profil) {
        $tables = [
            'eleve'          => 'eleves',
            'parent'         => 'parents',
            'professeur'     => 'professeurs',
            'vie_scolaire'   => 'vie_scolaire',
            'administrateur' => 'administrateurs'
        ];
        return $tables[$profil] ?? null;
    }

    /**
     * Journalise une tentative de connexion
     */
    private function logLogin($userId, $profil, $success, $identifiant = '') {
        $message = $success
            ? "Connexion réussie: Utilisateur ID={$userId}, Profil={$profil}"
            : "Échec de connexion: Profil={$profil}" . ($identifiant ? ", Identifiant={$identifiant}" : "");
        error_log($message);

        try {
            $audit = app('audit');
            if ($audit) {
                $audit->logAuth($success ? 'login' : 'login_failed', $identifiant ?: "user#{$userId}", $success, [
                    'profil' => $profil,
                    'ip'     => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
        } catch (\Throwable $e) {
            // Silencieux
        }
    }

    /**
     * Récupère le message d'erreur
     */
    public function getErrorMessage() {
        return $this->errorMessage;
    }

    /**
     * Trouve un utilisateur par son identifiant via la BDD centralisée
     */
    public function findUserByUsername($username, $userType) {
        try {
            $pdo = getPDO();
            $table = $this->getTableName($userType);
            if (!$table) {
                $this->errorMessage = "Type d'utilisateur non valide.";
                return false;
            }
            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE identifiant = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($user) return $user;
            $this->errorMessage = "Aucun utilisateur trouvé avec cet identifiant.";
            return false;
        } catch (\PDOException $e) {
            error_log("Erreur recherche utilisateur: " . $e->getMessage());
            $this->errorMessage = "Erreur lors de la recherche.";
            return false;
        }
    }

    /**
     * Trouve un utilisateur par identifiant + email + téléphone
     */
    public function findUserByCredentials($username, $email, $phone, $userType) {
        try {
            $pdo = getPDO();
            $table = $this->getTableName($userType);
            if (!$table) {
                $this->errorMessage = "Type d'utilisateur non valide.";
                return false;
            }
            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE identifiant = ? AND mail = ? AND telephone = ? LIMIT 1");
            $stmt->execute([$username, $email, $phone]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($user) return $user;
            $this->errorMessage = "Aucun utilisateur ne correspond à ces informations.";
            return false;
        } catch (\PDOException $e) {
            error_log("Erreur recherche utilisateur: " . $e->getMessage());
            $this->errorMessage = "Erreur lors de la recherche.";
            return false;
        }
    }

    /**
     * Change le mot de passe d'un utilisateur
     */
    public function changePassword($userId, $newPassword) {
        try {
            $pdo = getPDO();
            $tables = ['eleves', 'parents', 'professeurs', 'vie_scolaire', 'administrateurs'];
            foreach ($tables as $table) {
                $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                if ($stmt->fetch()) {
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmt2 = $pdo->prepare("UPDATE {$table} SET mot_de_passe = ? WHERE id = ?");
                    $stmt2->execute([$hash, $userId]);
                    return ['success' => true, 'message' => 'Mot de passe changé avec succès.'];
                }
            }
            return ['success' => false, 'message' => 'Utilisateur non trouvé.'];
        } catch (\PDOException $e) {
            error_log("Erreur changement mot de passe: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors du changement de mot de passe.'];
        }
    }

    /**
     * Crée une demande de réinitialisation de mot de passe
     */
    public function createResetRequest($userId, $userType) {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare("SELECT id FROM demandes_reinitialisation WHERE user_id = ? AND user_type = ? AND status = 'pending' LIMIT 1");
            $stmt->execute([$userId, $userType]);
            if ($stmt->fetch()) {
                $this->errorMessage = "Une demande de réinitialisation est déjà en attente.";
                return false;
            }
            $stmt = $pdo->prepare("INSERT INTO demandes_reinitialisation (user_id, user_type, date_demande, status) VALUES (?, ?, NOW(), 'pending')");
            return $stmt->execute([$userId, $userType]);
        } catch (\PDOException $e) {
            error_log("Erreur demande réinitialisation: " . $e->getMessage());
            $this->errorMessage = "Erreur lors de la création de la demande.";
            return false;
        }
    }

    /**
     * Génère un code de réinitialisation
     */
    public function generateResetCode($userId) {
        return sprintf("%06d", mt_rand(1, 999999));
    }
}