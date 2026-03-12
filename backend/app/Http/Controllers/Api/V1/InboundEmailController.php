<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InboundEmailController extends Controller
{
    /**
     * Webhook endpoint for inbound email providers (Mailgun, SendGrid, Postmark, etc.)
     *
     * Expected payload (normalized):
     * - from: sender email
     * - from_name: sender name (optional)
     * - to: recipient email (e.g. soporte+empresa-demo@autoservice.pe)
     * - subject: email subject
     * - body_plain: plain text body
     * - body_html: HTML body (optional)
     */
    public function webhook(Request $request): JsonResponse
    {
        if (!config('inbound_email.enabled')) {
            return response()->json(['error' => 'Inbound email disabled'], 403);
        }

        // Verify webhook secret if configured
        $secret = config('inbound_email.webhook_secret');
        if ($secret && $request->header('X-Webhook-Secret') !== $secret) {
            return response()->json(['error' => 'Invalid webhook secret'], 401);
        }

        // Normalize payload from different providers
        $email = $this->normalizePayload($request);

        if (!$email['from'] || !$email['subject']) {
            return response()->json(['error' => 'Missing required fields (from, subject)'], 422);
        }

        // Determine tenant from the "to" address: soporte+{slug}@domain.com
        $tenant = $this->resolveTenant($email['to']);

        if (!$tenant) {
            return response()->json(['error' => 'Could not resolve tenant'], 422);
        }

        // Set tenant context
        app()->instance('tenant_id', $tenant->id);

        // Find or create the requester
        $user = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email['from'])
            ->first();

        if (!$user) {
            $user = User::withoutGlobalScopes()->create([
                'name' => $email['from_name'] ?: Str::before($email['from'], '@'),
                'email' => $email['from'],
                'password' => bcrypt(Str::random(16)),
                'role' => 'end_user',
                'tenant_id' => $tenant->id,
            ]);
        }

        // Build ticket description from email body
        $description = $email['body_html'] ?: nl2br(e($email['body_plain'] ?: 'Sin contenido'));

        $ticketData = [
            'title' => $email['subject'],
            'description' => $description,
            'type' => 'incident',
            'status' => 'open',
            'priority' => 'medium',
            'source' => Ticket::SOURCE_EMAIL,
            'requester_id' => $user->id,
        ];

        // Auto-assign SLA
        $sla = SlaPolicy::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('priority', 'medium')
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
            'message' => 'Ticket created from email',
            'ticket_number' => $ticket->ticket_number,
        ], 201);
    }

    /**
     * Normalize payload from different email providers.
     */
    private function normalizePayload(Request $request): array
    {
        // Mailgun format
        if ($request->has('sender')) {
            return [
                'from' => $request->input('sender'),
                'from_name' => $request->input('from', ''),
                'to' => $request->input('recipient', ''),
                'subject' => $request->input('subject', ''),
                'body_plain' => $request->input('body-plain', ''),
                'body_html' => $request->input('body-html', ''),
            ];
        }

        // SendGrid format
        if ($request->has('envelope')) {
            $envelope = json_decode($request->input('envelope', '{}'), true);
            return [
                'from' => $envelope['from'] ?? $request->input('from', ''),
                'from_name' => '',
                'to' => $envelope['to'][0] ?? $request->input('to', ''),
                'subject' => $request->input('subject', ''),
                'body_plain' => $request->input('text', ''),
                'body_html' => $request->input('html', ''),
            ];
        }

        // Generic / Postmark / custom format
        return [
            'from' => $request->input('from') ?: $request->input('From', ''),
            'from_name' => $request->input('from_name') ?: $request->input('FromName', ''),
            'to' => $request->input('to') ?: $request->input('To', ''),
            'subject' => $request->input('subject') ?: $request->input('Subject', ''),
            'body_plain' => $request->input('body_plain') ?: $request->input('TextBody', ''),
            'body_html' => $request->input('body_html') ?: $request->input('HtmlBody', ''),
        ];
    }

    /**
     * Resolve tenant from the recipient email address.
     * Supports: soporte+{slug}@domain.com or lookup by custom domain.
     */
    private function resolveTenant(string $toEmail): ?Tenant
    {
        // Try to extract slug from plus-addressing: soporte+empresa-demo@autoservice.pe
        if (preg_match('/\+([^@]+)@/', $toEmail, $matches)) {
            $tenant = Tenant::where('slug', $matches[1])->where('is_active', true)->first();
            if ($tenant) {
                return $tenant;
            }
        }

        // Try matching domain part against custom_domain
        $domain = Str::after($toEmail, '@');
        if ($domain) {
            $tenant = Tenant::where('custom_domain', $domain)->where('is_active', true)->first();
            if ($tenant) {
                return $tenant;
            }
        }

        // Fallback to default tenant
        $defaultSlug = config('inbound_email.default_tenant_slug');
        if ($defaultSlug) {
            return Tenant::where('slug', $defaultSlug)->where('is_active', true)->first();
        }

        return null;
    }
}
