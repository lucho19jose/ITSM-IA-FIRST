<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(
        protected ApprovalService $approvalService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Approval::with(['workflow', 'requester', 'actions.approver']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('my_approvals') && $request->boolean('my_approvals')) {
            $query->where('requested_by', $request->user()->id);
        }

        $approvals = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        // Enrich each approval with approvable info
        $approvals->getCollection()->transform(function (Approval $approval) {
            $approval->load('approvable');
            return $approval;
        });

        return response()->json($approvals);
    }

    public function show(int $id): JsonResponse
    {
        $approval = Approval::with(['workflow.steps', 'requester', 'actions.approver', 'approvable'])
            ->findOrFail($id);

        return response()->json(['data' => $approval]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $approval = Approval::findOrFail($id);

        if (!$approval->isPending()) {
            return response()->json(['message' => 'Esta aprobación ya no está pendiente'], 422);
        }

        if (!$this->approvalService->canApprove($approval, $request->user())) {
            return response()->json(['message' => 'No tiene permisos para aprobar este paso'], 403);
        }

        $validated = $request->validate([
            'comment' => 'nullable|string|max:2000',
        ]);

        $this->approvalService->processAction(
            $approval,
            $request->user(),
            'approved',
            $validated['comment'] ?? null
        );

        return response()->json([
            'data' => $approval->fresh()->load(['workflow.steps', 'requester', 'actions.approver', 'approvable']),
            'message' => 'Aprobación registrada exitosamente',
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $approval = Approval::findOrFail($id);

        if (!$approval->isPending()) {
            return response()->json(['message' => 'Esta aprobación ya no está pendiente'], 422);
        }

        if (!$this->approvalService->canApprove($approval, $request->user())) {
            return response()->json(['message' => 'No tiene permisos para rechazar este paso'], 403);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:2000',
        ]);

        $this->approvalService->processAction(
            $approval,
            $request->user(),
            'rejected',
            $validated['comment']
        );

        return response()->json([
            'data' => $approval->fresh()->load(['workflow.steps', 'requester', 'actions.approver', 'approvable']),
            'message' => 'Aprobación rechazada',
        ]);
    }

    public function myPending(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get all pending approvals and filter by those the user can approve
        $pendingApprovals = Approval::with(['workflow.steps', 'requester', 'approvable'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        $myPending = $pendingApprovals->filter(function (Approval $approval) use ($user) {
            return $this->approvalService->canApprove($approval, $user);
        })->values();

        return response()->json(['data' => $myPending]);
    }
}
