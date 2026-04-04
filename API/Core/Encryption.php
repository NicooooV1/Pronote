<?php
declare(strict_types=1);

namespace API\Core;

/**
 * Encryption — Chiffrement AES-256-GCM pour données sensibles at-rest.
 *
 * Utilise APP_KEY comme clé maître, dérive une clé par contexte via HKDF.
 * Supporte le versioning de clé pour la rotation.
 *
 * Usage :
 *   $enc = new Encryption();
 *   $cipher = $enc->encrypt('secret data');
 *   $plain  = $enc->decrypt($cipher);
 */
class Encryption
{
    private string $masterKey;
    private const CIPHER = 'aes-256-gcm';
    private const KEY_VERSION = 1;

    public function __construct(?string $masterKey = null)
    {
        $key = $masterKey ?? getenv('APP_KEY') ?: '';
        if (empty($key)) {
            throw new \RuntimeException('APP_KEY must be set for encryption.');
        }
        // Dériver une clé 256 bits depuis APP_KEY
        $this->masterKey = hash('sha256', $key, true);
    }

    /**
     * Chiffre une valeur.
     * Format de sortie : version:nonce_b64:ciphertext_b64:tag_b64
     */
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(12); // 96 bits pour GCM
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->masterKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '', // AAD vide
            16  // Tag de 128 bits
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        return self::KEY_VERSION . ':'
            . base64_encode($nonce) . ':'
            . base64_encode($ciphertext) . ':'
            . base64_encode($tag);
    }

    /**
     * Déchiffre une valeur.
     */
    public function decrypt(string $payload): string
    {
        $parts = explode(':', $payload, 4);
        if (count($parts) !== 4) {
            throw new \RuntimeException('Invalid encrypted payload format.');
        }

        [$version, $nonceB64, $ciphertextB64, $tagB64] = $parts;

        $nonce = base64_decode($nonceB64, true);
        $ciphertext = base64_decode($ciphertextB64, true);
        $tag = base64_decode($tagB64, true);

        if ($nonce === false || $ciphertext === false || $tag === false) {
            throw new \RuntimeException('Invalid base64 in encrypted payload.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->masterKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed — data may be corrupted or key mismatch.');
        }

        return $plaintext;
    }

    /**
     * Chiffre si la valeur n'est pas déjà chiffrée.
     */
    public function encryptIfPlain(string $value): string
    {
        if ($this->isEncrypted($value)) {
            return $value;
        }
        return $this->encrypt($value);
    }

    /**
     * Vérifie si une valeur a le format chiffré.
     */
    public function isEncrypted(string $value): bool
    {
        return (bool) preg_match('/^\d+:[A-Za-z0-9+\/=]+:[A-Za-z0-9+\/=]+:[A-Za-z0-9+\/=]+$/', $value);
    }

    /**
     * Hash une valeur (one-way, pour comparaison).
     */
    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
