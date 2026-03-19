<?php
/**
 * Service d'import/export de donnees (utilisateurs, configuration, sauvegarde SQL).
 *
 * Usage :
 *   $svc = new ImportExportService($pdo);
 *   $csv = $svc->exportUsers('eleve');
 *   $svc->importUsers('professeur', $_FILES['file']);
 *   $json = $svc->exportConfig();
 *   $svc->importConfig($_FILES['file']);
 *   $sql = $svc->exportSQL(['eleves', 'professeurs']);
 *   $history = $svc->getImportHistory();
 */
class ImportExportService
{
    private PDO $pdo;

    /** Map profil => table name */
    private array $tableMap = [
        'eleve'          => 'eleves',
        'parent'         => 'parents',
        'professeur'     => 'professeurs',
        'vie_scolaire'   => 'vie_scolaire',
        'administrateur' => 'administrateurs',
    ];

    /** Colonnes exportables par table (hors mot_de_passe et donnees sensibles) */
    private array $exportColumns = [
        'eleves'          => ['id','nom','prenom','date_naissance','classe','lieu_naissance','adresse','mail','telephone','identifiant','actif','date_creation'],
        'professeurs'     => ['id','nom','prenom','mail','adresse','telephone','identifiant','professeur_principal','matiere','actif','date_creation'],
        'parents'         => ['id','nom','prenom','mail','adresse','telephone','metier','identifiant','est_parent_eleve','actif','date_creation'],
        'vie_scolaire'    => ['id','nom','prenom','mail','telephone','identifiant','est_CPE','est_infirmerie','actif','date_creation'],
        'administrateurs' => ['id','nom','prenom','mail','identifiant','adresse','role','actif','date_creation'],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ===================================================================
     *  EXPORT UTILISATEURS
     * =================================================================== */

    /**
     * Exporte les utilisateurs d'un type donne au format CSV ou JSON.
     *
     * @param  string $type   Profil : eleve, professeur, parent, vie_scolaire, administrateur, ou 'tous'
     * @param  string $format 'csv' ou 'json'
     * @return array  ['success', 'file_path', 'file_name', 'count', 'message']
     */
    public function exportUsers(string $type, string $format = 'csv'): array
    {
        $types = ($type === 'tous') ? array_keys($this->tableMap) : [$type];

        // Valider les types
        foreach ($types as $t) {
            if (!isset($this->tableMap[$t])) {
                return ['success' => false, 'message' => "Type d'utilisateur invalide : {$t}"];
            }
        }

        $allRows = [];
        foreach ($types as $t) {
            $table = $this->tableMap[$t];
            $cols  = $this->exportColumns[$table] ?? ['*'];
            $colsSQL = ($cols === ['*']) ? '*' : '`' . implode('`, `', $cols) . '`';

            try {
                $stmt = $this->pdo->prepare("SELECT {$colsSQL} FROM `{$table}` ORDER BY nom, prenom");
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as &$row) {
                    $row['_type'] = $t;
                }
                unset($row);
                $allRows = array_merge($allRows, $rows);
            } catch (PDOException $e) {
                error_log("ImportExportService::exportUsers — {$table}: " . $e->getMessage());
            }
        }

        if (empty($allRows)) {
            return ['success' => false, 'message' => 'Aucun utilisateur trouve.'];
        }

        $tempDir   = $this->ensureTempDir();
        $timestamp = date('Ymd_His');
        $label     = ($type === 'tous') ? 'tous' : $type;

        if ($format === 'json') {
            $fileName = "utilisateurs_{$label}_{$timestamp}.json";
            $filePath = $tempDir . '/' . $fileName;
            file_put_contents($filePath, json_encode($allRows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $fileName = "utilisateurs_{$label}_{$timestamp}.csv";
            $filePath = $tempDir . '/' . $fileName;
            $fp = fopen($filePath, 'w');
            fwrite($fp, "\xEF\xBB\xBF"); // BOM UTF-8
            // Unifier les colonnes
            $headers = [];
            foreach ($allRows as $row) {
                foreach (array_keys($row) as $k) {
                    if (!in_array($k, $headers)) {
                        $headers[] = $k;
                    }
                }
            }
            fputcsv($fp, $headers, ';');
            foreach ($allRows as $row) {
                $line = [];
                foreach ($headers as $h) {
                    $line[] = $row[$h] ?? '';
                }
                fputcsv($fp, $line, ';');
            }
            fclose($fp);
        }

        $this->logImport('export', count($allRows), 'termine', $fileName, "utilisateurs_{$label}");

        return [
            'success'   => true,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'count'     => count($allRows),
            'message'   => count($allRows) . ' utilisateur(s) exporte(s).',
        ];
    }

    /* ===================================================================
     *  IMPORT UTILISATEURS
     * =================================================================== */

    /**
     * Previsualise les premieres lignes d'un CSV uploade.
     *
     * @param  string $filePath Chemin vers le fichier temporaire
     * @param  int    $maxRows  Nombre de lignes a previsualiser
     * @return array  ['success', 'headers', 'rows', 'total_lines']
     */
    public function previewCsv(string $filePath, int $maxRows = 5): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'Fichier introuvable.'];
        }

