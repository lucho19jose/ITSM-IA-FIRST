<?php

namespace App\Jobs;

use App\Services\Ai\SlaPredictorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckSlaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SlaPredictorService $predictor): void
    {
        $atRisk = $predictor->checkAtRiskTickets();

        if ($atRisk->isNotEmpty()) {
            Log::info("SLA Check: {$atRisk->count()} tickets at risk or breached");
        }
    }
}
