<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('ruc', 'like', "%{$search}%")
                  ->orWhere('custom_domain', 'like', "%{$search}%");
            });
        }

        if ($request->filled('plan')) {
            $query->where('plan', $request->plan);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $tenants = $query->withCount('users')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($tenants);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->loadCount('users');

        $stats = [
            'users_count' => $tenant->users_count,
            'tickets_count' => Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            'open_tickets' => Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'open')->count(),
            'admin_users' => User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', 'admin')->count(),
            'agent_users' => User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', 'agent')->count(),
            'end_users' => User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', 'end_user')->count(),
        ];

        return response()->json([
            'data' => $tenant,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ruc' => 'nullable|string|size:11',
            'plan' => 'required|in:trial,basic,professional,enterprise',
            'is_active' => 'boolean',
            'trial_ends_at' => 'nullable|date',
            'custom_domain' => 'nullable|string|max:255|unique:tenants,custom_domain',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ]);

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'custom_domain' => $validated['custom_domain'] ?? null,
            'ruc' => $validated['ruc'] ?? null,
            'plan' => $validated['plan'],
            'is_active' => $validated['is_active'] ?? true,
            'trial_ends_at' => $validated['trial_ends_at'] ?? null,
        ]);

        User::withoutGlobalScopes()->create([
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($validated['admin_password']),
            'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        return response()->json(['data' => $tenant], 201);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'ruc' => 'nullable|string|size:11',
            'plan' => 'sometimes|in:trial,basic,professional,enterprise',
            'is_active' => 'boolean',
            'trial_ends_at' => 'nullable|date',
            'custom_domain' => 'nullable|string|max:255|unique:tenants,custom_domain,' . $tenant->id,
            'settings' => 'nullable|array',
        ]);

        $tenant->update($validated);

        return response()->json(['data' => $tenant]);
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $tenant->delete();
        return response()->json(['message' => 'Tenant eliminado']);
    }

    public function toggleActive(Tenant $tenant): JsonResponse
    {
        $tenant->update(['is_active' => !$tenant->is_active]);

        return response()->json([
            'data' => $tenant,
            'message' => $tenant->is_active ? 'Tenant activado' : 'Tenant desactivado',
        ]);
    }

    public function users(Tenant $tenant): JsonResponse
    {
        $users = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $users]);
    }

    public function platformStats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'total_tenants' => Tenant::count(),
                'active_tenants' => Tenant::where('is_active', true)->count(),
                'total_users' => User::withoutGlobalScopes()->where('role', '!=', 'super_admin')->count(),
                'total_tickets' => Ticket::withoutGlobalScopes()->count(),
                'tenants_by_plan' => Tenant::select('plan', DB::raw('COUNT(*) as count'))
                    ->groupBy('plan')
                    ->pluck('count', 'plan'),
                'recent_tenants' => Tenant::latest()->limit(5)->get(['id', 'name', 'plan', 'is_active', 'created_at']),
            ],
        ]);
    }

    public function impersonate(Tenant $tenant): JsonResponse
    {
        $admin = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('role', 'admin')
            ->first();

        if (!$admin) {
            return response()->json(['message' => 'No se encontró un admin para este tenant'], 404);
        }

        $token = $admin->createToken('impersonate-token')->accessToken;

        return response()->json([
            'user' => $admin->load('tenant'),
            'token' => $token,
        ]);
    }
}
