<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\SlaBreach;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $query = Ticket::query();

        if ($request->user()->isEndUser()) {
            $query->where('requester_id', $request->user()->id);
        }

        $total = (clone $query)->count();
        $open = (clone $query)->where('status', 'open')->count();
        $inProgress = (clone $query)->where('status', 'in_progress')->count();
        $pending = (clone $query)->where('status', 'pending')->count();
        $resolvedToday = (clone $query)->where('status', 'resolved')
            ->whereDate('resolved_at', today())->count();

        // Freshservice-style stats
        $overdue = (clone $query)->whereIn('status', ['open', 'in_progress', 'pending'])
            ->where('resolution_due_at', '<', now())->count();
        $dueToday = (clone $query)->whereIn('status', ['open', 'in_progress', 'pending'])
            ->whereDate('resolution_due_at', today())->count();
        $unassigned = (clone $query)->whereIn('status', ['open', 'in_progress', 'pending'])
            ->whereNull('assigned_to')->count();

        $avgResponseMinutes = Ticket::whereNotNull('responded_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, responded_at)) as avg_response')
            ->value('avg_response');

        // SLA compliance (last 30 days)
        $totalTickets30d = Ticket::where('created_at', '>=', now()->subDays(30))->count();
        $breaches30d = SlaBreach::where('created_at', '>=', now()->subDays(30))->count();
        $slaCompliance = $totalTickets30d > 0
            ? round((($totalTickets30d - $breaches30d) / $totalTickets30d) * 100, 1)
            : 100;

        return response()->json([
            'data' => [
                'total_tickets' => $total,
                'open_tickets' => $open,
                'in_progress_tickets' => $inProgress,
                'pending_tickets' => $pending,
                'resolved_today' => $resolvedToday,
                'overdue_tickets' => $overdue,
                'due_today' => $dueToday,
                'unassigned_tickets' => $unassigned,
                'avg_response_time' => round($avgResponseMinutes ?? 0),
                'sla_compliance' => $slaCompliance,
            ],
        ]);
    }

    public function ticketsByStatus(): JsonResponse
    {
        $data = Ticket::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json(['data' => $data]);
    }

    public function ticketsByPriority(): JsonResponse
    {
        $data = Ticket::select('priority', DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');

        return response()->json(['data' => $data]);
    }

    public function trends(): JsonResponse
    {
        $data = Ticket::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as created'),
                DB::raw('SUM(CASE WHEN status = "resolved" THEN 1 ELSE 0 END) as resolved')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $data]);
    }

    public function agentPerformance(): JsonResponse
    {
        $data = Ticket::select(
                'assigned_to',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "resolved" OR status = "closed" THEN 1 ELSE 0 END) as resolved'),
                DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) as avg_resolution_minutes')
            )
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->get()
            ->map(function ($item) {
                $assignee = \App\Models\User::withoutGlobalScopes()->find($item->assigned_to);
                return [
                    'agent_id' => $item->assigned_to,
                    'agent_name' => $assignee->name ?? 'Unknown',
                    'total_tickets' => $item->total,
                    'resolved_tickets' => $item->resolved,
                    'avg_resolution_minutes' => round($item->avg_resolution_minutes ?? 0),
                ];
            });

        return response()->json(['data' => $data]);
    }
}
