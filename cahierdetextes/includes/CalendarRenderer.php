<?php
/**
 * CalendarRenderer — Composant calendrier réutilisable (REF-3)
 *
 * Génère le HTML du calendrier mensuel pour le cahier de textes.
 * Extrait de cahierdetextes.php pour isoler la complexité.
 */
class CalendarRenderer
{
    private const MONTHS = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    private const DAYS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

    private int    $month;
    private int    $year;
    private array  $devoirsByDate = [];
    private string $orderField;

    /**
     * @param array    $devoirs    Liste de devoirs (chaque devoir a date_rendu, titre, nom_matiere, id)
     * @param int|null $month      Mois à afficher (défaut : mois courant)
     * @param int|null $year       Année à afficher (défaut : année courante)
     * @param string   $orderField Champ d'ordre actif (pour conserver dans les liens nav)
     */
    public function __construct(array $devoirs, ?int $month = null, ?int $year = null, string $orderField = 'date_rendu')
    {
        $this->month      = $month ?? (int) date('n');
        $this->year       = $year  ?? (int) date('Y');
        $this->orderField = $orderField;

        foreach ($devoirs as $d) {
            $this->devoirsByDate[$d['date_rendu']][] = $d;
        }
    }

    /**
     * Génère le HTML complet du calendrier.
     *
     * @param callable $statusFn  function(string $dateRendu): array{class:string, label:string, icon:string}
     */
    public function render(callable $statusFn): string
    {
        $firstDay    = mktime(0, 0, 0, $this->month, 1, $this->year);
        $numDays     = (int) date('t', $firstDay);
        $dayOfWeek   = (int) date('w', $firstDay);
        $dayOfWeek   = $dayOfWeek === 0 ? 6 : $dayOfWeek - 1; // Lundi = 0

        $prev = $this->adjacentMonth(-1);
        $next = $this->adjacentMonth(1);
        $curM = (int) date('n');
        $curY = (int) date('Y');
        $of   = htmlspecialchars($this->orderField, ENT_QUOTES);

        $h  = '<div class="calendar-container">';
        $h .= '<div class="calendar-header">';
        $h .= '<div class="calendar-title">' . self::MONTHS[$this->month] . ' ' . $this->year . '</div>';
        $h .= '<div class="calendar-nav">';
        $h .= '<a href="?mode=calendar&month=' . $prev['m'] . '&year=' . $prev['y'] . '&order=' . $of . '" class="calendar-nav-btn"><i class="fas fa-chevron-left"></i></a>';
        $h .= '<a href="?mode=calendar&month=' . $curM . '&year=' . $curY . '&order=' . $of . '" class="calendar-nav-btn"><i class="fas fa-circle"></i></a>';
        $h .= '<a href="?mode=calendar&month=' . $next['m'] . '&year=' . $next['y'] . '&order=' . $of . '" class="calendar-nav-btn"><i class="fas fa-chevron-right"></i></a>';
        $h .= '</div></div>';

        $h .= '<div class="calendar-grid">';

        foreach (self::DAYS as $d) {
            $h .= '<div class="calendar-weekday">' . $d . '</div>';
        }

        // Cases vides avant le premier jour
        for ($i = 0; $i < $dayOfWeek; $i++) {
            $h .= '<div class="calendar-day other-month"></div>';
        }

        // Jours du mois
        for ($day = 1; $day <= $numDays; $day++) {
            $date    = date('Y-m-d', mktime(0, 0, 0, $this->month, $day, $this->year));
            $isToday = ($day === (int) date('j') && $this->month === $curM && $this->year === $curY);

            $h .= '<div class="calendar-day' . ($isToday ? ' today' : '') . '">';
            $h .= '<div class="calendar-date">' . $day . '</div>';

            if (isset($this->devoirsByDate[$date])) {
                foreach ($this->devoirsByDate[$date] as $d) {
                    $s     = $statusFn($d['date_rendu']);
                    $title = htmlspecialchars($d['titre'] . ' — ' . $d['nom_matiere'] . ' (' . $d['nom_professeur'] . ')', ENT_QUOTES);
                    $h .= '<div class="calendar-event ' . $s['class'] . '" title="' . $title . '" '
                         . 'data-id="' . $d['id'] . '" '
                         . 'data-titre="' . htmlspecialchars($d['titre'], ENT_QUOTES) . '" '
                         . 'data-matiere="' . htmlspecialchars($d['nom_matiere'], ENT_QUOTES) . '" '
                         . 'data-prof="' . htmlspecialchars($d['nom_professeur'], ENT_QUOTES) . '" '
                         . 'data-desc="' . htmlspecialchars(mb_strimwidth($d['description'] ?? '', 0, 120, '…'), ENT_QUOTES) . '">';
                    $h .= htmlspecialchars($d['titre']);
                    $h .= '</div>';
                }
            }

            $h .= '</div>';
        }

        // Cases vides après le dernier jour
        $remaining = 7 - (($dayOfWeek + $numDays) % 7);
        if ($remaining < 7) {
            for ($i = 0; $i < $remaining; $i++) {
                $h .= '<div class="calendar-day other-month"></div>';
            }
        }

        $h .= '</div></div>';

        return $h;
    }

    private function adjacentMonth(int $delta): array
    {
        $m = $this->month + $delta;
        $y = $this->year;
        if ($m <= 0)  { $m = 12; $y--; }
        if ($m > 12)  { $m = 1;  $y++; }
        return ['m' => $m, 'y' => $y];
    }
}
