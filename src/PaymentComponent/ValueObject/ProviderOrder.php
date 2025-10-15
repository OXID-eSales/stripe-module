<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\ValueObject;

/**
 * Value object representing a provider order
 *
 * Reusability: 90% - Core structure generic, provider-specific fields in metadata
 *
 * This is an immutable value object that represents the state of an order
 * at the payment provider (Stripe, PayPal, Adyen, etc.)
 */
final class ProviderOrder
{
    /**
     * Constructor
     *
     * @param string $id Provider order ID
     * @param string $status Order status (CREATED, APPROVED, COMPLETED, etc.)
     * @param float $amount Order amount
     * @param string $currency Currency code (ISO 4217)
     * @param string|null $transactionId Transaction/capture ID (null if not captured)
     * @param string|null $approvalUrl URL for customer approval (for redirect-based flows)
     * @param array $metadata Provider-specific metadata
     */
    public function __construct(
        private readonly string $id,
        private readonly string $status,
        private readonly float $amount,
        private readonly string $currency,
        private readonly ?string $transactionId = null,
        private readonly ?string $approvalUrl = null,
        private readonly array $metadata = []
    ) {
    }

    /**
     * Get provider order ID
     *
     * @return string Provider order ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get order status
     *
     * @return string Order status
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Get order amount
     *
     * @return float Order amount
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * Get currency code
     *
     * @return string Currency code (ISO 4217)
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Get transaction ID
     *
     * @return string|null Transaction ID or null if not captured
     */
    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    /**
     * Get approval URL
     *
     * @return string|null Approval URL or null if not redirect-based
     */
    public function getApprovalUrl(): ?string
    {
        return $this->approvalUrl;
    }

    /**
     * Get provider-specific metadata
     *
     * @return array Metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get specific metadata value
     *
     * @param string $key Metadata key
     * @param mixed $default Default value if key not found
     * @return mixed Metadata value
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if order is created
     *
     * @return bool True if status is CREATED
     */
    public function isCreated(): bool
    {
        return strtoupper($this->status) === 'CREATED';
    }

    /**
     * Check if order is approved
     *
     * @return bool True if status is APPROVED
     */
    public function isApproved(): bool
    {
        return strtoupper($this->status) === 'APPROVED';
    }

    /**
     * Check if order is completed
     *
     * @return bool True if status is COMPLETED
     */
    public function isCompleted(): bool
    {
        return strtoupper($this->status) === 'COMPLETED';
    }

    /**
     * Check if order requires customer approval
     *
     * @return bool True if approval URL is present
     */
    public function requiresApproval(): bool
    {
        return $this->approvalUrl !== null;
    }

    /**
     * Create new instance with updated status
     *
     * @param string $status New status
     * @return self New instance
     */
    public function withStatus(string $status): self
    {
        return new self(
            $this->id,
            $status,
            $this->amount,
            $this->currency,
            $this->transactionId,
            $this->approvalUrl,
            $this->metadata
        );
    }

    /**
     * Create new instance with transaction ID
     *
     * @param string $transactionId Transaction ID
     * @return self New instance
     */
    public function withTransactionId(string $transactionId): self
    {
        return new self(
            $this->id,
            $this->status,
            $this->amount,
            $this->currency,
            $transactionId,
            $this->approvalUrl,
            $this->metadata
        );
    }

    /**
     * Convert to array representation
     *
     * @return array Array representation
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'transactionId' => $this->transactionId,
            'approvalUrl' => $this->approvalUrl,
            'metadata' => $this->metadata,
        ];
    }
}
