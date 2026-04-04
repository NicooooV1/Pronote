<?php
/**
 * EventRepository — Service centralisé pour la gestion des événements agenda.
 *
 * Remplace : calendar_functions.php, event_helpers.php, models/Evenement.php
 * Centralise : filtrage par rôle (×1 au lieu de ×6), types, visibilité, CRUD, matières.
 */
class EventRepository
{
    private PDO $pdo;

    /* ── Constante unique : types d'événements ── */
    const TYPES = [
        'cours'   => ['nom' => 'Cours',           'icone' => 'book',      'couleur' => '#007aff'],
        'devoirs' => ['nom' => 'Devoirs',          'icone' => 'pencil-alt','couleur' => '#34c759'],
        'reunion' => ['nom' => 'Réunion',          'icone' => 'users',     'couleur' => '#ff9500'],
        'examen'  => ['nom' => 'Examen',           'icone' => 'file-alt',  'couleur' => '#ff3b30'],
        'sortie'  => ['nom' => 'Sortie scolaire',  'icone' => 'map-marker-alt', 'couleur' => '#5856d6'],
        'autre'   => ['nom' => 'Autre',            'icone' => 'calendar',  'couleur' => '#8e8e93'],
    ];

    const VALID_STATUTS = ['actif', 'annulé', 'reporté'];

    const VALID_VISIBILITES = [
        'public', 'professeurs', 'eleves', 'administration',
        'classes_specifiques', 'personnel', 'parents',
    ];

    /* ── Matières de fallback ── */
    const MATIERES_FALLBACK = [
        'Français', 'Mathématiques', 'Histoire-Géographie', 'Anglais',
        'Espagnol', 'Allemand', 'Physique-Chimie', 'SVT', 'Technologie',
        'Arts Plastiques', 'Musique', 'EPS', 'EMC', 'SNT', 'NSI',
        'Philosophie', 'SES', 'LLCE', 'Latin', 'Grec', 'Autre',
    ];

