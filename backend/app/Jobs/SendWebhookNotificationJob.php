<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Services\WebhookNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public Integration $integration,
        public string $event,
        public array $data,
    ) {}

    public function handle(): void
    {
        $webhookUrl = $this->integration->config['incoming_webhook_url'] ?? null;

        if (!$webhookUrl) {
            Log::warning("Integration #{$this->integration->id} has no webhook URL configured.");
            return;
        }

        $payload = match ($this->integration->provider) {
            'slack' => WebhookNotificationService::formatSlackMessage($this->event, $this->data),
            'teams' => WebhookNotificationService::formatTeamsMessage($this->event, $this->data),
            default => WebhookNotificationService::formatGenericWebhookMessage($this->event, $this->data),
        };

        $response = Http::timeout(15)->post($webhookUrl, $payload);

        if ($response->failed()) {
            Log::error("Webhook notification failed for integration #{$this->integration->id}", [
                'provider' => $this->integration->provider,
                'event' => $this->event,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // Throw exception to trigger retry
            throw new \RuntimeException(
                "Webhook failed with status {$response->status()} for integration #{$this->integration->id}"
            );
        }

        Log::info("Webhook notification sent for integration #{$this->integration->id}", [
            'provider' => $this->integration->provider,
            'event' => $this->event,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Webhook notification permanently failed for integration #{$this->integration->id}", [
            'provider' => $this->integration->provider,
            'event' => $this->event,
            'error' => $exception->getMessage(),
        ]);
    }
}
