<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * PaymentService — Paiement en ligne (Stripe / PayPlug).
 *
 * Gère les paiements de cantine, de fournitures, etc.
 * Config .env : PAYMENT_PROVIDER=stripe, STRIPE_SECRET_KEY=sk_..., STRIPE_PUBLIC_KEY=pk_...
 */
class PaymentService
{
    private PDO $pdo;
    private string $provider;
    private string $secretKey;
    private string $publicKey;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->provider = getenv('PAYMENT_PROVIDER') ?: 'stripe';
        $this->secretKey = getenv('STRIPE_SECRET_KEY') ?: getenv('PAYPLUG_SECRET_KEY') ?: '';
        $this->publicKey = getenv('STRIPE_PUBLIC_KEY') ?: getenv('PAYPLUG_PUBLIC_KEY') ?: '';
    }

    public function isEnabled(): bool
    {
        return !empty($this->secretKey);
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * Crée une intention de paiement.
     */
    public function createPayment(int $userId, string $userType, float $amount, string $description, array $metadata = []): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'Paiement non configuré.'];
        }

        // Créer l'entrée en base
        $stmt = $this->pdo->prepare(
            "INSERT INTO payments (user_id, user_type, amount, currency, description, provider, metadata, created_at)
             VALUES (?, ?, ?, 'EUR', ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $userId, $userType, $amount, $description,
            $this->provider, json_encode($metadata),
        ]);
        $paymentId = (int) $this->pdo->lastInsertId();

        // Appeler le provider
        if ($this->provider === 'stripe') {
            return $this->createStripePayment($paymentId, $amount, $description, $metadata);
        }

        return ['success' => true, 'payment_id' => $paymentId, 'status' => 'pending'];
    }

    /**
     * Confirme un paiement après callback du provider.
     */
    public function confirmPayment(int $paymentId, string $providerReference): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE payments SET status = 'completed', provider_reference = ?, completed_at = NOW() WHERE id = ? AND status = 'pending'"
        );
        return $stmt->execute([$providerReference, $paymentId]);
    }

    /**
     * Marque un paiement comme échoué.
     */
    public function failPayment(int $paymentId, string $reason = ''): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE payments SET status = 'failed', provider_reference = ? WHERE id = ?"
        );
        return $stmt->execute([$reason, $paymentId]);
    }

    /**
     * Historique des paiements d'un utilisateur.
     */
    public function getUserPayments(int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM payments WHERE user_id = ? AND user_type = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$userId, $userType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Statistiques de paiement (admin).
     */
    public function getStats(): array
    {
        try {
            $row = $this->pdo->query(
                "SELECT
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) AS total_collected,
                    SUM(status = 'completed') AS total_completed,
                    SUM(status = 'pending') AS total_pending,
                    SUM(status = 'failed') AS total_failed
                 FROM payments"
            )->fetch(PDO::FETCH_ASSOC);
            return $row ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ─── Stripe ─────────────────────────────────────────────────────

    private function createStripePayment(int $paymentId, float $amount, string $description, array $metadata): array
    {
        $ch = curl_init('https://api.stripe.com/v1/payment_intents');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'amount' => (int)($amount * 100), // Stripe utilise les centimes
                'currency' => 'eur',
                'description' => $description,
                'metadata[payment_id]' => $paymentId,
            ]),
            CURLOPT_USERPWD => $this->secretKey . ':',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300) {
            $data = json_decode($response, true);
            $this->pdo->prepare("UPDATE payments SET provider_reference = ? WHERE id = ?")
                ->execute([$data['id'] ?? '', $paymentId]);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'client_secret' => $data['client_secret'] ?? '',
                'stripe_id' => $data['id'] ?? '',
            ];
        }

        $this->failPayment($paymentId, 'Stripe error: HTTP ' . $code);
        return ['success' => false, 'error' => 'Erreur de paiement (code ' . $code . ')'];
    }
}
