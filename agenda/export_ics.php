<?php
/**
 * Export ICS — Exporte les événements de l'agenda au format iCalendar (RFC 5545).
 *
 * Paramètres GET : ?mois=YYYY-MM (optionnel, défaut = mois courant)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../API/bootstrap.php';
$bridge = new \Pronote\Legacy\Bridge();
$bridge->requireAuth();
$pdo = $bridge->getPDO();

require_once __DIR__ . '/includes/EventRepository.php';
$repo = new EventRepository($pdo);

$user = $_SESSION['user'];
$role = $user['type'] ?? 'eleve';

// Dates : 1 an par défaut, ou un mois précis
$start = date('Y-m-d', strtotime('-1 month'));
$end   = date('Y-m-d', strtotime('+12 months'));
if (!empty($_GET['mois'])) {
    $start = $_GET['mois'] . '-01';
    $end   = date('Y-m-t', strtotime($start));
}

$events = $repo->findFiltered([
    'date_debut' => $start,
    'date_fin'   => $end,
]);

// Construire le iCalendar
$prodId = '-//Fronote//Agenda//FR';
$tz     = 'Europe/Paris';

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:' . $prodId,
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'X-WR-TIMEZONE:' . $tz,
];

foreach ($events as $ev) {
    $uid     = 'evt-' . $ev['id'] . '@fronote';
    $dtStart = icsDate($ev['date_debut']);
    $dtEnd   = !empty($ev['date_fin']) ? icsDate($ev['date_fin']) : $dtStart;
    $summary = icsEscape($ev['titre'] ?? 'Événement');
    $desc    = icsEscape($ev['description'] ?? '');
    $loc     = icsEscape($ev['lieu'] ?? '');

    $lines[] = 'BEGIN:VEVENT';
    $lines[] = 'UID:' . $uid;
    $lines[] = 'DTSTART:' . $dtStart;
    $lines[] = 'DTEND:' . $dtEnd;
    $lines[] = 'SUMMARY:' . $summary;
    if ($desc)  $lines[] = 'DESCRIPTION:' . $desc;
    if ($loc)   $lines[] = 'LOCATION:' . $loc;
    if (!empty($ev['type'])) $lines[] = 'CATEGORIES:' . strtoupper($ev['type']);
    if (!empty($ev['rrule'])) $lines[] = 'RRULE:' . $ev['rrule'];
    $lines[] = 'STATUS:CONFIRMED';
    $lines[] = 'END:VEVENT';
}

$lines[] = 'END:VCALENDAR';
$ics = implode("\r\n", $lines) . "\r\n";

// Envoyer
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="agenda_fronote.ics"');
header('Content-Length: ' . strlen($ics));
echo $ics;
exit;

/* Helpers */
function icsDate(string $datetime): string
{
    $ts = strtotime($datetime);
    return $ts ? gmdate('Ymd\THis\Z', $ts) : gmdate('Ymd\THis\Z');
}
function icsEscape(string $text): string
{
    return str_replace(["\r\n", "\n", "\r", ',', ';', '\\'], ['\\n', '\\n', '\\n', '\\,', '\\;', '\\\\'], $text);
}
