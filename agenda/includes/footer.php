<?php
/**
 * Pied de page — Module Agenda
 * Nettoyé : plus de ~170 lignes de JS inline dupliqué (navigateToPrevious/Next/Today,
 *           openDayView, openEventDetails, filterClasses, etc.)
 *           Tout est désormais dans assets/js/calendar.js (module Agenda unifié).
 */
?>
      </div><!-- .content-container -->

<?php
// Ajouter calendar.js par défaut, en préservant les JS définis par la page
$extraJs = array_merge($extraJs ?? [], ['assets/js/calendar.js']);

// Pas de script inline : tout est dans calendar.js / event_form.js
include __DIR__ . '/../../templates/shared_footer.php';
?>
