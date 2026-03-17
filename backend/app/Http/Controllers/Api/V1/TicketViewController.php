<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TicketView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketViewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $views = TicketView::where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                  ->orWhere('is_shared', true);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $views]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'sometimes|string|max:50',
            'filters' => 'required|array',
            'columns' => 'nullable|array',
            'is_default' => 'sometimes|boolean',
            'is_shared' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        // Only admin can share views
        if (!$request->user()->isAdmin()) {
            $validated['is_shared'] = false;
        }

        $validated['user_id'] = $request->user()->id;

        // If setting as default, unset other defaults
        if (!empty($validated['is_default'])) {
            TicketView::where('user_id', $request->user()->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $view = TicketView::create($validated);

        return response()->json(['data' => $view], 201);
    }

    public function update(Request $request, TicketView $ticketView): JsonResponse
    {
        // Only owner or admin can update
        if ($ticketView->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'icon' => 'sometimes|string|max:50',
            'filters' => 'sometimes|array',
            'columns' => 'nullable|array',
            'is_default' => 'sometimes|boolean',
            'is_shared' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        if (!$request->user()->isAdmin()) {
            unset($validated['is_shared']);
        }

        if (!empty($validated['is_default'])) {
            TicketView::where('user_id', $request->user()->id)
                ->where('is_default', true)
                ->where('id', '!=', $ticketView->id)
                ->update(['is_default' => false]);
        }

        $ticketView->update($validated);

        return response()->json(['data' => $ticketView]);
    }

    public function destroy(Request $request, TicketView $ticketView): JsonResponse
    {
        if ($ticketView->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $ticketView->delete();

        return response()->json(null, 204);
    }
}
