<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'company_name' => 'required|string|max:255',
            'ruc' => 'nullable|string|size:11',
        ]);

        $slug = Str::slug($request->company_name);
        $originalSlug = $slug;
        $counter = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $tenant = Tenant::create([
            'name' => $request->company_name,
            'slug' => $slug,
            'ruc' => $request->ruc,
            'plan' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        $token = $user->createToken('auth-token')->accessToken;

        return response()->json([
            'user' => $user->load('tenant'),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::withoutGlobalScopes()
            ->where('email', $request->email)
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

        app()->instance('tenant_id', $user->tenant_id);

        return response()->json([
            'user' => $user->load('tenant'),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('tenant', 'department');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function tenantInfo(Request $request): JsonResponse
    {
        if (!app()->bound('tenant_id') || !app('tenant_id')) {
            return response()->json(['data' => null]);
        }

        $tenant = Tenant::find(app('tenant_id'));

        if (!$tenant) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'plan' => $tenant->plan,
            ],
        ]);
    }
}
