<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ai\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    public function message(Request $request, string $tenantSlug, ChatbotService $chatbot): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->where('is_active', true)->firstOrFail();

        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'required|string',
        ]);

        $result = $chatbot->chat($tenant, $validated['message'], $validated['history'] ?? []);

        return response()->json(['data' => $result]);
    }

    public function createTicket(Request $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->where('is_active', true)->firstOrFail();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'requester_email' => 'required|email',
            'requester_name' => 'required|string|max:255',
        ]);

        app()->instance('tenant_id', $tenant->id);

        // Find or create the requester user
        $user = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $validated['requester_email'])
            ->first();

        if (!$user) {
            $user = User::withoutGlobalScopes()->create([
                'name' => $validated['requester_name'],
                'email' => $validated['requester_email'],
                'password' => bcrypt(Str::random(16)),
                'role' => 'end_user',
                'tenant_id' => $tenant->id,
            ]);
        }

        $priority = $validated['priority'] ?? 'medium';

        $ticketData = [
            'title' => $validated['title'],
            'description' => $validated['description'],
            'type' => 'incident',
            'status' => 'open',
            'priority' => $priority,
            'source' => Ticket::SOURCE_CHATBOT,
            'requester_id' => $user->id,
        ];

        // Auto-assign SLA policy
        $sla = SlaPolicy::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('priority', $priority)
            ->where('is_active', true)
            ->first();

        if ($sla) {
            $ticketData['sla_policy_id'] = $sla->id;
            $ticketData['response_due_at'] = now()->addMinutes($sla->response_time);
            $ticketData['resolution_due_at'] = now()->addMinutes($sla->resolution_time);
        }

        $ticket = Ticket::create($ticketData);

        // Dispatch AI classification
        if (class_exists(\App\Jobs\ClassifyTicketJob::class)) {
            \App\Jobs\ClassifyTicketJob::dispatch($ticket)->onQueue('ai');
        }

        return response()->json([
            'message' => 'Ticket creado exitosamente',
            'ticket_number' => $ticket->ticket_number,
        ], 201);
    }
}
