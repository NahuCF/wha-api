<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'payment_provider' => $this->payment_provider->value,
            'payment_provider_label' => $this->payment_provider->label(),
            'provider_payment_id' => $this->provider_payment_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_completed' => $this->isCompleted(),
            'is_pending' => $this->isPending(),
            'is_failed' => $this->isFailed(),
            'payment_method' => $this->payment_method,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'provider_response' => $this->provider_response,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
        ];
    }
}
