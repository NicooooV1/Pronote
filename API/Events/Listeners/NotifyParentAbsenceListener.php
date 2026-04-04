<?php

declare(strict_types=1);

namespace API\Events\Listeners;

use API\Events\AbsenceCreated;

/**
 * Déclenche l'envoi d'un email aux parents d'un élève absent.
 * L'envoi est mis en queue (asynchrone) pour ne pas bloquer la requête.
 */
class NotifyParentAbsenceListener
{
    public function handle(AbsenceCreated $event): void
    {
        try {
            // Vérifier le feature flag du module absences
            $features = app('features');
            if ($features && !$features->isEnabled('absences.notify_parents')) {
                return;
            }

            $queue = app('queue');
            if (!$queue) {
                return;
            }

            $queue->dispatch(
                \API\Jobs\SendAbsenceNotificationJob::class,
                [
                    'absence_id' => $event->absenceId,
                    'eleve_id'   => $event->data['id_eleve'] ?? null,
                    'date_debut' => $event->data['date_debut'] ?? null,
                    'date_fin'   => $event->data['date_fin'] ?? null,
                    'type'       => $event->data['type_absence'] ?? null,
                    'motif'      => $event->data['motif'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            error_log("NotifyParentAbsenceListener: Failed to queue job: " . $e->getMessage());
        }
    }
}
