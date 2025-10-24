<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MercadoPagoWebhookController extends Controller
{
    public function __construct(
        private MercadoPagoService $mercadoPagoService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            // Get MercadoPago webhook headers
            $xSignature = $request->header('x-signature');
            $xRequestId = $request->header('x-request-id');
            
            if (!$xSignature || !$xRequestId) {
                return response()->json(['error' => 'Missing required headers'], 401);
            }

            // Verify webhook signature
            if (!$this->mercadoPagoService->verifyWebhookSignature($xSignature, $xRequestId, $request->all())) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Process the webhook
            $this->mercadoPagoService->handleWebhook($request->all());

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            // Return success to avoid retries from MercadoPago
            return response()->json(['success' => false], 200);
        }
    }
}