<?php
/**
 * Export PDF diplôme / attestation
 * GET ?id=XX
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../API/bootstrap.php';
$bridge = new \Pronote\Legacy\Bridge();
$bridge->requireAuth();
$pdo = $bridge->getPDO();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { die('ID diplôme manquant.'); }

$stmt = $pdo->prepare(
    "SELECT d.*, CONCAT(el.prenom, ' ', el.nom) AS eleve_nom, el.date_naissance, el.classe
     FROM diplomes d
     LEFT JOIN eleves el ON d.eleve_id = el.id
     WHERE d.id = ?"
);
$stmt->execute([$id]);
$diplome = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$diplome) { die('Diplôme introuvable.'); }

$etab = [];
try { $etab = $pdo->query("SELECT * FROM etablissement LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: []; } catch (\Exception $e) {}
$nomEtab = $etab['nom'] ?? 'Établissement';
$academie = $etab['academie'] ?? 'Académie';

$mention = '';
if (!empty($diplome['mention'])) {
    $mentionLabels = ['AB' => 'Assez Bien', 'B' => 'Bien', 'TB' => 'Très Bien'];
    $mention = $mentionLabels[$diplome['mention']] ?? $diplome['mention'];
}

$html = '
<div style="text-align:center;margin-bottom:10px;font-size:12px">
    <p style="margin:0">Académie de ' . htmlspecialchars($academie) . '</p>
    <p style="margin:0">' . htmlspecialchars($nomEtab) . '</p>
</div>
<div style="text-align:center;margin:40px 0;padding:30px;border:3px double #333">
    <h2 style="font-size:24px;margin:0 0 10px;letter-spacing:2px">DIPLÔME</h2>
    <h3 style="font-size:18px;margin:0 0 20px;color:#444">' . htmlspecialchars($diplome['titre'] ?? $diplome['type_diplome'] ?? 'Diplôme') . '</h3>
    <p style="font-size:16px;margin:20px 0">Décerné à</p>
    <p style="font-size:22px;font-weight:bold;margin:10px 0">' . htmlspecialchars($diplome['eleve_nom'] ?? '—') . '</p>
    <p style="font-size:13px;margin:5px 0">Né(e) le ' . ($diplome['date_naissance'] ? date('d/m/Y', strtotime($diplome['date_naissance'])) : '…') . '</p>
    <p style="font-size:13px;margin:5px 0">Classe : ' . htmlspecialchars($diplome['classe'] ?? '—') . '</p>
    ' . ($mention ? '<p style="font-size:16px;margin:20px 0;color:#1a5276"><strong>Mention : ' . htmlspecialchars($mention) . '</strong></p>' : '') . '
    ' . (!empty($diplome['moyenne']) ? '<p style="font-size:14px;margin:10px 0">Moyenne obtenue : ' . number_format((float)$diplome['moyenne'], 2, ',', '') . '/20</p>' : '') . '
    <p style="font-size:12px;margin:20px 0;color:#666">Session ' . htmlspecialchars($diplome['session'] ?? date('Y')) . '</p>
</div>
<div style="margin-top:30px;text-align:right;font-size:12px">
    <p>Fait à ' . htmlspecialchars($etab['ville'] ?? '…') . ', le ' . ($diplome['date_obtention'] ? date('d/m/Y', strtotime($diplome['date_obtention'])) : date('d/m/Y')) . '</p>
    <p style="margin-top:30px">Le chef d\'établissement,<br><br><br>___________________________</p>
</div>
';

$pdfService = new \API\Services\PdfService($pdo);
$pdfService->generate($html, 'attestation', [
    'title'    => 'Diplôme — ' . ($diplome['eleve_nom'] ?? ''),
    'filename' => 'diplome_' . ($diplome['eleve_nom'] ?? $id),
]);
