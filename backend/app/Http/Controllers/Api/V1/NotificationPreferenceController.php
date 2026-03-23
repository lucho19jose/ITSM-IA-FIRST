<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * Get or create default notification preferences for the current user.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = app('tenant_id');

        $prefs = NotificationPreference::getOrCreate($user->id, $tenantId);

        return response()->json(['data' => $prefs]);
    }

    /**
     * Update notification preferences for the current user.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'sometimes|in:email,in_app,both',
            'ticket_created' => 'sometimes|boolean',
            'ticket_assigned' => 'sometimes|boolean',
            'ticket_commented' => 'sometimes|boolean',
            'ticket_closed' => 'sometimes|boolean',
            'sla_warning' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $tenantId = app('tenant_id');

        $prefs = NotificationPreference::getOrCreate($user->id, $tenantId);
        $prefs->update($validated);

        return response()->json(['data' => $prefs]);
    }
}
