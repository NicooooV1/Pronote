<?php
/**
 * Import CSV — Import en masse d'élèves ou de professeurs via fichier CSV.
 *
 * Colonnes attendues (élèves)   : nom, prenom, email, date_naissance, classe, sexe
 * Colonnes attendues (profs)    : nom, prenom, email, matiere
 *
 * Le mot de passe est auto-généré et affiché dans le rapport.
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$userObj = new User($pdo);

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$results  = [];
$error    = '';
$success  = '';
$imported = 0;
$failed   = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $csrf_token) {
    $profil = $_POST['profil'] ?? 'eleve';
    if (!in_array($profil, ['eleve', 'professeur'])) {
        $error = 'Profil invalide.';
    } elseif (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Veuillez sélectionner un fichier CSV valide.';
    } else {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) { $error = 'Impossible d\'ouvrir le fichier.'; }
        else {
            // Lire l'en-tête
            $header = fgetcsv($handle, 0, ';');
            if (!$header) { $error = 'En-tête CSV manquant.'; fclose($handle); }
            else {
                $header = array_map(fn($h) => strtolower(trim($h)), $header);
                $lineNum = 1;

                while (($row = fgetcsv($handle, 0, ';')) !== false) {
                    $lineNum++;
                    $rec = [];
                    foreach ($header as $i => $col) {
                        $rec[$col] = trim($row[$i] ?? '');
                    }

                    // Validation minimale
                    if (empty($rec['nom']) || empty($rec['prenom'])) {
                        $results[] = ['line' => $lineNum, 'status' => 'error', 'msg' => 'Nom/prénom manquant', 'nom' => '—'];
                        $failed++;
                        continue;
                    }

                    // Construire les données utilisateur (clés compatibles avec UserService::create())
                    $data = [
                        'nom'    => $rec['nom'],
                        'prenom' => $rec['prenom'],
                        'mail'   => $rec['email'] ?? $rec['mail'] ?? '',
                    ];

                    // Générer un email par défaut si absent
                    if (empty($data['mail'])) {
                        $base = strtolower(
                            preg_replace('/[^a-z0-9]/', '', @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $rec['prenom']))
                            . '.' .
                            preg_replace('/[^a-z0-9]/', '', @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $rec['nom']))
                        );
                        $data['mail'] = $base . '@etablissement.fr';
                    }

                    if ($profil === 'eleve') {
                        $data['date_naissance'] = $rec['date_naissance'] ?? null;
                        $data['classe']         = $rec['classe'] ?? null;
                        $data['sexe']           = $rec['sexe'] ?? null;
                    } elseif ($profil === 'professeur') {
                        $data['matiere'] = $rec['matiere'] ?? null;
                    }

                    try {
                        // User::createUser() retourne ['success' => bool, 'identifiant' => ..., 'password' => ...]
                        $result = $userObj->createUser($profil, $data);
                        if (!empty($result['success'])) {
                            $identifiant = $result['identifiant'] ?? '?';
                            $password    = $result['password'] ?? '?';
                            $results[] = [
                                'line'   => $lineNum,
                                'status' => 'ok',
                                'msg'    => "$identifiant / $password",
                                'nom'    => $rec['prenom'] . ' ' . $rec['nom'],
                            ];
                            $imported++;
                        } else {
                            $results[] = [
                                'line'   => $lineNum,
                                'status' => 'error',
                                'msg'    => $result['message'] ?? 'Échec insertion',
                                'nom'    => $rec['prenom'] . ' ' . $rec['nom'],
                            ];
                            $failed++;
                        }
                    } catch (\Exception $e) {
                        $results[] = ['line' => $lineNum, 'status' => 'error', 'msg' => $e->getMessage(), 'nom' => $rec['prenom'] . ' ' . $rec['nom']];
                        $failed++;
                    }
                }
                fclose($handle);
                if ($imported > 0 || $failed > 0) {
                    $success = "$imported importé(s), $failed en erreur.";
                    logAudit('csv_import', null, null, null, ['profil' => $profil, 'imported' => $imported, 'failed' => $failed]);
                }
            }
        }
    }
}

$pageTitle = 'Import CSV';
$currentPage = 'users_import';
$extraCss = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
.import-container { max-width: 900px; margin: 0 auto; }
.import-card { background: #fff; border-radius: 10px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; }
.import-card h3 { margin: 0 0 16px; font-size: 15px; color: #2d3748; display: flex; align-items: center; gap: 8px; }
.import-form { display: grid; grid-template-columns: 1fr 2fr auto; gap: 14px; align-items: end; }
@media (max-width: 640px) { .import-form { grid-template-columns: 1fr; } }
.import-form label { display: block; font-size: .88em; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
.import-form select, .import-form input[type="file"] {
    width: 100%; padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: .92em;
}
.import-form select:focus, .import-form input[type="file"]:focus {
    outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,.12);
}
.btn-import { padding: 10px 20px; background: #667eea; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: .92em; display: flex; align-items: center; gap: 6px; }
.btn-import:hover { background: #5a67d8; }
.format-help { font-size: .85em; color: #718096; line-height: 1.6; }
.format-help code { background: #edf2f7; padding: 2px 6px; border-radius: 3px; font-size: .92em; }
.results-table { width: 100%; border-collapse: collapse; font-size: .9em; }
.results-table th { background: #f7fafc; padding: 10px 12px; text-align: left; font-size: .85em; color: #4a5568; border-bottom: 2px solid #e2e8f0; }
.results-table td { padding: 8px 12px; border-bottom: 1px solid #edf2f7; }
.results-table tr.row-error td { background: #fff5f5; }
.badge-ok { padding: 2px 8px; border-radius: 10px; font-size: .78em; font-weight: 600; background: #c6f6d5; color: #276749; }
.badge-err { padding: 2px 8px; border-radius: 10px; font-size: .78em; font-weight: 600; background: #fed7d7; color: #9b2c2c; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="import-container">
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="import-card">
        <h3><i class="fas fa-file-csv"></i> Import CSV</h3>
        <p class="format-help" style="margin-bottom:16px">Importez des élèves ou des professeurs en masse à partir d'un fichier CSV (séparateur <code>;</code>).</p>
        <form method="post" enctype="multipart/form-data" class="import-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div>
                <label>Profil</label>
                <select name="profil">
                    <option value="eleve">Élèves</option>
                    <option value="professeur">Professeurs</option>
                </select>
            </div>
            <div>
                <label>Fichier CSV</label>
                <input name="csv_file" type="file" accept=".csv,.txt" required>
            </div>
            <div>
                <button type="submit" class="btn-import"><i class="fas fa-upload"></i> Importer</button>
            </div>
        </form>
    </div>

    <div class="import-card">
        <h3><i class="fas fa-info-circle"></i> Format attendu</h3>
        <div class="format-help">
            <p><strong>Élèves :</strong> <code>nom;prenom;email;date_naissance;classe;sexe</code></p>
            <p><strong>Professeurs :</strong> <code>nom;prenom;email;matiere</code></p>
            <p>La première ligne doit contenir les en-têtes. Le séparateur est le point-virgule (<code>;</code>).<br>
               Les identifiants et mots de passe sont générés automatiquement.</p>
        </div>
    </div>

    <?php if (!empty($results)): ?>
    <div class="import-card">
        <h3><i class="fas fa-list-alt"></i> Résultats (<?= count($results) ?> lignes)</h3>
        <div style="overflow-x:auto">
            <table class="results-table">
                <thead><tr><th>Ligne</th><th>Nom</th><th>Statut</th><th>Détail</th></tr></thead>
                <tbody>
                <?php foreach ($results as $r): ?>
                    <tr class="<?= $r['status'] === 'ok' ? '' : 'row-error' ?>">
                        <td><?= $r['line'] ?></td>
                        <td><?= htmlspecialchars($r['nom'] ?? '—') ?></td>
                        <td><span class="<?= $r['status'] === 'ok' ? 'badge-ok' : 'badge-err' ?>"><?= $r['status'] === 'ok' ? 'OK' : 'Erreur' ?></span></td>
                        <td><?= htmlspecialchars($r['msg']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
