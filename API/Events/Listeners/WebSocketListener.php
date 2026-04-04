<?php

declare(strict_types=1);

namespace API\Events\Listeners;

use API\Core\WebSocket;
use API\Events\NoteCreated;
use API\Events\AbsenceCreated;
use API\Events\MessageSent;
use API\Events\EvenementCreated;

/**
 * Envoie des notifications temps réel via le serveur WebSocket
 * pour les événements domaine critiques.
 */
class WebSocketListener
{
    public function handle(object $event): void
    {
        try {
            match (true) {
                $event instanceof NoteCreated      => $this->onNoteCreated($event),
                $event instanceof AbsenceCreated   => $this->onAbsenceCreated($event),
                $event instanceof MessageSent      => $this->onMessageSent($event),
                $event instanceof EvenementCreated => $this->onEvenementCreated($event),
                default                            => null,
            };
        } catch (\Throwable $e) {
            error_log("WebSocketListener: Error processing " . get_class($event) . ": " . $e->getMessage());
        }
    }

    private function onNoteCreated(NoteCreated $event): void
    {
        WebSocket::notifyNewGrade(
            $event->data['id_eleve'] ?? 0,
            [
                'noteId'   => $event->noteId,
                'note'     => $event->data['note'] ?? null,
                'matiere'  => $event->data['id_matiere'] ?? null,
                'trimestre' => $event->data['trimestre'] ?? null,
            ]
        );
    }

    private function onAbsenceCreated(AbsenceCreated $event): void
    {
        WebSocket::notifyNewAbsence(
            $event->data['id_eleve'] ?? 0,
            [
                'absenceId'  => $event->absenceId,
                'date_debut' => $event->data['date_debut'] ?? null,
                'type'       => $event->data['type_absence'] ?? null,
            ]
        );
    }

    private function onMessageSent(MessageSent $event): void
    {
        WebSocket::notifyUser(
            $event->senderId,
            [
                'type'        => 'message_sent',
                'messageId'   => $event->messageId,
                'sender_type' => $event->senderType,
            ]
        );
    }

    private function onEvenementCreated(EvenementCreated $event): void
    {
        WebSocket::notifyNewEvent(
            'all',
            0,
            [
                'evenementId' => $event->evenementId,
                'titre'       => $event->data['titre'] ?? '',
                'type'        => $event->data['type_evenement'] ?? '',
            ]
        );
    }
}
