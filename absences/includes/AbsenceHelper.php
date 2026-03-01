<?php
/**
 * AbsenceHelper — Fonctions utilitaires partagées pour le module Absences.
 * Remplace les utilities éparpillées et centralise :
 * - Récupération des classes (via API, plus de etablissement.json)
 * - Sanitization des entrées (remplacement de FILTER_SANITIZE_STRING)
 * - Formatage de durées
 * - CSRF
 * - Pagination
 * - Dates de période
 */

class AbsenceHelper
{
    /**
     * Récupère la liste plate des classes depuis l'API EtablissementService.
     * Remplace json_decode(file_get_contents('../login/data/etablissement.json')).
     */
    public static function getClassesList(): array
    {
        $classes = [];
        try {
            $data = getEtablissementData();
            if (!empty($data['classes'])) {
                foreach ($data['classes'] as $niveau => $niveaux) {
                    if (is_array($niveaux)) {
                        foreach ($niveaux as $cycle => $listeClasses) {
                            if (is_array($listeClasses)) {
                                foreach ($listeClasses as $nomClasse) {
                                    $classes[] = $nomClasse;
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Fallback silencieux
        }
        return $classes;
    }

    /**
     * Récupère et valide les filtres GET courants.
     * Remplace les dizaines de filter_input(INPUT_GET, …, FILTER_SANITIZE_STRING).
     */
    public static function getFilters(array $defaults = []): array
    {
        $defaults = array_merge([
            'date_debut' => date('Y-m-d', strtotime('-30 days')),
            'date_fin'   => date('Y-m-d'),
            'classe'     => '',
            'justifie'   => '',
            'traite'     => '',
            'view'       => 'list',
            'type'       => 'absences',
            'periode'    => '',
            'page'       => 1,
            'sort'       => 'date',
            'order'      => 'desc',
        ], $defaults);

        return [
            'date_debut' => self::sanitizeDate($_GET['date_debut'] ?? $defaults['date_debut']),
            'date_fin'   => self::sanitizeDate($_GET['date_fin'] ?? $defaults['date_fin']),
            'classe'     => self::sanitize($_GET['classe'] ?? $defaults['classe']),
            'justifie'   => self::sanitize($_GET['justifie'] ?? $defaults['justifie']),
            'traite'     => self::sanitize($_GET['traite'] ?? $defaults['traite']),
            'view'       => self::sanitize($_GET['view'] ?? $defaults['view']),
            'type'       => self::sanitize($_GET['type'] ?? $defaults['type']),
            'periode'    => self::sanitize($_GET['periode'] ?? $defaults['periode']),
            'page'       => max(1, intval($_GET['page'] ?? $defaults['page'])),
            'sort'       => self::sanitize($_GET['sort'] ?? $defaults['sort']),
            'order'      => self::sanitize($_GET['order'] ?? $defaults['order']),
        ];
    }

    /**
     * Sanitize une chaîne — remplacement de FILTER_SANITIZE_STRING (déprécié PHP 8.1).
     */
    public static function sanitize(?string $value): string
    {
        if ($value === null) return '';
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Valide et sanitize une date au format Y-m-d.
     */
    public static function sanitizeDate(?string $date): string
    {
        if (empty($date)) return '';
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if ($d && $d->format('Y-m-d') === $date) {
            return $date;
        }
        return date('Y-m-d');
    }

    /**
     * Formate une durée en minutes en texte lisible.
     */
    public static function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) return '0 min';
        
        $jours = floor($minutes / (24 * 60));
        $heures = floor(($minutes % (24 * 60)) / 60);
        $mins = $minutes % 60;

        $parts = [];
        if ($jours > 0) $parts[] = $jours . ' jour' . ($jours > 1 ? 's' : '');
        if ($heures > 0) $parts[] = $heures . 'h';
        if ($mins > 0 || empty($parts)) $parts[] = $mins . 'min';

        return implode(' ', $parts);
    }

    /**
     * Formate une durée entre deux DateTime en texte lisible.
     */
    public static function formatDurationBetween(string $dateDebut, string $dateFin): string
    {
        $debut = new DateTime($dateDebut);
        $fin   = new DateTime($dateFin);
        $interval = $debut->diff($fin);
        
        $parts = [];
        if ($interval->d > 0) {
            $parts[] = $interval->d . ' jour' . ($interval->d > 1 ? 's' : '');
        }
        if ($interval->h > 0) {
            $parts[] = $interval->h . ' heure' . ($interval->h > 1 ? 's' : '');
        }
        if ($interval->i > 0 || empty($parts)) {
            $parts[] = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
        }
        return implode(' et ', $parts);
    }

    /**
     * Génère et stocke un token CSRF en session.
     */
    public static function generateCsrf(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    /**
     * Vérifie un token CSRF soumis en POST.
     */
    public static function verifyCsrf(): bool
    {
        $submitted = $_POST['csrf_token'] ?? '';
        $stored = $_SESSION['csrf_token'] ?? '';
        if (empty($submitted) || empty($stored)) return false;
        return hash_equals($stored, $submitted);
    }

    /**
     * Pagine un tableau.
     * @return array ['items' => array, 'current_page' => int, 'total_pages' => int, 'total' => int]
     */
    public static function paginate(array $items, int $page = 1, int $perPage = 20): array
    {
        $total = count($items);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($totalPages, $page));
        $offset = ($page - 1) * $perPage;

        return [
            'items'        => array_slice($items, $offset, $perPage),
            'current_page' => $page,
            'total_pages'  => $totalPages,
            'total'        => $total,
        ];
    }

    /**
     * Calcule les dates de début/fin selon la période sélectionnée.
     */
    public static function getPeriodDates(string $periode): array
    {
        $year = date('Y');
        $month = (int) date('m');
        
        // Année scolaire: sept à août
        $anneeScolaire = $month >= 9 ? $year : $year - 1;

        switch ($periode) {
            case 'trimestre_1':
                return [
                    'debut' => "$anneeScolaire-09-01",
                    'fin'   => "$anneeScolaire-11-30"
                ];
            case 'trimestre_2':
                $next = $anneeScolaire + 1;
                return [
                    'debut' => "$anneeScolaire-12-01",
                    'fin'   => "$next-02-28"
                ];
            case 'trimestre_3':
                $next = $anneeScolaire + 1;
                return [
                    'debut' => "$next-03-01",
                    'fin'   => "$next-05-31"
                ];
            case 'trimestre_4':
                $next = $anneeScolaire + 1;
                return [
                    'debut' => "$next-06-01",
                    'fin'   => "$next-08-31"
                ];
            case 'annee':
            default:
                $next = $anneeScolaire + 1;
                return [
                    'debut' => "$anneeScolaire-09-01",
                    'fin'   => "$next-08-31"
                ];
        }
    }

    /**
     * Libellé lisible pour un type d'absence.
     */
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'cours'         => 'Cours',
            'demi-journee'  => 'Demi-journée',
            'journee'       => 'Journée complète',
            default         => ucfirst($type),
        };
    }

    /**
     * Libellé lisible pour un motif.
     */
    public static function motifLabel(?string $motif): string
    {
        return match ($motif) {
            'maladie'      => 'Maladie',
            'rdv_medical'  => 'Rendez-vous médical',
            'familial'     => 'Raison familiale',
            'transport'    => 'Problème de transport',
            'autre'        => 'Autre',
            null, ''       => 'Non spécifié',
            default        => ucfirst($motif),
        };
    }

    /**
     * Types d'absence valides.
     */
    public static function validTypes(): array
    {
        return ['cours', 'demi-journee', 'journee'];
    }

    /**
     * Motifs valides.
     */
    public static function validMotifs(): array
    {
        return ['', 'maladie', 'rdv_medical', 'familial', 'transport', 'autre'];
    }
}
