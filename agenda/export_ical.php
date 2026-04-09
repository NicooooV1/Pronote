<?php
/**
 * Export iCal (.ics) — Agenda événements.
 * Generates a personal .ics file with all user's events.
 */
require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/EventRepository.php';

requireAuth();

$pdo = getPDO();
$repo = new EventRepository($pdo);
$user = getCurrentUser();
$userId = $user['id'];
$role = getUserRole();

// Get events for the next 3 months
$dateDebut = date('Y-m-d');
$dateFin   = date('Y-m-d', strtotime('+3 months'));

// Fetch events visible to the user
$sql = "
    SELECT e.* FROM evenements e
    WHERE e.date_debut >= ? AND e.date_debut <= ?
    AND (
        e.visibilite = 'global'
        OR (e.visibilite = 'classe' AND e.classe_id IN (
            SELECT classe_id FROM eleves WHERE id = ? AND ? = 'eleve'
            UNION SELECT classe_id FROM professeurs WHERE id = ? AND ? = 'professeur'
        ))
        OR e.createur_id = ?
    )
    ORDER BY e.date_debut
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dateDebut, $dateFin, $userId, $role, $userId, $role, $userId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="agenda_fronote.ics"');

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//FRONOTE//AGENDA//FR\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME:Agenda FRONOTE\r\n";

foreach ($events as $ev) {
    $dtStart = date('Ymd', strtotime($ev['date_debut']));
    $dtEnd = !empty($ev['date_fin'])
        ? date('Ymd', strtotime($ev['date_fin']))
        : $dtStart;

    // If times are specified
    if (!empty($ev['heure_debut'])) {
        $dtStart .= 'T' . str_replace(':', '', substr($ev['heure_debut'], 0, 5)) . '00';
    }
    if (!empty($ev['heure_fin'])) {
        $dtEnd .= 'T' . str_replace(':', '', substr($ev['heure_fin'], 0, 5)) . '00';
    }

    $summary = $ev['titre'] ?? 'Événement';
    $description = $ev['description'] ?? '';
    $location = $ev['lieu'] ?? '';

    echo "BEGIN:VEVENT\r\n";
    echo "UID:" . md5('fronote-event-' . $ev['id']) . "@fronote\r\n";
    echo "DTSTART:" . $dtStart . "\r\n";
    echo "DTEND:" . $dtEnd . "\r\n";
    echo "SUMMARY:" . icalEscape($summary) . "\r\n";
    if ($description) echo "DESCRIPTION:" . icalEscape($description) . "\r\n";
    if ($location) echo "LOCATION:" . icalEscape($location) . "\r\n";

    // Handle recurrence (rrule)
    if (!empty($ev['rrule'])) {
        echo "RRULE:" . $ev['rrule'] . "\r\n";
    }

    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";

function icalEscape(string $str): string {
    return str_replace(["\n", "\r", ";", ","], ["\\n", "", "\\;", "\\,"], $str);
}
