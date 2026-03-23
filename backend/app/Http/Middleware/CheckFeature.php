<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\PlanService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeature
{
    public function __construct(
        protected PlanService $planService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Usage in routes: ->middleware('check.feature:chatbot')
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if (!$tenant) {
            return $next($request);
        }

        if (!$this->planService->hasFeature($tenant, $feature)) {
            $featureNames = [
                'ai_classification' => 'Clasificación IA',
                'ai_suggestions' => 'Sugerencias IA',
                'chatbot' => 'Chatbot',
                'custom_domain' => 'Dominio personalizado',
                'sla_policies' => 'Políticas SLA',
                'service_catalog' => 'Catálogo de servicios',
                'api_access' => 'Acceso API',
            ];

            $featureName = $featureNames[$feature] ?? $feature;

            return response()->json([
                'message' => "La función \"{$featureName}\" no está disponible en tu plan actual. Actualiza tu plan para acceder a esta función.",
                'error' => 'feature_not_available',
                'feature' => $feature,
                'upgrade_url' => '/settings/billing',
            ], 403);
        }

        return $next($request);
    }
}
