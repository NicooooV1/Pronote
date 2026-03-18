<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * Service universel d'export PDF
 * 
 * Génère des PDFs à partir de HTML en utilisant les templates configurables
 * stockés dans la table `pdf_templates`.
 * 
 * Pas de dépendance externe — utilise le buffer HTML + wkhtmltopdf si disponible,
 * sinon fallback sur un rendu HTML imprimable (print-friendly CSS).
 * 
 * L'admin peut configurer les templates (header, footer, CSS) depuis l'interface.
 */
class PdfService
{
    private PDO $pdo;
    private ?array $etablissement = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Génération PDF ──────────────────────────────────────────────────

    /**
     * Génère un PDF et le renvoie au navigateur
     *
     * @param string $html      Corps HTML à inclure dans le PDF
     * @param string $type      Type de template: bulletin, convocation, attestation, generic
     * @param array  $options   Options: title, filename, variables, inline (bool)
     */
    public function generate(string $html, string $type = 'generic', array $options = []): void
    {
        $template = $this->getTemplate($type);
        $etab = $this->getEtablissement();
        $filename = ($options['filename'] ?? 'export') . '.pdf';

        // Variables de remplacement
        $vars = array_merge([
            'etablissement_nom'      => $etab['nom'] ?? 'Établissement',
            'etablissement_adresse'  => $etab['adresse'] ?? '',
            'etablissement_cp'       => $etab['code_postal'] ?? '',
            'etablissement_ville'    => $etab['ville'] ?? '',
            'etablissement_tel'      => $etab['telephone'] ?? '',
            'etablissement_email'    => $etab['email'] ?? '',
            'etablissement_academie' => $etab['academie'] ?? '',
            'title'  => $options['title'] ?? 'Document',
            'date'   => date('d/m/Y'),
            'page'   => '{{page}}',
            'pages'  => '{{pages}}',
        ], $options['variables'] ?? []);

        // Rendre le header/footer avec variables
        $headerHtml = $this->replaceVars($template['header_html'] ?? '', $vars);
        $footerHtml = $this->replaceVars($template['footer_html'] ?? '', $vars);
        $bodyCss    = $template['body_css'] ?? '';
        $orientation = $template['orientation'] ?? 'portrait';
        $format      = $template['page_format'] ?? 'A4';
        $margins     = json_decode($template['margins_json'] ?? '{}', true) ?: ['top' => 15, 'right' => 10, 'bottom' => 15, 'left' => 10];

        // Logo de l'établissement
        $logoHtml = '';
        if (!empty($template['show_logo']) && !empty($etab['logo'])) {
            $logoPath = defined('BASE_PATH') ? BASE_PATH . '/' . $etab['logo'] : $etab['logo'];
            if (file_exists($logoPath)) {
                $logoData = base64_encode(file_get_contents($logoPath));
                $ext = pathinfo($logoPath, PATHINFO_EXTENSION);
                $logoHtml = '<img src="data:image/' . $ext . ';base64,' . $logoData . '" style="max-height:60px;max-width:200px" alt="Logo">';
            }
        }

        // Construire le document HTML complet
        $fullHtml = $this->buildDocument($headerHtml, $footerHtml, $html, $bodyCss, $logoHtml, $orientation, $format, $margins, $options['title'] ?? 'Document');

        // Essayer wkhtmltopdf d'abord, sinon Dompdf, sinon HTML imprimable
        if ($this->hasWkhtmltopdf()) {
            $this->renderWithWkhtmltopdf($fullHtml, $filename, !empty($options['inline']));
        } elseif (class_exists('Dompdf\\Dompdf')) {
            $this->renderWithDompdf($fullHtml, $filename, $orientation, $format, !empty($options['inline']));
        } else {
            $this->renderAsHtml($fullHtml, $filename);
        }
    }

