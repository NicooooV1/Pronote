<?php
declare(strict_types=1);

namespace API\Services;

/**
 * VideoConferenceService — Intégration Jitsi Meet pour les réunions.
 *
 * Génère des URLs de visioconférence sécurisées (JWT) pour les réunions
 * parents-professeurs et autres événements.
 *
 * Config .env :
 *   JITSI_DOMAIN=meet.jit.si         (ou instance self-hosted)
 *   JITSI_APP_ID=fronote              (pour JWT)
 *   JITSI_SECRET=...                  (clé secrète JWT)
 */
class VideoConferenceService
{
    private string $domain;
    private string $appId;
    private string $secret;

    public function __construct()
    {
        $this->domain = getenv('JITSI_DOMAIN') ?: 'meet.jit.si';
        $this->appId = getenv('JITSI_APP_ID') ?: 'fronote';
        $this->secret = getenv('JITSI_SECRET') ?: '';
    }

    /**
     * Vérifie si le service est configuré.
     */
    public function isEnabled(): bool
    {
        return !empty($this->domain);
    }

    /**
     * Génère une URL de visioconférence pour une réunion.
     */
    public function createRoom(string $roomName, array $options = []): array
    {
        $sanitizedRoom = $this->sanitizeRoomName($roomName);
        $url = 'https://' . $this->domain . '/' . $sanitizedRoom;

        $result = [
            'url' => $url,
            'room_name' => $sanitizedRoom,
            'domain' => $this->domain,
        ];

        // Si un secret JWT est configuré, générer un token
        if ($this->secret) {
            $result['jwt'] = $this->generateJwt($sanitizedRoom, $options);
            $result['url'] .= '?jwt=' . $result['jwt'];
        }

        return $result;
    }

    /**
     * Génère une URL pour une réunion parents-professeurs.
     */
    public function createReunionRoom(int $reunionId, string $professeurName, string $parentName = ''): array
    {
        $roomName = 'fronote-reunion-' . $reunionId;
        return $this->createRoom($roomName, [
            'moderator_name' => $professeurName,
            'participant_name' => $parentName,
            'subject' => 'Réunion parents-professeurs',
        ]);
    }

    /**
     * Génère un JWT pour authentifier l'accès à une salle Jitsi.
     */
    private function generateJwt(string $room, array $options = []): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();

        $payload = [
            'iss' => $this->appId,
            'sub' => $this->domain,
            'aud' => $this->appId,
            'iat' => $now,
            'exp' => $now + 7200, // 2 heures
            'room' => $room,
            'context' => [
                'user' => [
                    'name' => $options['moderator_name'] ?? 'Participant',
                ],
                'features' => [
                    'recording' => false,
                    'livestreaming' => false,
                ],
            ],
        ];

        if (!empty($options['subject'])) {
            $payload['context']['room'] = ['name' => $options['subject']];
        }

        $headerB64 = $this->base64url(json_encode($header));
        $payloadB64 = $this->base64url(json_encode($payload));
        $signature = $this->base64url(hash_hmac('sha256', "$headerB64.$payloadB64", $this->secret, true));

        return "$headerB64.$payloadB64.$signature";
    }

    private function sanitizeRoomName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '-', $name);
        return substr($name, 0, 100);
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
