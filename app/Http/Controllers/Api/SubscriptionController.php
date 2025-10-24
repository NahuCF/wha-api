<?php

namespace App\Http\Controllers\Api;

use App\Enums\BillingCycle;
use App\Enums\PlanType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private MercadoPagoService $mercadoPagoService
    ) {}

    public function generateMercadoPagoLink(CreateSubscriptionRequest $request): JsonResponse
    {
        try {
            $tenantId = tenant()->id;
            $planType = PlanType::from($request->plan_type);
            $billingCycle = BillingCycle::from($request->billing_cycle);
            $extraUsers = data_get($request, 'extra_users', 0);
            
            // Validate extra users for Growth plan
            if (!$planType->allowsExtraUsers() && $extraUsers > 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'The Growth plan does not allow extra users',
                ], 422);
            }

            $link = $this->mercadoPagoService->createSubscriptionLink(
                $tenantId,
                $planType,
                $billingCycle,
                $extraUsers
            );

            return response()->json([
                'data' => [
                    'link' => $link
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate subscription link',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function current(): JsonResponse
    {
        $subscription = Subscription::current();

        if (! $subscription) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new SubscriptionResource($subscription),
        ]);
    }

    public function cancel(): JsonResponse
    {
        $subscription = Subscription::current();

        if (! $subscription) {
            return response()->json([
                'success' => false,
                'error' => 'No active subscription found',
            ], 404);
        }

        $subscription->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled successfully',
            'data' => new SubscriptionResource($subscription),
        ]);
    }

    public function purchaseExtraUsers(Request $request): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $subscription = Subscription::current();

        if (! $subscription) {
            return response()->json([
                'success' => false,
                'error' => 'No active subscription found',
            ], 404);
        }

        $subscription->extra_users_purchased += $request->quantity;
        $subscription->save();

        return response()->json([
            'success' => true,
            'message' => 'Extra users purchased successfully',
            'data' => new SubscriptionResource($subscription),
        ]);
    }

}
