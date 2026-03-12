<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            if ($user->tenant_id) {
                // If subdomain already set a tenant, verify user belongs to it
                if (app()->bound('tenant_id') && app('tenant_id') !== null) {
                    if ($user->tenant_id !== app('tenant_id')) {
                        return response()->json([
                            'message' => 'No tienes acceso a esta empresa',
                        ], 403);
                    }
                } else {
                    app()->instance('tenant_id', $user->tenant_id);
                }
            }
        }
        return $next($request);
    }
}
