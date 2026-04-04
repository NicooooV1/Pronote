<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * BulletinPdfService — Génération de bulletins scolaires PDF/HTML.
 *
 * Mode 1 (fallback) : HTML optimisé pour @media print
 * Mode 2 (si DomPDF) : Fichier PDF natif
 */
class BulletinPdfService
{
    private PDO $pdo;
    private string $basePath;

    public function __construct(PDO $pdo, string $basePath)
    {
        $this->pdo = $pdo;
        $this->basePath = $basePath;
    }

    public function hasDomPdf(): bool
    {
        return class_exists('\\Dompdf\\Dompdf');
    }

    public function generateBulletin(int $eleveId, int $periodeId): array
    {
        $eleve = $this->getEleve($eleveId);
        if (!$eleve) return ['success' => false, 'error' => 'Élève introuvable'];

        $periode = $this->getPeriode($periodeId);
        $notes = $this->getNotes($eleveId, $periodeId);
        $absences = $this->getAbsences($eleveId, $periodeId);
        $etab = $this->getEtablissement();

        $html = $this->renderHtml($eleve, $periode, $notes, $absences, $etab);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $eleve['nom'] . '_' . ($periode['nom'] ?? 'P'));

        if ($this->hasDomPdf()) {
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $filename = "bulletin_{$safeName}.pdf";
            $dir = $this->basePath . '/storage/pdf';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($dir . '/' . $filename, $dompdf->output());

            return ['success' => true, 'type' => 'pdf', 'path' => $dir . '/' . $filename, 'filename' => $filename];
        }

        return ['success' => true, 'type' => 'html', 'html' => $html, 'filename' => "bulletin_{$safeName}.html"];
    }

    private function renderHtml(array $eleve, ?array $periode, array $notes, array $absences, array $etab): string
    {
        $pNom = htmlspecialchars($periode['nom'] ?? 'N/A');
        $eNom = htmlspecialchars($etab['nom'] ?? 'Établissement');
        $fullName = htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']);
        $classe = htmlspecialchars($eleve['classe_nom'] ?? '-');
        $dob = htmlspecialchars($eleve['date_naissance'] ?? '-');
        $now = date('d/m/Y');

        $byMat = [];
        $tw = 0; $tc = 0;
        foreach ($notes as $n) {
            $m = $n['matiere_nom'] ?? '?';
            if (!isset($byMat[$m])) $byMat[$m] = ['c' => 0, 's' => 0, 'w' => 0];
            $n20 = $n['bareme'] > 0 ? $n['note'] / $n['bareme'] * 20 : 0;
            $co = (float)($n['coefficient'] ?? 1);
            $byMat[$m]['c']++;
            $byMat[$m]['s'] += $n20 * $co;
            $byMat[$m]['w'] += $co;
            $tw += $n20 * $co; $tc += $co;
        }
        $mg = $tc > 0 ? round($tw / $tc, 2) : '-';
        $aT = count($absences);
        $aJ = count(array_filter($absences, fn($a) => !empty($a['justifie'])));

        $rows = '';
        foreach ($byMat as $mat => $d) {
            $avg = $d['w'] > 0 ? round($d['s'] / $d['w'], 2) : '-';
            $rows .= "<tr><td><b>" . htmlspecialchars($mat) . "</b></td><td class='c'>{$d['c']}</td><td class='c'><b>{$avg}</b>/20</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Bulletin - {$fullName}</title>
<style>
@page{size:A4;margin:15mm}*{box-sizing:border-box}body{font-family:'Segoe UI',Arial,sans-serif;font-size:10pt;color:#333;padding:20px;margin:0}
.h{display:flex;justify-content:space-between;border-bottom:3px solid #667eea;padding-bottom:12px;margin-bottom:16px}
.h h1{font-size:15pt;color:#667eea;margin:0}.h .s{font-size:9pt;color:#718096}
.ig{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;background:#f7fafc;padding:10px;border-radius:6px;font-size:9pt}
table{width:100%;border-collapse:collapse;margin-bottom:16px}th{background:#667eea;color:#fff;padding:6px 10px;text-align:left;font-size:8.5pt}
td{padding:5px 10px;border-bottom:1px solid #e2e8f0;font-size:9pt}.c{text-align:center}tr:nth-child(even){background:#f7fafc}
.sm{background:#f0f4ff;padding:12px;border-radius:6px;display:flex;gap:24px;justify-content:center}
.sv{font-size:16pt;font-weight:700;color:#667eea;text-align:center}.sl{font-size:7.5pt;color:#718096;text-align:center}
.ft{margin-top:20px;font-size:7.5pt;color:#a0aec0;text-align:center;border-top:1px solid #eee;padding-top:8px}
</style></head><body>
<div class="h"><div><h1>BULLETIN SCOLAIRE</h1><div class="s">{$eNom}</div></div><div style="text-align:right"><b>{$pNom}</b><br><span class="s">{$now}</span></div></div>
<div class="ig"><span><b>Élève :</b> {$fullName}</span><span><b>Classe :</b> {$classe}</span><span><b>Naissance :</b> {$dob}</span><span><b>Année :</b> {$periode['annee_scolaire'] ?? date('Y')}</span></div>
<table><thead><tr><th>Matière</th><th class="c">Éval.</th><th class="c">Moyenne</th></tr></thead><tbody>{$rows}</tbody></table>
<div class="sm"><div><div class="sv">{$mg}</div><div class="sl">Moyenne /20</div></div><div><div class="sv">{$aT}</div><div class="sl">Absences</div></div><div><div class="sv">{$aJ}</div><div class="sl">Justifiées</div></div></div>
<div class="ft">Fronote — {$eNom}</div></body></html>
HTML;
    }

    private function getEleve(int $id): ?array
    {
        $s = $this->pdo->prepare("SELECT e.*, c.nom AS classe_nom FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id WHERE e.id = ?");
        $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getPeriode(int $id): ?array
    {
        $s = $this->pdo->prepare("SELECT * FROM periodes WHERE id = ?");
        $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getNotes(int $eleveId, int $periodeId): array
    {
        $s = $this->pdo->prepare("SELECT n.*, m.nom AS matiere_nom FROM notes n LEFT JOIN matieres m ON m.id = n.matiere_id WHERE n.eleve_id = ? AND n.periode_id = ? ORDER BY m.nom");
        $s->execute([$eleveId, $periodeId]); return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getAbsences(int $eleveId, int $periodeId): array
    {
        $s = $this->pdo->prepare("SELECT a.* FROM absences a JOIN periodes p ON p.id = ? WHERE a.eleve_id = ? AND a.date_absence BETWEEN p.date_debut AND p.date_fin");
        $s->execute([$periodeId, $eleveId]); return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getEtablissement(): array
    {
        try { return app('etablissement')->getInfo(); } catch (\Throwable $e) { return ['nom' => 'Établissement']; }
    }
}
