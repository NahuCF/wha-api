<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\PaymentProvider;
use App\Enums\PlanType;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory, HasUlids;

    protected $casts = [
        'plan_type' => PlanType::class,
        'billing_cycle' => BillingCycle::class,
        'payment_provider' => PaymentProvider::class,
        'status' => SubscriptionStatus::class,
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'cancelled_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'metadata' => 'array',
        'amount' => 'decimal:2',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isActive(): bool
    {
        // A subscription is active if:
        // 1. Status is ACTIVE or GRACE_PERIOD AND
        // 2. The end_date hasn't passed OR it was cancelled but still within the paid period
        if (!$this->status->isActive()) {
            return false;
        }
        
        // If cancelled, check if we're still within the paid period
        if ($this->cancelled_at) {
            return $this->end_date && $this->end_date->isFuture();
        }
        
        return true;
    }

    public function isInGracePeriod(): bool
    {
        return $this->status === SubscriptionStatus::GRACE_PERIOD &&
               $this->grace_period_ends_at &&
               $this->grace_period_ends_at->isFuture();
    }

    public function canAddUsers(int $count = 1): bool
    {
        if ($this->max_users === null) {
            return true;
        }

        $totalAllowed = $this->max_users + $this->extra_users_purchased;

        return ($this->used_users + $count) <= $totalAllowed;
    }

    public function canAddContacts(int $count = 1): bool
    {
        if ($this->max_contacts === null) {
            return true;
        }

        return ($this->used_contacts + $count) <= $this->max_contacts;
    }

    public function canAddWhatsappNumber(): bool
    {
        if ($this->max_whatsapp_numbers === null) {
            return true;
        }

        return $this->used_whatsapp_numbers < $this->max_whatsapp_numbers;
    }

    public function getRemainingUsers(): ?int
    {
        if ($this->max_users === null) {
            return null;
        }

        $totalAllowed = $this->max_users + $this->extra_users_purchased;

        return max(0, $totalAllowed - $this->used_users);
    }

    public function getRemainingContacts(): ?int
    {
        if ($this->max_contacts === null) {
            return null;
        }

        return max(0, $this->max_contacts - $this->used_contacts);
    }

    public function getRemainingWhatsappNumbers(): ?int
    {
        if ($this->max_whatsapp_numbers === null) {
            return null;
        }

        return max(0, $this->max_whatsapp_numbers - $this->used_whatsapp_numbers);
    }

    public function cancel(): void
    {
        $this->cancelled_at = now();
        
        // Keep the subscription active until the end_date (already paid period)
        // Don't change status immediately - let them use what they paid for
        if ($this->end_date && $this->end_date->isFuture()) {
            // User can continue using until end_date
            // Status remains ACTIVE but with cancelled_at set
            // A scheduled job should change status to CANCELLED after end_date
        } else {
            // If end_date has already passed, cancel immediately
            $this->status = SubscriptionStatus::CANCELLED;
        }
        
        $this->save();
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            SubscriptionStatus::ACTIVE->value,
            SubscriptionStatus::GRACE_PERIOD->value,
        ])->where(function ($q) {
            // Include subscriptions that are cancelled but still within paid period
            $q->whereNull('cancelled_at')
              ->orWhere('end_date', '>', now());
        });
    }


    public static function current(): ?self
    {
        return self::active()
            ->latest('created_at')
            ->first();
    }
    
    public function shouldExpire(): bool
    {
        // Check if subscription should be marked as expired
        return $this->status === SubscriptionStatus::ACTIVE 
            && $this->cancelled_at 
            && $this->end_date 
            && $this->end_date->isPast();
    }
    
    public function expire(): void
    {
        $this->status = SubscriptionStatus::CANCELLED;
        $this->save();
    }
}
