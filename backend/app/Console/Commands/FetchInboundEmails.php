<?php

namespace App\Console\Commands;

use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FetchInboundEmails extends Command
{
    protected $signature = 'emails:fetch {--tenant= : Specific tenant slug to assign emails to}';

    protected $description = 'Fetch inbound emails via IMAP and create tickets';

    public function handle(): int
    {
        if (!config('inbound_email.enabled')) {
            $this->error('Inbound email is disabled. Set INBOUND_EMAIL_ENABLED=true in .env');
            return self::FAILURE;
        }

        $imapConfig = config('inbound_email.imap');

        if (!$imapConfig['host'] || !$imapConfig['username']) {
            $this->error('IMAP configuration incomplete. Set INBOUND_EMAIL_IMAP_* in .env');
            return self::FAILURE;
        }

        if (!function_exists('imap_open')) {
            $this->error('PHP IMAP extension not available. Install php-imap or use the webhook endpoint instead.');
            return self::FAILURE;
        }

        $mailbox = sprintf(
            '{%s:%d/%s}%s',
            $imapConfig['host'],
            $imapConfig['port'],
            $imapConfig['encryption'] === 'ssl' ? 'imap/ssl' : 'imap',
            $imapConfig['folder']
        );

        $connection = @imap_open($mailbox, $imapConfig['username'], $imapConfig['password']);

        if (!$connection) {
            $this->error('Failed to connect to IMAP: ' . imap_last_error());
            return self::FAILURE;
        }

        $emails = imap_search($connection, 'UNSEEN');

        if (!$emails) {
            $this->info('No new emails found.');
            imap_close($connection);
            return self::SUCCESS;
        }

        $created = 0;

        foreach ($emails as $emailNumber) {
            try {
                $header = imap_headerinfo($connection, $emailNumber);
                $structure = imap_fetchstructure($connection, $emailNumber);

                $fromAddress = $header->from[0]->mailbox . '@' . $header->from[0]->host;
                $fromName = isset($header->from[0]->personal)
                    ? imap_utf8($header->from[0]->personal)
                    : Str::before($fromAddress, '@');

                $subject = isset($header->subject) ? imap_utf8($header->subject) : 'Sin asunto';
                $toAddress = $header->to[0]->mailbox . '@' . $header->to[0]->host;

                $body = $this->getBody($connection, $emailNumber, $structure);

                // Resolve tenant
                $tenant = $this->resolveTenant($toAddress);
                if (!$tenant) {
                    $this->warn("Could not resolve tenant for: {$toAddress} — skipping email from {$fromAddress}");
                    continue;
                }

                app()->instance('tenant_id', $tenant->id);

                // Find or create user
                $user = User::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('email', $fromAddress)
                    ->first();

                if (!$user) {
                    $user = User::withoutGlobalScopes()->create([
                        'name' => $fromName,
                        'email' => $fromAddress,
                        'password' => bcrypt(Str::random(16)),
                        'role' => 'end_user',
                        'tenant_id' => $tenant->id,
                    ]);
                }

                $ticketData = [
                    'title' => $subject,
                    'description' => $body ?: 'Sin contenido',
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

                if (class_exists(\App\Jobs\ClassifyTicketJob::class)) {
                    \App\Jobs\ClassifyTicketJob::dispatch($ticket)->onQueue('ai');
                }

                // Mark as seen
                imap_setflag_full($connection, (string) $emailNumber, '\\Seen');

                if ($imapConfig['delete_after_processing']) {
                    imap_delete($connection, (string) $emailNumber);
                }

                $created++;
                $this->line("Created ticket {$ticket->ticket_number} from {$fromAddress}");
            } catch (\Throwable $e) {
                $this->error("Error processing email #{$emailNumber}: {$e->getMessage()}");
            }
        }

        if ($imapConfig['delete_after_processing']) {
            imap_expunge($connection);
        }

        imap_close($connection);

        $this->info("Done. Created {$created} ticket(s) from " . count($emails) . " email(s).");

        return self::SUCCESS;
    }

    private function getBody($connection, int $emailNumber, $structure): string
    {
        // Try to get HTML body first, then plain text
        if (isset($structure->parts)) {
            foreach ($structure->parts as $partIndex => $part) {
                if ($part->subtype === 'HTML') {
                    $body = imap_fetchbody($connection, $emailNumber, (string) ($partIndex + 1));
                    return $this->decodeBody($body, $part->encoding);
                }
            }
            foreach ($structure->parts as $partIndex => $part) {
                if ($part->subtype === 'PLAIN') {
                    $body = imap_fetchbody($connection, $emailNumber, (string) ($partIndex + 1));
                    return nl2br(e($this->decodeBody($body, $part->encoding)));
                }
            }
        }

        // Simple message (no parts)
        $body = imap_body($connection, $emailNumber);
        if ($structure->subtype === 'HTML') {
            return $this->decodeBody($body, $structure->encoding);
        }
        return nl2br(e($this->decodeBody($body, $structure->encoding)));
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($body), // BASE64
            4 => quoted_printable_decode($body), // QUOTED-PRINTABLE
            default => $body,
        };
    }

    private function resolveTenant(string $toEmail): ?Tenant
    {
        $tenantSlug = $this->option('tenant');

        if ($tenantSlug) {
            return Tenant::where('slug', $tenantSlug)->where('is_active', true)->first();
        }

        // Plus-addressing: soporte+empresa-demo@autoservice.pe
        if (preg_match('/\+([^@]+)@/', $toEmail, $matches)) {
            $tenant = Tenant::where('slug', $matches[1])->where('is_active', true)->first();
            if ($tenant) {
                return $tenant;
            }
        }

        // Fallback
        $defaultSlug = config('inbound_email.default_tenant_slug');
        if ($defaultSlug) {
            return Tenant::where('slug', $defaultSlug)->where('is_active', true)->first();
        }

        return null;
    }
}
