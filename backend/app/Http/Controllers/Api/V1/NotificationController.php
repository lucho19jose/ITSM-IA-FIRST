<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = DB::table('notifications')
            ->where('notifiable_id', $request->user()->id)
            ->where('notifiable_type', get_class($request->user()))
            ->where('tenant_id', app('tenant_id'))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($notifications);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_id', $request->user()->id)
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Notificación marcada como leída']);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = DB::table('notifications')
            ->where('notifiable_id', $request->user()->id)
            ->where('notifiable_type', get_class($request->user()))
            ->where('tenant_id', app('tenant_id'))
            ->whereNull('read_at')
            ->count();

        return response()->json(['data' => ['count' => $count]]);
    }
}
