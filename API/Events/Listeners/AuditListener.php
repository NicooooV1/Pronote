<?php

declare(strict_types=1);

namespace API\Events\Listeners;

/**
 * Écrit une ligne d'audit pour chaque événement domaine dispatché.
 * Remplace les appels manuels logAudit() dispersés dans les pages admin.
 */
class AuditListener
{
    /**
     * Map classe d'événement → label d'action lisible.
     */
    private static array $actionMap = [
        // Notes
        'API\Events\NoteCreated'           => 'note.created',
        'API\Events\NoteUpdated'           => 'note.updated',
        'API\Events\NoteDeleted'           => 'note.deleted',
        // Absences
        'API\Events\AbsenceCreated'        => 'absence.created',
        'API\Events\AbsenceDeleted'        => 'absence.deleted',
        'API\Events\RetardCreated'         => 'retard.created',
        'API\Events\RetardDeleted'         => 'retard.deleted',
        'API\Events\JustificatifApproved'  => 'justificatif.approved',
        'API\Events\JustificatifRejected'  => 'justificatif.rejected',
        // Devoirs
        'API\Events\DevoirCreated'         => 'devoir.created',
        'API\Events\DevoirUpdated'         => 'devoir.updated',
        'API\Events\DevoirDeleted'         => 'devoir.deleted',
        // Événements agenda
        'API\Events\EvenementCreated'      => 'evenement.created',
        'API\Events\EvenementUpdated'      => 'evenement.updated',
        'API\Events\EvenementDeleted'      => 'evenement.deleted',
        // Matières
        'API\Events\MatiereCreated'        => 'matiere.created',
        'API\Events\MatiereUpdated'        => 'matiere.updated',
        'API\Events\MatiereDeleted'        => 'matiere.deleted',
        // Périodes
        'API\Events\PeriodeCreated'        => 'periode.created',
        'API\Events\PeriodeUpdated'        => 'periode.updated',
        'API\Events\PeriodeDeleted'        => 'periode.deleted',
        // Classes
        'API\Events\ClasseCreated'         => 'classe.created',
        'API\Events\ClasseUpdated'         => 'classe.updated',
        'API\Events\ClasseDeleted'         => 'classe.deleted',
        // Utilisateurs
        'API\Events\UserCreated'           => 'user.created',
        'API\Events\UserPasswordChanged'   => 'user.password_changed',
        // Messages
        'API\Events\MessageSent'           => 'message.sent',
    ];

    public function handle(object $event): void
    {
        try {
            $audit = app('audit');
            if (!$audit) {
                return;
            }

            $class  = get_class($event);
            $action = self::$actionMap[$class] ?? strtolower(str_replace(['API\Events\\', '\\'], ['', '.'], $class));

            $props = get_object_vars($event);
            $modelId = null;

            foreach ($props as $key => $value) {
                if (is_int($value) && str_ends_with($key, 'Id')) {
                    $modelId = $value;
                    break;
                }
            }

            $audit->log($action, $modelId, ['new' => $props]);
        } catch (\Throwable $e) {
            error_log("AuditListener: Failed to log event " . get_class($event) . ": " . $e->getMessage());
        }
    }
}
