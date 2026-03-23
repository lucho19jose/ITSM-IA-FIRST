<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;

class PlanService
{
    /**
     * Get the effective plan limits for a tenant.
     * Merges config defaults with any custom overrides stored on the tenant.
     */
    public function getPlanLimits(Tenant $tenant): array
    {
        $plan = $tenant->plan ?? 'free';
        $defaults = config("plans.tiers.{$plan}", config('plans.tiers.free'));

        // Allow per-tenant overrides stored in plan_limits JSON column
        $overrides = $tenant->plan_limits ?? [];

        return array_merge($defaults, $overrides);
    }

    /**
     * Check if the tenant can add another agent (admin or agent role).
     */
    public function canAddAgent(Tenant $tenant): bool
    {
        $limits = $this->getPlanLimits($tenant);
        $maxAgents = $tenant->max_agents ?? $limits['max_agents'];

        // -1 means unlimited
        if ($maxAgents === -1) {
            return true;
        }

        $currentAgents = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('role', ['admin', 'agent'])
            ->count();

        return $currentAgents < $maxAgents;
    }

    /**
     * Check if the tenant can create another ticket this month.
     */
    public function canCreateTicket(Tenant $tenant): bool
    {
        $limits = $this->getPlanLimits($tenant);
        $maxTickets = $tenant->max_tickets_per_month ?? $limits['max_tickets_per_month'];

        // -1 means unlimited
        if ($maxTickets === -1) {
            return true;
        }

        $ticketsThisMonth = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        return $ticketsThisMonth < $maxTickets;
    }

    /**
     * Check if the tenant's plan includes a specific feature.
     */
    public function hasFeature(Tenant $tenant, string $feature): bool
    {
        // Check tenant-level feature overrides first
        $tenantFeatures = $tenant->features ?? [];
        if (array_key_exists($feature, $tenantFeatures)) {
            return (bool) $tenantFeatures[$feature];
        }

        $limits = $this->getPlanLimits($tenant);
        $features = $limits['features'] ?? [];

        return !empty($features[$feature]);
    }

    /**
     * Get current usage stats for the tenant.
     */
    public function getUsage(Tenant $tenant): array
    {
        $limits = $this->getPlanLimits($tenant);

        $currentAgents = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('role', ['admin', 'agent'])
            ->count();

        $ticketsThisMonth = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        // Calculate storage usage from ticket attachments (in MB)
        $storageMb = (int) ceil(
            \App\Models\TicketAttachment::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->sum('size') / (1024 * 1024)
        );

        $maxAgents = $tenant->max_agents ?? $limits['max_agents'];
        $maxTickets = $tenant->max_tickets_per_month ?? $limits['max_tickets_per_month'];
        $maxStorage = $tenant->max_storage_mb ?? $limits['max_storage_mb'];

        return [
            'plan' => $tenant->plan ?? 'free',
            'plan_expires_at' => $tenant->plan_expires_at?->toISOString(),
            'agents' => [
                'current' => $currentAgents,
                'max' => $maxAgents,
                'percentage' => $maxAgents > 0 ? round(($currentAgents / $maxAgents) * 100) : 0,
            ],
            'tickets_this_month' => [
                'current' => $ticketsThisMonth,
                'max' => $maxTickets,
                'percentage' => $maxTickets > 0 ? round(($ticketsThisMonth / $maxTickets) * 100) : 0,
            ],
            'storage_mb' => [
                'current' => $storageMb,
                'max' => $maxStorage,
                'percentage' => $maxStorage > 0 ? round(($storageMb / $maxStorage) * 100) : 0,
            ],
            'features' => $limits['features'] ?? [],
        ];
    }

    /**
     * Check if the tenant is within all plan limits.
     */
    public function isWithinLimits(Tenant $tenant): bool
    {
        // Check plan expiration
        if ($tenant->plan_expires_at && $tenant->plan_expires_at->isPast()) {
            return false;
        }

        return $this->canAddAgent($tenant) || $this->agentCountWithinLimit($tenant);
    }

    /**
     * Helper: check if current agent count is at or below the limit (not necessarily room for more).
     */
    private function agentCountWithinLimit(Tenant $tenant): bool
    {
        $limits = $this->getPlanLimits($tenant);
        $maxAgents = $tenant->max_agents ?? $limits['max_agents'];

        if ($maxAgents === -1) {
            return true;
        }

        $currentAgents = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('role', ['admin', 'agent'])
            ->count();

        return $currentAgents <= $maxAgents;
    }
}
