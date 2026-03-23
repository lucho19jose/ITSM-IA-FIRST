<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\PlanService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimits
{
    public function __construct(
        protected PlanService $planService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if (!$tenant) {
            return $next($request);
        }

        // Check plan expiration
        if ($tenant->plan_expires_at && $tenant->plan_expires_at->isPast()) {
            return response()->json([
                'message' => 'Tu plan ha expirado. Por favor actualiza tu plan para continuar.',
                'error' => 'plan_expired',
                'upgrade_url' => '/settings/billing',
            ], 403);
        }

        // Check monthly ticket limit on ticket creation routes
        if ($this->isTicketCreationRequest($request)) {
            if (!$this->planService->canCreateTicket($tenant)) {
                return response()->json([
                    'message' => 'Has alcanzado el límite mensual de tickets de tu plan. Actualiza tu plan para crear más tickets.',
                    'error' => 'ticket_limit_reached',
                    'upgrade_url' => '/settings/billing',
                ], 403);
            }
        }

        return $next($request);
    }

    private function isTicketCreationRequest(Request $request): bool
    {
        return $request->isMethod('POST')
            && (
                $request->is('api/v1/tickets')
                || $request->is('*/tickets')
            )
            && !$request->is('*/tickets/*/comments')
            && !$request->is('*/tickets/*/attachments')
            && !$request->is('*/tickets/*/assign')
            && !$request->is('*/tickets/*/close')
            && !$request->is('*/tickets/*/reopen')
            && !$request->is('*/tickets/export')
            && !$request->is('*/tickets/bulk-update');
    }
}
