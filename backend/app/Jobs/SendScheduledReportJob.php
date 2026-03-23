<?php

namespace App\Jobs;

use App\Models\SavedReport;
use App\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendScheduledReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ReportService $reportService): void
    {
        $reports = SavedReport::withoutGlobalScopes()
            ->whereNotNull('schedule_cron')
            ->whereNotNull('schedule_emails')
            ->get();

        foreach ($reports as $report) {
            if (!$this->shouldRunNow($report->schedule_cron)) {
                continue;
            }

            try {
                $result = $reportService->executeReport($report);
                $columns = $report->config['columns'] ?? ['group_label', 'count'];
                $csv = $reportService->exportToCsv($result['data'], $columns);

                $filename = str_replace(' ', '_', $report->name) . '_' . now()->format('Y-m-d') . '.csv';
                $emails = $report->schedule_emails;

                if (empty($emails)) {
                    continue;
                }

                Mail::raw(
                    "Adjunto el reporte programado: {$report->name}\n\nGenerado el " . now()->format('d/m/Y H:i'),
                    function ($message) use ($emails, $report, $csv, $filename) {
                        $message->to($emails)
                            ->subject("Reporte: {$report->name}")
                            ->attachData($csv, $filename, ['mime' => 'text/csv']);
                    }
                );

                $report->update(['last_run_at' => now()]);

                Log::info("Scheduled report sent", [
                    'report_id' => $report->id,
                    'name' => $report->name,
                    'recipients' => $emails,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send scheduled report', [
                    'report_id' => $report->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Simple cron expression check — evaluates whether the report should run at
     * the current minute. Supports standard 5-field cron (min hour dom mon dow).
     */
    private function shouldRunNow(string $cron): bool
    {
        $parts = preg_split('/\s+/', trim($cron));
        if (count($parts) !== 5) {
            return false;
        }

        $now = now();
        $checks = [
            (int) $now->format('i'),  // minute
            (int) $now->format('G'),  // hour
            (int) $now->format('j'),  // day of month
            (int) $now->format('n'),  // month
            (int) $now->format('w'),  // day of week (0=Sunday)
        ];

        foreach ($parts as $i => $part) {
            if ($part === '*') {
                continue;
            }

            // Handle comma-separated values
            $values = explode(',', $part);
            $match = false;
            foreach ($values as $val) {
                // Handle step values like */5
                if (str_contains($val, '/')) {
                    $step = (int) explode('/', $val)[1];
                    if ($step > 0 && $checks[$i] % $step === 0) {
                        $match = true;
                        break;
                    }
                } elseif ((int) $val === $checks[$i]) {
                    $match = true;
                    break;
                }
            }

            if (!$match) {
                return false;
            }
        }

        return true;
    }
}
