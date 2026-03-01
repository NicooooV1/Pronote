<?php
/**
 * statistiques.php — Redirection vers la page unifiée
 * Les statistiques sont maintenant gérées par absences.php?view=stats
 */
header('Location: absences.php?view=stats&' . http_build_query($_GET));
exit;