    /**
     * Génère le HTML du PDF sans le rendre (pour preview ou email)
     */
    public function generateHtml(string $html, string $type = 'generic', array $options = []): string
    {
        $template = $this->getTemplate($type);
        $etab = $this->getEtablissement();

        $vars = array_merge([
            'etablissement_nom'      => $etab['nom'] ?? 'Établissement',
            'etablissement_adresse'  => $etab['adresse'] ?? '',
            'etablissement_cp'       => $etab['code_postal'] ?? '',
            'etablissement_ville'    => $etab['ville'] ?? '',
            'etablissement_tel'      => $etab['telephone'] ?? '',
            'etablissement_email'    => $etab['email'] ?? '',
            'etablissement_academie' => $etab['academie'] ?? '',
            'title' => $options['title'] ?? 'Document',
            'date'  => date('d/m/Y'),
            'page'  => '', 'pages' => '',
        ], $options['variables'] ?? []);

        $headerHtml = $this->replaceVars($template['header_html'] ?? '', $vars);
        $footerHtml = $this->replaceVars($template['footer_html'] ?? '', $vars);
        $bodyCss = $template['body_css'] ?? '';

        return $this->buildDocument($headerHtml, $footerHtml, $html, $bodyCss, '', 'portrait', 'A4', [], $options['title'] ?? 'Document');
    }

    // ─── Helpers de génération rapide ────────────────────────────────────

    /**
     * Génère un PDF à partir d'un tableau de données
     */
    public function generateTable(array $headers, array $rows, string $title = 'Export', array $options = []): void
    {
        $html = '<h2 style="margin:0 0 15px;color:#333">' . htmlspecialchars($title) . '</h2>';
        $html .= '<table><thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th>' . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $html .= '<p style="margin-top:15px;font-size:10px;color:#999">' . count($rows) . ' enregistrement(s) — Généré le ' . date('d/m/Y à H:i') . '</p>';

        $this->generate($html, $options['type'] ?? 'generic', array_merge($options, ['title' => $title]));
    }

