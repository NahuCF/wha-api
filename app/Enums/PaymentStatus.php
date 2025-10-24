<?php

namespace App\Enums;

enum PaymentStatus: string
{
    // Generic statuses (for internal use)
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case CANCELLED = 'cancelled';

    // MercadoPago specific statuses
    case MERCADOPAGO_APPROVED = 'mercadopago_approved';
    case MERCADOPAGO_PENDING = 'mercadopago_pending';
    case MERCADOPAGO_IN_PROCESS = 'mercadopago_in_process';
    case MERCADOPAGO_IN_MEDIATION = 'mercadopago_in_mediation';
    case MERCADOPAGO_REJECTED = 'mercadopago_rejected';
    case MERCADOPAGO_CANCELLED = 'mercadopago_cancelled';
    case MERCADOPAGO_AUTHORIZED = 'mercadopago_authorized';
    case MERCADOPAGO_REFUNDED = 'mercadopago_refunded';
    case MERCADOPAGO_PARTIALLY_REFUNDED = 'mercadopago_partially_refunded';
    case MERCADOPAGO_CHARGED_BACK = 'mercadopago_charged_back';

    // Stripe specific statuses (for future use)
    case STRIPE_SUCCEEDED = 'stripe_succeeded';
    case STRIPE_PENDING = 'stripe_pending';
    case STRIPE_FAILED = 'stripe_failed';
    case STRIPE_CANCELLED = 'stripe_cancelled';
    case STRIPE_PROCESSING = 'stripe_processing';
    case STRIPE_REQUIRES_ACTION = 'stripe_requires_action';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all MercadoPago specific statuses
     */
    public static function mercadoPagoStatuses(): array
    {
        return [
            self::MERCADOPAGO_APPROVED,
            self::MERCADOPAGO_PENDING,
            self::MERCADOPAGO_IN_PROCESS,
            self::MERCADOPAGO_IN_MEDIATION,
            self::MERCADOPAGO_REJECTED,
            self::MERCADOPAGO_CANCELLED,
            self::MERCADOPAGO_AUTHORIZED,
            self::MERCADOPAGO_REFUNDED,
            self::MERCADOPAGO_PARTIALLY_REFUNDED,
            self::MERCADOPAGO_CHARGED_BACK,
        ];
    }

    /**
     * Get all Stripe specific statuses
     */
    public static function stripeStatuses(): array
    {
        return [
            self::STRIPE_SUCCEEDED,
            self::STRIPE_PENDING,
            self::STRIPE_FAILED,
            self::STRIPE_CANCELLED,
            self::STRIPE_PROCESSING,
            self::STRIPE_REQUIRES_ACTION,
        ];
    }

    /**
     * Map MercadoPago API status to our enum
     */
    public static function fromMercadoPagoStatus(string $status): self
    {
        return match ($status) {
            'approved' => self::MERCADOPAGO_APPROVED,
            'pending', 'pending_contingency', 'pending_review_manual' => self::MERCADOPAGO_PENDING,
            'in_process' => self::MERCADOPAGO_IN_PROCESS,
            'in_mediation' => self::MERCADOPAGO_IN_MEDIATION,
            'rejected' => self::MERCADOPAGO_REJECTED,
            'cancelled' => self::MERCADOPAGO_CANCELLED,
            'authorized' => self::MERCADOPAGO_AUTHORIZED,
            'refunded' => self::MERCADOPAGO_REFUNDED,
            'partially_refunded' => self::MERCADOPAGO_PARTIALLY_REFUNDED,
            'charged_back' => self::MERCADOPAGO_CHARGED_BACK,
            default => self::PENDING,
        };
    }

    /**
     * Convert provider-specific status to generic status
     */
    public function toGenericStatus(): self
    {
        return match ($this) {
            self::MERCADOPAGO_APPROVED,
            self::STRIPE_SUCCEEDED => self::COMPLETED,

            self::MERCADOPAGO_PENDING,
            self::MERCADOPAGO_IN_PROCESS,
            self::MERCADOPAGO_AUTHORIZED,
            self::STRIPE_PENDING,
            self::STRIPE_PROCESSING,
            self::STRIPE_REQUIRES_ACTION => self::PENDING,

            self::MERCADOPAGO_REJECTED,
            self::STRIPE_FAILED => self::FAILED,

            self::MERCADOPAGO_CANCELLED,
            self::STRIPE_CANCELLED => self::CANCELLED,

            self::MERCADOPAGO_REFUNDED,
            self::MERCADOPAGO_PARTIALLY_REFUNDED,
            self::MERCADOPAGO_CHARGED_BACK => self::REFUNDED,

            self::MERCADOPAGO_IN_MEDIATION => self::PENDING,

            default => $this,
        };
    }

    /**
     * Check if payment is successful
     */
    public function isSuccessful(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::MERCADOPAGO_APPROVED,
            self::STRIPE_SUCCEEDED,
        ]);
    }

    /**
     * Check if payment is still processing
     */
    public function isPending(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::MERCADOPAGO_PENDING,
            self::MERCADOPAGO_IN_PROCESS,
            self::MERCADOPAGO_AUTHORIZED,
            self::MERCADOPAGO_IN_MEDIATION,
            self::STRIPE_PENDING,
            self::STRIPE_PROCESSING,
            self::STRIPE_REQUIRES_ACTION,
        ]);
    }

    /**
     * Check if payment failed
     */
    public function isFailed(): bool
    {
        return in_array($this, [
            self::FAILED,
            self::MERCADOPAGO_REJECTED,
            self::STRIPE_FAILED,
        ]);
    }
}
