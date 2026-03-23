<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\ApprovalAction;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\ServiceCatalogItem;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ApprovalService
{
    /**
     * Create a new approval process for a given approvable model.
     */
    public function createApproval(Model $approvable, ApprovalWorkflow $workflow, User $requester): Approval
    {
        return Approval::create([
            'approvable_type' => get_class($approvable),
            'approvable_id' => $approvable->id,
            'workflow_id' => $workflow->id,
            'current_step' => 1,
            'status' => 'pending',
            'requested_by' => $requester->id,
        ]);
    }

    /**
     * Process an approve or reject action on an approval.
     */
    public function processAction(Approval $approval, User $approver, string $action, ?string $comment = null): void
    {
        // Record the action
        ApprovalAction::create([
            'approval_id' => $approval->id,
            'step_order' => $approval->current_step,
            'approver_id' => $approver->id,
            'action' => $action,
            'comment' => $comment,
            'acted_at' => now(),
            'created_at' => now(),
        ]);

        if ($action === 'rejected') {
            $approval->update(['status' => 'rejected']);
            return;
        }

        if ($action === 'approved') {
            $totalSteps = $approval->workflow->steps()->count();

            if ($approval->current_step >= $totalSteps) {
                // Last step approved — mark as fully approved
                $approval->update(['status' => 'approved']);
                $this->executePostApproval($approval);
            } else {
                // Advance to next step
                $approval->update(['current_step' => $approval->current_step + 1]);
            }
        }
    }

    /**
     * Get the list of users who can approve the current step of an approval.
     */
    public function getCurrentApprovers(Approval $approval): Collection
    {
        $step = $approval->currentStepDefinition();
        if (!$step) {
            return collect();
        }

        return $this->resolveApproversForStep($step, $approval);
    }

    /**
     * Check if a given user can approve the current step.
     */
    public function canApprove(Approval $approval, User $user): bool
    {
        if (!$approval->isPending()) {
            return false;
        }

        $approvers = $this->getCurrentApprovers($approval);
        return $approvers->contains('id', $user->id);
    }

    /**
     * Resolve which users should approve a given step.
     */
    protected function resolveApproversForStep(ApprovalWorkflowStep $step, Approval $approval): Collection
    {
        return match ($step->approver_type) {
            'user' => $step->approver_id
                ? User::where('id', $step->approver_id)->where('is_active', true)->get()
                : collect(),

            'role' => User::where('role', $step->approver_role)
                ->where('is_active', true)
                ->get(),

            'department_head' => $this->resolveDepartmentHead($approval),

            default => collect(),
        };
    }

    /**
     * Resolve the department head for the requester's department.
     */
    protected function resolveDepartmentHead(Approval $approval): Collection
    {
        $requester = $approval->requester;
        if (!$requester || !$requester->department_id) {
            return collect();
        }

        $department = $requester->department;
        if (!$department || !$department->head_id) {
            return collect();
        }

        return User::where('id', $department->head_id)->where('is_active', true)->get();
    }

    /**
     * Execute post-approval actions (e.g., create ticket for catalog request).
     */
    protected function executePostApproval(Approval $approval): void
    {
        $approvable = $approval->approvable;

        if ($approvable instanceof ServiceCatalogItem) {
            $this->createTicketFromCatalogApproval($approval, $approvable);
        }
    }

    /**
     * Create a ticket after a catalog request has been approved.
     */
    protected function createTicketFromCatalogApproval(Approval $approval, ServiceCatalogItem $catalogItem): void
    {
        $ticketData = [
            'title' => "Solicitud aprobada: {$catalogItem->name}",
            'description' => "Solicitud del catálogo aprobada: {$catalogItem->name}",
            'type' => 'request',
            'status' => 'open',
            'priority' => 'medium',
            'source' => Ticket::SOURCE_CATALOG,
            'requester_id' => $approval->requested_by,
            'tags' => ['catalog', $catalogItem->slug, 'approved'],
        ];

        // Auto-assign SLA policy
        $sla = SlaPolicy::where('priority', 'medium')->where('is_active', true)->first();
        if ($sla) {
            $ticketData['sla_policy_id'] = $sla->id;
            $ticketData['response_due_at'] = now()->addMinutes($sla->response_time);
            $ticketData['resolution_due_at'] = now()->addMinutes($sla->resolution_time);
        }

        $ticket = Ticket::create($ticketData);

        // Dispatch AI classification if available
        if (class_exists(\App\Jobs\ClassifyTicketJob::class)) {
            \App\Jobs\ClassifyTicketJob::dispatch($ticket)->onQueue('ai');
        }
    }
}
