<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\PasswordResetMail;
use App\Mail\WelcomeMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

        // Queue welcome email
        Mail::to($user->email)->queue(new WelcomeMail($user, $tenant));

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

        // Check if the user's tenant is active
        if ($user->tenant_id && $user->role !== 'super_admin') {
            $tenant = Tenant::find($user->tenant_id);
            if ($tenant && !$tenant->is_active) {
                return response()->json([
                    'message' => 'Cuenta suspendida. Tu empresa ha sido desactivada por falta de pago o a solicitud del administrador. Contacta soporte o reactiva tu suscripción.',
                    'error_code' => 'tenant_suspended',
                    'suspended_at' => $tenant->suspended_at?->toIso8601String(),
                    'suspension_reason' => $tenant->suspension_reason,
                ], 403);
            }
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

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::withoutGlobalScopes()->where('email', $request->email)->first();

        // Always return success to prevent email enumeration
        if (!$user) {
            return response()->json([
                'message' => 'Si el correo existe, recibirás un enlace para restablecer tu contraseña.',
            ]);
        }

        // Delete old tokens for this email
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => $request->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $resetUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5174'))
            . '/reset-password?token=' . $token . '&email=' . urlencode($request->email);

        Mail::to($user->email)->queue(new PasswordResetMail($user, $resetUrl));

        return response()->json([
            'message' => 'Si el correo existe, recibirás un enlace para restablecer tu contraseña.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json([
                'message' => 'Token inválido o expirado.',
            ], 422);
        }

        // Check expiration (60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'Token expirado. Solicita un nuevo enlace.',
            ], 422);
        }

        $user = User::withoutGlobalScopes()->where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Delete used token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Contraseña restablecida exitosamente.',
        ]);
    }
}