        $fp = fopen($filePath, 'r');
        if (!$fp) {
            return ['success' => false, 'message' => "Impossible d'ouvrir le fichier."];
        }

        // Skip BOM
        $bom = fread($fp, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fp);
        }

        $headers = fgetcsv($fp, 0, ';');
        if (!$headers) {
            fclose($fp);
            return ['success' => false, 'message' => 'En-tetes CSV introuvables.'];
        }
        $headers = array_map('trim', $headers);

        $rows = [];
        $totalLines = 0;
        while (($row = fgetcsv($fp, 0, ';')) !== false) {
            $totalLines++;
            if (count($rows) < $maxRows) {
                $assoc = [];
                foreach ($headers as $i => $h) {
                    $assoc[$h] = $row[$i] ?? '';
                }
                $rows[] = $assoc;
            }
        }
        fclose($fp);

        return [
            'success'     => true,
            'headers'     => $headers,
            'rows'        => $rows,
            'total_lines' => $totalLines,
        ];
    }

    /**
     * Importe des utilisateurs d'un type donne depuis un fichier CSV.
     *
     * Le CSV doit contenir au minimum : nom, prenom, mail, identifiant.
     * Les colonnes supplementaires sont inserees si elles correspondent a la table.
     *
     * @param  string $type Profil cible : eleve, professeur, parent, vie_scolaire, administrateur
     * @param  array  $file Entree $_FILES (tmp_name, name, size, etc.)
     * @param  array  $options ['generate_passwords' => bool]
     * @return array  ['success','nb_total','nb_importes','nb_doublons','nb_erreurs','erreurs','passwords','message']
     */
    public function importUsers(string $type, array $file, array $options = []): array
    {
        if (!isset($this->tableMap[$type])) {
            return ['success' => false, 'message' => "Type d'utilisateur invalide : {$type}"];
        }

        $filePath = $file['tmp_name'] ?? '';
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'Fichier uploade introuvable.'];
        }

        $table = $this->tableMap[$type];
        $generatePasswords = $options['generate_passwords'] ?? true;

        $fp = fopen($filePath, 'r');
        if (!$fp) {
            return ['success' => false, 'message' => "Impossible d'ouvrir le fichier."];
        }

        // Skip BOM
        $bom = fread($fp, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fp);
        }

        $headers = fgetcsv($fp, 0, ';');
        if (!$headers) {
            fclose($fp);
            return ['success' => false, 'message' => 'En-tetes CSV introuvables.'];
        }
        $headers = array_map('trim', array_map('strtolower', $headers));

        // Colonnes obligatoires
        $required = ['nom', 'prenom', 'mail'];
        $missing  = array_diff($required, $headers);
        if (!empty($missing)) {
            fclose($fp);
            return ['success' => false, 'message' => 'Colonnes manquantes : ' . implode(', ', $missing)];
        }

        $lineNum     = 1;
        $nbImported  = 0;
        $nbDoublons  = 0;
        $errors      = [];
        $passwords   = [];

        while (($row = fgetcsv($fp, 0, ';')) !== false) {
            $lineNum++;
            $data = [];
            foreach ($headers as $i => $h) {
                $data[$h] = trim($row[$i] ?? '');
            }

            // Validation basique
            if (empty($data['nom']) || empty($data['prenom']) || empty($data['mail'])) {
                $errors[] = "Ligne {$lineNum} : nom, prenom et mail sont obligatoires.";
                continue;
            }

            // Validation email
            if (!filter_var($data['mail'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Ligne {$lineNum} : email '{$data['mail']}' invalide.";
                continue;
            }

            // Detection de doublon par email
            try {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE mail = ?");
                $stmt->execute([$data['mail']]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $nbDoublons++;
                    $errors[] = "Ligne {$lineNum} : email '{$data['mail']}' deja existant (doublon ignore).";
                    continue;
                }
            } catch (PDOException $e) {
                $errors[] = "Ligne {$lineNum} : erreur verification email — " . $e->getMessage();
                continue;
            }

            // Identifiant
            $identifiant = !empty($data['identifiant'])
                ? $data['identifiant']
                : $this->generateIdentifier($data['nom'], $data['prenom'], $table);

            // Verifier unicite identifiant
            try {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE identifiant = ?");
                $stmt->execute([$identifiant]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $identifiant = $this->generateIdentifier($data['nom'], $data['prenom'], $table);
                }
            } catch (PDOException $e) {
                // On continue avec l'identifiant genere
            }

            // Mot de passe
            $plainPassword = '';
            if (!empty($data['mot_de_passe'])) {
                $plainPassword = $data['mot_de_passe'];
            } elseif ($generatePasswords) {
                $plainPassword = $this->generatePassword();
            } else {
                $errors[] = "Ligne {$lineNum} : mot de passe manquant.";
                continue;
            }

            $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);

            // Construction de l'insertion
            $insertData = [
                'identifiant'  => $identifiant,
                'nom'          => $data['nom'],
                'prenom'       => $data['prenom'],
                'mail'         => $data['mail'],
                'mot_de_passe' => $hashedPassword,
            ];

            // Champs optionnels selon le type
            if (!empty($data['adresse']))   $insertData['adresse']   = $data['adresse'];
            if (!empty($data['telephone'])) $insertData['telephone'] = $data['telephone'];

            if ($type === 'eleve') {
                if (!empty($data['classe']))         $insertData['classe']         = $data['classe'];
                if (!empty($data['date_naissance']))  $insertData['date_naissance'] = $data['date_naissance'];
                if (!empty($data['lieu_naissance']))  $insertData['lieu_naissance'] = $data['lieu_naissance'];
            }
            if ($type === 'professeur') {
                if (!empty($data['matiere']))              $insertData['matiere']              = $data['matiere'];
                if (!empty($data['professeur_principal'])) $insertData['professeur_principal'] = $data['professeur_principal'];
            }
            if ($type === 'parent') {
                if (!empty($data['metier']))          $insertData['metier']          = $data['metier'];
                if (!empty($data['est_parent_eleve'])) $insertData['est_parent_eleve'] = $data['est_parent_eleve'];
            }
            if ($type === 'vie_scolaire') {
                if (!empty($data['est_cpe']))        $insertData['est_CPE']        = $data['est_cpe'];
                if (!empty($data['est_infirmerie'])) $insertData['est_infirmerie'] = $data['est_infirmerie'];
            }
            if ($type === 'administrateur') {
                $insertData['role'] = $data['role'] ?? 'administrateur';
            }

            $columns      = array_keys($insertData);
            $placeholders = array_fill(0, count($insertData), '?');

            try {
                $sql = sprintf(
                    "INSERT INTO `%s` (%s) VALUES (%s)",
                    $table,
                    implode(', ', $columns),
                    implode(', ', $placeholders)
                );
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(array_values($insertData));
                $nbImported++;

                $passwords[] = [
                    'identifiant' => $identifiant,
                    'nom'         => $data['nom'],
                    'prenom'      => $data['prenom'],
                    'type'        => $type,
                    'password'    => $plainPassword,
                ];
            } catch (PDOException $e) {
                $errors[] = "Ligne {$lineNum} : erreur insertion — " . $e->getMessage();
            }
        }
        fclose($fp);

        $nbTotal = $lineNum - 1;
        $status  = (count($errors) > 0 && $nbImported === 0) ? 'erreur' : 'termine';

        $this->logImport(
            'import',
            $nbImported,
            $status,
            $file['name'] ?? 'import.csv',
            "utilisateurs_{$type}",
            $nbTotal,
            count($errors),
            $errors
        );

        return [
            'success'     => true,
            'nb_total'    => $nbTotal,
            'nb_importes' => $nbImported,
            'nb_doublons' => $nbDoublons,
            'nb_erreurs'  => count($errors),
            'erreurs'     => $errors,
            'passwords'   => $passwords,
            'message'     => "{$nbImported}/{$nbTotal} utilisateur(s) importe(s).",
        ];
    }

    /* ===================================================================
     *  EXPORT CONFIGURATION
     * =================================================================== */

    /**
     * Exporte la configuration du systeme (modules_config + module_permissions + user_settings) en JSON.
     *
     * @return array ['success', 'file_path', 'file_name', 'message']
     */
    public function exportConfig(): array
    {
        $bundle = [
            'export_type' => 'configuration',
            'exported_at' => date('Y-m-d H:i:s'),
            'modules_config'     => [],
            'module_permissions' => [],
        ];

        try {
            $bundle['modules_config'] = $this->pdo->query(
                "SELECT * FROM modules_config ORDER BY sort_order, label"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ImportExportService::exportConfig modules_config: " . $e->getMessage());
        }

        try {
            $bundle['module_permissions'] = $this->pdo->query(
                "SELECT * FROM module_permissions ORDER BY module_key, role"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ImportExportService::exportConfig module_permissions: " . $e->getMessage());
        }

        $tempDir   = $this->ensureTempDir();
        $timestamp = date('Ymd_His');
        $fileName  = "config_{$timestamp}.json";
        $filePath  = $tempDir . '/' . $fileName;

        file_put_contents($filePath, json_encode($bundle, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $total = count($bundle['modules_config']) + count($bundle['module_permissions']);
        $this->logImport('export', $total, 'termine', $fileName, 'configuration');

        return [
            'success'   => true,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'count'     => $total,
            'message'   => 'Configuration exportee (' . count($bundle['modules_config']) . ' modules, ' . count($bundle['module_permissions']) . ' permissions).',
        ];
    }

    /* ===================================================================
     *  IMPORT CONFIGURATION
     * =================================================================== */

    /**
     * Importe une configuration depuis un fichier JSON uploade.
     *
     * @param  array $file Entree $_FILES
     * @return array ['success', 'nb_traites', 'nb_erreurs', 'erreurs', 'message']
     */
    public function importConfig(array $file): array
    {
        $filePath = $file['tmp_name'] ?? '';
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'Fichier introuvable.'];
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        if (!$data || ($data['export_type'] ?? '') !== 'configuration') {
            return ['success' => false, 'message' => 'Format de fichier invalide (type attendu : configuration).'];
        }

        $errors      = [];
        $nbProcessed = 0;

        // Import modules_config
        if (!empty($data['modules_config']) && is_array($data['modules_config'])) {
            foreach ($data['modules_config'] as $mod) {
                $key = $mod['module_key'] ?? null;
                if (!$key) continue;

                try {
                    $stmt = $this->pdo->prepare("SELECT id FROM modules_config WHERE module_key = ?");
                    $stmt->execute([$key]);

                    if ($stmt->fetch()) {
                        $upd = $this->pdo->prepare("
                            UPDATE modules_config SET
                                label = ?, description = ?, icon = ?, category = ?,
                                enabled = ?, config_json = ?, roles_autorises = ?,
                                sort_order = ?, is_core = ?
                            WHERE module_key = ?
                        ");
                        $upd->execute([
                            $mod['label'] ?? '',
                            $mod['description'] ?? null,
                            $mod['icon'] ?? 'fas fa-puzzle-piece',
                            $mod['category'] ?? 'general',
                            $mod['enabled'] ?? 1,
                            $mod['config_json'] ?? null,
                            is_array($mod['roles_autorises'] ?? null) ? json_encode($mod['roles_autorises']) : ($mod['roles_autorises'] ?? null),
                            $mod['sort_order'] ?? 100,
                            $mod['is_core'] ?? 0,
                            $key,
                        ]);
                    } else {
                        $ins = $this->pdo->prepare("
                            INSERT INTO modules_config
                                (module_key, label, description, icon, category, enabled, config_json, roles_autorises, sort_order, is_core)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins->execute([
                            $key,
                            $mod['label'] ?? '',
                            $mod['description'] ?? null,
                            $mod['icon'] ?? 'fas fa-puzzle-piece',
                            $mod['category'] ?? 'general',
                            $mod['enabled'] ?? 1,
                            $mod['config_json'] ?? null,
                            is_array($mod['roles_autorises'] ?? null) ? json_encode($mod['roles_autorises']) : ($mod['roles_autorises'] ?? null),
                            $mod['sort_order'] ?? 100,
                            $mod['is_core'] ?? 0,
                        ]);
                    }
                    $nbProcessed++;
                } catch (PDOException $e) {
                    $errors[] = "Module '{$key}' : " . $e->getMessage();
                }
            }
        }

        // Import module_permissions
        if (!empty($data['module_permissions']) && is_array($data['module_permissions'])) {
            foreach ($data['module_permissions'] as $perm) {
                $key  = $perm['module_key'] ?? null;
                $role = $perm['role'] ?? null;
                if (!$key || !$role) continue;

                try {
                    $stmt = $this->pdo->prepare("SELECT id FROM module_permissions WHERE module_key = ? AND role = ?");
                    $stmt->execute([$key, $role]);

                    if ($stmt->fetch()) {
                        $upd = $this->pdo->prepare("
                            UPDATE module_permissions SET
                                can_view = ?, can_create = ?, can_edit = ?, can_delete = ?,
                                can_export = ?, can_import = ?, custom_permissions = ?
                            WHERE module_key = ? AND role = ?
                        ");
                        $upd->execute([
                            $perm['can_view'] ?? 1,
                            $perm['can_create'] ?? 0,
                            $perm['can_edit'] ?? 0,
                            $perm['can_delete'] ?? 0,
                            $perm['can_export'] ?? 0,
                            $perm['can_import'] ?? 0,
                            is_array($perm['custom_permissions'] ?? null) ? json_encode($perm['custom_permissions']) : ($perm['custom_permissions'] ?? null),
                            $key,
                            $role,
                        ]);
                    } else {
                        $ins = $this->pdo->prepare("
                            INSERT INTO module_permissions
                                (module_key, role, can_view, can_create, can_edit, can_delete, can_export, can_import, custom_permissions)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins->execute([
                            $key,
                            $role,
                            $perm['can_view'] ?? 1,
                            $perm['can_create'] ?? 0,
                            $perm['can_edit'] ?? 0,
                            $perm['can_delete'] ?? 0,
                            $perm['can_export'] ?? 0,
                            $perm['can_import'] ?? 0,
                            is_array($perm['custom_permissions'] ?? null) ? json_encode($perm['custom_permissions']) : ($perm['custom_permissions'] ?? null),
                        ]);
                    }
                    $nbProcessed++;
                } catch (PDOException $e) {
                    $errors[] = "Permission '{$key}/{$role}' : " . $e->getMessage();
                }
            }
        }

        $status = (count($errors) > 0 && $nbProcessed === 0) ? 'erreur' : 'termine';
        $this->logImport('import', $nbProcessed, $status, $file['name'] ?? 'config_import.json', 'configuration', $nbProcessed, count($errors), $errors);

        return [
            'success'    => count($errors) === 0,
            'nb_traites' => $nbProcessed,
            'nb_erreurs' => count($errors),
            'erreurs'    => $errors,
            'message'    => "{$nbProcessed} entree(s) traitee(s)" . (count($errors) > 0 ? ", " . count($errors) . " erreur(s)." : '.'),
        ];
    }

    /* ===================================================================
     *  EXPORT SQL DUMP (via PHP — pas de commande shell)
     * =================================================================== */

    /**
     * Exporte une ou plusieurs tables sous forme de dump SQL (INSERT INTO).
     *
     * @param  array  $tables Liste de tables a exporter (vide = tables principales)
     * @param  bool   $includeStructure Inclure CREATE TABLE
     * @return array  ['success', 'file_path', 'file_name', 'message']
     */
    public function exportSQL(array $tables = [], bool $includeStructure = true): array
    {
        if (empty($tables)) {
            $tables = [
                'eleves', 'professeurs', 'parents', 'vie_scolaire', 'administrateurs',
                'classes', 'matieres', 'periodes', 'etablissement_info',
                'modules_config', 'module_permissions',
            ];
        }

        $tempDir   = $this->ensureTempDir();
        $timestamp = date('Ymd_His');
        $fileName  = "backup_{$timestamp}.sql";
        $filePath  = $tempDir . '/' . $fileName;

        $fp = fopen($filePath, 'w');
        if (!$fp) {
            return ['success' => false, 'message' => "Impossible de creer le fichier d'export."];
        }

        fwrite($fp, "-- Fronote SQL Backup\n");
        fwrite($fp, "-- Date : " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "-- Tables : " . implode(', ', $tables) . "\n");
        fwrite($fp, "SET NAMES utf8mb4;\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

        $totalRows = 0;

        foreach ($tables as $table) {
            // Verifier que la table existe
            try {
                $check = $this->pdo->prepare("SHOW TABLES LIKE ?");
                $check->execute([$table]);
                if (!$check->fetch()) {
                    fwrite($fp, "-- Table '{$table}' non trouvee, ignoree.\n\n");
                    continue;
                }
            } catch (PDOException $e) {
                fwrite($fp, "-- Erreur verification table '{$table}': " . $e->getMessage() . "\n\n");
                continue;
            }

            fwrite($fp, "-- -----------------------------------------------\n");
            fwrite($fp, "-- Table : {$table}\n");
            fwrite($fp, "-- -----------------------------------------------\n\n");

            // Structure
            if ($includeStructure) {
                try {
                    $createStmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
                    $createRow  = $createStmt->fetch(PDO::FETCH_NUM);
                    if ($createRow && isset($createRow[1])) {
                        fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
                        fwrite($fp, $createRow[1] . ";\n\n");
                    }
                } catch (PDOException $e) {
                    fwrite($fp, "-- Erreur CREATE TABLE: " . $e->getMessage() . "\n\n");
                }
            }

            // Donnees
            try {
                $stmt = $this->pdo->prepare("SELECT * FROM `{$table}`");
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                    $colList = '`' . implode('`, `', $columns) . '`';

                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($row as $val) {
                            if ($val === null) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = $this->pdo->quote($val);
                            }
                        }
                        fwrite($fp, "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(', ', $values) . ");\n");
                        $totalRows++;
                    }
                    fwrite($fp, "\n");
                } else {
                    fwrite($fp, "-- (table vide)\n\n");
                }
            } catch (PDOException $e) {
                fwrite($fp, "-- Erreur lecture donnees: " . $e->getMessage() . "\n\n");
            }
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fp);

        $this->logImport('export', $totalRows, 'termine', $fileName, 'backup_sql');

        return [
            'success'   => true,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'count'     => $totalRows,
            'message'   => "Backup SQL genere ({$totalRows} lignes, " . count($tables) . " table(s)).",
        ];
    }

    /* ===================================================================
     *  HISTORIQUE
     * =================================================================== */

    /**
     * Recupere l'historique des operations d'import/export.
     *
     * @param  int $limit
     * @param  int $offset
     * @return array
     */
    public function getImportHistory(int $limit = 50, int $offset = 0): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM import_export_logs
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ImportExportService::getImportHistory — " . $e->getMessage());
            return [];
        }
    }

    /**
     * Compte le nombre total d'entrees dans l'historique.
     */
    public function getHistoryCount(): int
    {
        try {
            return (int)$this->pdo->query("SELECT COUNT(*) FROM import_export_logs")->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Enregistre une operation dans l'historique import_export_logs.
     *
     * @param  string $type      'import' ou 'export'
     * @param  int    $count     Nombre d'enregistrements traites
     * @param  string $status    'termine', 'erreur', 'en_cours', 'annule'
     * @param  string $filename  Nom du fichier
     * @param  string $cible     Cible (utilisateurs_eleve, configuration, backup_sql, etc.)
     * @param  int    $nbTotal   Nombre total de lignes lues
     * @param  int    $nbErreurs Nombre d'erreurs
     * @param  array  $errors    Detail des erreurs
     * @return int|null          ID du log cree
     */
    public function logImport(
        string $type,
        int $count,
        string $status,
        string $filename = '',
        string $cible = '',
        int $nbTotal = 0,
        int $nbErreurs = 0,
        array $errors = []
    ): ?int {
        try {
            $user = function_exists('getCurrentUser') ? getCurrentUser() : null;

            $format = 'csv';
            if (str_ends_with($filename, '.json')) $format = 'json';
            if (str_ends_with($filename, '.sql'))  $format = 'sql';

            $stmt = $this->pdo->prepare("
                INSERT INTO import_export_logs
                    (type, cible, format, fichier_nom, nb_lignes_total, nb_lignes_traitees, nb_erreurs, erreurs_detail, statut, user_id, user_type, completed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $type,
                $cible,
                $format,
                $filename,
                $nbTotal > 0 ? $nbTotal : $count,
                $count,
                $nbErreurs,
                !empty($errors) ? json_encode($errors, JSON_UNESCAPED_UNICODE) : null,
                $status,
                $user['id'] ?? 0,
                $user['type'] ?? $user['profil'] ?? 'administrateur',
                $status !== 'en_cours' ? date('Y-m-d H:i:s') : null,
            ]);

            return (int)$this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("ImportExportService::logImport — " . $e->getMessage());
            return null;
        }
    }

    /* ===================================================================
     *  METHODES INTERNES
     * =================================================================== */

    /**
     * Genere un identifiant unique nom.prenom[XX].
     */
    private function generateIdentifier(string $nom, string $prenom, string $table): string
    {
        $nom    = $this->normalizeString($nom);
        $prenom = $this->normalizeString($prenom);
        $base   = strtolower($nom . '.' . $prenom);

        $stmt = $this->pdo->prepare("SELECT identifiant FROM `{$table}` WHERE identifiant LIKE ?");
        $stmt->execute([$base . '%']);
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array($base, $existing)) {
            return $base;
        }

        $i = 1;
        do {
            $candidate = $base . sprintf('%02d', $i++);
        } while (in_array($candidate, $existing));

        return $candidate;
    }

    /**
     * Normalise une chaine (supprime accents et caracteres speciaux).
     */
    private function normalizeString(string $string): string
    {
        $string = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        return preg_replace('/[^a-zA-Z0-9]/', '', $string ?: '');
    }

    /**
     * Genere un mot de passe aleatoire securise.
     */
    private function generatePassword(int $length = 12): string
    {
        $length  = max($length, 12);
        $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower   = 'abcdefghijkmnopqrstuvwxyz';
        $digits  = '23456789';
        $special = '!@#$%^&*_-+=';

        $password  = $upper[random_int(0, strlen($upper) - 1)];
        $password .= $lower[random_int(0, strlen($lower) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        $all = $upper . $lower . $digits . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }

    /**
     * S'assure que le repertoire temp/ existe.
     */
    private function ensureTempDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/temp';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Retourne la liste des types utilisateurs valides.
     */
    public function getValidUserTypes(): array
    {
        return [
            'eleve'          => 'Eleves',
            'professeur'     => 'Professeurs',
            'parent'         => 'Parents',
            'vie_scolaire'   => 'Vie scolaire',
            'administrateur' => 'Administrateurs',
        ];
    }

    /**
     * Retourne la liste des tables exportables en SQL.
     */
    public function getExportableTables(): array
    {
        $tables = [];
        try {
            $stmt = $this->pdo->query("SHOW TABLES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
        } catch (PDOException $e) {
            error_log("ImportExportService::getExportableTables — " . $e->getMessage());
        }
        sort($tables);
        return $tables;
    }
}
