<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryResource;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TimeEntryController extends Controller
{
    public function index(Ticket $ticket): AnonymousResourceCollection
    {
        $entries = TimeEntry::with('user')
            ->where('ticket_id', $ticket->id)
            ->orderBy('executed_at', 'desc')
            ->get();

        return TimeEntryResource::collection($entries);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'hours' => 'required|numeric|min:0.01|max:999.99',
            'note' => 'nullable|string|max:2000',
            'executed_at' => 'required|date',
            'billable' => 'boolean',
        ]);

        $entry = TimeEntry::create([
            ...$validated,
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
        ]);

        ActivityLogService::log(
            $request->user(), 'time_logged', $ticket,
            "registró {$validated['hours']}h en el ticket {$ticket->title} ({$ticket->ticket_number})",
            [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'ticket_title' => $ticket->title,
                'hours' => $validated['hours'],
            ]
        );

        return response()->json([
            'data' => new TimeEntryResource($entry->load('user')),
        ], 201);
    }

    public function update(Request $request, Ticket $ticket, TimeEntry $entry): JsonResponse
    {
        if ($entry->ticket_id !== $ticket->id) {
            return response()->json(['message' => 'Entrada no pertenece al ticket'], 404);
        }

        $validated = $request->validate([
            'hours' => 'sometimes|numeric|min:0.01|max:999.99',
            'note' => 'nullable|string|max:2000',
            'executed_at' => 'sometimes|date',
            'billable' => 'boolean',
        ]);

        $entry->update($validated);

        return response()->json([
            'data' => new TimeEntryResource($entry->load('user')),
        ]);
    }

    public function destroy(Request $request, Ticket $ticket, TimeEntry $entry): JsonResponse
    {
        if ($entry->ticket_id !== $ticket->id) {
            return response()->json(['message' => 'Entrada no pertenece al ticket'], 404);
        }

        $entry->delete();

        return response()->json(['message' => 'Entrada de tiempo eliminada']);
    }
}
