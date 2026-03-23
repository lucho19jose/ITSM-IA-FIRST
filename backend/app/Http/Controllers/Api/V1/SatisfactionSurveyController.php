<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SatisfactionSurvey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SatisfactionSurveyController extends Controller
{
    /**
     * PUBLIC: Show survey info by token (for the rating page).
     */
    public function show(string $token): JsonResponse
    {
        $survey = SatisfactionSurvey::withoutGlobalScopes()
            ->where('token', $token)
            ->with(['ticket:id,ticket_number,title', 'ticket.assignee:id,name', 'user:id,name'])
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $survey->id,
                'rating' => $survey->rating,
                'comment' => $survey->comment,
                'responded_at' => $survey->responded_at,
                'ticket' => $survey->ticket ? [
                    'id' => $survey->ticket->id,
                    'ticket_number' => $survey->ticket->ticket_number,
                    'title' => $survey->ticket->title,
                    'agent_name' => $survey->ticket->assignee?->name,
                ] : null,
                'user_name' => $survey->user?->name,
            ],
        ]);
    }

    /**
     * PUBLIC: Submit survey response by token.
     */
    public function respond(Request $request, string $token): JsonResponse
    {
        $survey = SatisfactionSurvey::withoutGlobalScopes()
            ->where('token', $token)
            ->firstOrFail();

        if ($survey->responded_at) {
            return response()->json([
                'message' => 'Esta encuesta ya fue respondida.',
                'data' => [
                    'rating' => $survey->rating,
                    'comment' => $survey->comment,
                    'responded_at' => $survey->responded_at,
                ],
            ], 409);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $survey->update([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'responded_at' => now(),
        ]);

        // Also update the ticket's satisfaction_rating for quick access
        if ($survey->ticket) {
            $survey->ticket()->withoutGlobalScopes()->update([
                'satisfaction_rating' => $validated['rating'],
                'satisfaction_comment' => $validated['comment'] ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Gracias por tu respuesta.',
            'data' => [
                'rating' => $survey->rating,
                'comment' => $survey->comment,
                'responded_at' => $survey->responded_at,
            ],
        ]);
    }

    /**
     * Protected: CSAT stats for admin dashboard.
     */
    public function stats(): JsonResponse
    {
        $totalSurveys = SatisfactionSurvey::count();
        $respondedSurveys = SatisfactionSurvey::responded()->count();
        $responseRate = $totalSurveys > 0
            ? round(($respondedSurveys / $totalSurveys) * 100, 1)
            : 0;

        $avgRating = SatisfactionSurvey::responded()->avg('rating');

        // Rating distribution
        $distribution = SatisfactionSurvey::responded()
            ->select('rating', DB::raw('COUNT(*) as count'))
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        // Fill missing ratings with 0
        $ratingDistribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $ratingDistribution[$i] = $distribution[$i] ?? 0;
        }

        // Trend over last 12 weeks
        $trend = SatisfactionSurvey::responded()
            ->where('responded_at', '>=', now()->subWeeks(12))
            ->select(
                DB::raw('YEARWEEK(responded_at, 1) as week'),
                DB::raw('AVG(rating) as avg_rating'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('week')
            ->orderBy('week')
            ->get()
            ->map(fn($item) => [
                'week' => $item->week,
                'avg_rating' => round($item->avg_rating, 2),
                'count' => $item->count,
            ]);

        return response()->json([
            'data' => [
                'average_rating' => $avgRating ? round($avgRating, 2) : null,
                'total_surveys' => $totalSurveys,
                'responded_surveys' => $respondedSurveys,
                'response_rate' => $responseRate,
                'rating_distribution' => $ratingDistribution,
                'trend' => $trend,
            ],
        ]);
    }

    /**
     * Protected: List surveys with filters (admin/agent).
     */
    public function index(Request $request): JsonResponse
    {
        $query = SatisfactionSurvey::with(['ticket:id,ticket_number,title', 'user:id,name,email']);

        // Filter by response status
        if ($request->filled('status')) {
            if ($request->status === 'responded') {
                $query->responded();
            } elseif ($request->status === 'pending') {
                $query->pending();
            }
        }

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $query->orderBy('created_at', 'desc');

        return response()->json(
            $query->paginate($request->get('per_page', 15))
        );
    }
}
