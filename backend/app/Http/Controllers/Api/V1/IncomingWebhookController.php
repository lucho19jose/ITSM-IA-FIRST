<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IncomingWebhookController extends Controller
{
    public function slack(Request $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->where('is_active', true)->first();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Handle Slack URL verification challenge
        if ($request->input('type') === 'url_verification') {
            return response()->json(['challenge' => $request->input('challenge')]);
        }

        // Verify Slack request signature if signing secret is configured
        if (!$this->verifySlackSignature($request, $tenant)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Handle slash command
        $text = trim($request->input('text', ''));
        $command = $request->input('command', '');
        $userId = $request->input('user_name', 'slack-user');

        // Parse command: /autoservice create {subject} OR /autoservice status {id}
        if (str_starts_with($text, 'create ')) {
            $subject = trim(substr($text, 7));
            return $this->createTicketFromWebhook($tenant, $subject, "slack:{$userId}");
        }

        if (str_starts_with($text, 'status ')) {
            $ticketNumber = trim(substr($text, 7));
            return $this->getTicketStatus($tenant, $ticketNumber);
        }

        return response()->json([
            'response_type' => 'ephemeral',
            'text' => "Comandos disponibles:\n" .
                "- `/autoservice create {asunto}` - Crear un nuevo ticket\n" .
                "- `/autoservice status {numero_ticket}` - Consultar estado de un ticket",
        ]);
    }

    public function teams(Request $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->where('is_active', true)->first();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Verify Teams request via HMAC if secret configured
        if (!$this->verifyTeamsSignature($request, $tenant)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Teams bot messages come in a different format
        $text = trim($request->input('text', '') ?: ($request->input('value.text', '') ?: ''));

        // Try to extract from activity
        if (empty($text) && $request->has('text')) {
            $text = $request->input('text');
        }

        // Strip bot mention if present
        $text = preg_replace('/<at>.*?<\/at>\s*/', '', $text);
        $text = trim($text);

        $userName = $request->input('from.name', 'teams-user');

        if (str_starts_with($text, 'create ') || str_starts_with($text, 'crear ')) {
            $prefix = str_starts_with($text, 'create ') ? 'create ' : 'crear ';
            $subject = trim(substr($text, strlen($prefix)));
            return $this->createTicketFromWebhook($tenant, $subject, "teams:{$userName}");
        }

        if (str_starts_with($text, 'status ') || str_starts_with($text, 'estado ')) {
            $prefix = str_starts_with($text, 'status ') ? 'status ' : 'estado ';
            $ticketNumber = trim(substr($text, strlen($prefix)));
            return $this->getTicketStatus($tenant, $ticketNumber);
        }

        return response()->json([
            'type' => 'message',
            'text' => "Comandos disponibles:\n" .
                "- `create {asunto}` - Crear un nuevo ticket\n" .
                "- `status {numero_ticket}` - Consultar estado de un ticket",
        ]);
    }

    private function createTicketFromWebhook(Tenant $tenant, string $subject, string $source): JsonResponse
    {
        if (empty($subject)) {
            return response()->json([
                'response_type' => 'ephemeral',
                'text' => 'Por favor, proporciona un asunto para el ticket.',
            ]);
        }

        // Find or use a default requester (first admin of the tenant)
        $requester = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('role', 'admin')
            ->first();

        if (!$requester) {
            return response()->json([
                'response_type' => 'ephemeral',
                'text' => 'No se encontro un usuario para crear el ticket.',
            ]);
        }

        $ticket = Ticket::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'title' => $subject,
            'description' => "Ticket creado via webhook ({$source}): {$subject}",
            'type' => 'request',
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'api',
            'requester_id' => $requester->id,
        ]);

        $responseText = "Ticket creado exitosamente: #{$ticket->ticket_number} - {$ticket->title}";

        return response()->json([
            'response_type' => 'in_channel',
            'text' => $responseText,
        ]);
    }

    private function getTicketStatus(Tenant $tenant, string $ticketNumber): JsonResponse
    {
        $ticket = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('ticket_number', $ticketNumber)
            ->first();

        if (!$ticket) {
            return response()->json([
                'response_type' => 'ephemeral',
                'text' => "No se encontro el ticket {$ticketNumber}.",
            ]);
        }

        $statusLabels = [
            'open' => 'Abierto',
            'in_progress' => 'En Progreso',
            'pending' => 'Pendiente',
            'resolved' => 'Resuelto',
            'closed' => 'Cerrado',
        ];

        $priorityLabels = [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'urgent' => 'Urgente',
        ];

        $status = $statusLabels[$ticket->status] ?? $ticket->status;
        $priority = $priorityLabels[$ticket->priority] ?? $ticket->priority;
        $assignee = $ticket->assignee?->name ?? 'Sin asignar';

        $text = "Ticket #{$ticket->ticket_number}: {$ticket->title}\n" .
            "Estado: {$status}\n" .
            "Prioridad: {$priority}\n" .
            "Asignado a: {$assignee}\n" .
            "Creado: {$ticket->created_at->format('d/m/Y H:i')}";

        return response()->json([
            'response_type' => 'ephemeral',
            'text' => $text,
        ]);
    }

    private function verifySlackSignature(Request $request, Tenant $tenant): bool
    {
        // Check if tenant has a signing secret configured in integrations
        $signingSecret = $this->getSlackSigningSecret($tenant);

        if (!$signingSecret) {
            // If no signing secret configured, allow request (trust network)
            return true;
        }

        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (!$timestamp || !$signature) {
            return false;
        }

        // Prevent replay attacks (5 minute window)
        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }

        $sigBasestring = "v0:{$timestamp}:{$request->getContent()}";
        $mySignature = 'v0=' . hash_hmac('sha256', $sigBasestring, $signingSecret);

        return hash_equals($mySignature, $signature);
    }

    private function verifyTeamsSignature(Request $request, Tenant $tenant): bool
    {
        // Teams webhook verification uses HMAC-SHA256 with the security token
        $hmacHeader = $request->header('Authorization');

        if (!$hmacHeader) {
            // If no auth header, allow request (basic setup without verification)
            return true;
        }

        $secret = $this->getTeamsSecret($tenant);
        if (!$secret) {
            return true;
        }

        $expectedHmac = 'HMAC ' . base64_encode(hash_hmac('sha256', $request->getContent(), base64_decode($secret), true));

        return hash_equals($expectedHmac, $hmacHeader);
    }

    private function getSlackSigningSecret(Tenant $tenant): ?string
    {
        $integration = \App\Models\Integration::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('provider', 'slack')
            ->where('is_active', true)
            ->first();

        return $integration?->config['signing_secret'] ?? null;
    }

    private function getTeamsSecret(Tenant $tenant): ?string
    {
        $integration = \App\Models\Integration::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('provider', 'teams')
            ->where('is_active', true)
            ->first();

        return $integration?->config['security_token'] ?? null;
    }
}
