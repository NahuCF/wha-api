<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan_type' => $this->plan_type->value,
            'plan_label' => $this->plan_type->label(),
            'billing_cycle' => $this->billing_cycle->value,
            'billing_cycle_label' => $this->billing_cycle->label(),
            'payment_provider' => $this->payment_provider->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_active' => $this->isActive(),
            'is_in_grace_period' => $this->isInGracePeriod(),
            'limits' => [
                'max_users' => $this->max_users,
                'used_users' => $this->used_users,
                'remaining_users' => $this->getRemainingUsers(),
                'extra_users_purchased' => $this->extra_users_purchased,
                'max_contacts' => $this->max_contacts,
                'used_contacts' => $this->used_contacts,
                'remaining_contacts' => $this->getRemainingContacts(),
                'max_whatsapp_numbers' => $this->max_whatsapp_numbers,
                'used_whatsapp_numbers' => $this->used_whatsapp_numbers,
                'remaining_whatsapp_numbers' => $this->getRemainingWhatsappNumbers(),
            ],
            'pricing' => [
                'amount' => $this->amount,
                'currency' => $this->currency,
                'monthly_price' => $this->plan_type->monthlyPrice(),
                'yearly_price' => $this->plan_type->yearlyPrice(),
            ],
            'dates' => [
                'start_date' => $this->start_date->toIso8601String(),
                'end_date' => $this->end_date->toIso8601String(),
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
                'grace_period_ends_at' => $this->grace_period_ends_at?->toIso8601String(),
                'created_at' => $this->created_at->toIso8601String(),
                'updated_at' => $this->updated_at->toIso8601String(),
            ],
            'features' => $this->plan_type->features(),
            'provider_subscription_id' => $this->provider_subscription_id,
            'metadata' => $this->metadata,
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
