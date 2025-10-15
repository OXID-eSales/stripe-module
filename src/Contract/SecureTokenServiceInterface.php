<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\Contract;

/**
 * Interface for secure token management
 *
 * Purpose: Single-use tokens for sensitive data reference
 * Format: tok_{64_random_hex_chars}
 * Storage: Redis/Memcached with TTL
 * Reusability: 100%
 */
interface SecureTokenServiceInterface
{
    /**
     * Generate secure token for sensitive data
     *
     * @param string $data Data to tokenize (should be encrypted)
     * @param int $ttl Time to live in seconds (default: 3600)
     * @return string Secure token (format: tok_...)
     */
    public function generateToken(string $data, int $ttl = 3600): string;

    /**
     * Validate and retrieve data from token (one-time use)
     *
     * @param string $token Token to validate
     * @return string|null Decrypted data or null if invalid/expired
     */
    public function validateToken(string $token): ?string;

    /**
     * Expire token immediately
     *
     * @param string $token Token to expire
     */
    public function expireToken(string $token): void;

    /**
     * Check if token exists and is valid
     *
     * @param string $token Token to check
     * @return bool True if token is valid
     */
    public function isValidToken(string $token): bool;
}
