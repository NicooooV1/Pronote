<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * Service d'authentification à deux facteurs (TOTP — RFC 6238)
 * 
 * Implémentation intégrée sans dépendance externe.
 * Compatible avec Google Authenticator, Authy, Microsoft Authenticator, etc.
 */
class TwoFactorService
{
    private PDO $pdo;

    /** Base32 alphabet */
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** TOTP period in seconds */
    private const PERIOD = 30;

    /** Number of digits in the OTP code */
    private const DIGITS = 6;

    /** Time steps to check before/after current (tolerance window) */
    private const WINDOW = 1;

    /** App name shown in authenticator apps */
    private const ISSUER = 'FRONOTE';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Secret management ───────────────────────────────────────

    /**
     * Generate a new base32-encoded secret (160-bit = 32 chars).
     */
    public function generateSecret(): string
    {
        $bytes = random_bytes(20); // 160 bits
        return $this->encodeBase32($bytes);
    }

    /**
     * Build the otpauth:// URI for QR code generation.
     * format: otpauth://totp/ISSUER:email?secret=SECRET&issuer=ISSUER&digits=6&period=30
     */
    public function getOtpauthUri(string $secret, string $accountName): string
    {
        $issuer = self::ISSUER;
        $label = rawurlencode($issuer . ':' . $accountName);
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&digits=%d&period=%d',
            $label,
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD
        );
    }

    /**
     * Get a QR code image URL using Google Charts API.
     * For offline use, consider generating QR codes server-side.
     */
    public function getQrCodeUrl(string $otpauthUri, int $size = 200): string
    {
        return 'https://chart.googleapis.com/chart?chs=' . $size . 'x' . $size
            . '&chld=M|0&cht=qr&chl=' . urlencode($otpauthUri);
    }

    // ─── TOTP verification ───────────────────────────────────────

    /**
     * Verify a 6-digit code against a secret.
     * Checks current time step ± WINDOW steps for tolerance.
     */
    public function verifyCode(string $secret, string $code): bool
    {
        if (strlen($code) !== self::DIGITS || !ctype_digit($code)) {
            return false;
        }

        $secretBytes = $this->decodeBase32($secret);
        $now = (int) floor(time() / self::PERIOD);

        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            $expected = $this->generateTOTP($secretBytes, $now + $i);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a TOTP code for a specific time counter.
     */
    private function generateTOTP(string $secretBytes, int $counter): string
    {
        // Counter as 8-byte big-endian
        $counterBytes = pack('N*', 0) . pack('N*', $counter);

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $counterBytes, $secretBytes, true);

        // Dynamic truncation
        $offset = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            ((ord($hash[$offset + 3]) & 0xFF))
        ) % pow(10, self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    // ─── Database integration ────────────────────────────────────

    /**
     * Check if 2FA is enabled for a user.
     */
    public function isEnabled(int $userId, string $userType): bool
    {
        $table = self::getTable($userType);
        if (!$table) return false;

        try {
            $stmt = $this->pdo->prepare("SELECT two_factor_enabled FROM {$table} WHERE id = ?");
            $stmt->execute([$userId]);
            return (bool) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            // Column may not exist yet (pre-migration)
            return false;
        }
    }

    /**
     * Get the stored secret for a user.
     */
    public function getSecret(int $userId, string $userType): ?string
    {
        $table = self::getTable($userType);
        if (!$table) return null;

        try {
            $stmt = $this->pdo->prepare("SELECT two_factor_secret FROM {$table} WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetchColumn() ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Enable 2FA for a user: stores the secret and sets enabled = 1.
     * Requires a valid code to confirm setup.
     */
    public function enable(int $userId, string $userType, string $secret, string $code): bool
    {
        if (!$this->verifyCode($secret, $code)) {
            return false;
        }

        $table = self::getTable($userType);
        if (!$table) return false;

        try {
            $stmt = $this->pdo->prepare("UPDATE {$table} SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?");
            return $stmt->execute([$secret, $userId]);
        } catch (\PDOException $e) {
            error_log("TwoFactorService::enable error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Disable 2FA for a user.
     */
    public function disable(int $userId, string $userType): bool
    {
        $table = self::getTable($userType);
        if (!$table) return false;

        try {
            $stmt = $this->pdo->prepare("UPDATE {$table} SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
            return $stmt->execute([$userId]);
        } catch (\PDOException $e) {
            error_log("TwoFactorService::disable error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate a 2FA code during login.
     */
    public function validateLogin(int $userId, string $userType, string $code): bool
    {
        $secret = $this->getSecret($userId, $userType);
        if (!$secret) return false;

        return $this->verifyCode($secret, $code);
    }

    // ─── Base32 encoding/decoding ────────────────────────────────

    private function encodeBase32(string $data): string
    {
        $binary = '';
        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $binary .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        $result = '';
        for ($i = 0, $len = strlen($binary); $i < $len; $i += 5) {
            $chunk = substr($binary, $i, 5);
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0');
            }
            $result .= self::BASE32_CHARS[bindec($chunk)];
        }

        return $result;
    }

    private function decodeBase32(string $base32): string
    {
        $base32 = strtoupper(rtrim($base32, '='));
        $binary = '';

        for ($i = 0, $len = strlen($base32); $i < $len; $i++) {
            $pos = strpos(self::BASE32_CHARS, $base32[$i]);
            if ($pos === false) continue;
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        for ($i = 0, $len = strlen($binary) - (strlen($binary) % 8); $i < $len; $i += 8) {
            $result .= chr(bindec(substr($binary, $i, 8)));
        }

        return $result;
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private static function getTable(string $userType): ?string
    {
        return match ($userType) {
            'administrateur' => 'administrateurs',
            'professeur'     => 'professeurs',
            'eleve'          => 'eleves',
            'parent'         => 'parents',
            'vie_scolaire'   => 'vie_scolaire',
            default          => null,
        };
    }
}
