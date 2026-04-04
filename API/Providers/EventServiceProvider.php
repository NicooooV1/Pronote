<?php

declare(strict_types=1);

namespace API\Providers;

use API\Core\ServiceProvider;
use API\Events\Listeners\AuditListener;
use API\Events\Listeners\WebSocketListener;
use API\Events\Listeners\NotifyParentAbsenceListener;

/**
 * Enregistre les listeners sur les événements domaine.
 * Chargé depuis bootstrap.php après le singleton 'hooks'.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * Map événement → listeners (un événement peut avoir plusieurs listeners).
     */
    private const LISTEN = [
        // Audit sur tous les événements domaine
        \API\Events\NoteCreated::class          => [AuditListener::class, WebSocketListener::class],
        \API\Events\NoteUpdated::class          => [AuditListener::class],
        \API\Events\NoteDeleted::class          => [AuditListener::class],

        \API\Events\AbsenceCreated::class       => [AuditListener::class, WebSocketListener::class, NotifyParentAbsenceListener::class],
        \API\Events\AbsenceDeleted::class       => [AuditListener::class],
        \API\Events\RetardCreated::class        => [AuditListener::class],
        \API\Events\RetardDeleted::class        => [AuditListener::class],
        \API\Events\JustificatifApproved::class => [AuditListener::class],
        \API\Events\JustificatifRejected::class => [AuditListener::class],

        \API\Events\DevoirCreated::class        => [AuditListener::class],
        \API\Events\DevoirUpdated::class        => [AuditListener::class],
        \API\Events\DevoirDeleted::class        => [AuditListener::class],

        \API\Events\EvenementCreated::class     => [AuditListener::class, WebSocketListener::class],
        \API\Events\EvenementUpdated::class     => [AuditListener::class],
        \API\Events\EvenementDeleted::class     => [AuditListener::class],

        \API\Events\MatiereCreated::class       => [AuditListener::class],
        \API\Events\MatiereUpdated::class       => [AuditListener::class],
        \API\Events\MatiereDeleted::class       => [AuditListener::class],

        \API\Events\PeriodeCreated::class       => [AuditListener::class],
        \API\Events\PeriodeUpdated::class       => [AuditListener::class],
        \API\Events\PeriodeDeleted::class       => [AuditListener::class],

        \API\Events\ClasseCreated::class        => [AuditListener::class],
        \API\Events\ClasseUpdated::class        => [AuditListener::class],
        \API\Events\ClasseDeleted::class        => [AuditListener::class],

        \API\Events\UserCreated::class          => [AuditListener::class],
        \API\Events\UserPasswordChanged::class  => [AuditListener::class],

        \API\Events\MessageSent::class          => [AuditListener::class, WebSocketListener::class],
    ];

    public function register(): void
    {
        // Les listeners sont enregistrés dans boot() pour avoir accès au HookManager
    }

    public function boot(): void
    {
        $hooks     = $this->app->make('hooks');
        $instances = [];

        foreach (self::LISTEN as $eventClass => $listenerClasses) {
            foreach ($listenerClasses as $listenerClass) {
                $instances[$listenerClass] ??= new $listenerClass();
                $hooks->register($eventClass, [$instances[$listenerClass], 'handle']);
            }
        }
    }
}
