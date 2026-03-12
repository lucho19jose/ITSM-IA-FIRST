<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $tenant = Tenant::find($request->user()->tenant_id);

        return response()->json(['data' => $tenant]);
    }

    public function update(Request $request): JsonResponse
    {
        $tenant = Tenant::find($request->user()->tenant_id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'ruc' => 'nullable|string|size:11',
            'settings' => 'nullable|array',
        ]);

        $tenant->update($validated);

        return response()->json(['data' => $tenant]);
    }

    public function updateBrandColors(Request $request): JsonResponse
    {
        $tenant = Tenant::find($request->user()->tenant_id);

        $validated = $request->validate([
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $settings = $tenant->settings ?? [];
        $settings['primary_color'] = $validated['primary_color'] ?? null;
        $settings['secondary_color'] = $validated['secondary_color'] ?? null;
        $settings['accent_color'] = $validated['accent_color'] ?? null;

        $tenant->update(['settings' => $settings]);

        return response()->json([
            'data' => $tenant->fresh(),
            'message' => 'Colores actualizados correctamente',
        ]);
    }

    public function updateDomain(Request $request): JsonResponse
    {
        $tenant = Tenant::find($request->user()->tenant_id);

        $validated = $request->validate([
            'custom_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/',
                'unique:tenants,custom_domain,' . $tenant->id,
            ],
        ], [
            'custom_domain.regex' => 'El dominio no tiene un formato válido (ej: soporte.miempresa.com)',
            'custom_domain.unique' => 'Este dominio ya está en uso por otra empresa',
        ]);

        $tenant->update(['custom_domain' => $validated['custom_domain']]);

        return response()->json([
            'data' => $tenant,
            'message' => $validated['custom_domain']
                ? 'Dominio personalizado configurado. Configure un registro CNAME apuntando a autoservice.pe'
                : 'Dominio personalizado eliminado',
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        $tenant = Tenant::find($request->user()->tenant_id);

        // Delete old logo
        if ($tenant->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
        }

        $path = $request->file('logo')->store("branding/{$tenant->id}", 'public');
        $tenant->update(['logo_path' => $path]);

        return response()->json([
            'data' => $tenant->fresh(),
            'message' => 'Logo actualizado correctamente',
        ]);
    }

    public function deleteLogo(Request $request): JsonResponse
    {
        $tenant = Tenant::find($request->user()->tenant_id);

        if ($tenant->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
            $tenant->update(['logo_path' => null]);
        }

        return response()->json([
            'data' => $tenant->fresh(),
            'message' => 'Logo eliminado',
        ]);
    }

    public function uploadFavicon(Request $request): JsonResponse
    {
        $request->validate([
            'favicon' => 'required|image|mimes:png,ico,svg|max:512',
        ]);

        $tenant = Tenant::find($request->user()->tenant_id);

        // Delete old favicon
        if ($tenant->favicon_path) {
            Storage::disk('public')->delete($tenant->favicon_path);
        }

        $path = $request->file('favicon')->store("branding/{$tenant->id}", 'public');
        $tenant->update(['favicon_path' => $path]);

        return response()->json([
            'data' => $tenant->fresh(),
            'message' => 'Favicon actualizado correctamente',
        ]);
    }

    public function deleteFavicon(Request $request): JsonResponse
    {
        $tenant = Tenant::find($request->user()->tenant_id);

        if ($tenant->favicon_path) {
            Storage::disk('public')->delete($tenant->favicon_path);
            $tenant->update(['favicon_path' => null]);
        }

        return response()->json([
            'data' => $tenant->fresh(),
            'message' => 'Favicon eliminado',
        ]);
    }

    public function verifyDomain(Request $request): JsonResponse
    {
        $tenant = Tenant::find($request->user()->tenant_id);

        if (!$tenant->custom_domain) {
            return response()->json([
                'verified' => false,
                'message' => 'No hay dominio personalizado configurado',
            ]);
        }

        // Check if the domain resolves to our server
        $dns = @dns_get_record($tenant->custom_domain, DNS_CNAME);
        $resolved = false;

        if ($dns) {
            foreach ($dns as $record) {
                if (isset($record['target']) && str_contains($record['target'], 'autoservice')) {
                    $resolved = true;
                    break;
                }
            }
        }

        // Also check A record pointing to our IP
        if (!$resolved) {
            $ip = @gethostbyname($tenant->custom_domain);
            if ($ip && $ip !== $tenant->custom_domain) {
                $resolved = true; // Domain resolves to some IP
            }
        }

        return response()->json([
            'verified' => $resolved,
            'domain' => $tenant->custom_domain,
            'message' => $resolved
                ? 'Dominio verificado correctamente'
                : 'El dominio aún no apunta a nuestros servidores. Configure un registro CNAME: ' . $tenant->custom_domain . ' → autoservice.pe',
        ]);
    }
}
