<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, HasUlids;


    protected $casts = [
        'payment_provider' => PaymentProvider::class,
        'status' => PaymentStatus::class,
        'paid_at' => 'datetime',
        'provider_response' => 'array',
        'metadata' => 'array',
        'amount' => 'decimal:2',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isCompleted(): bool
    {
        return $this->status->isSuccessful();
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    public function markAsCompleted(?string $providerPaymentId = null): void
    {
        $this->status = PaymentStatus::COMPLETED;
        $this->paid_at = now();

        if ($providerPaymentId) {
            $this->provider_payment_id = $providerPaymentId;
        }

        $this->save();
    }

    public function markAsFailed(array $providerResponse = []): void
    {
        $this->status = PaymentStatus::FAILED;

        if (! empty($providerResponse)) {
            $this->provider_response = $providerResponse;
        }

        $this->save();
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', PaymentStatus::COMPLETED->value);
    }

    public function scopePending($query)
    {
        return $query->where('status', PaymentStatus::PENDING->value);
    }

    public function scopeForSubscription($query, string $subscriptionId)
    {
        return $query->where('subscription_id', $subscriptionId);
    }
}
