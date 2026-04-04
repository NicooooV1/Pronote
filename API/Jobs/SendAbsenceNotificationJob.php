<?php

declare(strict_types=1);

namespace API\Jobs;

/**
 * Envoie un email de notification d'absence aux parents d'un élève.
 * Payload attendu :
 *   absence_id int
 *   eleve_id   int
 *   date_debut string
 *   date_fin   string
 *   type       string
 *   motif      string|null
 */
class SendAbsenceNotificationJob
{
    public function handle(array $payload): void
    {
        $pdo   = app('db')?->getConnection();
        $email = app('email');

        if (!$pdo || !$email) {
            throw new \RuntimeException('Dépendances indisponibles (db ou email)');
        }

        $eleveId = (int) ($payload['eleve_id'] ?? 0);
        if ($eleveId === 0) {
            return;
        }

        // Récupérer nom de l'élève
        $stmt = $pdo->prepare('SELECT nom, prenom, classe FROM eleves WHERE id = ?');
        $stmt->execute([$eleveId]);
        $eleve = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$eleve) {
            return;
        }

        // Récupérer les emails des parents
        $stmt = $pdo->prepare(
            'SELECT p.email FROM parents p
             JOIN parent_eleve pe ON pe.parent_id = p.id
             WHERE pe.eleve_id = ? AND p.email IS NOT NULL AND p.email != \'\''
        );
        $stmt->execute([$eleveId]);
        $parentEmails = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($parentEmails)) {
            return;
        }

        $nomEleve = trim($eleve['prenom'] . ' ' . $eleve['nom']);
        $dateDebut = $payload['date_debut'] ?? '';
        $dateFin   = $payload['date_fin'] ?? '';
        $type      = $payload['type'] ?? 'Absence';
        $motif     = $payload['motif'] ?? null;

        $result = $email->sendTemplate(
            $parentEmails,
            "Absence de {$nomEleve}",
            'absence',
            [
                'eleve_nom'  => $nomEleve,
                'classe'     => $eleve['classe'] ?? '',
                'date_debut' => $dateDebut,
                'date_fin'   => $dateFin,
                'type'       => $type,
                'motif'      => $motif ?? 'Non renseigné',
            ]
        );

        if (!($result['success'] ?? false)) {
            throw new \RuntimeException('Échec envoi notification absence : ' . ($result['message'] ?? 'erreur inconnue'));
        }
    }
}
