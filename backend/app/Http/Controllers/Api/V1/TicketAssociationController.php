<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketAssociationResource;
use App\Models\Ticket;
use App\Models\TicketAssociation;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketAssociationController extends Controller
{
    public function index(Ticket $ticket): AnonymousResourceCollection
    {
        $associations = TicketAssociation::with('relatedTicket')
            ->where('ticket_id', $ticket->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Also get reverse associations (where this ticket is the related one)
        $reverseAssociations = TicketAssociation::with('ticket')
            ->where('related_ticket_id', $ticket->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($assoc) {
                $reverseType = match ($assoc->type) {
                    'parent' => 'child',
                    'child' => 'parent',
                    default => $assoc->type,
                };
                return (object) [
                    'id' => $assoc->id,
                    'ticket_id' => $assoc->related_ticket_id,
                    'related_ticket_id' => $assoc->ticket_id,
                    'type' => $reverseType,
                    'relatedTicket' => $assoc->ticket,
                    'created_at' => $assoc->created_at,
                ];
            });

        $all = $associations->concat($reverseAssociations);

        return TicketAssociationResource::collection($all);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'related_ticket_id' => 'required|integer|exists:tickets,id',
            'type' => 'required|in:parent,child,related,cause',
        ]);

        if ($validated['related_ticket_id'] == $ticket->id) {
            return response()->json(['message' => 'No puede asociar un ticket consigo mismo'], 422);
        }

        // Check if association already exists
        $exists = TicketAssociation::where('ticket_id', $ticket->id)
            ->where('related_ticket_id', $validated['related_ticket_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Esta asociación ya existe'], 422);
        }

        $association = TicketAssociation::create([
            ...$validated,
            'ticket_id' => $ticket->id,
            'created_at' => now(),
        ]);

        $relatedTicket = Ticket::find($validated['related_ticket_id']);
        $typeLabels = ['parent' => 'padre', 'child' => 'hijo', 'related' => 'relacionado', 'cause' => 'causa'];

        ActivityLogService::log(
            $request->user(), 'associated', $ticket,
            "asoció el ticket {$ticket->title} ({$ticket->ticket_number}) como {$typeLabels[$validated['type']]} de {$relatedTicket->title} ({$relatedTicket->ticket_number})",
            [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'ticket_title' => $ticket->title,
                'related_ticket_id' => $relatedTicket->id,
                'related_ticket_number' => $relatedTicket->ticket_number,
                'association_type' => $validated['type'],
            ]
        );

        return response()->json([
            'data' => new TicketAssociationResource($association->load('relatedTicket')),
        ], 201);
    }

    public function destroy(Request $request, Ticket $ticket, TicketAssociation $association): JsonResponse
    {
        if ($association->ticket_id !== $ticket->id && $association->related_ticket_id !== $ticket->id) {
            return response()->json(['message' => 'Asociación no pertenece al ticket'], 404);
        }

        $association->delete();

        return response()->json(['message' => 'Asociación eliminada']);
    }
}
