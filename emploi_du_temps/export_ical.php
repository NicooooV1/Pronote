<?php
/**
 * Export iCal (.ics) — Emploi du temps.
 * Generates a .ics file for calendar sync.
 * GET params: classe_id, prof_id, semaine (Y-W format)
 */
require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/EdtService.php';

requireAuth();

$pdo = getPDO();
$edtService = new EdtService($pdo);
$user = getCurrentUser();
$role = getUserRole();

// Determine filters based on role
$classeId = (int) ($_GET['classe_id'] ?? 0);
$profId   = (int) ($_GET['prof_id'] ?? 0);

if ($role === 'professeur' && !$profId) {
    $profId = $user['id'];
} elseif ($role === 'eleve' && !$classeId) {
    // Get student's class
    $stmt = $pdo->prepare("SELECT classe_id FROM eleves WHERE id = ?");
    $stmt->execute([$user['id']]);
    $classeId = (int) $stmt->fetchColumn();
}

// Fetch cours for the current week (or specified week)
$semaineParam = $_GET['semaine'] ?? date('Y-W');
$parts = explode('-', str_replace('W', '', $semaineParam));
$year = (int) ($parts[0] ?? date('Y'));
$week = (int) ($parts[1] ?? date('W'));

// Get Monday of the week
$dto = new DateTime();
$dto->setISODate($year, $week, 1);
$mondayDate = $dto->format('Y-m-d');
$dto->modify('+6 days');
$sundayDate = $dto->format('Y-m-d');

// Build query
$sql = "
    SELECT edt.*, c.nom AS classe_nom, m.nom AS matiere_nom,
           CONCAT(p.prenom, ' ', p.nom) AS prof_nom, s.nom AS salle_nom,
           ch.heure_debut, ch.heure_fin
    FROM emploi_du_temps edt
    LEFT JOIN classes c ON edt.classe_id = c.id
    LEFT JOIN matieres m ON edt.matiere_id = m.id
    LEFT JOIN professeurs p ON edt.professeur_id = p.id
    LEFT JOIN salles s ON edt.salle_id = s.id
    LEFT JOIN creneaux_horaires ch ON edt.creneau_id = ch.id
    WHERE edt.jour BETWEEN ? AND ?
";
$params = [$mondayDate, $sundayDate];

if ($classeId) {
    $sql .= " AND edt.classe_id = ?";
    $params[] = $classeId;
}
if ($profId) {
    $sql .= " AND edt.professeur_id = ?";
    $params[] = $profId;
}
$sql .= " ORDER BY edt.jour, ch.heure_debut";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate iCal
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="emploi_du_temps_S' . $week . '.ics"');

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//FRONOTE//EDT//FR\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME:Emploi du temps FRONOTE\r\n";

foreach ($cours as $c) {
    $jour = $c['jour'];
    $heureDebut = $c['heure_debut'] ?? '08:00:00';
    $heureFin   = $c['heure_fin'] ?? '09:00:00';

    $dtStart = date('Ymd', strtotime($jour)) . 'T' . str_replace(':', '', substr($heureDebut, 0, 5)) . '00';
    $dtEnd   = date('Ymd', strtotime($jour)) . 'T' . str_replace(':', '', substr($heureFin, 0, 5)) . '00';

    $summary = ($c['matiere_nom'] ?? 'Cours');
    $location = ($c['salle_nom'] ?? '');
    $description = '';
    if (!empty($c['prof_nom'])) $description .= 'Prof: ' . $c['prof_nom'];
    if (!empty($c['classe_nom'])) $description .= ($description ? ' - ' : '') . 'Classe: ' . $c['classe_nom'];

    echo "BEGIN:VEVENT\r\n";
    echo "UID:" . md5($c['id'] . $jour . $heureDebut) . "@fronote\r\n";
    echo "DTSTART:" . $dtStart . "\r\n";
    echo "DTEND:" . $dtEnd . "\r\n";
    echo "SUMMARY:" . icalEscape($summary) . "\r\n";
    if ($location) echo "LOCATION:" . icalEscape($location) . "\r\n";
    if ($description) echo "DESCRIPTION:" . icalEscape($description) . "\r\n";
    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";

function icalEscape(string $str): string {
    return str_replace(["\n", "\r", ";", ","], ["\\n", "", "\\;", "\\,"], $str);
}
