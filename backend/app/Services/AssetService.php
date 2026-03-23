<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

class AssetService
{
    public function generateAssetTag(Tenant $tenant): string
    {
        $lastAsset = Asset::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderByRaw("CAST(SUBSTRING(asset_tag, 5) AS UNSIGNED) DESC")
            ->first();

        if ($lastAsset && preg_match('/^AST-(\d+)$/', $lastAsset->asset_tag, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return 'AST-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    public function logChange(Asset $asset, ?User $user, string $action, ?array $old = null, ?array $new = null, ?string $description = null): void
    {
        $desc = $description ?? $this->buildDescription($action, $asset, $user, $old, $new);

        AssetLog::create([
            'tenant_id' => $asset->tenant_id,
            'asset_id' => $asset->id,
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $desc,
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }

    private function buildDescription(string $action, Asset $asset, ?User $user, ?array $old, ?array $new): string
    {
        $userName = $user?->name ?? 'Sistema';

        return match ($action) {
            'created' => "{$userName} creó el activo {$asset->name} ({$asset->asset_tag})",
            'updated' => "{$userName} actualizó el activo {$asset->name} ({$asset->asset_tag})",
            'assigned' => "{$userName} asignó el activo a " . ($new['assigned_to_name'] ?? 'un usuario'),
            'unassigned' => "{$userName} desasignó el activo de " . ($old['assigned_to_name'] ?? 'un usuario'),
            'status_changed' => "{$userName} cambió el estado a " . ($new['status'] ?? 'desconocido'),
            'maintenance' => "{$userName} marcó el activo en mantenimiento",
            'note_added' => "{$userName} agregó una nota al activo",
            'ticket_linked' => "{$userName} vinculó el activo al ticket " . ($new['ticket_number'] ?? ''),
            'ticket_unlinked' => "{$userName} desvinculó el activo del ticket " . ($old['ticket_number'] ?? ''),
            'relationship_added' => "{$userName} agregó una relación con otro activo",
            'relationship_removed' => "{$userName} eliminó una relación con otro activo",
            default => "{$userName} realizó la acción '{$action}' en el activo {$asset->name}",
        };
    }

    public function getAssetTimeline(Asset $asset): Collection
    {
        return $asset->logs()
            ->with('user:id,name,avatar_path')
            ->orderByDesc('created_at')
            ->get();
    }

    public function assignToUser(Asset $asset, User $user, User $performer): void
    {
        $oldAssigned = $asset->assigned_to;
        $oldName = $asset->assignee?->name;

        $asset->update(['assigned_to' => $user->id]);

        $this->logChange(
            $asset,
            $performer,
            'assigned',
            $oldAssigned ? ['assigned_to' => $oldAssigned, 'assigned_to_name' => $oldName] : null,
            ['assigned_to' => $user->id, 'assigned_to_name' => $user->name]
        );
    }

    public function unassign(Asset $asset, User $performer): void
    {
        $oldAssigned = $asset->assigned_to;
        $oldName = $asset->assignee?->name;

        $asset->update(['assigned_to' => null]);

        $this->logChange(
            $asset,
            $performer,
            'unassigned',
            ['assigned_to' => $oldAssigned, 'assigned_to_name' => $oldName],
            null
        );
    }

    public function getDashboardStats(Tenant $tenant): array
    {
        $assets = Asset::withoutGlobalScopes()->where('tenant_id', $tenant->id);

        $total = (clone $assets)->count();
        $byStatus = (clone $assets)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byType = Asset::withoutGlobalScopes()
            ->where('assets.tenant_id', $tenant->id)
            ->join('asset_types', 'assets.asset_type_id', '=', 'asset_types.id')
            ->selectRaw('asset_types.name as type_name, COUNT(*) as count')
            ->groupBy('asset_types.name')
            ->pluck('count', 'type_name')
            ->toArray();

        $expiringWarranties = (clone $assets)
            ->whereNotNull('warranty_expiry')
            ->where('warranty_expiry', '<=', now()->addDays(30))
            ->where('warranty_expiry', '>=', now())
            ->count();

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'by_type' => $byType,
            'expiring_warranties' => $expiringWarranties,
            'active' => $byStatus['active'] ?? 0,
            'maintenance' => $byStatus['maintenance'] ?? 0,
            'retired' => $byStatus['retired'] ?? 0,
        ];
    }
}
