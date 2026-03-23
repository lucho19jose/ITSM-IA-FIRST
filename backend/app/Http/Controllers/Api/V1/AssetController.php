<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\AssetRelationship;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    public function __construct(
        private AssetService $assetService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Asset::with(['assetType', 'assignee', 'department']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('asset_tag', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('vendor', 'like', "%{$search}%")
                    ->orWhere('manufacturer', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        if ($request->filled('asset_type_id')) {
            $query->where('asset_type_id', $request->asset_type_id);
        }
        if ($request->filled('status')) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }
        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        $sortField = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['name', 'asset_tag', 'status', 'created_at', 'updated_at', 'purchase_date', 'warranty_expiry'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->input('per_page', 15), 100);

        return AssetResource::collection($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_type_id' => 'required|exists:asset_types,id',
            'name' => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,maintenance,retired,lost,disposed',
            'condition' => 'nullable|in:new,good,fair,poor,broken',
            'assigned_to' => 'nullable|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'location' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'vendor' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'ip_address' => 'nullable|string|max:45',
            'mac_address' => 'nullable|string|max:17',
            'custom_fields' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $tenant = Tenant::find(app('tenant_id'));
        $validated['asset_tag'] = $this->assetService->generateAssetTag($tenant);

        $asset = Asset::create($validated);
        $asset->load(['assetType', 'assignee', 'department']);

        $this->assetService->logChange($asset, $request->user(), 'created');

        return response()->json([
            'data' => new AssetResource($asset),
        ], 201);
    }

    public function show(Asset $asset): JsonResponse
    {
        $asset->load(['assetType', 'assignee', 'department', 'tickets.requester', 'tickets.assignee']);

        return response()->json([
            'data' => new AssetResource($asset),
        ]);
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $validated = $request->validate([
            'asset_type_id' => 'sometimes|exists:asset_types,id',
            'name' => 'sometimes|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,maintenance,retired,lost,disposed',
            'condition' => 'nullable|in:new,good,fair,poor,broken',
            'assigned_to' => 'nullable|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'location' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'vendor' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'ip_address' => 'nullable|string|max:45',
            'mac_address' => 'nullable|string|max:17',
            'custom_fields' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $oldValues = $asset->only(array_keys($validated));

        $asset->update($validated);
        $asset->load(['assetType', 'assignee', 'department']);

        $changedFields = [];
        foreach ($validated as $key => $value) {
            $oldVal = $oldValues[$key] ?? null;
            if ($oldVal instanceof \DateTimeInterface) {
                $oldVal = $oldVal->format('Y-m-d');
            }
            if ((string) $oldVal !== (string) $value) {
                $changedFields[$key] = ['old' => $oldVal, 'new' => $value];
            }
        }

        if (!empty($changedFields)) {
            $action = isset($changedFields['status']) ? 'status_changed' : 'updated';
            $this->assetService->logChange($asset, $request->user(), $action, $oldValues, $validated);
        }

        return response()->json([
            'data' => new AssetResource($asset),
        ]);
    }

    public function destroy(Asset $asset): JsonResponse
    {
        $asset->delete();

        return response()->json(['message' => 'Activo eliminado']);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $this->assetService->assignToUser($asset, $user, $request->user());

        $asset->load(['assetType', 'assignee', 'department']);

        return response()->json([
            'data' => new AssetResource($asset),
            'message' => 'Activo asignado correctamente',
        ]);
    }

    public function unassign(Request $request, int $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);

        if (!$asset->assigned_to) {
            return response()->json(['message' => 'El activo no está asignado'], 422);
        }

        $this->assetService->unassign($asset, $request->user());

        $asset->load(['assetType', 'assignee', 'department']);

        return response()->json([
            'data' => new AssetResource($asset),
            'message' => 'Activo desasignado correctamente',
        ]);
    }

    public function linkTicket(Request $request, int $id, int $ticketId): JsonResponse
    {
        $asset = Asset::findOrFail($id);
        $ticket = Ticket::findOrFail($ticketId);

        if ($asset->tickets()->where('ticket_id', $ticketId)->exists()) {
            return response()->json(['message' => 'El ticket ya está vinculado'], 422);
        }

        $asset->tickets()->attach($ticketId);

        $this->assetService->logChange(
            $asset,
            $request->user(),
            'ticket_linked',
            null,
            ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number]
        );

        return response()->json(['message' => 'Ticket vinculado correctamente']);
    }

    public function unlinkTicket(Request $request, int $id, int $ticketId): JsonResponse
    {
        $asset = Asset::findOrFail($id);
        $ticket = Ticket::findOrFail($ticketId);

        $asset->tickets()->detach($ticketId);

        $this->assetService->logChange(
            $asset,
            $request->user(),
            'ticket_unlinked',
            ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number],
            null
        );

        return response()->json(['message' => 'Ticket desvinculado correctamente']);
    }

    public function relationships(int $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);

        $outgoing = $asset->outgoingRelationships()
            ->with('targetAsset:id,name,asset_tag,status')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'direction' => 'outgoing',
                'relationship_type' => $r->relationship_type,
                'related_asset' => [
                    'id' => $r->targetAsset->id,
                    'name' => $r->targetAsset->name,
                    'asset_tag' => $r->targetAsset->asset_tag,
                    'status' => $r->targetAsset->status,
                ],
                'created_at' => $r->created_at?->toISOString(),
            ]);

        $incoming = $asset->incomingRelationships()
            ->with('sourceAsset:id,name,asset_tag,status')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'direction' => 'incoming',
                'relationship_type' => $r->relationship_type,
                'related_asset' => [
                    'id' => $r->sourceAsset->id,
                    'name' => $r->sourceAsset->name,
                    'asset_tag' => $r->sourceAsset->asset_tag,
                    'status' => $r->sourceAsset->status,
                ],
                'created_at' => $r->created_at?->toISOString(),
            ]);

        return response()->json([
            'data' => $outgoing->merge($incoming)->values(),
        ]);
    }

    public function addRelationship(Request $request, int $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);

        $validated = $request->validate([
            'target_asset_id' => 'required|exists:assets,id',
            'relationship_type' => 'required|in:contains,depends_on,connected_to,installed_on,runs_on',
        ]);

        if ($validated['target_asset_id'] == $id) {
            return response()->json(['message' => 'No se puede crear una relación consigo mismo'], 422);
        }

        $existing = AssetRelationship::where('source_asset_id', $id)
            ->where('target_asset_id', $validated['target_asset_id'])
            ->where('relationship_type', $validated['relationship_type'])
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Esta relación ya existe'], 422);
        }

        $relationship = AssetRelationship::create([
            'tenant_id' => $asset->tenant_id,
            'source_asset_id' => $id,
            'target_asset_id' => $validated['target_asset_id'],
            'relationship_type' => $validated['relationship_type'],
        ]);

        $targetAsset = Asset::find($validated['target_asset_id']);
        $this->assetService->logChange(
            $asset,
            $request->user(),
            'relationship_added',
            null,
            ['target_asset' => $targetAsset->name, 'type' => $validated['relationship_type']]
        );

        return response()->json([
            'data' => $relationship,
            'message' => 'Relación creada correctamente',
        ], 201);
    }

    public function removeRelationship(Request $request, int $id, int $relationshipId): JsonResponse
    {
        $asset = Asset::findOrFail($id);

        $relationship = AssetRelationship::where(function ($q) use ($id) {
            $q->where('source_asset_id', $id)->orWhere('target_asset_id', $id);
        })->findOrFail($relationshipId);

        $relationship->delete();

        $this->assetService->logChange(
            $asset,
            $request->user(),
            'relationship_removed',
            ['relationship_type' => $relationship->relationship_type],
            null
        );

        return response()->json(['message' => 'Relación eliminada correctamente']);
    }

    public function timeline(int $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);
        $logs = $this->assetService->getAssetTimeline($asset);

        return response()->json([
            'data' => $logs->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'avatar_url' => $log->user->avatar_url,
                ] : null,
                'created_at' => $log->created_at?->toISOString(),
            ]),
        ]);
    }

    public function dashboard(): JsonResponse
    {
        $tenant = Tenant::find(app('tenant_id'));
        $stats = $this->assetService->getDashboardStats($tenant);

        return response()->json(['data' => $stats]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Asset::with(['assetType', 'assignee', 'department']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('asset_type_id')) {
            $query->where('asset_type_id', $request->asset_type_id);
        }

        $assets = $query->orderBy('asset_tag')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="assets_export_' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($assets) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for UTF-8

            fputcsv($handle, [
                'Asset Tag', 'Nombre', 'Tipo', 'Estado', 'Condición',
                'Asignado a', 'Departamento', 'Ubicación', 'No. Serie',
                'Fabricante', 'Modelo', 'IP', 'MAC',
                'Fecha Compra', 'Costo', 'Vencimiento Garantía', 'Proveedor',
            ]);

            foreach ($assets as $asset) {
                fputcsv($handle, [
                    $asset->asset_tag,
                    $asset->name,
                    $asset->assetType?->name,
                    $asset->status,
                    $asset->condition,
                    $asset->assignee?->name,
                    $asset->department?->name,
                    $asset->location,
                    $asset->serial_number,
                    $asset->manufacturer,
                    $asset->model,
                    $asset->ip_address,
                    $asset->mac_address,
                    $asset->purchase_date?->toDateString(),
                    $asset->purchase_cost,
                    $asset->warranty_expiry?->toDateString(),
                    $asset->vendor,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function nextTag(): JsonResponse
    {
        $tenant = Tenant::find(app('tenant_id'));
        $tag = $this->assetService->generateAssetTag($tenant);

        return response()->json(['data' => ['asset_tag' => $tag]]);
    }
}
