<?php

use App\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;

// Tenant channel — all agents/admins in the tenant
Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    return (int) $user->tenant_id === (int) $tenantId;
});

// Ticket channel — any user in the same tenant can listen
Broadcast::channel('ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = Ticket::withoutGlobalScopes()->find($ticketId);

    if (!$ticket) {
        return false;
    }

    // Same tenant
    if ((int) $ticket->tenant_id !== (int) $user->tenant_id) {
        return false;
    }

    // End-users can only listen to their own tickets
    if ($user->isEndUser() && (int) $ticket->requester_id !== (int) $user->id) {
        return false;
    }

    return true;
});

// User channel — personal notifications
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
