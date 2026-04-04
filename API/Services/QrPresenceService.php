<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * QrPresenceService — Gestion des présences par QR code.
 *
 * Génère un QR code unique par cours, que les élèves scannent pour
 * valider leur présence. Alternative rapide à l'appel classique.
 */
class QrPresenceService
{
    private PDO $pdo;

    /** Durée de validité d'un QR code en secondes */
    private const QR_TTL = 900; // 15 minutes

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Génère un token QR unique pour un cours donné.
     * Retourne le token à encoder dans le QR code.
     */
    public function generateQrToken(int $coursId, int $professeurId): array
    {
        $token = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', time() + self::QR_TTL);

        // Stocker en cache session ou fichier (pas en base pour éviter les requêtes)
        $cache = app('cache');
        $cache->put('qr_presence_' . $token, [
            'cours_id' => $coursId,
            'professeur_id' => $professeurId,
            'created_at' => time(),
            'expires_at' => time() + self::QR_TTL,
        ], self::QR_TTL);

        // URL à encoder dans le QR code
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        $url = $baseUrl . '/appel/qr_scan.php?token=' . $token;

        return [
            'token' => $token,
            'url' => $url,
            'expires_at' => $expiresAt,
            'qr_data' => $url, // Ce qui sera encodé dans le QR
        ];
    }

    /**
     * Valide un scan de QR code par un élève.
     */
    public function validateScan(string $token, int $eleveId): array
    {
        $cache = app('cache');
        $data = $cache->get('qr_presence_' . $token);

        if (!$data) {
            return ['success' => false, 'error' => 'QR code expiré ou invalide.'];
        }

        if ($data['expires_at'] < time()) {
            return ['success' => false, 'error' => 'QR code expiré.'];
        }

        // Vérifier que l'élève n'a pas déjà scanné
        $coursId = $data['cours_id'];
        try {
            $check = $this->pdo->prepare(
                "SELECT id FROM appel WHERE cours_id = ? AND eleve_id = ? AND date_appel = CURDATE()"
            );
            $check->execute([$coursId, $eleveId]);
            if ($check->rowCount() > 0) {
                return ['success' => false, 'error' => 'Présence déjà enregistrée.'];
            }

            // Enregistrer la présence
            $this->pdo->prepare(
                "INSERT INTO appel (cours_id, eleve_id, statut, date_appel, heure_appel, methode)
                 VALUES (?, ?, 'present', CURDATE(), CURTIME(), 'qr_code')"
            )->execute([$coursId, $eleveId]);

            return ['success' => true, 'message' => 'Présence enregistrée.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Erreur lors de l\'enregistrement.'];
        }
    }

    /**
     * Génère le SVG d'un QR code (implémentation simplifiée).
     * Pour un vrai QR code, utilisez une librairie JS côté client ou phpqrcode.
     */
    public function generateQrSvg(string $data, int $size = 200): string
    {
        // Fallback : retourner une URL vers une API QR externe ou un placeholder
        // En production, utilisez une librairie PHP ou le JS côté client
        $encoded = urlencode($data);
        return "<img src='https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded}' alt='QR Code' width='{$size}' height='{$size}'>";
    }
}