    /**
     * Génère un PDF bulletin scolaire
     */
    public function generateBulletin(array $bulletinData, array $options = []): void
    {
        $eleve = $bulletinData['eleve'] ?? [];
        $periode = $bulletinData['periode'] ?? '';
        $matieres = $bulletinData['matieres'] ?? [];
        $appreciation = $bulletinData['appreciation_generale'] ?? '';

        $html = '<div style="margin-bottom:20px">';
        $html .= '<h2 style="text-align:center;margin:0">BULLETIN SCOLAIRE</h2>';
        $html .= '<p style="text-align:center;color:#666">' . htmlspecialchars($periode) . '</p>';
        $html .= '</div>';

        $html .= '<div style="background:#f8f9fa;padding:10px 15px;border-radius:4px;margin-bottom:15px">';
        $html .= '<strong>Élève :</strong> ' . htmlspecialchars(($eleve['prenom'] ?? '') . ' ' . ($eleve['nom'] ?? ''));
        $html .= ' — <strong>Classe :</strong> ' . htmlspecialchars($eleve['classe'] ?? '');
        $html .= '</div>';

        if (!empty($matieres)) {
            $html .= '<table>';
            $html .= '<thead><tr><th>Matière</th><th style="width:80px;text-align:center">Moyenne</th><th style="width:80px;text-align:center">Classe</th><th>Appréciation</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($matieres as $m) {
                $html .= '<tr>';
                $html .= '<td><strong>' . htmlspecialchars($m['nom'] ?? '') . '</strong><br><small style="color:#666">' . htmlspecialchars($m['professeur'] ?? '') . '</small></td>';
                $html .= '<td style="text-align:center" class="moyenne">' . htmlspecialchars($m['moyenne'] ?? '-') . '</td>';
                $html .= '<td style="text-align:center">' . htmlspecialchars($m['moyenne_classe'] ?? '-') . '</td>';
                $html .= '<td>' . htmlspecialchars($m['appreciation'] ?? '') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        if (!empty($appreciation)) {
            $html .= '<div style="margin-top:20px;padding:12px 15px;border:1px solid #ddd;border-radius:4px">';
            $html .= '<strong>Appréciation générale :</strong><br>' . nl2br(htmlspecialchars($appreciation));
            $html .= '</div>';
        }

        $this->generate($html, 'bulletin', array_merge($options, [
            'title' => 'Bulletin - ' . ($eleve['prenom'] ?? '') . ' ' . ($eleve['nom'] ?? '') . ' - ' . $periode,
            'filename' => 'bulletin_' . ($eleve['nom'] ?? 'eleve') . '_' . str_replace(' ', '_', strtolower($periode)),
        ]));
    }

    // ─── Gestion des templates ───────────────────────────────────────────

    /**
     * Récupère un template par type (le template par défaut)
     */
    public function getTemplate(string $type): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM pdf_templates WHERE type = ? AND is_default = 1 LIMIT 1"
            );
            $stmt->execute([$type]);
            $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tpl) return $tpl;

            // Fallback: premier template de ce type
            $stmt = $this->pdo->prepare("SELECT * FROM pdf_templates WHERE type = ? LIMIT 1");
            $stmt->execute([$type]);
            $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tpl) return $tpl;
        } catch (\PDOException $e) {
            error_log("PdfService::getTemplate error: " . $e->getMessage());
        }

        // Fallback total
        return [
            'header_html' => '<div style="border-bottom:1px solid #333;padding-bottom:8px;margin-bottom:15px"><strong>{{etablissement_nom}}</strong></div>',
            'footer_html' => '<div style="text-align:center;font-size:9px;color:#999">Généré le {{date}}</div>',
            'body_css' => 'body{font-family:Arial,sans-serif;font-size:12px} table{width:100%;border-collapse:collapse} th,td{border:1px solid #ddd;padding:6px}',
            'page_format' => 'A4', 'orientation' => 'portrait',
            'margins_json' => '{"top":15,"right":10,"bottom":15,"left":10}',
            'show_logo' => 1, 'show_etablissement' => 1,
        ];
    }

    /**
     * Récupère un template par ID
     */
    public function getTemplateById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM pdf_templates WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Liste tous les templates
     */
    public function listTemplates(?string $type = null): array
    {
        try {
            if ($type) {
                $stmt = $this->pdo->prepare("SELECT * FROM pdf_templates WHERE type = ? ORDER BY is_default DESC, name");
                $stmt->execute([$type]);
            } else {
                $stmt = $this->pdo->query("SELECT * FROM pdf_templates ORDER BY type, is_default DESC, name");
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Sauvegarde un template (create ou update)
     */
    public function saveTemplate(array $data): bool
    {
        try {
            if (!empty($data['id'])) {
                $stmt = $this->pdo->prepare("
                    UPDATE pdf_templates SET
                        name = ?, type = ?, description = ?, header_html = ?, footer_html = ?,
                        body_css = ?, page_format = ?, orientation = ?, margins_json = ?,
                        show_logo = ?, show_etablissement = ?, is_default = ?
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $data['name'], $data['type'], $data['description'] ?? '',
                    $data['header_html'] ?? '', $data['footer_html'] ?? '',
                    $data['body_css'] ?? '', $data['page_format'] ?? 'A4',
                    $data['orientation'] ?? 'portrait',
                    $data['margins_json'] ?? '{"top":15,"right":10,"bottom":15,"left":10}',
                    (int)($data['show_logo'] ?? 1), (int)($data['show_etablissement'] ?? 1),
                    (int)($data['is_default'] ?? 0), $data['id'],
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO pdf_templates
                        (name, type, description, header_html, footer_html, body_css,
                         page_format, orientation, margins_json, show_logo, show_etablissement, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                return $stmt->execute([
                    $data['name'], $data['type'], $data['description'] ?? '',
                    $data['header_html'] ?? '', $data['footer_html'] ?? '',
                    $data['body_css'] ?? '', $data['page_format'] ?? 'A4',
                    $data['orientation'] ?? 'portrait',
                    $data['margins_json'] ?? '{"top":15,"right":10,"bottom":15,"left":10}',
                    (int)($data['show_logo'] ?? 1), (int)($data['show_etablissement'] ?? 1),
                    (int)($data['is_default'] ?? 0),
                ]);
            }
        } catch (\PDOException $e) {
            error_log("PdfService::saveTemplate error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime un template
     */
    public function deleteTemplate(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM pdf_templates WHERE id = ? AND is_default = 0");
            return $stmt->execute([$id]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    // ─── Méthodes de rendu ───────────────────────────────────────────────

    private function buildDocument(string $header, string $footer, string $body, string $css, string $logo, string $orientation, string $format, array $margins, string $title): string
    {
        $marginsCss = '';
        if (!empty($margins)) {
            $marginsCss = sprintf('@page{size:%s %s;margin:%dmm %dmm %dmm %dmm}',
                $format, $orientation,
                $margins['top'] ?? 15, $margins['right'] ?? 10,
                $margins['bottom'] ?? 15, $margins['left'] ?? 10
            );
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>{$title}</title>
<style>
{$marginsCss}
@media print{.no-print{display:none!important} body{margin:0}}
{$css}
.pdf-header{margin-bottom:10px}
.pdf-footer{margin-top:20px}
.pdf-logo{margin-bottom:10px}
</style>
</head>
<body>
<div class="pdf-header">{$logo}{$header}</div>
<div class="pdf-body">{$body}</div>
<div class="pdf-footer">{$footer}</div>
</body>
</html>
HTML;
    }

    private function hasWkhtmltopdf(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            exec('where wkhtmltopdf 2>NUL', $out, $code);
        } else {
            exec('which wkhtmltopdf 2>/dev/null', $out, $code);
        }
        return $code === 0;
    }

    private function renderWithWkhtmltopdf(string $html, string $filename, bool $inline): void
    {
        $tmpIn = tempnam(sys_get_temp_dir(), 'pdf_in_') . '.html';
        $tmpOut = tempnam(sys_get_temp_dir(), 'pdf_out_') . '.pdf';
        file_put_contents($tmpIn, $html);

        $cmd = sprintf('wkhtmltopdf --quiet --encoding UTF-8 %s %s 2>&1',
            escapeshellarg($tmpIn), escapeshellarg($tmpOut)
        );
        exec($cmd, $output, $code);

        if ($code === 0 && file_exists($tmpOut)) {
            $disposition = $inline ? 'inline' : 'attachment';
            header('Content-Type: application/pdf');
            header("Content-Disposition: {$disposition}; filename=\"{$filename}\"");
            header('Content-Length: ' . filesize($tmpOut));
            readfile($tmpOut);
        } else {
            // Fallback to HTML
            $this->renderAsHtml($html, $filename);
        }

        @unlink($tmpIn);
        @unlink($tmpOut);
        exit;
    }

    private function renderWithDompdf(string $html, string $filename, string $orientation, string $format, bool $inline): void
    {
        $dompdf = new \Dompdf\Dompdf(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($format, $orientation);
        $dompdf->render();

        $disposition = $inline ? 'inline' : 'attachment';
        $dompdf->stream($filename, ['Attachment' => !$inline]);
        exit;
    }

    /**
     * Fallback: rendu HTML avec CSS d'impression
     */
    private function renderAsHtml(string $html, string $filename): void
    {
        // Ajouter un bouton d'impression et CSS
        $printBar = '<div class="no-print" style="position:fixed;top:0;left:0;right:0;background:#333;padding:10px 20px;z-index:9999;display:flex;align-items:center;gap:15px">'
            . '<button onclick="window.print()" style="padding:8px 20px;background:#667eea;color:#fff;border:none;border-radius:4px;cursor:pointer;font-weight:bold">🖨️ Imprimer / PDF</button>'
            . '<span style="color:#ccc;font-size:13px">Utilisez « Enregistrer en PDF » dans la boîte de dialogue d\'impression</span>'
            . '</div>'
            . '<div style="height:50px" class="no-print"></div>';

        $html = str_replace('<body>', '<body>' . $printBar, $html);

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    // ─── Utilitaires ─────────────────────────────────────────────────────

    private function replaceVars(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string)$value, $template);
        }
        return $template;
    }

    private function getEtablissement(): array
    {
        if ($this->etablissement === null) {
            try {
                $stmt = $this->pdo->query("SELECT * FROM etablissement_info LIMIT 1");
                $this->etablissement = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $e) {
                $this->etablissement = [];
            }
        }
        return $this->etablissement;
    }
}
