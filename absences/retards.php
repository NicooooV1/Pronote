<?php
/**
 * retards.php — Redirection vers la page unifiée
 * Les retards sont maintenant gérés par absences.php?type=retards
 */
header('Location: absences.php?type=retards&' . http_build_query($_GET));
exit;