    /* ── Noms français ── */
    const MONTH_NAMES = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];
    const DAY_NAMES      = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
    const DAY_NAMES_FULL = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ================================================================
       QUERIES — filtrage unique par rôle
       ================================================================ */

    /**
     * Méthode unique de filtrage par rôle et critères.
     * Remplace les 6 copies dupliquées du filtrage SQL.
     *
     * @param array $options Clés possibles :
     *   - date_start, date_end  (plage)
     *   - date                  (jour unique)
     *   - month, year           (mois)
     *   - types                 (array de type_evenement)
     *   - classes               (array de classes)
     *   - user_role, user_fullname, user_classe  (filtrage rôle)
     *   - limit                 (int)
     *   - upcoming              (bool — événements futurs uniquement)
     *   - recent_days           (int — inclure les N derniers jours)
     */
    public function findFiltered(array $options = []): array
    {
        $params = [];
        $where  = [];

        // ── Période ──
        if (!empty($options['date_start']) && !empty($options['date_end'])) {
            $where[]  = "DATE(date_debut) BETWEEN ? AND ?";
            $params[] = $options['date_start'];
            $params[] = $options['date_end'];
        } elseif (!empty($options['date'])) {
            $where[]  = "(DATE(date_debut) = ? OR DATE(date_fin) = ?)";
            $params[] = $options['date'];
            $params[] = $options['date'];
        } elseif (!empty($options['month']) && !empty($options['year'])) {
            $where[]  = "MONTH(date_debut) = ? AND YEAR(date_debut) = ?";
            $params[] = (int) $options['month'];
            $params[] = (int) $options['year'];
        }

        // ── Filtre par types ──
        if (!empty($options['types']) && is_array($options['types'])) {
            $ph = implode(',', array_fill(0, count($options['types']), '?'));
            $where[] = "type_evenement IN ($ph)";
            $params  = array_merge($params, $options['types']);
        }

        // ── Filtre par classes ──
        if (!empty($options['classes']) && is_array($options['classes'])) {
            $clauses = [];
            foreach ($options['classes'] as $class) {
                $clauses[] = "classes LIKE ?";
                $params[]  = "%$class%";
            }
            $where[] = "(" . implode(" OR ", $clauses) . ")";
        }

        // ── Upcoming / Recent ──
        if (!empty($options['upcoming'])) {
            $where[]  = "date_debut >= NOW()";
        }
        if (!empty($options['recent_days'])) {
            $days     = (int) $options['recent_days'];
            $where[]  = "(date_debut >= DATE_SUB(NOW(), INTERVAL ? DAY) OR date_fin >= CURDATE())";
            $params[] = $days;
        }

        // ── Filtre par rôle utilisateur — LA logique unique ──
        $role     = $options['user_role']     ?? '';
        $fullname = $options['user_fullname'] ?? '';
        $classe   = $options['user_classe']   ?? '';

        if (in_array($role, ['administrateur', 'vie_scolaire'], true)) {
            // Pas de restriction
        } elseif ($role === 'professeur') {
            $where[]  = "(visibilite = 'public'
                          OR visibilite = 'professeurs'
                          OR createur = ?
                          OR personnes_concernees LIKE ?)";
            $params[] = $fullname;
            $params[] = "%$fullname%";
        } elseif ($role === 'eleve') {
            $where[]  = "(visibilite = 'public'
                          OR visibilite = 'eleves'
                          OR classes LIKE ?
                          OR createur = ?
                          OR personnes_concernees LIKE ?)";
            $params[] = "%$classe%";
            $params[] = $fullname;
            $params[] = "%$fullname%";
        } elseif ($role === 'parent') {
            $where[] = "(visibilite = 'public' OR visibilite = 'parents')";
        } else {
            // personnel / administration
            $where[]  = "(visibilite = 'public'
                          OR visibilite = 'personnel'
                          OR visibilite = 'administration'
                          OR createur = ?
                          OR personnes_concernees LIKE ?)";
            $params[] = $fullname;
            $params[] = "%$fullname%";
        }

        $sql = "SELECT * FROM evenements";
        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY date_debut";

        if (!empty($options['limit'])) {
            $sql .= " LIMIT " . (int) $options['limit'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Expand RRULE recurring events ──
        $rangeStart = $options['date_start'] ?? $options['date'] ?? null;
        $rangeEnd   = $options['date_end']   ?? $options['date'] ?? null;
        if ($rangeStart && $rangeEnd) {
            $events = $this->expandRecurring($events, $rangeStart, $rangeEnd);
        }

        return $events;
    }

    /**
     * Expand events that have an rrule field into virtual occurrences within the given date range.
     * Supports FREQ=DAILY|WEEKLY|MONTHLY|YEARLY, INTERVAL, UNTIL, COUNT, BYDAY, EXDATE.
     * Also supports single-occurrence exceptions stored in evenement_exceptions table.
     */
    private function expandRecurring(array $events, string $rangeStart, string $rangeEnd): array
    {
        $rsTs = strtotime($rangeStart);
        $reTs = strtotime($rangeEnd . ' 23:59:59');
        $result = [];

        // Pre-load all recurrence exceptions for events in this batch
        $parentIds = [];
        foreach ($events as $ev) {
            if (!empty($ev['rrule'])) {
                $parentIds[] = (int)$ev['id'];
            }
        }
        $exceptions = $this->loadRecurrenceExceptions($parentIds);

        foreach ($events as $ev) {
            if (empty($ev['rrule'])) {
                $result[] = $ev;
                continue;
            }

            // Parse RRULE string
            $rule = $this->parseRRule($ev['rrule']);
            $freq     = $rule['FREQ'] ?? 'WEEKLY';
            $interval = (int)($rule['INTERVAL'] ?? 1);
            $until    = !empty($rule['UNTIL']) ? strtotime($rule['UNTIL']) : null;
            $count    = !empty($rule['COUNT']) ? (int)$rule['COUNT'] : 365;
            $byDay    = !empty($rule['BYDAY']) ? explode(',', $rule['BYDAY']) : [];

            // Parse EXDATE — comma-separated list of excluded dates (YYYYMMDD or YYYYMMDDTHHMMSSZ)
            $exdates = $this->parseExdates($rule['EXDATE'] ?? ($ev['exdate'] ?? ''));

            // Get single-occurrence exceptions (modified or deleted) for this parent event
            $eventExceptions = $exceptions[(int)$ev['id']] ?? [];

            $dtStart  = strtotime($ev['date_debut']);
            $duration = !empty($ev['date_fin']) ? (strtotime($ev['date_fin']) - $dtStart) : 3600;

            $dayMap = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];

            $occurrences = 0;
            $current = $dtStart;

            while ($occurrences < $count) {
                if ($until && $current > $until) break;
                if ($current > $reTs + 86400 * 365) break; // safety limit

                $currentDate = date('Y-m-d', $current);
                $inRange = ($current >= $rsTs && $current <= $reTs);
                $dayOk = true;
                if (!empty($byDay)) {
                    $dayOfWeek = (int)date('N', $current);
                    $dayOk = false;
                    foreach ($byDay as $d) {
                        if (isset($dayMap[trim($d)]) && $dayMap[trim($d)] === $dayOfWeek) {
                            $dayOk = true;
                            break;
                        }
                    }
                }

                // Check if this date is excluded via EXDATE
                $isExcluded = in_array($currentDate, $exdates, true);

                // Check for single-occurrence exception
                $exception = $eventExceptions[$currentDate] ?? null;

                if ($inRange && $dayOk && !$isExcluded) {
                    if ($exception !== null) {
                        if ($exception['type'] === 'deleted') {
                            // This occurrence was deleted — skip it
                        } else {
                            // This occurrence was modified — use exception data
                            $occurrence = $ev;
                            $occurrence['date_debut'] = $exception['date_debut'] ?? date('Y-m-d H:i:s', $current);
                            $occurrence['date_fin']   = $exception['date_fin'] ?? date('Y-m-d H:i:s', $current + $duration);
                            if (!empty($exception['titre']))       $occurrence['titre']       = $exception['titre'];
                            if (!empty($exception['description'])) $occurrence['description'] = $exception['description'];
                            if (!empty($exception['lieu']))        $occurrence['lieu']        = $exception['lieu'];
                            if (!empty($exception['statut']))      $occurrence['statut']      = $exception['statut'];
                            $occurrence['is_recurrence']       = true;
                            $occurrence['recurrence_parent_id'] = $ev['id'];
                            $occurrence['is_exception']        = true;
                            $occurrence['exception_id']        = $exception['id'];
                            $occurrence['original_date']       = $currentDate;
                            $result[] = $occurrence;
                        }
                    } else {
                        $occurrence = $ev;
                        $occurrence['date_debut'] = date('Y-m-d H:i:s', $current);
                        $occurrence['date_fin']   = date('Y-m-d H:i:s', $current + $duration);
                        $occurrence['is_recurrence']       = true;
                        $occurrence['recurrence_parent_id'] = $ev['id'];
                        $occurrence['original_date']       = $currentDate;
                        $result[] = $occurrence;
                    }
                }

                $occurrences++;

                // Advance to next occurrence
                switch ($freq) {
                    case 'DAILY':   $current = strtotime("+{$interval} days",   $current); break;
                    case 'WEEKLY':  $current = strtotime("+{$interval} weeks",  $current); break;
                    case 'MONTHLY': $current = strtotime("+{$interval} months", $current); break;
                    case 'YEARLY':  $current = strtotime("+{$interval} years",  $current); break;
                    default:        $current = strtotime("+{$interval} weeks",  $current); break;
                }
            }
        }

        // Sort by date
        usort($result, fn($a, $b) => strcmp($a['date_debut'], $b['date_debut']));
        return $result;
    }

    /**
     * Parse EXDATE string into an array of Y-m-d date strings.
     * Supports formats: "20250315,20250322" or "20250315T080000Z,20250322T080000Z"
     */
    private function parseExdates(string $exdate): array
    {
        if (empty($exdate)) return [];

        $dates = [];
        foreach (explode(',', $exdate) as $raw) {
            $raw = trim($raw);
            if (empty($raw)) continue;
            // Strip time component if present (YYYYMMDDTHHMMSSZ → YYYYMMDD)
            $dateStr = substr($raw, 0, 8);
            $parsed = DateTime::createFromFormat('Ymd', $dateStr);
            if ($parsed) {
                $dates[] = $parsed->format('Y-m-d');
            }
        }
        return $dates;
    }

    /**
     * Load recurrence exceptions from the evenement_exceptions table.
     * Returns array keyed by parent_event_id → original_date → exception data.
     */
    private function loadRecurrenceExceptions(array $parentIds): array
    {
        if (empty($parentIds)) return [];

        try {
            $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
            $stmt = $this->pdo->prepare("
                SELECT * FROM evenement_exceptions
                WHERE parent_event_id IN ({$placeholders})
                ORDER BY original_date
            ");
            $stmt->execute($parentIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $grouped = [];
            foreach ($rows as $row) {
                $pid  = (int)$row['parent_event_id'];
                $date = $row['original_date'];
                $grouped[$pid][$date] = $row;
            }
            return $grouped;
        } catch (\PDOException $e) {
            // Table may not exist yet — degrade gracefully
            return [];
        }
    }

    /**
     * Create a single-occurrence exception for a recurring event.
     *
     * @param int    $parentEventId  The recurring parent event ID
     * @param string $originalDate   The date (Y-m-d) of the occurrence to modify/delete
     * @param string $type           'modified' or 'deleted'
     * @param array  $overrides      Fields to override: titre, description, date_debut, date_fin, lieu, statut
     */
    public function createRecurrenceException(int $parentEventId, string $originalDate, string $type = 'modified', array $overrides = []): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO evenement_exceptions (parent_event_id, original_date, type, titre, description, date_debut, date_fin, lieu, statut, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE type = VALUES(type), titre = VALUES(titre), description = VALUES(description),
                date_debut = VALUES(date_debut), date_fin = VALUES(date_fin), lieu = VALUES(lieu), statut = VALUES(statut)
        ");
        $stmt->execute([
            $parentEventId,
            $originalDate,
            $type,
            $overrides['titre'] ?? null,
            $overrides['description'] ?? null,
            $overrides['date_debut'] ?? null,
            $overrides['date_fin'] ?? null,
            $overrides['lieu'] ?? null,
            $overrides['statut'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Delete a single occurrence of a recurring event (creates a 'deleted' exception).
     */
    public function deleteRecurrenceOccurrence(int $parentEventId, string $originalDate): bool
    {
        return $this->createRecurrenceException($parentEventId, $originalDate, 'deleted') > 0;
    }

    /**
     * Add an EXDATE entry to an event's rrule or exdate column.
     */
    public function addExdate(int $eventId, string $date): bool
    {
        $event = $this->findById($eventId);
        if (!$event) return false;

        $existing = !empty($event['exdate']) ? $event['exdate'] : '';
        $newDate  = (new DateTime($date))->format('Ymd');

        // Avoid duplicates
        $currentDates = array_filter(explode(',', $existing));
        if (in_array($newDate, $currentDates, true)) return true;

        $currentDates[] = $newDate;
        $updated = implode(',', $currentDates);

        $stmt = $this->pdo->prepare("UPDATE evenements SET exdate = ? WHERE id = ?");
        return $stmt->execute([$updated, $eventId]);
    }

    /**
     * Parse an RRULE string like "FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;UNTIL=20250630T235959Z"
     */
    private function parseRRule(string $rrule): array
    {
        $parts = [];
        foreach (explode(';', $rrule) as $segment) {
            $kv = explode('=', $segment, 2);
            if (count($kv) === 2) {
                $parts[strtoupper(trim($kv[0]))] = trim($kv[1]);
            }
        }
        return $parts;
    }

    /* ================================================================
       RÉUNIONS → Événements (intégration agenda ↔ réunions)
       ================================================================ */

    /**
     * Récupère les réunions et les convertit en événements agenda.
     * Permet d'afficher réunions, conseils de classe, RDV parents-profs
     * directement dans le calendrier.
     */
    public function findReunionsAsEvents(array $options = []): array
    {
        $params = [];
        $where  = ["r.statut != 'annulee'"];

        if (!empty($options['date_start']) && !empty($options['date_end'])) {
            $where[]  = "DATE(r.date_debut) BETWEEN ? AND ?";
            $params[] = $options['date_start'];
            $params[] = $options['date_end'];
        } elseif (!empty($options['date'])) {
            $where[]  = "DATE(r.date_debut) = ?";
            $params[] = $options['date'];
        } elseif (!empty($options['month']) && !empty($options['year'])) {
            $where[]  = "MONTH(r.date_debut) = ? AND YEAR(r.date_debut) = ?";
            $params[] = (int)$options['month'];
            $params[] = (int)$options['year'];
        }

        if (!empty($options['upcoming'])) {
            $where[] = "r.date_debut >= NOW()";
        }

        // Filtrage rôle : les parents ne voient que les réunions parents_profs
        $role = $options['user_role'] ?? '';
        if ($role === 'parent') {
            $where[] = "r.type IN ('parents_profs', 'individuel')";
        } elseif ($role === 'eleve') {
            $where[] = "r.type IN ('conseil_classe', 'parents_profs')";
        }

        $sql = "SELECT r.id, r.titre, r.description, r.date_debut, r.date_fin,
                       r.lieu, r.type, r.statut, r.classe_id, c.nom AS classe_nom
                FROM reunions r
                LEFT JOIN classes c ON r.classe_id = c.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY r.date_debut";

        if (!empty($options['limit'])) {
            $sql .= " LIMIT " . (int)$options['limit'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $reunions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convertir en format événement agenda
        $typeLabels = [
            'parents_profs'  => 'Réunion parents-profs',
            'conseil_classe' => 'Conseil de classe',
            'reunion_equipe' => 'Réunion d\'équipe',
            'individuel'     => 'Entretien individuel',
            'autre'          => 'Réunion',
        ];

        return array_map(function ($r) use ($typeLabels) {
            return [
                'id'                => 'reunion_' . $r['id'],
                'reunion_id'        => $r['id'],
                'titre'             => $r['titre'],
                'description'       => $r['description'] ?? '',
                'date_debut'        => $r['date_debut'],
                'date_fin'          => $r['date_fin'],
                'type_evenement'    => 'reunion',
                'type_personnalise' => $typeLabels[$r['type']] ?? 'Réunion',
                'statut'            => $r['statut'] === 'terminee' ? 'actif' : ($r['statut'] === 'annulee' ? 'annulé' : 'actif'),
                'lieu'              => $r['lieu'] ?? '',
                'classes'           => $r['classe_nom'] ?? '',
                'visibilite'        => 'public',
                'createur'          => '',
                'personnes_concernees' => '',
                'matieres'          => '',
                'rrule'             => null,
                'is_reunion'        => true,
            ];
        }, $reunions);
    }

    /**
     * Requête combinée : événements + réunions, triés par date.
     */
    public function findAllWithReunions(array $options = []): array
    {
        $events   = $this->findFiltered($options);
        $reunions = $this->findReunionsAsEvents($options);
        $combined = array_merge($events, $reunions);
        usort($combined, fn($a, $b) => strcmp($a['date_debut'], $b['date_debut']));
        return $combined;
    }

    /* ── Raccourcis ── */

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM evenements WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findUpcoming(array $roleOpts, int $limit = 8): array
    {
        return $this->findFiltered(array_merge($roleOpts, [
            'upcoming' => true,
            'limit'    => $limit,
        ]));
    }

    public function findRecentPast(array $roleOpts, int $days = 7, int $limit = 5): array
    {
        $events = $this->findFiltered(array_merge($roleOpts, [
            'date_start' => date('Y-m-d', strtotime("-{$days} days")),
            'date_end'   => date('Y-m-d', strtotime('-1 day')),
        ]));
        return array_slice($events, 0, $limit);
    }

    /* ================================================================
       CRUD
       ================================================================ */

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO evenements
             (titre, description, date_debut, date_fin, type_evenement, type_personnalise,
              statut, createur, visibilite, personnes_concernees, lieu, classes, matieres, rrule, date_creation)
             VALUES (?, ?, ?, ?, ?, ?, 'actif', ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $data['titre'],
            $data['description']            ?? '',
            $data['date_debut'],
            $data['date_fin'],
            $data['type_evenement'],
            $data['type_personnalise']       ?? '',
            $data['createur'],
            $data['visibilite'],
            $data['personnes_concernees']    ?? '',
            $data['lieu']                    ?? '',
            $data['classes']                 ?? '',
            $data['matieres']               ?? '',
            $data['rrule']                  ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE evenements SET
             titre = ?, description = ?, date_debut = ?, date_fin = ?,
             type_evenement = ?, statut = ?, lieu = ?, visibilite = ?,
             classes = ?, matieres = ?, personnes_concernees = ?, rrule = ?,
             date_modification = NOW()
             WHERE id = ?"
        );
        return $stmt->execute([
            $data['titre'],
            $data['description']            ?? '',
            $data['date_debut'],
            $data['date_fin'],
            $data['type_evenement'],
            $data['statut']                 ?? 'actif',
            $data['lieu']                   ?? '',
            $data['visibilite'],
            $data['classes']                ?? '',
            $data['matieres']               ?? '',
            $data['personnes_concernees']   ?? '',
            $data['rrule']                  ?? null,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM evenements WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /* ================================================================
       Détection de conflits
       ================================================================ */

    /**
     * Détecte les conflits de créneaux pour un lieu ou des classes donnés.
     * @return array Liste des événements en conflit
     */
    /**
     * Detect scheduling conflicts for a proposed event.
     * Checks for overlapping: lieu (room), classes, and professor (createur).
     * Returns conflicts with a 'conflict_type' field indicating the reason.
     *
     * @param string      $dateDebut
     * @param string      $dateFin
     * @param string|null $lieu
     * @param string|null $classes    CSV string of class names
     * @param int|null    $excludeId Event ID to exclude (for edits)
     * @param string|null $createur   Professor/creator identifier for teacher conflicts
     * @return array Conflicting events with 'conflict_types' array per event
     */
    public function detectConflicts(string $dateDebut, string $dateFin, ?string $lieu = null, ?string $classes = null, ?int $excludeId = null, ?string $createur = null): array
    {
        $where  = ["statut = 'actif'"];
        $params = [];

        // Chevauchement temporel
        $where[]  = "(date_debut < ? AND date_fin > ?)";
        $params[] = $dateFin;
        $params[] = $dateDebut;

        // Exclure l'événement lui-même en cas de modification
        if ($excludeId) {
            $where[]  = "id != ?";
            $params[] = $excludeId;
        }

        // Conflit de lieu (même salle)
        $lieuConflict = !empty($lieu);
        // Conflit de classe
        $classeConflict = !empty($classes);
        // Conflit de professeur (même créateur)
        $profConflict = !empty($createur);

        if (!$lieuConflict && !$classeConflict && !$profConflict) {
            return [];
        }

        $orConditions = [];
        if ($lieuConflict) {
            $orConditions[] = "(lieu = ? AND lieu != '')";
            $params[] = $lieu;
        }
        if ($classeConflict) {
            // Vérifier le chevauchement de classes (stockées en CSV)
            $classeList = array_map('trim', explode(',', $classes));
            $likeConditions = [];
            foreach ($classeList as $cl) {
                $likeConditions[] = "classes LIKE ?";
                $params[] = "%$cl%";
            }
            $orConditions[] = "(" . implode(' OR ', $likeConditions) . ")";
        }
        if ($profConflict) {
            $orConditions[] = "(createur = ?)";
            $params[] = $createur;
        }

        $where[] = "(" . implode(' OR ', $orConditions) . ")";

        $sql = "SELECT * FROM evenements WHERE " . implode(' AND ', $where) . " ORDER BY date_debut";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Annotate each conflict with the type(s) of conflict detected
        foreach ($conflicts as &$c) {
            $types = [];
            if ($lieuConflict && !empty($c['lieu']) && $c['lieu'] === $lieu) {
                $types[] = 'lieu';
            }
            if ($classeConflict && !empty($c['classes'])) {
                $cClasses = array_map('trim', explode(',', $c['classes']));
                $classeList = array_map('trim', explode(',', $classes));
                if (array_intersect($classeList, $cClasses)) {
                    $types[] = 'classe';
                }
            }
            if ($profConflict && !empty($c['createur']) && $c['createur'] === $createur) {
                $types[] = 'professeur';
            }
            $c['conflict_types'] = $types;
        }

        return $conflicts;
    }

    /**
     * Builds an RRULE string from form data.
     */
    public static function buildRRule(array $data): ?string
    {
        $freq = $data['recurrence_freq'] ?? '';
        if (empty($freq) || $freq === 'none') return null;

        $parts = ['FREQ=' . strtoupper($freq)];

        $interval = (int) ($data['recurrence_interval'] ?? 1);
        if ($interval > 1) $parts[] = 'INTERVAL=' . $interval;

        if (!empty($data['recurrence_byday'])) {
            $days = is_array($data['recurrence_byday']) ? implode(',', $data['recurrence_byday']) : $data['recurrence_byday'];
            $parts[] = 'BYDAY=' . strtoupper($days);
        }

        if (!empty($data['recurrence_until'])) {
            $parts[] = 'UNTIL=' . date('Ymd\THis\Z', strtotime($data['recurrence_until']));
        } elseif (!empty($data['recurrence_count'])) {
            $parts[] = 'COUNT=' . (int) $data['recurrence_count'];
        }

        return implode(';', $parts);
    }

    /* ================================================================
       Validation
       ================================================================ */

    /**
     * Valide les données d'un événement.
     * @return string[] Tableau d'erreurs (vide = valide)
     */
    public function validate(array $data): array
    {
        $errors = [];

        if (empty(trim($data['titre'] ?? ''))) {
            $errors[] = "Le titre est obligatoire.";
        } elseif (mb_strlen($data['titre']) > 100) {
            $errors[] = "Le titre ne doit pas dépasser 100 caractères.";
        }

        if (!empty($data['description']) && mb_strlen($data['description']) > 2000) {
            $errors[] = "La description ne doit pas dépasser 2000 caractères.";
        }

        // Type
        if (!array_key_exists($data['type_evenement'] ?? '', self::TYPES)) {
            $errors[] = "Type d'événement invalide.";
        }

        // Statut (pour modification)
        if (!empty($data['statut']) && !in_array($data['statut'], self::VALID_STATUTS, true)) {
            $errors[] = "Statut invalide.";
        }

        // Dates
        $dateDebut = DateTime::createFromFormat('Y-m-d H:i:s', $data['date_debut'] ?? '');
        $dateFin   = DateTime::createFromFormat('Y-m-d H:i:s', $data['date_fin']   ?? '');
        if (!$dateDebut || !$dateFin) {
            $errors[] = "Format de date/heure invalide.";
        } elseif ($dateFin <= $dateDebut) {
            $errors[] = "La date de fin doit être postérieure à la date de début.";
        }

        // Visibilité
        $vis = $data['visibilite'] ?? '';
        if (!in_array($vis, self::VALID_VISIBILITES, true) && strpos($vis, 'classes:') !== 0) {
            $errors[] = "Visibilité invalide.";
        }

        return $errors;
    }

    /* ================================================================
       Helpers statiques
       ================================================================ */

    public static function getTypeInfo(string $type): array
    {
        return self::TYPES[$type] ?? self::TYPES['autre'];
    }

    public static function getTypeLabel(string $type): string
    {
        return (self::TYPES[$type] ?? self::TYPES['autre'])['nom'];
    }

    public static function getTypesForRole(string $role): array
    {
        if (in_array($role, ['administrateur', 'vie_scolaire'], true)) {
            return self::TYPES;
        }
        if ($role === 'professeur') {
            return array_intersect_key(self::TYPES,
                array_flip(['cours', 'devoirs', 'examen', 'autre']));
        }
        return ['autre' => self::TYPES['autre']];
    }

    public static function getTypesSimple(): array
    {
        $out = [];
        foreach (self::TYPES as $code => $info) {
            $out[$code] = $info['nom'];
        }
        return $out;
    }

    public static function getVisibilityForRole(string $role): array
    {
        $all = [
            'public'              => ['nom' => 'Public (visible par tous)',         'icone' => 'globe'],
            'professeurs'         => ['nom' => 'Professeurs uniquement',            'icone' => 'user-tie'],
            'eleves'              => ['nom' => 'Élèves uniquement',                 'icone' => 'user-graduate'],
            'administration'      => ['nom' => 'Administration uniquement',         'icone' => 'user-shield'],
            'classes_specifiques' => ['nom' => 'Classes spécifiques',               'icone' => 'users'],
            'personnel'           => ['nom' => 'Personnel (moi uniquement)',        'icone' => 'user-lock'],
        ];
        if (in_array($role, ['administrateur', 'vie_scolaire'], true)) return $all;
        if ($role === 'professeur') {
            unset($all['administration']);
            return $all;
        }
        return ['personnel' => $all['personnel']];
    }

    public static function getVisibilityLabel(string $vis): array
    {
        $map = [
            'public'         => ['label' => 'Public (visible par tous)', 'icone' => 'globe'],
            'professeurs'    => ['label' => 'Professeurs uniquement',    'icone' => 'user-tie'],
            'eleves'         => ['label' => 'Élèves uniquement',        'icone' => 'user-graduate'],
            'administration' => ['label' => 'Administration uniquement', 'icone' => 'user-shield'],
            'personnel'      => ['label' => 'Personnel',                'icone' => 'user-lock'],
            'parents'        => ['label' => 'Parents',                  'icone' => 'user-friends'],
        ];
        if (strpos($vis, 'classes:') === 0) {
            return ['label' => 'Classes : ' . substr($vis, 8), 'icone' => 'users'];
        }
        return $map[$vis] ?? ['label' => $vis, 'icone' => 'lock'];
    }

    /**
     * Retourne les matières depuis la BDD ou le fallback.
     */
    public function getMatieres(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT DISTINCT nom FROM matieres ORDER BY nom');
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($result)) return $result;
        } catch (\PDOException $e) {
            // table doesn't exist
        }
        return self::MATIERES_FALLBACK;
    }

    /* ================================================================
       Mini-calendrier (HTML helper)
       ================================================================ */

    /**
     * Génère le HTML du mini-calendrier sidebar.
     */
    public function renderMiniCalendar(int $month, int $year, ?string $selectedDate = null, string $filterParams = ''): string
    {
        $numDays   = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $firstDay  = (int) date('N', mktime(0, 0, 0, $month, 1, $year));
        $todayD    = (int) date('j');
        $todayM    = (int) date('n');
        $todayY    = (int) date('Y');
        $selD = $selM = $selY = null;
        if ($selectedDate) {
            $selD = (int) date('j', strtotime($selectedDate));
            $selM = (int) date('n', strtotime($selectedDate));
            $selY = (int) date('Y', strtotime($selectedDate));
        }

        $prevM = $month - 1; $prevY = $year;
        if ($prevM < 1) { $prevM = 12; $prevY--; }
        $nextM = $month + 1; $nextY = $year;
        if ($nextM > 12) { $nextM = 1; $nextY++; }
        $fp = htmlspecialchars($filterParams);

        $h  = '<div class="mini-calendar-header">';
        $h .= '<span class="mini-calendar-title">' . self::MONTH_NAMES[$month] . ' ' . $year . '</span>';
        $h .= '<div class="mini-calendar-nav">';
        $h .= '<button class="mini-calendar-nav-btn" data-month="' . $prevM . '" data-year="' . $prevY . '" data-filters="' . $fp . '" aria-label="Mois précédent">&#9668;</button>';
        $h .= '<button class="mini-calendar-nav-btn" data-month="' . $nextM . '" data-year="' . $nextY . '" data-filters="' . $fp . '" aria-label="Mois suivant">&#9658;</button>';
        $h .= '</div></div>';

        $h .= '<div class="mini-calendar-grid">';
        foreach (self::DAY_NAMES as $d) {
            $h .= '<div class="mini-calendar-day-name">' . $d . '</div>';
        }

        // Jours mois précédent
        $prevMonthDays = cal_days_in_month(CAL_GREGORIAN, $prevM, $prevY);
        for ($i = 1; $i < $firstDay; $i++) {
            $h .= '<div class="mini-calendar-day other-month">' . ($prevMonthDays - $firstDay + $i + 1) . '</div>';
        }

        // Jours courants
        for ($d = 1; $d <= $numDays; $d++) {
            $cls = '';
            if ($d === $todayD && $month === $todayM && $year === $todayY) $cls .= ' today';
            if ($d === $selD && $month === $selM && $year === $selY) $cls .= ' selected';
            $dt = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $h .= '<div class="mini-calendar-day' . $cls . '" data-date="' . $dt . '" role="button" tabindex="0">' . $d . '</div>';
        }

        // Jours mois suivant
        $shown = $firstDay - 1 + $numDays;
        $rem   = 7 - ($shown % 7);
        if ($rem < 7) {
            for ($d = 1; $d <= $rem; $d++) {
                $h .= '<div class="mini-calendar-day other-month">' . $d . '</div>';
            }
        }

        $h .= '</div>';
        return $h;
    }

    /* ================================================================
       Helpers utilitaires
       ================================================================ */

    /**
     * Organise les événements par jour (clé = int jour du mois).
     */
    public static function groupByDay(array $events): array
    {
        $byDay = [];
        foreach ($events as $e) {
            $day = (int) date('j', strtotime($e['date_debut']));
            $byDay[$day][] = $e;
        }
        return $byDay;
    }

    /**
     * Organise les événements par date (clé = Y-m-d).
     */
    public static function groupByDate(array $events): array
    {
        $byDate = [];
        foreach ($events as $e) {
            $d = date('Y-m-d', strtotime($e['date_debut']));
            $byDate[$d][] = $e;
        }
        return $byDate;
    }

    /**
     * Comptage par type d'événement.
     */
    public static function countByType(array $events): array
    {
        $counts = [];
        foreach ($events as $e) {
            $t = $e['type_evenement'] ?? 'autre';
            $counts[$t] = ($counts[$t] ?? 0) + 1;
        }
        return $counts;
    }

    /**
     * Calcule les options de filtre pour le rôle user.
     */
    public function getUserFilterOptions(): array
    {
        return [
            'user_role'     => getUserRole(),
            'user_fullname' => getUserFullName(),
            'user_classe'   => getCurrentUser()['classe'] ?? '',
        ];
    }
}
