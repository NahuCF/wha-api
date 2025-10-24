<?php

namespace App\Services;

use App\Enums\PlanType;
use App\Models\Payment;
use App\Enums\BillingCycle;
use App\Enums\PaymentStatus;
use App\Models\Subscription;
use App\Enums\PaymentProvider;
use App\Enums\SubscriptionStatus;
use Illuminate\Support\Facades\Cache;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\PreApproval\PreApprovalClient;
use MercadoPago\Client\PreApprovalPlan\PreApprovalPlanClient;

class MercadoPagoService
{
    private CurrencyExchangeService $currencyService;

    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
        $this->currencyService = new CurrencyExchangeService;
    }
    public function createSubscriptionLink(
        string $tenantId,
        PlanType $planType,
        BillingCycle $billingCycle,
        int $extraUsers = 0
    ): string {
        try {
            
            $basePriceUsd = $billingCycle === BillingCycle::MONTHLY
                ? $planType->monthlyPrice()
                : $planType->yearlyPrice();

            // Calculate price with extra users in USD using plan-specific pricing
            $extraUserPriceUsd = $extraUsers * $planType->extraUserPrice();
            $totalPriceUsd = $basePriceUsd + $extraUserPriceUsd;

            // Create or get the preapproved plan and return its init_point
            $plan = $this->createOrGetPlan($planType, $billingCycle, $extraUsers, $totalPriceUsd);

            // Return only the subscription link
            return $plan->init_point;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function createOrGetPlan(PlanType $planType, BillingCycle $billingCycle, int $extraUsers, float $totalPriceUsd): object
    {
        // Generate cache key based on plan configuration only (not price)
        $cacheKey = $this->generatePlanCacheKey($planType, $billingCycle, $extraUsers);
        
        // Convert total price to ARS for MercadoPago
        $priceArs = $this->currencyService->convertUsdToArs($totalPriceUsd);
        
        $cachedPlan = Cache::get($cacheKey);
        if ($cachedPlan) {
            try {
                $planClient = new PreApprovalPlanClient;
                $plan = $planClient->get($cachedPlan['id']);
                if ($plan && $plan->status === 'active') {
                    return (object) $cachedPlan;
                }
            } catch (\Exception $e) {
                Cache::forget($cacheKey);
            }
        }
        
        $planClient = new PreApprovalPlanClient;

        $appName = config('app.name', 'WHA-API');
        $planDescription = $extraUsers > 0 
            ? "{$planType->label()} {$billingCycle->label()} (+{$extraUsers} users)"
            : "{$planType->label()} {$billingCycle->label()}";
            
        $planData = [
            'reason' => "{$appName} - {$planDescription}",
            'auto_recurring' => [
                'frequency' => $billingCycle->months(),
                'frequency_type' => 'months',
                'transaction_amount' => $priceArs,
                'currency_id' => 'ARS',
            ],
            'back_url' => rtrim(config('app.client_url'), '/').'/subscription/success',
            'external_reference' => json_encode([
                'tenant_id' => tenant('id'),
                'plan_type' => $planType->value,
                'billing_cycle' => $billingCycle->value,
                'extra_users' => $extraUsers,
                'price_usd' => $totalPriceUsd,
            ]),
        ];

        try {
            $plan = $planClient->create($planData);
            
            $planToCache = [
                'id' => $plan->id,
                'init_point' => $plan->init_point,
                'status' => $plan->status,
            ];
            Cache::put($cacheKey, $planToCache, now()->addHours(24));
            
            return $plan;
        } catch (MPApiException $e) {
            throw new \Exception('Failed to create MercadoPago plan: '.$e->getMessage());
        }
    }
    
    private function generatePlanCacheKey(PlanType $planType, BillingCycle $billingCycle, int $extraUsers): string
    {
        return sprintf(
            'mercadopago_plan:%s:%s:%d',
            $planType->value,
            $billingCycle->value,
            $extraUsers
        );
    }





    private function getCurrentUserCount(): int
    {
        return \App\Models\User::count();
    }

    private function getCurrentContactCount(): int
    {
        return \App\Models\Contact::count();
    }

    private function getCurrentWhatsappNumberCount(): int
    {
        return \App\Models\PhoneNumber::count();
    }

    public function handleWebhook(array $data): void
    {
        $topic = $data['topic'] ?? $data['type'] ?? null;
        $resourceId = $data['data']['id'] ?? $data['resource'] ?? null;

        if (!$topic || !$resourceId) {
            return;
        }

        match ($topic) {
            'subscription_preapproval' => $this->processSubscriptionPreapproval($resourceId),
            'subscription_authorized_payment' => $this->processSubscriptionPayment($resourceId),
            'payment' => $this->processPaymentWebhook($resourceId),
            default => null,
        };
    }

    public function verifyWebhookSignature(string $xSignature, string $xRequestId, array $data): bool
    {
        $secret = config('services.mercadopago.webhook_secret');
        
        if (!$secret) {
            return false;
        }

        // MercadoPago signature format: ts=timestamp,v1=signature
        $parts = explode(',', $xSignature);
        $timestamp = null;
        $signature = null;

        foreach ($parts as $part) {
            $keyValue = explode('=', $part, 2);
            if (count($keyValue) === 2) {
                if ($keyValue[0] === 'ts') {
                    $timestamp = $keyValue[1];
                } elseif ($keyValue[0] === 'v1') {
                    $signature = $keyValue[1];
                }
            }
        }

        if (!$timestamp || !$signature) {
            return false;
        }

        // Build the signed payload
        $manifest = "id:{$data['data']['id']};request-id:{$xRequestId};ts:{$timestamp};";
        
        // Generate the expected signature
        $expectedSignature = hash_hmac('sha256', $manifest, $secret);

        // Compare signatures
        $isValid = hash_equals($expectedSignature, $signature);


        return $isValid;
    }

    private function processSubscriptionPreapproval(string $preapprovalId): void
    {
        $client = new PreApprovalClient;

        try {
            $preapproval = $client->get($preapprovalId);
        } catch (MPApiException $e) {
            return;
        }

        $externalReference = $preapproval->external_reference ?? null;
        if (!$externalReference) {
            return;
        }

        $metadata = json_decode($externalReference, true);
        if (!$metadata) {
            return;
        }

        $tenantId = $metadata['tenant_id'] ?? null;
        if (!$tenantId) {
            return;
        }

        // Initialize tenant context
        $tenant = \App\Models\Tenant::find($tenantId);
        if (!$tenant) {
            return;
        }
        
        tenancy()->initialize($tenant);

        // Check if subscription already exists for this preapproval
        $existingSubscription = Subscription::where('provider_subscription_id', $preapprovalId)->first();
        if ($existingSubscription) {
            // Update subscription status if needed
            $newStatus = $this->mapPreapprovalStatus($preapproval->status);
            if ($existingSubscription->status !== $newStatus) {
                $existingSubscription->update(['status' => $newStatus]);
            }
            return;
        }

        // Cancel any existing active subscription
        $activeSubscription = Subscription::current();
        if ($activeSubscription) {
            $activeSubscription->cancel();
        }

        // Create new subscription
        $planType = PlanType::from($metadata['plan_type']);
        $billingCycle = BillingCycle::from($metadata['billing_cycle']);
        $extraUsers = $metadata['extra_users'] ?? 0;
        $priceUsd = $metadata['price_usd'] ?? 0;

        $startDate = now();
        $endDate = $billingCycle === BillingCycle::MONTHLY
            ? $startDate->copy()->addMonth()
            : $startDate->copy()->addYear();

        $subscription = Subscription::create([
            'tenant_id' => $tenantId,
            'plan_type' => $planType,
            'billing_cycle' => $billingCycle,
            'payment_provider' => PaymentProvider::MERCADOPAGO,
            'status' => $this->mapPreapprovalStatus($preapproval->status),
            'provider_subscription_id' => $preapprovalId,
            'max_users' => $planType->maxUsers(),
            'used_users' => $this->getCurrentUserCount(),
            'max_contacts' => $planType->maxContacts(),
            'used_contacts' => $this->getCurrentContactCount(),
            'max_whatsapp_numbers' => $planType->maxWhatsappNumbers(),
            'used_whatsapp_numbers' => $this->getCurrentWhatsappNumberCount(),
            'extra_users_purchased' => $extraUsers,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'amount' => $priceUsd,
            'currency' => 'USD',
            'metadata' => [
                'features' => $planType->features(),
                'base_price' => $priceUsd - ($extraUsers * $planType->extraUserPrice()),
                'extra_users_price' => $extraUsers * $planType->extraUserPrice(),
                'mercadopago_data' => (array) $preapproval,
            ],
        ]);
    }

    private function processSubscriptionPayment(string $paymentId): void
    {
        $paymentClient = new PaymentClient;

        try {
            $paymentData = $paymentClient->get($paymentId);
        } catch (MPApiException $e) {
            return;
        }

        // Get subscription ID from payment metadata
        $preapprovalId = $paymentData->metadata->preapproval_id ?? null;
        if (!$preapprovalId) {
            return;
        }

        // Find subscription by preapproval ID
        $subscription = Subscription::where('provider_subscription_id', $preapprovalId)->first();
        if (!$subscription) {
            return;
        }

        // Initialize tenant context
        $tenant = \App\Models\Tenant::find($subscription->tenant_id);
        if ($tenant) {
            tenancy()->initialize($tenant);
        }

        // Check if payment already exists
        $existingPayment = Payment::where('provider_payment_id', $paymentId)->first();
        if ($existingPayment) {
            // Update payment status
            $newStatus = PaymentStatus::fromMercadoPagoStatus($paymentData->status ?? 'pending');
            if ($existingPayment->status !== $newStatus) {
                $existingPayment->update([
                    'status' => $newStatus,
                    'provider_response' => (array) $paymentData,
                    'paid_at' => $newStatus->isSuccessful() ? now() : null,
                ]);
            }
            return;
        }

        // Create new payment record
        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'payment_provider' => PaymentProvider::MERCADOPAGO,
            'amount' => $paymentData->transaction_amount,
            'currency' => $paymentData->currency_id,
            'status' => PaymentStatus::fromMercadoPagoStatus($paymentData->status ?? 'pending'),
            'provider_payment_id' => $paymentId,
            'paid_at' => PaymentStatus::fromMercadoPagoStatus($paymentData->status ?? 'pending')->isSuccessful() ? now() : null,
            'provider_response' => (array) $paymentData,
        ]);

        // Update subscription status if this is the first payment
        if ($payment->status->isSuccessful() && $subscription->status === SubscriptionStatus::PENDING) {
            $subscription->update(['status' => SubscriptionStatus::ACTIVE]);
            
            // Update subscription end date for recurring payments
            if ($subscription->payments()->where('status', PaymentStatus::COMPLETED)->count() > 1) {
                $newEndDate = $subscription->billing_cycle === BillingCycle::MONTHLY
                    ? $subscription->end_date->addMonth()
                    : $subscription->end_date->addYear();
                
                $subscription->update(['end_date' => $newEndDate]);
            }
        }
    }

    private function mapPreapprovalStatus(string $status): SubscriptionStatus
    {
        return match ($status) {
            'authorized', 'active' => SubscriptionStatus::ACTIVE,
            'paused' => SubscriptionStatus::SUSPENDED,
            'cancelled' => SubscriptionStatus::CANCELLED,
            'pending' => SubscriptionStatus::PENDING,
            default => SubscriptionStatus::PENDING,
        };
    }

    private function processPaymentWebhook(string $paymentId): void
    {
        $paymentClient = new PaymentClient;

        try {
            $paymentData = $paymentClient->get($paymentId);
        } catch (MPApiException $e) {
            return;
        }

        $externalReference = $paymentData->external_reference ?? null;

        if (! $externalReference) {
            return;
        }

        $subscription = Subscription::find($externalReference);

        if (! $subscription) {
            return;
        }

        $payment = Payment::where('subscription_id', $subscription->id)
            ->where('payment_provider', PaymentProvider::MERCADOPAGO)
            ->whereIn('status', [
                PaymentStatus::MERCADOPAGO_PENDING->value,
                PaymentStatus::MERCADOPAGO_IN_PROCESS->value,
                PaymentStatus::MERCADOPAGO_AUTHORIZED->value,
            ])
            ->first();

        if (! $payment) {
            $payment = Payment::create([
                'subscription_id' => $subscription->id,
                'payment_provider' => PaymentProvider::MERCADOPAGO,
                'amount' => $paymentData->transaction_amount,
                'currency' => $paymentData->currency_id,
                'status' => PaymentStatus::fromMercadoPagoStatus($paymentData->status ?? 'pending'),
                'provider_payment_id' => $paymentId,
                'metadata' => (array) $paymentData,
            ]);
        }

        $newStatus = PaymentStatus::fromMercadoPagoStatus($paymentData->status ?? 'pending');

        if ($payment->status !== $newStatus) {
            $payment->status = $newStatus;
            $payment->provider_response = (array) $paymentData;

            if ($newStatus->isSuccessful()) {
                $payment->paid_at = now();

                if ($subscription->status === SubscriptionStatus::PENDING) {
                    $subscription->update(['status' => SubscriptionStatus::ACTIVE]);
                }
            }

            $payment->save();
        }
    }


    public function cancelSubscription(string $subscriptionId): bool
    {
        $subscription = Subscription::find($subscriptionId);

        if (! $subscription || ! $subscription->provider_subscription_id) {
            return false;
        }

        $client = new PreApprovalClient;

        try {
            $client->update($subscription->provider_subscription_id, [
                'status' => 'cancelled',
            ]);

            $subscription->cancel();

            return true;
        } catch (MPApiException $e) {
            return false;
        }
    }

    public function getSubscriptionDetails(string $mercadoPagoSubscriptionId)
    {
        $client = new PreApprovalClient;

        try {
            return $client->get($mercadoPagoSubscriptionId);
        } catch (MPApiException $e) {
            return null;
        }
    }
}
