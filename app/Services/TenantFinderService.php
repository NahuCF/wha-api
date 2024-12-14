<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class TenantFinderService extends TenantFinder
{
    public function findForRequest(Request $request): ?IsTenant
    {
        $tenantId = $request->header('X-Tenant');

        if (! $tenantId) {
            return null;
        }

        $tenant = Tenant::find($tenantId);

        return $tenant instanceof IsTenant ? $tenant : null;
    }
}
