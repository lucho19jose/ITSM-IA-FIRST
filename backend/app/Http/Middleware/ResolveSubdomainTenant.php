<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveSubdomainTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $appDomain = config('app.domain', 'autoservice.test');

        // 1. Check custom domain first
        $tenant = Tenant::where('custom_domain', $host)->where('is_active', true)->first();

        if ($tenant) {
            app()->instance('tenant_id', $tenant->id);
            app()->instance('tenant', $tenant);
            return $next($request);
        }

        // 2. Check subdomain
        $subdomain = str_replace('.' . $appDomain, '', $host);

        if ($subdomain === $host || $subdomain === 'www' || $subdomain === '') {
            return $next($request);
        }

        $tenant = Tenant::where('slug', $subdomain)->where('is_active', true)->first();

        if (!$tenant) {
            return response()->json([
                'message' => 'Empresa no encontrada',
            ], 404);
        }

        app()->instance('tenant_id', $tenant->id);
        app()->instance('tenant', $tenant);

        return $next($request);
    }
}
