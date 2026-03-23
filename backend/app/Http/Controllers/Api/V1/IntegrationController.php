<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\WebhookNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntegrationController extends Controller
{
    public function index(): JsonResponse
    {
        $integrations = Integration::orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $integrations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|in:slack,teams,generic_webhook',
            'name' => 'required|string|max:255',
            'config' => 'required|array',
            'config.incoming_webhook_url' => 'required|url|max:1000',
            'config.channel' => 'nullable|string|max:255',
            'config.bot_token' => 'nullable|string|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:' . implode(',', WebhookNotificationService::ALL_EVENTS),
            'is_active' => 'boolean',
        ]);

        $integration = Integration::create($validated);

        return response()->json([
            'data' => $integration,
            'message' => 'Integracion creada correctamente',
        ], 201);
    }

    public function show(Integration $integration): JsonResponse
    {
        return response()->json(['data' => $integration]);
    }

    public function update(Request $request, Integration $integration): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'sometimes|in:slack,teams,generic_webhook',
            'name' => 'sometimes|string|max:255',
            'config' => 'sometimes|array',
            'config.incoming_webhook_url' => 'required_with:config|url|max:1000',
            'config.channel' => 'nullable|string|max:255',
            'config.bot_token' => 'nullable|string|max:500',
            'events' => 'sometimes|array|min:1',
            'events.*' => 'string|in:' . implode(',', WebhookNotificationService::ALL_EVENTS),
            'is_active' => 'sometimes|boolean',
        ]);

        $integration->update($validated);

        return response()->json([
            'data' => $integration->fresh(),
            'message' => 'Integracion actualizada correctamente',
        ]);
    }

    public function destroy(Integration $integration): JsonResponse
    {
        $integration->delete();

        return response()->json(['message' => 'Integracion eliminada']);
    }

    public function test(Integration $integration): JsonResponse
    {
        $webhookUrl = $integration->config['incoming_webhook_url'] ?? null;

        if (!$webhookUrl) {
            return response()->json([
                'success' => false,
                'message' => 'No hay URL de webhook configurada',
            ], 422);
        }

        $payload = WebhookNotificationService::buildTestPayload($integration->provider);

        try {
            $response = Http::timeout(15)->post($webhookUrl, $payload);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Mensaje de prueba enviado correctamente',
                ]);
            }

            Log::warning("Webhook test failed for integration #{$integration->id}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Error al enviar: HTTP {$response->status()}",
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Webhook test exception for integration #{$integration->id}", [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error de conexion: ' . $e->getMessage(),
            ], 422);
        }
    }
}
