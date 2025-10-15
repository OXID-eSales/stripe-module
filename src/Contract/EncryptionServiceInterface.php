<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\Contract;

/**
 * Interface for client-side encryption (PCI compliance)
 *
 * Algorithm: RSA-OAEP with SHA-256
 * Key Size: 2048 bits minimum
 * Format: "ENC:{base64_encoded_encrypted_data}"
 * Reusability: 100%
 */
interface EncryptionServiceInterface
{
    /**
     * Encrypt sensitive data using RSA public key
     *
     * @param string $data Plain text data to encrypt
     * @return string Encrypted data with "ENC:" prefix
     */
    public function encrypt(string $data): string;

    /**
     * Decrypt encrypted data using RSA private key
     *
     * @param string $encryptedData Encrypted data with "ENC:" prefix
     * @return string Decrypted plain text
     * @throws \RuntimeException If decryption fails
     */
    public function decrypt(string $encryptedData): string;

    /**
     * Get public key for client-side encryption
     *
     * @return string PEM-encoded RSA public key
     */
    public function getPublicKey(): string;

    /**
     * Rotate encryption keys (security best practice)
     */
    public function rotateKeys(): void;

    /**
     * Verify if data is encrypted
     *
     * @param string $data Data to check
     * @return bool True if data starts with "ENC:"
     */
    public function isEncrypted(string $data): bool;
}
