<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CurrencyExchangeService
{
    private const CACHE_KEY = 'usd_to_ars_rate';

    private const CACHE_DURATION = 3600; // 1 hour in seconds

    private const DEFAULT_RATE = 1500;

    /**
     * Get the current USD to ARS exchange rate
     */
    public function getUsdToArsRate(): float
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_DURATION, function () {
            // TODO: In the future, fetch from a real API like:
            // - https://api.exchangerate-api.com/v4/latest/USD
            // - https://api.bluelytics.com.ar/v2/latest
            // - https://dolarapi.com/v1/dolares/oficial

            // For now, return hardcoded value
            return self::DEFAULT_RATE;
        });
    }

    /**
     * Convert USD to ARS
     */
    public function convertUsdToArs(float $usdAmount): float
    {
        return $usdAmount * $this->getUsdToArsRate();
    }

    /**
     * Convert ARS to USD
     */
    public function convertArsToUsd(float $arsAmount): float
    {
        $rate = $this->getUsdToArsRate();
        if ($rate == 0) {
            return 0;
        }

        return $arsAmount / $rate;
    }

    /**
     * Format price in ARS with proper formatting
     */
    public function formatArs(float $amount): string
    {
        return '$'.number_format($amount, 0, ',', '.');
    }

    /**
     * Get subscription prices in ARS
     */
    public function getSubscriptionPriceInArs(float $usdPrice): array
    {
        $arsPrice = $this->convertUsdToArs($usdPrice);

        return [
            'usd' => $usdPrice,
            'ars' => round($arsPrice, 2),
            'formatted_usd' => '$'.number_format($usdPrice, 2),
            'formatted_ars' => $this->formatArs($arsPrice),
            'exchange_rate' => $this->getUsdToArsRate(),
        ];
    }

    /**
     * Force refresh the exchange rate cache
     */
    public function refreshRate(): float
    {
        Cache::forget(self::CACHE_KEY);

        return $this->getUsdToArsRate();
    }

    /**
     * Future implementation: Fetch from real API
     */
    private function fetchFromApi(): ?float
    {
        // Example implementation for future use:
        /*
        try {
            // Option 1: Blue Dollar API (Argentina specific)
            $response = Http::timeout(5)->get('https://api.bluelytics.com.ar/v2/latest');
            if ($response->successful()) {
                return $response->json()['blue']['value_sell'] ?? null;
            }

            // Option 2: DolarAPI
            $response = Http::timeout(5)->get('https://dolarapi.com/v1/dolares/blue');
            if ($response->successful()) {
                return $response->json()['venta'] ?? null;
            }

            // Option 3: Generic exchange rate API
            $response = Http::timeout(5)->get('https://api.exchangerate-api.com/v4/latest/USD');
            if ($response->successful()) {
                return $response->json()['rates']['ARS'] ?? null;
            }
        } catch (\Exception $e) {
            \Log::error('Failed to fetch USD to ARS rate: ' . $e->getMessage());
        }
        */

        return null;
    }
}
