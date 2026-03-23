<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
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

                // Check if tenant is active — super_admins bypass this check
                if ($user->role !== 'super_admin') {
                    $tenant = Tenant::find($user->tenant_id);

                    if (!$tenant) {
                        return response()->json([
                            'message' => 'Empresa no encontrada.',
                            'error_code' => 'tenant_not_found',
                        ], 403);
                    }

                    if (!$tenant->is_active) {
                        $reason = $tenant->suspension_reason;
                        $suspendedAt = $tenant->suspended_at;

                        return response()->json([
                            'message' => 'Cuenta suspendida. Tu empresa ha sido desactivada. Contacta al administrador o reactiva tu suscripción para restaurar el acceso.',
                            'error_code' => 'tenant_suspended',
                            'suspended_at' => $suspendedAt?->toIso8601String(),
                            'suspension_reason' => $reason,
                        ], 403);
                    }
                }
            }
        }
        return $next($request);
    }
}
