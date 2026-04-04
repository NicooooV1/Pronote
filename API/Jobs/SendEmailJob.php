<?php

declare(strict_types=1);

namespace API\Jobs;

/**
 * Job générique d'envoi d'email.
 * Payload attendu :
 *   to      string|array  Destinataire(s)
 *   subject string        Sujet
 *   body    string        Corps HTML
 *   text    string        Corps texte (optionnel)
 */
class SendEmailJob
{
    public function handle(array $payload): void
    {
        $email = app('email');
        if (!$email) {
            throw new \RuntimeException('EmailService non disponible');
        }

        $result = $email->send(
            $payload['to'],
            $payload['subject'],
            $payload['body'],
            $payload['text'] ?? '',
            $payload['options'] ?? []
        );

        if (!($result['success'] ?? false)) {
            throw new \RuntimeException('Échec envoi email : ' . ($result['message'] ?? 'erreur inconnue'));
        }
    }
}
