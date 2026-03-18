<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * Service d'export générique – CSV et PDF.
 *
 * Usage :
 *   $export = new ExportService($pdo);
 *   $export->csv($data, $columns, 'absences_export.csv');
 *   $export->pdf($html, 'bulletin.pdf');
 */
class ExportService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Export CSV – envoie directement au navigateur
     *
     * @param array  $data     Tableau de lignes (assoc)
     * @param array  $columns  ['db_key' => 'Label affiché', ...]
     * @param string $filename Nom du fichier
     */
    public function csv(array $data, array $columns, string $filename = 'export.csv'): void
    {
        $this->logExport('csv', $filename);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $output = fopen('php://output', 'w');
        // BOM UTF-8 pour Excel
        fwrite($output, "\xEF\xBB\xBF");

        // En-têtes
        fputcsv($output, array_values($columns), ';');

        // Données
        foreach ($data as $row) {
            $line = [];
            foreach (array_keys($columns) as $key) {
                $line[] = $row[$key] ?? '';
            }
            fputcsv($output, $line, ';');
        }

        fclose($output);
        exit;
    }

    /**
     * Export CSV depuis une requête SQL
     */
    public function csvFromQuery(string $sql, array $params, array $columns, string $filename = 'export.csv'): void
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->csv($data, $columns, $filename);
    }

    /**
     * Export PDF basique (HTML → PDF via navigateur)
     * Génère une page HTML optimisée pour l'impression
     */
    public function pdf(string $htmlContent, string $title = 'Export', string $filename = 'export.pdf'): void
    {
        $this->logExport('pdf', $filename);

        $printCss = $this->getPrintCss();

        $html = '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>' . htmlspecialchars($title) . '</title>
<style>' . $printCss . '</style>
</head>
<body>
<div class="print-header">
    <h1>' . htmlspecialchars($title) . '</h1>
    <p class="print-date">Généré le ' . date('d/m/Y à H:i') . '</p>
</div>
' . $htmlContent . '
<script>window.onload=function(){window.print();}</script>
</body>
</html>';

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . str_replace('.pdf', '.html', $filename) . '"');
        echo $html;
        exit;
    }

    /**
     * Génère un tableau HTML à partir de données (pour export PDF)
     */
    public function buildTable(array $data, array $columns, string $title = ''): string
    {
        $html = '';
        if ($title) {
            $html .= '<h2>' . htmlspecialchars($title) . '</h2>';
        }
        $html .= '<table class="print-table"><thead><tr>';
        foreach (array_values($columns) as $label) {
            $html .= '<th>' . htmlspecialchars($label) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($data as $row) {
            $html .= '<tr>';
            foreach (array_keys($columns) as $key) {
                $html .= '<td>' . htmlspecialchars((string)($row[$key] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }
        if (empty($data)) {
            $html .= '<tr><td colspan="' . count($columns) . '" class="empty">Aucune donnée</td></tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<p class="print-footer">Total : ' . count($data) . ' enregistrement(s)</p>';
        return $html;
    }

    /**
     * Enregistre l'export dans le journal
     */
    private function logExport(string $type, string $filename): void
    {
        try {
            $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
            $stmt = $this->pdo->prepare("
                INSERT INTO export_jobs (user_id, user_type, type, module, filename, status, completed_at)
                VALUES (?, ?, ?, ?, ?, 'completed', NOW())
            ");
            $stmt->execute([
                $user['id'] ?? 0,
                $user['type'] ?? 'unknown',
                $type,
                $this->guessModule(),
                $filename
            ]);
        } catch (\PDOException $e) {
            // Silencieux – la table n'existe peut-être pas encore
        }
    }

    private function guessModule(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $parts = explode('/', trim(parse_url($uri, PHP_URL_PATH) ?? '', '/'));
        return $parts[1] ?? $parts[0] ?? 'unknown';
    }

    private function getPrintCss(): string
    {
        return '
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: "Segoe UI", Arial, sans-serif; font-size: 11pt; color: #333; padding: 20mm; }
.print-header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
.print-header h1 { font-size: 18pt; color: #1e293b; margin-bottom: 5px; }
.print-date { color: #64748b; font-size: 9pt; }
.print-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
.print-table th { background: #2563eb; color: #fff; padding: 8px 10px; text-align: left; font-size: 9pt; text-transform: uppercase; letter-spacing: .5px; }
.print-table td { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; font-size: 10pt; }
.print-table tr:nth-child(even) { background: #f8fafc; }
.print-table .empty { text-align: center; color: #94a3b8; font-style: italic; padding: 20px; }
.print-footer { text-align: right; font-size: 9pt; color: #64748b; margin-top: 10px; }
h2 { font-size: 14pt; color: #1e293b; margin: 20px 0 10px; }
@media print { body { padding: 10mm; } .no-print { display: none !important; } }
@page { size: A4; margin: 15mm; }
';
    }
}
