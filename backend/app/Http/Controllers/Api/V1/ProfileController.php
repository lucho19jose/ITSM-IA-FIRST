<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('department');

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:30',
            'work_phone' => 'nullable|string|max:30',
            'location' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:50',
            'language' => 'nullable|string|in:es,en',
            'signature' => 'nullable|string|max:2000',
            'is_available_for_assignment' => 'nullable|boolean',
            'time_format' => 'nullable|string|in:12h,24h',
        ]);

        $user->update($validated);

        return response()->json([
            'data' => new UserResource($user->fresh()->load('department')),
            'message' => 'Perfil actualizado correctamente',
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'La contraseña actual es incorrecta',
                'errors' => ['current_password' => ['La contraseña actual es incorrecta']],
            ], 422);
        }

        $user->update(['password' => Hash::make($validated['new_password'])]);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente',
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return response()->json([
            'data' => new UserResource($user->fresh()),
            'message' => 'Avatar actualizado',
        ]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return response()->json([
            'data' => new UserResource($user->fresh()),
            'message' => 'Avatar eliminado',
        ]);
    }
}
