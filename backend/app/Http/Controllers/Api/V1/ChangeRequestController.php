<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChangeRequestResource;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestApproval;
use App\Services\ChangeManagementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChangeRequestController extends Controller
{
    public function __construct(
        private ChangeManagementService $service,
    ) {}

    /**
     * List change requests with filters, pagination and search.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ChangeRequest::with(['category', 'requester', 'assignee', 'department']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }
        if ($request->filled('priority')) {
            $priorities = explode(',', $request->priority);
            $query->whereIn('priority', $priorities);
        }
        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }
        if ($request->filled('requested_by')) {
            $query->where('requested_by', $request->requested_by);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('reason_for_change', 'like', "%{$search}%");
            });
        }

        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('direction', 'desc');
        $allowedSorts = ['created_at', 'updated_at', 'priority', 'status', 'scheduled_start', 'title'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min((int) $request->get('per_page', 15), 100);

        return ChangeRequestResource::collection($query->paginate($perPage));
    }

    /**
     * Create a draft change request.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'sometimes|in:standard,normal,emergency',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'risk_level' => 'sometimes|in:low,medium,high,very_high',
            'impact' => 'sometimes|in:low,medium,high,extensive',
            'category_id' => 'nullable|exists:categories,id',
            'assigned_to' => 'nullable|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'reason_for_change' => 'required|string',
            'implementation_plan' => 'nullable|string',
            'rollback_plan' => 'nullable|string',
            'test_plan' => 'nullable|string',
        ]);

        $validated['requested_by'] = $request->user()->id;
        $validated['status'] = ChangeRequest::STATUS_DRAFT;

        $cr = ChangeRequest::create($validated);
        $cr->load(['category', 'requester', 'assignee', 'department']);

        return (new ChangeRequestResource($cr))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show full detail with approvals and linked tickets.
     */
    public function show(int $id): ChangeRequestResource
    {
        $cr = ChangeRequest::with([
            'category', 'requester', 'assignee', 'department', 'cabDecider',
            'tickets', 'approvals.approver',
        ])->findOrFail($id);

        return new ChangeRequestResource($cr);
    }

    /**
     * Update a draft or submitted change request.
     */
    public function update(Request $request, int $id): ChangeRequestResource
    {
        $cr = ChangeRequest::findOrFail($id);

        if (!$cr->isEditable()) {
            return abort(422, 'Change request can only be edited in draft or submitted status.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'type' => 'sometimes|in:standard,normal,emergency',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'risk_level' => 'sometimes|in:low,medium,high,very_high',
            'impact' => 'sometimes|in:low,medium,high,extensive',
            'category_id' => 'nullable|exists:categories,id',
            'assigned_to' => 'nullable|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'reason_for_change' => 'sometimes|string',
            'implementation_plan' => 'nullable|string',
            'rollback_plan' => 'nullable|string',
            'test_plan' => 'nullable|string',
        ]);

        $cr->update($validated);
        $cr->load(['category', 'requester', 'assignee', 'department', 'approvals.approver', 'tickets']);

        return new ChangeRequestResource($cr);
    }

    /**
     * Soft delete a draft change request.
     */
    public function destroy(int $id): JsonResponse
    {
        $cr = ChangeRequest::findOrFail($id);

        if (!$cr->isDeletable()) {
            return response()->json(['message' => 'Only draft change requests can be deleted.'], 422);
        }

        $cr->delete();

        return response()->json(['message' => 'Change request deleted.']);
    }

    /**
     * Submit for review.
     */
    public function submit(int $id): ChangeRequestResource
    {
        $cr = ChangeRequest::findOrFail($id);
        $this->service->submitForReview($cr);
        $cr->refresh()->load(['category', 'requester', 'assignee', 'department', 'approvals.approver', 'tickets']);

        return new ChangeRequestResource($cr);
    }

    /**
     * Trigger AI risk assessment.
     */
    public function assessRisk(int $id): JsonResponse
    {
        $cr = ChangeRequest::findOrFail($id);
        $result = $this->service->assessRisk($cr);
        $cr->refresh();

        return response()->json([
            'data' => new ChangeRequestResource($cr->load(['category', 'requester', 'assignee', 'department'])),
            'assessment' => $result['assessment'],
            'model' => $result['model'],
            'processing_time_ms' => $result['processing_time_ms'],
        ]);
    }

    /**
     * Send to CAB with approver list.
     */
    public function requestCabReview(Request $request, int $id): ChangeRequestResource
    {
        $request->validate([
            'approver_ids' => 'required|array|min:1',
            'approver_ids.*' => 'exists:users,id',
        ]);

        $cr = ChangeRequest::findOrFail($id);
        $this->service->requestCabReview($cr, $request->approver_ids);
        $cr->refresh()->load(['category', 'requester', 'assignee', 'department', 'approvals.approver', 'tickets']);

        return new ChangeRequestResource($cr);
    }

    /**
     * CAB member approves.
     */
    public function approveCab(Request $request, int $id): ChangeRequestResource
    {
        $cr = ChangeRequest::findOrFail($id);
        $userId = $request->user()->id;

        $approval = ChangeRequestApproval::where('change_request_id', $cr->id)
            ->where('approver_id', $userId)
            ->where('status', 'pending')
            ->firstOrFail();

        $approval->update([
            'status' => 'approved',
            'comment' => $request->get('comment'),
            'decided_at' => now(),
        ]);

        // Check if all approvals are done
        $this->service->processCabDecision($cr);

        $cr->refresh()->load(['category', 'requester', 'assignee', 'department', 'approvals.approver', 'tickets']);

        return new ChangeRequestResource($cr);
    }

    /**
     * CAB member rejects with reason.
     */
    public function rejectCab(Request $request, int $id): ChangeRequestResource
    {
        $request->validate([
            'comment' => 'required|string|min:5',
        ]);

        $cr = ChangeRequest::findOrFail($id);
        $userId = $request->user()->id;

        $approval = ChangeRequestApproval::where('change_request_id', $cr->id)
            ->where('approver_id', $userId)
            ->where('status', 'pending')
            ->firstOrFail();

        $approval->update([
            'status' => 'rejected',
            'comment' => $request->comment,
            'decided_at' => now(),
        ]);

        $this->service->processCabDecision($cr);

        $cr->refresh()->load(['category', 'requester', 'assignee', 'department', 'approvals.approver', 'tickets']);

        return new ChangeRequestResource($cr);
    }

    /**
     * Set implementation window.
     */
    public function schedule(Request $request, int $id): ChangeRequestResource
    {
        $request->validate([
            'scheduled_start' => 'required|date',
            'scheduled_end' => 'required|date|after:scheduled_start',
        ]);

        $cr = ChangeRequest::findOrFail($id);
        $this->service->scheduleImplementation(
            $cr,
            Carbon::parse($request->scheduled_start),
            Carbon::parse($request->scheduled_end),
        );

        $cr->refresh()->load(['category', 'requester', 'assignee', 'department', 'approvals.approver', 'tickets']);

        return new ChangeRequestResource($cr);
    }

    /**
     * Begin implementation.
     */
    public function startImplementation(int $id): ChangeRequestResource
    {
        $cr = ChangeRequest::findOrFail($id);
        $this->service->startImplementation($cr);
        $cr->refresh()->load(['category', 'requester', 'assignee', 'department', 'approvals.approver', 'tickets']);

        return new ChangeRequestResource($cr);
    }

    /**
     * Mark implemented.
     */
    public function completeImplementation(int $id): ChangeRequestResource
    {
        $cr = ChangeRequest::findOrFail($id);
        $this->service->completeImplementation($cr);
        $cr->refresh()->load(['category', 'requester', 'assignee', 'department', 'approvals.approver', 'tickets']);

        return new ChangeRequestResource($cr);
    }

    /**
     * Post-implementation review and close.
     */
    public function closeReview(Request $request, int $id): ChangeRequestResource
    {
        $request->validate([
            'review_notes' => 'required|string|min:10',
        ]);

        $cr = ChangeRequest::findOrFail($id);
        $this->service->closeReview($cr, $request->review_notes);
        $cr->refresh()->load(['category', 'requester', 'assignee', 'department', 'approvals.approver', 'tickets']);

        return new ChangeRequestResource($cr);
    }

    /**
     * Link related tickets.
     */
    public function linkTickets(Request $request, int $id): ChangeRequestResource
    {
        $request->validate([
            'ticket_ids' => 'required|array|min:1',
            'ticket_ids.*' => 'exists:tickets,id',
            'relationship_type' => 'sometimes|in:caused_by,related,implements',
        ]);

        $cr = ChangeRequest::findOrFail($id);
        $relType = $request->get('relationship_type', 'related');

        foreach ($request->ticket_ids as $ticketId) {
            $cr->tickets()->syncWithoutDetaching([
                $ticketId => ['relationship_type' => $relType],
            ]);
        }

        $cr->load(['category', 'requester', 'assignee', 'department', 'approvals.approver', 'tickets']);

        return new ChangeRequestResource($cr);
    }

    /**
     * Return scheduled changes for calendar view.
     */
    public function calendar(Request $request): JsonResponse
    {
        $query = ChangeRequest::with(['requester', 'assignee'])
            ->whereNotNull('scheduled_start')
            ->whereNotNull('scheduled_end');

        if ($request->filled('from')) {
            $query->where('scheduled_end', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('scheduled_start', '<=', $request->to);
        }

        $changes = $query->orderBy('scheduled_start')->get();

        $events = $changes->map(fn ($cr) => [
            'id' => $cr->id,
            'title' => $cr->title,
            'start' => $cr->scheduled_start->toISOString(),
            'end' => $cr->scheduled_end->toISOString(),
            'status' => $cr->status,
            'type' => $cr->type,
            'priority' => $cr->priority,
            'risk_level' => $cr->risk_level,
            'requester' => $cr->requester ? ['id' => $cr->requester->id, 'name' => $cr->requester->name] : null,
            'assignee' => $cr->assignee ? ['id' => $cr->assignee->id, 'name' => $cr->assignee->name] : null,
        ]);

        return response()->json(['data' => $events]);
    }
}
