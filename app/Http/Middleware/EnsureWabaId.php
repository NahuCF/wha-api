<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWabaId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headerName = 'X-waba-id';
        $wabaId = $request->header($headerName);

        if (! $wabaId) {
            return response()->json([
                'message' => 'Missing header '.$headerName,
            ], 400);
        }

        $request->merge(['waba_id' => $wabaId]);

        app()->instance('waba_id', $wabaId);

        return $next($request);
    }
}
