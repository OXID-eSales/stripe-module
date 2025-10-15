<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\Contract;

use OxidSolutionCatalysts\Stripe\PaymentComponent\Contract\PciViolationException;

/**
 * Interface for PCI compliance enforcement
 *
 * Purpose: Prevent accidental plain text storage of sensitive data
 * Sensitive Fields: cardNumber, cvv, cardholderName, expiryDate
 * Reusability: 100%
 */
interface PciComplianceGuardInterface
{
    /**
     * Validate that sensitive data is encrypted before storage
     *
     * @param string $data Data to validate
     * @return bool True if data is encrypted or non-sensitive
     * @throws PciViolationException If plain text sensitive data detected
     */
    public function validateEncryptedData(string $data): bool;

    /**
     * Sanitize output by masking sensitive fields
     *
     * @param array $data Data to sanitize
     * @return array Sanitized data with masked sensitive fields
     */
    public function sanitizeOutput(array $data): array;

    /**
     * Prevent plain text storage of sensitive data
     *
     * @param array $data Data to check
     * @throws PciViolationException If plain text sensitive fields detected
     */
    public function preventPlainTextStorage(array $data): void;

    /**
     * Check if field is sensitive
     *
     * @param string $fieldName Field name to check
     * @return bool True if field is sensitive
     */
    public function isSensitiveField(string $fieldName): bool;

    /**
     * Mask sensitive value (e.g., 4242****4242)
     *
     * @param string $value Value to mask
     * @param string $fieldName Field name for context
     * @return string Masked value
     */
    public function maskValue(string $value, string $fieldName): string;
}
