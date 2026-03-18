<?php
/**
 * Export PDF convention de stage
 * GET ?id=XX
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../API/bootstrap.php';
$bridge = new \Pronote\Legacy\Bridge();
$bridge->requireAuth();
$pdo = $bridge->getPDO();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { die('ID stage manquant.'); }

$stmt = $pdo->prepare(
    "SELECT s.*, CONCAT(el.prenom, ' ', el.nom) AS eleve_nom, el.date_naissance, el.classe,
            CONCAT(p.prenom, ' ', p.nom) AS tuteur_nom
     FROM stages s
     LEFT JOIN eleves el ON s.eleve_id = el.id
     LEFT JOIN professeurs p ON s.tuteur_id = p.id
     WHERE s.id = ?"
);
$stmt->execute([$id]);
$stage = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$stage) { die('Stage introuvable.'); }

$etab = [];
try { $etab = $pdo->query("SELECT * FROM etablissement LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: []; } catch (\Exception $e) {}
$nomEtab = $etab['nom'] ?? 'Établissement';
$adresse = trim(($etab['adresse'] ?? '') . ' ' . ($etab['code_postal'] ?? '') . ' ' . ($etab['ville'] ?? ''));

$html = '
<div style="text-align:center;margin-bottom:30px">
    <h2 style="margin:0">' . htmlspecialchars($nomEtab) . '</h2>
    <p style="margin:4px 0;font-size:12px">' . htmlspecialchars($adresse) . '</p>
</div>
<h3 style="text-align:center;border-bottom:2px solid #333;padding-bottom:8px">CONVENTION DE STAGE</h3>

<p style="font-size:13px;margin-top:20px"><strong>Entre :</strong></p>
<ol style="font-size:13px">
    <li><strong>' . htmlspecialchars($nomEtab) . '</strong>, ci-après dénommé « l\'établissement »</li>
    <li><strong>' . htmlspecialchars($stage['entreprise'] ?? '—') . '</strong>, ci-après dénommé « l\'organisme d\'accueil »</li>
    <li><strong>' . htmlspecialchars($stage['eleve_nom'] ?? '—') . '</strong>, ci-après dénommé « le/la stagiaire »</li>
</ol>

<h4 style="margin-top:20px">Article 1 — Objet du stage</h4>
<table style="width:100%;font-size:13px;margin:10px 0">
    <tr><td style="width:200px;font-weight:bold">Élève :</td><td>' . htmlspecialchars($stage['eleve_nom'] ?? '—') . '</td></tr>
    <tr><td style="font-weight:bold">Classe :</td><td>' . htmlspecialchars($stage['classe'] ?? '—') . '</td></tr>
    <tr><td style="font-weight:bold">Entreprise :</td><td>' . htmlspecialchars($stage['entreprise'] ?? '—') . '</td></tr>
    <tr><td style="font-weight:bold">Adresse :</td><td>' . htmlspecialchars($stage['adresse_entreprise'] ?? '—') . '</td></tr>
    <tr><td style="font-weight:bold">Tuteur entreprise :</td><td>' . htmlspecialchars($stage['tuteur_entreprise'] ?? '—') . '</td></tr>
    <tr><td style="font-weight:bold">Tuteur pédagogique :</td><td>' . htmlspecialchars($stage['tuteur_nom'] ?? '—') . '</td></tr>
</table>

<h4>Article 2 — Durée</h4>
<p style="font-size:13px">Du <strong>' . ($stage['date_debut'] ? date('d/m/Y', strtotime($stage['date_debut'])) : '…') . '</strong>
   au <strong>' . ($stage['date_fin'] ? date('d/m/Y', strtotime($stage['date_fin'])) : '…') . '</strong></p>

<h4>Article 3 — Objectifs</h4>
<p style="font-size:13px">' . nl2br(htmlspecialchars($stage['objectifs'] ?? 'À compléter')) . '</p>

<h4>Article 4 — Horaires</h4>
<p style="font-size:13px">' . htmlspecialchars($stage['horaires'] ?? 'Selon les horaires de l\'organisme d\'accueil.') . '</p>

<div style="margin-top:40px;display:flex;justify-content:space-between;font-size:12px">
    <div style="width:30%;text-align:center;border-top:1px solid #333;padding-top:5px">Le chef d\'établissement</div>
    <div style="width:30%;text-align:center;border-top:1px solid #333;padding-top:5px">Le responsable de l\'organisme</div>
    <div style="width:30%;text-align:center;border-top:1px solid #333;padding-top:5px">Le/la stagiaire</div>
</div>
<p style="text-align:right;font-size:11px;margin-top:30px">Fait à ' . htmlspecialchars($etab['ville'] ?? '…') . ', le ' . date('d/m/Y') . '</p>
';

$pdfService = new \API\Services\PdfService($pdo);
$pdfService->generate($html, 'convention', [
    'title'    => 'Convention de stage',
    'filename' => 'convention_' . ($stage['eleve_nom'] ?? $id),
]);
