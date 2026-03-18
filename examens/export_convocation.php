<?php
/**
 * Export PDF convocation examen
 * GET ?id=XX
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../API/bootstrap.php';
$bridge = new \Pronote\Legacy\Bridge();
$bridge->requireAuth();
$pdo = $bridge->getPDO();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { die('ID examen manquant.'); }

// Charger examen
$stmt = $pdo->prepare("SELECT e.*, CONCAT(el.prenom, ' ', el.nom) AS eleve_nom, el.date_naissance, el.classe,
                               m.nom AS matiere_nom
                        FROM examens e
                        LEFT JOIN eleves el ON e.eleve_id = el.id
                        LEFT JOIN matieres m ON e.matiere_id = m.id
                        WHERE e.id = ?");
$stmt->execute([$id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$exam) { die('Examen introuvable.'); }

// Charger l'établissement
$etab = [];
try {
    $etab = $pdo->query("SELECT * FROM etablissement LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (\Exception $e) {}

$nomEtab = $etab['nom'] ?? 'Établissement';
$adresse = trim(($etab['adresse'] ?? '') . ' ' . ($etab['code_postal'] ?? '') . ' ' . ($etab['ville'] ?? ''));

$html = '
<div style="text-align:center;margin-bottom:30px">
    <h2 style="margin:0">' . htmlspecialchars($nomEtab) . '</h2>
    <p style="margin:4px 0;font-size:12px">' . htmlspecialchars($adresse) . '</p>
</div>
<h3 style="text-align:center;border-bottom:2px solid #333;padding-bottom:8px">CONVOCATION À L\'EXAMEN</h3>
<table style="width:100%;margin:20px 0;font-size:14px">
    <tr><td style="width:200px;font-weight:bold">Élève :</td><td>' . htmlspecialchars($exam['eleve_nom'] ?? '—') . '</td></tr>
    <tr><td style="font-weight:bold">Classe :</td><td>' . htmlspecialchars($exam['classe'] ?? '—') . '</td></tr>
    <tr><td style="font-weight:bold">Date de naissance :</td><td>' . ($exam['date_naissance'] ? date('d/m/Y', strtotime($exam['date_naissance'])) : '—') . '</td></tr>
    <tr><td style="font-weight:bold">Examen :</td><td>' . htmlspecialchars($exam['titre'] ?? $exam['matiere_nom'] ?? '—') . '</td></tr>
    <tr><td style="font-weight:bold">Date :</td><td>' . ($exam['date_examen'] ? date('d/m/Y à H:i', strtotime($exam['date_examen'])) : '—') . '</td></tr>
    <tr><td style="font-weight:bold">Lieu / Salle :</td><td>' . htmlspecialchars($exam['salle'] ?? '—') . '</td></tr>
    <tr><td style="font-weight:bold">Durée :</td><td>' . htmlspecialchars($exam['duree'] ?? '—') . '</td></tr>
</table>
<div style="margin:30px 0;padding:15px;border:1px solid #ccc;border-radius:4px;background:#f9f9f9">
    <h4 style="margin:0 0 8px">Consignes :</h4>
    <ul style="margin:0;padding-left:20px;font-size:13px">
        <li>Munissez-vous de votre carte d\'identité ou d\'une pièce d\'identité valide.</li>
        <li>Présentez-vous 15 minutes avant le début de l\'épreuve.</li>
        <li>Aucun appareil électronique n\'est autorisé.</li>
    </ul>
</div>
<p style="margin-top:40px;text-align:right;font-size:13px">Fait à ' . htmlspecialchars($etab['ville'] ?? '…') . ', le ' . date('d/m/Y') . '</p>
<p style="text-align:right;font-size:13px;margin-top:30px">Le chef d\'établissement,<br><br><br>___________________________</p>
';

$pdfService = new \API\Services\PdfService($pdo);
$pdfService->generate($html, 'convocation', [
    'title'    => 'Convocation examen',
    'filename' => 'convocation_' . ($exam['eleve_nom'] ?? $id),
]);
