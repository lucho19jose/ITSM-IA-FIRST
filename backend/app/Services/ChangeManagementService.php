<?php

namespace App\Services;

use App\Models\ChangeRequest;
use App\Models\ChangeRequestApproval;
use App\Services\Ai\ClaudeClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ChangeManagementService
{
    public function __construct(
        private ClaudeClient $claude,
    ) {}

    /**
     * Submit a change request for review. Validates required fields.
     */
    public function submitForReview(ChangeRequest $cr): void
    {
        $this->ensureStatus($cr, [ChangeRequest::STATUS_DRAFT]);

        // Validate required fields for submission
        $missing = [];
        if (empty($cr->title)) $missing[] = 'title';
        if (empty($cr->description)) $missing[] = 'description';
        if (empty($cr->reason_for_change)) $missing[] = 'reason_for_change';

        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'fields' => ['Missing required fields: ' . implode(', ', $missing)],
            ]);
        }

        // Standard changes auto-approve (skip CAB)
        if ($cr->isStandard()) {
            $cr->update([
                'status' => ChangeRequest::STATUS_APPROVED,
                'cab_decision' => 'Auto-approved: standard change type.',
                'cab_decided_at' => now(),
            ]);
            return;
        }

        $cr->update(['status' => ChangeRequest::STATUS_SUBMITTED]);
    }

    /**
     * AI-powered risk assessment using ClaudeClient.
     */
    public function assessRisk(ChangeRequest $cr): array
    {
        $this->ensureStatus($cr, [
            ChangeRequest::STATUS_SUBMITTED,
            ChangeRequest::STATUS_ASSESSMENT,
            ChangeRequest::STATUS_DRAFT,
        ]);

        $cr->update(['status' => ChangeRequest::STATUS_ASSESSMENT]);

        $systemPrompt = <<<'PROMPT'
You are an ITIL Change Management risk assessment expert. Analyze the change request and provide a structured risk assessment in JSON format:
{
  "risk_level": "low|medium|high|very_high",
  "risk_score": <1-10>,
  "impact_analysis": "...",
  "risk_factors": ["..."],
  "mitigation_recommendations": ["..."],
  "recommended_priority": "low|medium|high|critical",
  "requires_cab": true|false,
  "summary": "..."
}
PROMPT;

        $userMessage = <<<MSG
Change Request:
- Title: {$cr->title}
- Type: {$cr->type}
- Priority: {$cr->priority}
- Current Risk Level: {$cr->risk_level}
- Impact: {$cr->impact}
- Description: {$cr->description}
- Reason for Change: {$cr->reason_for_change}
- Implementation Plan: {$cr->implementation_plan}
- Rollback Plan: {$cr->rollback_plan}
- Test Plan: {$cr->test_plan}
MSG;

        try {
            $response = $this->claude->sendMessage($systemPrompt, $userMessage, 1500);
            $content = $response['content'];

            // Extract JSON from response
            $jsonMatch = [];
            if (preg_match('/\{[\s\S]*\}/', $content, $jsonMatch)) {
                $assessment = json_decode($jsonMatch[0], true);
            } else {
                $assessment = ['summary' => $content, 'risk_level' => $cr->risk_level];
            }

            // Update change request with assessment results
            $riskLevel = $assessment['risk_level'] ?? $cr->risk_level;
            if (!in_array($riskLevel, ChangeRequest::RISK_LEVELS)) {
                $riskLevel = $cr->risk_level;
            }

            $cr->update([
                'risk_assessment' => json_encode($assessment, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'risk_level' => $riskLevel,
            ]);

            return [
                'assessment' => $assessment,
                'model' => $response['model'],
                'processing_time_ms' => $response['processing_time_ms'],
            ];
        } catch (\Exception $e) {
            Log::error('Change risk assessment AI error', [
                'change_request_id' => $cr->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Request CAB review. Creates approval records for each approver.
     */
    public function requestCabReview(ChangeRequest $cr, array $approverIds): void
    {
        $this->ensureStatus($cr, [
            ChangeRequest::STATUS_SUBMITTED,
            ChangeRequest::STATUS_ASSESSMENT,
        ]);

        if (empty($approverIds)) {
            throw ValidationException::withMessages([
                'approvers' => ['At least one CAB approver is required.'],
            ]);
        }

        // Create approval records
        foreach ($approverIds as $approverId) {
            ChangeRequestApproval::create([
                'change_request_id' => $cr->id,
                'approver_id' => $approverId,
                'role' => 'cab_member',
                'status' => 'pending',
            ]);
        }

        $cr->update(['status' => ChangeRequest::STATUS_CAB_REVIEW]);
    }

    /**
     * Process CAB decision: check all approvals and determine outcome.
     */
    public function processCabDecision(ChangeRequest $cr): void
    {
        $approvals = $cr->approvals()->get();

        if ($approvals->isEmpty()) {
            return;
        }

        $pending = $approvals->where('status', 'pending')->count();
        $rejected = $approvals->where('status', 'rejected')->count();
        $approved = $approvals->where('status', 'approved')->count();

        // If any rejection, the whole CR is rejected
        if ($rejected > 0) {
            $rejectionComments = $approvals->where('status', 'rejected')
                ->pluck('comment')
                ->filter()
                ->implode('; ');

            $cr->update([
                'status' => ChangeRequest::STATUS_REJECTED,
                'cab_decision' => 'Rejected by CAB. ' . ($rejectionComments ?: 'No reason provided.'),
                'cab_decided_at' => now(),
            ]);
            return;
        }

        // All approved
        if ($pending === 0 && $approved > 0) {
            $cr->update([
                'status' => ChangeRequest::STATUS_APPROVED,
                'cab_decision' => "Approved by CAB ({$approved} approvals).",
                'cab_decided_at' => now(),
            ]);
        }
    }

    /**
     * Schedule the implementation window.
     */
    public function scheduleImplementation(ChangeRequest $cr, Carbon $start, Carbon $end): void
    {
        $this->ensureStatus($cr, [ChangeRequest::STATUS_APPROVED]);

        if ($end->lte($start)) {
            throw ValidationException::withMessages([
                'scheduled_end' => ['End date must be after start date.'],
            ]);
        }

        $cr->update([
            'status' => ChangeRequest::STATUS_SCHEDULED,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
        ]);
    }

    /**
     * Start implementation.
     */
    public function startImplementation(ChangeRequest $cr): void
    {
        $this->ensureStatus($cr, [ChangeRequest::STATUS_SCHEDULED]);

        $cr->update([
            'status' => ChangeRequest::STATUS_IMPLEMENTING,
            'actual_start' => now(),
        ]);
    }

    /**
     * Complete implementation.
     */
    public function completeImplementation(ChangeRequest $cr): void
    {
        $this->ensureStatus($cr, [ChangeRequest::STATUS_IMPLEMENTING]);

        $cr->update([
            'status' => ChangeRequest::STATUS_IMPLEMENTED,
            'actual_end' => now(),
        ]);

        // Emergency changes move directly to review for post-hoc evaluation
        if ($cr->isEmergency()) {
            $cr->update(['status' => ChangeRequest::STATUS_REVIEW]);
        }
    }

    /**
     * Post-implementation review and close.
     */
    public function closeReview(ChangeRequest $cr, string $notes): void
    {
        $this->ensureStatus($cr, [
            ChangeRequest::STATUS_IMPLEMENTED,
            ChangeRequest::STATUS_REVIEW,
        ]);

        $cr->update([
            'status' => ChangeRequest::STATUS_CLOSED,
            'review_notes' => $notes,
        ]);
    }

    /**
     * Ensure the change request is in one of the allowed statuses.
     */
    private function ensureStatus(ChangeRequest $cr, array $allowed): void
    {
        if (!in_array($cr->status, $allowed)) {
            $allowedStr = implode(', ', $allowed);
            throw ValidationException::withMessages([
                'status' => ["Action not allowed. Current status: {$cr->status}. Required: {$allowedStr}."],
            ]);
        }
    }
}
