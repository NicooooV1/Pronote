<?php
/**
 * M22 – Reporting — Export CSV
 */
$pageTitle = 'Exporter des données';

$type = $_GET['type'] ?? '';
$classeId = isset($_GET['classe_id']) ? (int)$_GET['classe_id'] : 0;
$go = isset($_GET['go']);

// Si export direct, on ne charge pas le template complet
if ($go && $type && $classeId) {
    require_once __DIR__ . '/../API/bootstrap.php';
    requireAuth();
    if (!isAdmin() && !isTeacher() && !isVieScolaire()) { die('Accès refusé'); }

    require_once __DIR__ . '/includes/ReportingService.php';
    $reportService = new ReportingService(getPDO());

    switch ($type) {
        case 'absences':
            $dateDebut = $_GET['date_debut'] ?? null;
            $dateFin   = $_GET['date_fin'] ?? null;
            $rows = $reportService->exportAbsencesCSV($classeId, $dateDebut, $dateFin);
            ReportingService::sendCSV('absences_export.csv',
                ['Nom','Prénom','Classe','Date','Motif','Justifiée','Heure début','Heure fin'],
                $rows);
            break;

        case 'notes':
            $periodeId = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
            $rows = $reportService->exportNotesCSV($classeId, $periodeId ?: null);
            ReportingService::sendCSV('notes_export.csv',
                ['Nom','Prénom','Matière','Note','Sur','Coefficient','Commentaire','Date'],
                $rows);
            break;

        case 'moyennes':
            $periodeId = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
            $rows = $reportService->exportMoyennesClasse($classeId, $periodeId ?: null);
            ReportingService::sendCSV('moyennes_export.csv',
                ['Nom','Prénom','Matière','Moyenne'],
                $rows);
            break;

        case 'incidents':
            $dateDebut = $_GET['date_debut'] ?? null;
            $dateFin   = $_GET['date_fin'] ?? null;
            $rows = $reportService->exportIncidents($classeId, $dateDebut, $dateFin);
            ReportingService::sendCSV('incidents_export.csv',
                ['Nom','Prénom','Classe','Type','Description','Gravité','Date','Statut'],
                $rows);
            break;

        default:
            die('Type d\'export inconnu.');
    }
    exit;
}

// Sinon, afficher le formulaire
require_once __DIR__ . '/includes/header.php';
$classes = $reportService->getClasses();
$periodes = $reportService->getPeriodes();
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-file-csv"></i> Exporter des données</h1>
        <a href="reporting.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="export-form-wrap">
        <div class="card">
            <div class="card-header"><h2>Paramètres d'export</h2></div>
            <div class="card-body">
                <form method="get">
                    <input type="hidden" name="go" value="1">

                    <div class="form-group">
                        <label class="form-label">Type d'export</label>
                        <select name="type" class="form-select" required>
                            <option value="">-- Choisir --</option>
                            <option value="absences" <?= $type === 'absences' ? 'selected' : '' ?>>Absences</option>
                            <option value="notes" <?= $type === 'notes' ? 'selected' : '' ?>>Notes</option>
                            <option value="moyennes" <?= $type === 'moyennes' ? 'selected' : '' ?>>Moyennes</option>
                            <option value="incidents" <?= $type === 'incidents' ? 'selected' : '' ?>>Incidents</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Classe</label>
                        <select name="classe_id" class="form-select" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $c['id'] == $classeId ? 'selected' : '' ?>><?= htmlspecialchars($c['niveau'].' – '.$c['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Date début <small>(optionnel)</small></label>
                            <input type="date" name="date_debut" class="form-control" value="<?= htmlspecialchars($_GET['date_debut'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date fin <small>(optionnel)</small></label>
                            <input type="date" name="date_fin" class="form-control" value="<?= htmlspecialchars($_GET['date_fin'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Période <small>(pour notes/moyennes)</small></label>
                        <select name="periode_id" class="form-select">
                            <option value="0">Toutes les périodes</option>
                            <?php foreach ($periodes as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-download"></i> Télécharger le CSV</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
