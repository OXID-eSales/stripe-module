<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\Model;

/**
 * Payment transaction model
 *
 * Reusability: 100% - Fully generic transaction tracking
 *
 * Represents a payment transaction record that links shop orders
 * to provider transactions. Supports multiple transactions per order
 * (authorization → capture → refunds).
 *
 * Database table: payment_transaction (or osc_transaction)
 */
class PaymentTransaction
{
    /**
     * Transaction ID (primary key)
     */
    private ?string $id = null;

    /**
     * Shop ID (multi-shop support)
     */
    private ?int $shopId = null;

    /**
     * Shop order ID
     */
    private ?string $shopOrderId = null;

    /**
     * Provider order ID
     */
    private ?string $providerOrderId = null;

    /**
     * Provider transaction ID (capture/authorization/refund ID)
     */
    private ?string $transactionId = null;

    /**
     * Transaction status
     */
    private ?string $status = null;

    /**
     * Payment method ID
     */
    private ?string $paymentMethodId = null;

    /**
     * Transaction type: 'capture', 'authorization', 'refund'
     */
    private ?string $transactionType = 'capture';

    /**
     * Shipment tracking code
     */
    private ?string $trackingCode = null;

    /**
     * Shipment tracking carrier
     */
    private ?string $trackingCarrier = null;

    /**
     * Provider-specific data (JSON)
     */
    private array $providerData = [];

    /**
     * Created timestamp
     */
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Updated timestamp
     */
    private ?\DateTimeInterface $updatedAt = null;

    // ==================== Getters ====================

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getShopId(): ?int
    {
        return $this->shopId;
    }

    public function getShopOrderId(): ?string
    {
        return $this->shopOrderId;
    }

    public function getProviderOrderId(): ?string
    {
        return $this->providerOrderId;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getPaymentMethodId(): ?string
    {
        return $this->paymentMethodId;
    }

    public function getTransactionType(): ?string
    {
        return $this->transactionType;
    }

    public function getTrackingCode(): ?string
    {
        return $this->trackingCode;
    }

    public function getTrackingCarrier(): ?string
    {
        return $this->trackingCarrier;
    }

    public function getProviderData(): array
    {
        return $this->providerData;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    // ==================== Setters ====================

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setShopId(int $shopId): self
    {
        $this->shopId = $shopId;
        return $this;
    }

    public function setShopOrderId(string $shopOrderId): self
    {
        $this->shopOrderId = $shopOrderId;
        return $this;
    }

    public function setProviderOrderId(string $providerOrderId): self
    {
        $this->providerOrderId = $providerOrderId;
        return $this;
    }

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function setPaymentMethodId(string $paymentMethodId): self
    {
        $this->paymentMethodId = $paymentMethodId;
        return $this;
    }

    public function setTransactionType(string $transactionType): self
    {
        $this->transactionType = $transactionType;
        return $this;
    }

    public function setTrackingCode(?string $trackingCode): self
    {
        $this->trackingCode = $trackingCode;
        return $this;
    }

    public function setTrackingCarrier(?string $trackingCarrier): self
    {
        $this->trackingCarrier = $trackingCarrier;
        return $this;
    }

    public function setProviderData(array $providerData): self
    {
        $this->providerData = $providerData;
        return $this;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // ==================== Helper Methods ====================

    /**
     * Check if transaction is completed
     *
     * @return bool True if status is COMPLETED
     */
    public function isCompleted(): bool
    {
        return strtoupper($this->status ?? '') === 'COMPLETED';
    }

    /**
     * Check if transaction is pending
     *
     * @return bool True if status is PENDING
     */
    public function isPending(): bool
    {
        return strtoupper($this->status ?? '') === 'PENDING';
    }

    /**
     * Check if transaction is refunded
     *
     * @return bool True if status is REFUNDED
     */
    public function isRefunded(): bool
    {
        return strtoupper($this->status ?? '') === 'REFUNDED';
    }

    /**
     * Check if transaction is a capture
     *
     * @return bool True if transaction type is capture
     */
    public function isCapture(): bool
    {
        return $this->transactionType === 'capture';
    }

    /**
     * Check if transaction is an authorization
     *
     * @return bool True if transaction type is authorization
     */
    public function isAuthorization(): bool
    {
        return $this->transactionType === 'authorization';
    }

    /**
     * Check if transaction is a refund
     *
     * @return bool True if transaction type is refund
     */
    public function isRefund(): bool
    {
        return $this->transactionType === 'refund';
    }

    /**
     * Get provider-specific data value
     *
     * @param string $key Data key
     * @param mixed $default Default value
     * @return mixed Data value
     */
    public function getProviderDataValue(string $key, mixed $default = null): mixed
    {
        return $this->providerData[$key] ?? $default;
    }

    /**
     * Set provider-specific data value
     *
     * @param string $key Data key
     * @param mixed $value Data value
     * @return self
     */
    public function setProviderDataValue(string $key, mixed $value): self
    {
        $this->providerData[$key] = $value;
        return $this;
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
            'shopId' => $this->shopId,
            'shopOrderId' => $this->shopOrderId,
            'providerOrderId' => $this->providerOrderId,
            'transactionId' => $this->transactionId,
            'status' => $this->status,
            'paymentMethodId' => $this->paymentMethodId,
            'transactionType' => $this->transactionType,
            'trackingCode' => $this->trackingCode,
            'trackingCarrier' => $this->trackingCarrier,
            'providerData' => $this->providerData,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Save transaction (to be implemented by shop-specific extension)
     *
     * @return bool True if saved successfully
     */
    public function save(): bool
    {
        // This method should be implemented by shop-specific extension
        // (OXID, Shopware, etc.) to save to the database
        throw new \RuntimeException(
            'save() method must be implemented by shop-specific PaymentTransaction extension'
        );
    }
}
