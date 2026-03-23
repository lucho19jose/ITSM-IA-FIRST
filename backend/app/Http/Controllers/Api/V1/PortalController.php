<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseCategory;
use App\Models\ServiceCatalogItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PortalController extends Controller
{
    /**
     * Resolve tenant from slug (used by all portal routes).
     * Returns the tenant even if inactive so callers can show
     * an appropriate suspension message.
     */
    private function resolveTenant(string $slug): ?Tenant
    {
        return Tenant::where('slug', $slug)->first();
    }

    /**
     * Return a 403 response if the tenant is suspended,
     * or 404 if it doesn't exist at all.
     */
    private function guardTenant(?Tenant $tenant): ?JsonResponse
    {
        if ($guard = $this->guardTenant($tenant)) {
            return $guard;
        }

        if (!$tenant->is_active) {
            return response()->json([
                'message' => 'Cuenta suspendida. Esta empresa ha sido desactivada temporalmente.',
                'error_code' => 'tenant_suspended',
                'suspended_at' => $tenant->suspended_at?->toIso8601String(),
            ], 403);
        }

        return null;
    }

    /**
     * Get public tenant info for portal branding.
     */
    public function tenantInfo(string $tenantSlug): JsonResponse
    {
        $tenant = $this->resolveTenant($tenantSlug);

        if ($guard = $this->guardTenant($tenant)) {
            return $guard;
        }

        return response()->json([
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'logo_url' => $tenant->logo_url,
                'favicon_url' => $tenant->favicon_url,
                'settings' => $tenant->settings,
            ],
        ]);
    }

    /**
     * Self-register as end_user for a specific tenant.
     */
    public function register(string $tenantSlug, Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($tenantSlug);

        if ($guard = $this->guardTenant($tenant)) {
            return $guard;
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Check email uniqueness within this tenant
        $exists = User::withoutGlobalScopes()
            ->where('email', $request->email)
            ->where('tenant_id', $tenant->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => ['email' => ['Este correo ya está registrado en esta empresa.']],
            ], 422);
        }

        $user = User::withoutGlobalScopes()->create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'end_user',
            'tenant_id' => $tenant->id,
        ]);

        $token = $user->createToken('auth-token')->accessToken;

        app()->instance('tenant_id', $tenant->id);

        return response()->json([
            'user' => $user->load('tenant'),
            'token' => $token,
        ], 201);
    }

    /**
     * Login for portal (validates user belongs to tenant).
     */
    public function login(string $tenantSlug, Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($tenantSlug);

        if ($guard = $this->guardTenant($tenant)) {
            return $guard;
        }

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::withoutGlobalScopes()
            ->where('email', $request->email)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Tu cuenta está desactivada',
            ], 403);
        }

        $token = $user->createToken('auth-token')->accessToken;

        app()->instance('tenant_id', $tenant->id);

        return response()->json([
            'user' => $user->load('tenant'),
            'token' => $token,
        ]);
    }

    /**
     * Public KB categories for a tenant.
     */
    public function kbCategories(string $tenantSlug): JsonResponse
    {
        $tenant = $this->resolveTenant($tenantSlug);

        if ($guard = $this->guardTenant($tenant)) {
            return $guard;
        }

        app()->instance('tenant_id', $tenant->id);

        $categories = KnowledgeBaseCategory::where('is_active', true)
            ->withCount(['articles' => function ($q) {
                $q->where('status', 'published')->where('is_public', true);
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * Public KB articles for a tenant (published + public only).
     */
    public function kbArticles(string $tenantSlug, Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($tenantSlug);

        if ($guard = $this->guardTenant($tenant)) {
            return $guard;
        }

        app()->instance('tenant_id', $tenant->id);

        $query = KnowledgeBaseArticle::where('status', 'published')
            ->where('is_public', true)
            ->with('category', 'author');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $articles = $query->orderByDesc('published_at')
            ->paginate($request->input('per_page', 15));

        return response()->json($articles);
    }

    /**
     * Public KB article detail.
     */
    public function kbArticle(string $tenantSlug, int $id): JsonResponse
    {
        $tenant = $this->resolveTenant($tenantSlug);

        if ($guard = $this->guardTenant($tenant)) {
            return $guard;
        }

        app()->instance('tenant_id', $tenant->id);

        $article = KnowledgeBaseArticle::where('status', 'published')
            ->where('is_public', true)
            ->with('category', 'author')
            ->findOrFail($id);

        $article->increment('views_count');

        return response()->json(['data' => $article]);
    }

    /**
     * Public catalog items for a tenant.
     */
    public function catalog(string $tenantSlug): JsonResponse
    {
        $tenant = $this->resolveTenant($tenantSlug);

        if ($guard = $this->guardTenant($tenant)) {
            return $guard;
        }

        app()->instance('tenant_id', $tenant->id);

        $items = ServiceCatalogItem::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $items]);
    }
}
