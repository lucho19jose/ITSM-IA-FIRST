<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\TicketCreated;
use App\Events\TicketUpdated;
use App\Events\TicketCommentAdded;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Http\Resources\TicketCommentResource;
use App\Mail\SatisfactionSurveyMail;
use App\Mail\TicketAssignedMail;
use App\Mail\TicketClosedMail;
use App\Models\NotificationPreference;
use App\Models\SatisfactionSurvey;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\SlaPolicy;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\BusinessHourService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function __construct(
        private BusinessHourService $businessHourService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Ticket::with(['category', 'requester', 'assignee', 'department']);

        if ($request->user()->isEndUser()) {
            $query->where('requester_id', $request->user()->id);
        }

        if ($request->filled('status')) {
            $statuses = explode(',', $request->status);
            if (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            } else {
                $query->where('status', $statuses[0]);
            }
        }
        if ($request->filled('priority')) {
            $priorities = explode(',', $request->priority);
            if (count($priorities) > 1) {
                $query->whereIn('priority', $priorities);
            } else {
                $query->where('priority', $priorities[0]);
            }
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('impact')) {
            $query->where('impact', $request->impact);
        }
        if ($request->filled('urgency')) {
            $query->where('urgency', $request->urgency);
        }
        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('ticket_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status_not_in')) {
            $excludeStatuses = explode(',', $request->status_not_in);
            $query->whereNotIn('status', $excludeStatuses);
        }

        if ($request->filled('requester_id')) {
            $query->where('requester_id', $request->requester_id);
        }

        if ($request->filled('assigned_to_me') && $request->boolean('assigned_to_me')) {
            $query->where('assigned_to', $request->user()->id);
        }

        if ($request->filled('overdue') && $request->boolean('overdue')) {
            $query->where('resolution_due_at', '<', now())
                  ->whereNotIn('status', ['resolved', 'closed']);
        }

        if ($request->filled('response_overdue') && $request->boolean('response_overdue')) {
            $query->where('response_due_at', '<', now())
                  ->whereNull('responded_at');
        }

        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }

        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDir);

        return TicketResource::collection($query->paginate($request->get('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:incident,request,problem,change',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'category_id' => 'nullable|exists:categories,id',
            'tags' => 'nullable|array',
            'due_date' => 'nullable|date|after:now',
            'custom_fields' => 'nullable|array',
            'assigned_to' => 'nullable|exists:users,id',
            'source' => 'nullable|string|in:portal,email,phone,chatbot',
            'cc_emails' => 'nullable',
            'department_id' => 'nullable|exists:departments,id',
            'subcategory' => 'nullable|string|max:100',
            'item' => 'nullable|string|max:100',
            'impact' => 'nullable|in:low,medium,high',
            'urgency' => 'nullable|in:low,medium,high',
            'contact_number' => 'nullable|string|max:30',
            'requester_location' => 'nullable|string|max:255',
            'specific_subject' => 'nullable|string|max:255',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'planned_effort' => 'nullable|string|max:50',
            'approval_status' => 'nullable|in:not_requested,requested,approved,rejected',
            'association_type' => 'nullable|in:parent,child,related,cause',
            'major_incident_type' => 'nullable|string|max:50',
            'customers_impacted' => 'nullable|integer|min:0',
            'impacted_locations' => 'nullable|array',
            'asset_id' => 'nullable|exists:assets,id',
        ]);

        $validated['requester_id'] = $request->user()->id;
        $validated['status'] = 'open';

        // Only admin/agent can override source and assigned_to
        if ($request->user()->isEndUser()) {
            $validated['source'] = Ticket::SOURCE_PORTAL;
            unset($validated['assigned_to']);
        } else {
            $validated['source'] = $validated['source'] ?? Ticket::SOURCE_PORTAL;
        }

        // Auto-assign SLA policy based on priority
        $priority = $validated['priority'] ?? 'medium';
        $sla = SlaPolicy::where('priority', $priority)->where('is_active', true)->first();
        if ($sla) {
            $validated['sla_policy_id'] = $sla->id;
            $bh = $sla->businessHour;
            $validated['response_due_at'] = $this->businessHourService->calculateDeadline(now(), $sla->response_time, $bh);
            $validated['resolution_due_at'] = $this->businessHourService->calculateDeadline(now(), $sla->resolution_time, $bh);
        }

        $ticket = Ticket::create($validated);

        // Dispatch AI classification job if available
        if (class_exists(\App\Jobs\ClassifyTicketJob::class)) {
            \App\Jobs\ClassifyTicketJob::dispatch($ticket)->onQueue('ai');
        }

        broadcast(new TicketCreated($ticket))->toOthers();

        ActivityLogService::log(
            $request->user(), 'created', $ticket,
            "envió un nuevo ticket {$ticket->title} ({$ticket->ticket_number})",
            ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'ticket_title' => $ticket->title]
        );

        return response()->json([
            'data' => new TicketResource($ticket->load(['category', 'requester', 'assignee', 'department'])),
        ], 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $ticket->load(['category', 'requester', 'assignee', 'department', 'slaPolicy', 'comments.user', 'comments.attachments', 'attachments.user', 'timeEntries.user', 'agentGroup', 'asset.assetType']);

        return response()->json([
            'data' => new TicketResource($ticket),
        ]);
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'type' => 'sometimes|in:incident,request,problem,change',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'status' => 'sometimes|in:open,in_progress,pending,resolved,closed',
            'category_id' => 'nullable|exists:categories,id',
            'tags' => 'nullable|array',
            'department_id' => 'nullable|exists:departments,id',
            'subcategory' => 'nullable|string|max:100',
            'item' => 'nullable|string|max:100',
            'impact' => 'nullable|in:low,medium,high',
            'urgency' => 'nullable|in:low,medium,high',
            'contact_number' => 'nullable|string|max:30',
            'requester_location' => 'nullable|string|max:255',
            'specific_subject' => 'nullable|string|max:255',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'planned_effort' => 'nullable|string|max:50',
            'approval_status' => 'nullable|in:not_requested,requested,approved,rejected',
            'association_type' => 'nullable|in:parent,child,related,cause',
            'major_incident_type' => 'nullable|string|max:50',
            'customers_impacted' => 'nullable|integer|min:0',
            'impacted_locations' => 'nullable|array',
            'resolution_notes' => 'nullable|string|max:5000',
            'agent_group_id' => 'nullable|exists:agent_groups,id',
            'asset_id' => 'nullable|exists:assets,id',
        ]);

        // Track status transitions
        if (isset($validated['status'])) {
            if ($validated['status'] === 'resolved' && !$ticket->resolved_at) {
                $validated['resolved_at'] = now();
            }
            if ($validated['status'] === 'closed' && !$ticket->closed_at) {
                $validated['closed_at'] = now();
            }
            if (in_array($validated['status'], ['open', 'in_progress']) && $ticket->status === 'resolved') {
                $validated['resolved_at'] = null;
            }
        }

        // Update SLA if priority changed
        if (isset($validated['priority']) && $validated['priority'] !== $ticket->priority) {
            $sla = SlaPolicy::where('priority', $validated['priority'])->where('is_active', true)->first();
            if ($sla) {
                $validated['sla_policy_id'] = $sla->id;
                $bh = $sla->businessHour;
                if (!$ticket->responded_at) {
                    $validated['response_due_at'] = $this->businessHourService->calculateDeadline($ticket->created_at, $sla->response_time, $bh);
                }
                $validated['resolution_due_at'] = $this->businessHourService->calculateDeadline($ticket->created_at, $sla->resolution_time, $bh);
            }
        }

        // Capture old values for activity log tracking
        $trackFields = ['status', 'priority', 'planned_start_date', 'planned_end_date'];
        $oldValues = [];
        foreach ($trackFields as $f) {
            if (array_key_exists($f, $validated)) {
                $oldValues[$f] = $ticket->{$f};
            }
        }

        $changedFields = array_keys($validated);
        $ticket->update($validated);

        broadcast(new TicketUpdated($ticket, $changedFields))->toOthers();

        ActivityLogService::logTicketFieldChanges($request->user(), $ticket, $oldValues, $validated);

        return response()->json([
            'data' => new TicketResource($ticket->load(['category', 'requester', 'assignee', 'department'])),
        ]);
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $this->authorize('delete', $ticket);
        $ticket->delete();
        return response()->json(['message' => 'Ticket eliminado'], 200);
    }

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('assign', $ticket);

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $ticket->update([
            'assigned_to' => $validated['assigned_to'],
            'status' => $ticket->status === 'open' ? 'in_progress' : $ticket->status,
            'responded_at' => $ticket->responded_at ?? now(),
        ]);

        broadcast(new TicketUpdated($ticket, ['assigned_to', 'status']))->toOthers();

        $assignee = User::find($validated['assigned_to']);
        ActivityLogService::log(
            $request->user(), 'assigned', $ticket,
            "asignó el ticket {$ticket->title} ({$ticket->ticket_number}) a {$assignee->name}",
            [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'ticket_title' => $ticket->title,
                'assignee_id' => $assignee->id,
                'assignee_name' => $assignee->name,
            ]
        );

        // Queue assignment email to the agent
        if ($assignee && $assignee->email) {
            $prefs = NotificationPreference::getOrCreate($assignee->id, $ticket->tenant_id);
            if ($prefs->wantsEmail('ticket_assigned')) {
                Mail::to($assignee->email)->queue(new TicketAssignedMail($ticket, $assignee));
            }
        }

        return response()->json([
            'data' => new TicketResource($ticket->load(['category', 'requester', 'assignee', 'department'])),
        ]);
    }

    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
            'resolved_at' => $ticket->resolved_at ?? now(),
        ]);

        broadcast(new TicketUpdated($ticket, ['status', 'closed_at']))->toOthers();

        ActivityLogService::log(
            $request->user(), 'closed', $ticket,
            "cerró el ticket {$ticket->title} ({$ticket->ticket_number})",
            ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'ticket_title' => $ticket->title]
        );

        // Queue closed email to the requester
        $ticket->loadMissing('requester');
        $requester = $ticket->requester;
        if ($requester && $requester->email) {
            $prefs = NotificationPreference::getOrCreate($requester->id, $ticket->tenant_id);
            if ($prefs->wantsEmail('ticket_closed')) {
                Mail::to($requester->email)->queue(new TicketClosedMail($ticket));
            }
        }

        // Create satisfaction survey and send email to requester
        if ($requester && $requester->email && !SatisfactionSurvey::withoutGlobalScopes()->where('ticket_id', $ticket->id)->exists()) {
            $survey = SatisfactionSurvey::create([
                'tenant_id' => $ticket->tenant_id,
                'ticket_id' => $ticket->id,
                'user_id' => $ticket->requester_id,
                'sent_at' => now(),
            ]);

            Mail::to($requester->email)
                ->queue(new SatisfactionSurveyMail($survey, $ticket));
        }

        return response()->json([
            'data' => new TicketResource($ticket->load(['category', 'requester', 'assignee', 'department'])),
        ]);
    }

    public function reopen(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $ticket->update([
            'status' => 'open',
            'closed_at' => null,
            'resolved_at' => null,
        ]);

        broadcast(new TicketUpdated($ticket, ['status']))->toOthers();

        ActivityLogService::log(
            $request->user(), 'reopened', $ticket,
            "reabrió el ticket {$ticket->title} ({$ticket->ticket_number})",
            ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'ticket_title' => $ticket->title]
        );

        return response()->json([
            'data' => new TicketResource($ticket->load(['category', 'requester', 'assignee', 'department'])),
        ]);
    }

    public function addComment(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $validated = $request->validate([
            'body' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        // End users can't create internal notes
        if ($request->user()->isEndUser()) {
            $validated['is_internal'] = false;
        }

        $comment = $ticket->comments()->create([
            ...$validated,
            'user_id' => $request->user()->id,
            'tenant_id' => app('tenant_id'),
        ]);

        broadcast(new TicketCommentAdded($comment->load('user')))->toOthers();

        $replyType = $validated['is_internal'] ?? false ? 'una nota interna' : 'una reply';
        ActivityLogService::log(
            $request->user(), 'commented', $ticket,
            "ha enviado {$replyType} al ticket {$ticket->title} ({$ticket->ticket_number})",
            ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'ticket_title' => $ticket->title, 'is_internal' => $validated['is_internal'] ?? false]
        );

        return response()->json([
            'data' => new TicketCommentResource($comment->load('user')),
        ], 201);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        // Only admin and agent can bulk update
        if ($request->user()->isEndUser()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'ticket_ids' => 'required|array|min:1',
            'ticket_ids.*' => 'integer|exists:tickets,id',
            'status' => 'sometimes|in:open,in_progress,pending,resolved,closed',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'assigned_to' => 'sometimes|nullable|exists:users,id',
        ]);

        $ticketIds = $validated['ticket_ids'];
        unset($validated['ticket_ids']);

        if (empty($validated)) {
            return response()->json(['message' => 'No hay cambios que aplicar'], 422);
        }

        $updated = 0;
        foreach ($ticketIds as $id) {
            $ticket = Ticket::find($id);
            if (!$ticket) continue;

            $updateData = $validated;

            // Track status transitions
            if (isset($updateData['status'])) {
                if ($updateData['status'] === 'resolved' && !$ticket->resolved_at) {
                    $updateData['resolved_at'] = now();
                }
                if ($updateData['status'] === 'closed' && !$ticket->closed_at) {
                    $updateData['closed_at'] = now();
                }
            }

            // Update SLA if priority changed
            if (isset($updateData['priority']) && $updateData['priority'] !== $ticket->priority) {
                $sla = \App\Models\SlaPolicy::where('priority', $updateData['priority'])->where('is_active', true)->first();
                if ($sla) {
                    $updateData['sla_policy_id'] = $sla->id;
                    $bh = $sla->businessHour;
                    if (!$ticket->responded_at) {
                        $updateData['response_due_at'] = $this->businessHourService->calculateDeadline($ticket->created_at, $sla->response_time, $bh);
                    }
                    $updateData['resolution_due_at'] = $this->businessHourService->calculateDeadline($ticket->created_at, $sla->resolution_time, $bh);
                }
            }

            // Auto-set responded_at when assigning
            if (isset($updateData['assigned_to']) && $updateData['assigned_to'] && !$ticket->responded_at) {
                $updateData['responded_at'] = now();
                if ($ticket->status === 'open') {
                    $updateData['status'] = $updateData['status'] ?? 'in_progress';
                }
            }

            $ticket->update($updateData);
            $updated++;
        }

        return response()->json([
            'message' => $updated . ' ticket(s) actualizado(s)',
            'count' => $updated,
        ]);
    }

    public function quickUpdate(Request $request, Ticket $ticket): JsonResponse
    {
        // Check if user can update (reuse policy)
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'status' => 'sometimes|in:open,in_progress,pending,resolved,closed',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'assigned_to' => 'sometimes|nullable|exists:users,id',
        ]);

        // Track status transitions
        if (isset($validated['status'])) {
            if ($validated['status'] === 'resolved' && !$ticket->resolved_at) {
                $validated['resolved_at'] = now();
            }
            if ($validated['status'] === 'closed' && !$ticket->closed_at) {
                $validated['closed_at'] = now();
            }
            if (in_array($validated['status'], ['open', 'in_progress']) && $ticket->status === 'resolved') {
                $validated['resolved_at'] = null;
            }
        }

        // Update SLA if priority changed
        if (isset($validated['priority']) && $validated['priority'] !== $ticket->priority) {
            $sla = \App\Models\SlaPolicy::where('priority', $validated['priority'])->where('is_active', true)->first();
            if ($sla) {
                $validated['sla_policy_id'] = $sla->id;
                $bh = $sla->businessHour;
                if (!$ticket->responded_at) {
                    $validated['response_due_at'] = $this->businessHourService->calculateDeadline($ticket->created_at, $sla->response_time, $bh);
                }
                $validated['resolution_due_at'] = $this->businessHourService->calculateDeadline($ticket->created_at, $sla->resolution_time, $bh);
            }
        }

        // Auto-set responded_at when assigning
        if (isset($validated['assigned_to']) && $validated['assigned_to'] && !$ticket->responded_at) {
            $validated['responded_at'] = now();
            if ($ticket->status === 'open' && !isset($validated['status'])) {
                $validated['status'] = 'in_progress';
            }
        }

        // Capture old values for activity log
        $trackFields = ['status', 'priority'];
        $oldValues = [];
        foreach ($trackFields as $f) {
            if (array_key_exists($f, $validated)) {
                $oldValues[$f] = $ticket->{$f};
            }
        }

        $changedFields = array_keys($validated);
        $ticket->update($validated);

        broadcast(new TicketUpdated($ticket, $changedFields))->toOthers();

        ActivityLogService::logTicketFieldChanges($request->user(), $ticket, $oldValues, $validated);

        // Log assignment separately if it changed
        if (isset($validated['assigned_to']) && $validated['assigned_to']) {
            $assignee = User::find($validated['assigned_to']);
            if ($assignee) {
                ActivityLogService::log(
                    $request->user(), 'assigned', $ticket,
                    "asignó el ticket {$ticket->title} ({$ticket->ticket_number}) a {$assignee->name}",
                    [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'ticket_title' => $ticket->title,
                        'assignee_id' => $assignee->id,
                        'assignee_name' => $assignee->name,
                    ]
                );
            }
        }

        return response()->json([
            'data' => new TicketResource($ticket->load(['category', 'requester', 'assignee', 'department'])),
        ]);
    }

    public function addAttachments(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'file|max:10240', // 10MB per file
            'comment_id' => 'nullable|integer|exists:ticket_comments,id',
        ]);

        $attachments = [];

        foreach ($request->file('files') as $file) {
            $path = $file->store("tickets/{$ticket->id}", 'public');

            $attachments[] = $ticket->attachments()->create([
                'filename' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'user_id' => $request->user()->id,
                'tenant_id' => app('tenant_id'),
                'comment_id' => $request->input('comment_id'),
            ]);
        }

        return response()->json(['data' => $attachments], 201);
    }

    public function deleteAttachment(Request $request, Ticket $ticket, TicketAttachment $attachment): JsonResponse
    {
        $this->authorize('update', $ticket);

        if ($attachment->ticket_id !== $ticket->id) {
            return response()->json(['message' => 'Archivo no pertenece al ticket'], 404);
        }

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return response()->json(['message' => 'Archivo eliminado']);
    }

    public function tags(Request $request): JsonResponse
    {
        $tags = Ticket::whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        return response()->json(['data' => $tags]);
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'fields' => 'required|array|min:1',
            'fields.*' => 'string',
            'format' => 'in:csv,excel',
            'filter_field' => 'nullable|string',
            'filter_period' => 'nullable|string',
        ]);

        $query = Ticket::with(['category', 'requester', 'assignee', 'department', 'slaPolicy']);

        if ($request->user()->isEndUser()) {
            $query->where('requester_id', $request->user()->id);
        }

        // Apply existing filters
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('priority')) $query->where('priority', $request->priority);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('ticket_number', 'like', "%{$search}%");
            });
        }

        // Date filter
        if ($request->filled('filter_field') && $request->filled('filter_period')) {
            $field = $request->filter_field;
            $period = $request->filter_period;
            $allowedFields = ['created_at', 'updated_at', 'resolved_at', 'closed_at'];
            if (in_array($field, $allowedFields)) {
                $days = match ($period) {
                    '7d' => 7, '30d' => 30, '60d' => 60, '90d' => 90, default => null,
                };
                if ($days) $query->where($field, '>=', now()->subDays($days));
            }
        }

        $query->orderBy('created_at', 'desc');
        $tickets = $query->get();
        $fields = $request->input('fields');

        // Field label mapping
        $fieldLabels = [
            'ticket_number' => 'ID del Ticket',
            'title' => 'Asunto',
            'type' => 'Tipo',
            'source' => 'Origen',
            'status' => 'Estado',
            'urgency' => 'Urgencia',
            'impact' => 'Impacto',
            'priority' => 'Prioridad',
            'department' => 'Departamento',
            'assignee' => 'Agente',
            'requester_name' => 'Nombre del solicitante',
            'requester_email' => 'Correo del solicitante',
            'description' => 'Descripción',
            'category' => 'Categoría',
            'subcategory' => 'Subcategoría',
            'item' => 'Elemento',
            'tags' => 'Etiquetas',
            'created_at' => 'Hora de creación',
            'updated_at' => 'Hora de última actualización',
            'due_date' => 'Hora de vencimiento',
            'resolved_at' => 'Hora de resolución',
            'closed_at' => 'Hora de cierre',
            'response_due_at' => 'Tiempo inicial de respuesta',
            'resolution_due_at' => 'Resolución pendiente',
            'planned_start_date' => 'Fecha de inicio planificada',
            'planned_end_date' => 'Fecha de finalización planificada',
            'planned_effort' => 'Esfuerzo planificado',
            'approval_status' => 'Estado de aprobación',
            'association_type' => 'Tipo de asociación',
            'requester_location' => 'Ubicación del solicitante',
            'requester_vip' => 'Solicitante VIP',
            'major_incident_type' => 'Major incident type',
            'impacted_locations' => 'Impacted locations',
            'customers_impacted' => 'No. of customers impacted',
            'specific_subject' => 'Asunto específico',
            'contact_number' => 'Número de contacto',
            'satisfaction_rating' => 'Resultado de encuestas',
            'status_details' => 'Detalles de estado',
        ];

        $filename = 'tickets_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($tickets, $fields, $fieldLabels) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            $headers = array_map(fn($f) => $fieldLabels[$f] ?? $f, $fields);
            fputcsv($handle, $headers);

            // Data rows
            foreach ($tickets as $ticket) {
                $row = [];
                foreach ($fields as $field) {
                    $row[] = match ($field) {
                        'department' => $ticket->department?->name ?? '',
                        'assignee' => $ticket->assignee?->name ?? '',
                        'requester_name' => $ticket->requester?->name ?? '',
                        'requester_email' => $ticket->requester?->email ?? '',
                        'category' => $ticket->category?->name ?? '',
                        'description' => strip_tags($ticket->description ?? ''),
                        'tags' => implode(', ', $ticket->tags ?? []),
                        'impacted_locations' => implode(', ', $ticket->impacted_locations ?? []),
                        'requester_vip' => $ticket->requester?->is_vip ? 'Sí' : 'No',
                        default => $ticket->{$field} ?? '',
                    };
                }
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function merge(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'source_ticket_id' => 'required|integer|exists:tickets,id',
        ]);

        $source = Ticket::with(['comments', 'attachments', 'timeEntries'])->findOrFail($validated['source_ticket_id']);

        if ($source->id === $ticket->id) {
            return response()->json(['message' => 'No puede combinar un ticket consigo mismo'], 422);
        }

        // Move comments from source to target
        foreach ($source->comments as $comment) {
            $comment->update(['ticket_id' => $ticket->id]);
        }

        // Move attachments from source to target
        foreach ($source->attachments as $attachment) {
            $attachment->update(['ticket_id' => $ticket->id]);
        }

        // Move time entries from source to target
        foreach ($source->timeEntries as $entry) {
            $entry->update(['ticket_id' => $ticket->id]);
        }

        // Add merge note to target
        $ticket->comments()->create([
            'body' => "Ticket combinado desde <strong>{$source->ticket_number}</strong>: {$source->title}",
            'is_internal' => true,
            'user_id' => $request->user()->id,
            'tenant_id' => app('tenant_id'),
        ]);

        // Close source ticket
        $source->update([
            'status' => 'closed',
            'closed_at' => now(),
            'resolved_at' => $source->resolved_at ?? now(),
        ]);

        // Add note to source
        $source->comments()->create([
            'body' => "Este ticket fue combinado en <strong>{$ticket->ticket_number}</strong>: {$ticket->title}",
            'is_internal' => true,
            'user_id' => $request->user()->id,
            'tenant_id' => app('tenant_id'),
        ]);

        ActivityLogService::log(
            $request->user(), 'merged', $ticket,
            "combinó el ticket {$source->ticket_number} en {$ticket->title} ({$ticket->ticket_number})",
            [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'ticket_title' => $ticket->title,
                'source_ticket_id' => $source->id,
                'source_ticket_number' => $source->ticket_number,
            ]
        );

        return response()->json([
            'data' => new TicketResource($ticket->fresh()->load(['category', 'requester', 'assignee', 'department', 'comments.user', 'attachments.user', 'timeEntries.user'])),
            'message' => "Ticket {$source->ticket_number} combinado exitosamente",
        ]);
    }

    public function toggleSpam(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $ticket->update(['is_spam' => !$ticket->is_spam]);

        $action = $ticket->is_spam ? 'marcó como basura' : 'desmarcó como basura';
        ActivityLogService::log(
            $request->user(), 'spam_toggled', $ticket,
            "{$action} el ticket {$ticket->title} ({$ticket->ticket_number})",
            ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'ticket_title' => $ticket->title, 'is_spam' => $ticket->is_spam]
        );

        return response()->json([
            'data' => new TicketResource($ticket->load(['category', 'requester', 'assignee', 'department'])),
            'message' => $ticket->is_spam ? 'Ticket marcado como basura' : 'Ticket desmarcado como basura',
        ]);
    }

    public function toggleFavorite(Request $request, Ticket $ticket): JsonResponse
    {
        $userId = $request->user()->id;
        $exists = \DB::table('ticket_favorites')
            ->where('user_id', $userId)
            ->where('ticket_id', $ticket->id)
            ->exists();

        if ($exists) {
            \DB::table('ticket_favorites')
                ->where('user_id', $userId)
                ->where('ticket_id', $ticket->id)
                ->delete();
            return response()->json(['is_favorite' => false, 'message' => 'Eliminado de favoritos']);
        }

        \DB::table('ticket_favorites')->insert([
            'user_id' => $userId,
            'ticket_id' => $ticket->id,
            'created_at' => now(),
        ]);

        return response()->json(['is_favorite' => true, 'message' => 'Agregado a favoritos']);
    }

    public function share(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $validated = $request->validate([
            'email' => 'required|email',
            'message' => 'nullable|string|max:2000',
        ]);

        $ticket->load('requester');

        \Illuminate\Support\Facades\Mail::to($validated['email'])
            ->send(new \App\Mail\TicketShared(
                $ticket,
                $request->user(),
                $validated['message'] ?? '',
            ));

        ActivityLogService::log(
            $request->user(), 'shared', $ticket,
            "compartió el ticket {$ticket->title} ({$ticket->ticket_number}) con {$validated['email']}",
            [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'ticket_title' => $ticket->title,
                'shared_with' => $validated['email'],
            ]
        );

        return response()->json(['message' => "Ticket compartido con {$validated['email']}"]);
    }
}
