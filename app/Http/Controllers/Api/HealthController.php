<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        try {
            $appResponding = true;
            
            $dbConnected = $this->checkDatabase();
            
            return response()->json([
                'status' => 'healthy',
                'timestamp' => now()->toIso8601String(),
                'app' => $appResponding,
                'database' => $dbConnected,
                'version' => config('app.version', '1.0.0'),
                'environment' => app()->environment(),
            ], 200);
        } catch (\Exception) {
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toIso8601String(),
            ], 503);
        }
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            return true;
        } catch (\Exception) {
            return false;
        }
    }

}