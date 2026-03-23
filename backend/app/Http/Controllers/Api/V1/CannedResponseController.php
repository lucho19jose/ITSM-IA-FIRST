<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CannedResponseResource;
use App\Models\CannedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CannedResponseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CannedResponse::visibleTo($request->user())
            ->with('user')
            ->orderBy('title');

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('visibility')) {
            $query->where('visibility', $request->input('visibility'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('shortcut', 'like', "%{$search}%");
            });
        }

        return CannedResponseResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'nullable|string|max:100',
            'visibility' => 'sometimes|in:personal,team,global',
            'shortcut' => 'nullable|string|max:50',
        ]);

        // Only admins can create global responses
        if (($validated['visibility'] ?? 'personal') === 'global' && $request->user()->role !== 'admin') {
            $validated['visibility'] = 'team';
        }

        $validated['user_id'] = $request->user()->id;

        $cannedResponse = CannedResponse::create($validated);

        return response()->json([
            'data' => new CannedResponseResource($cannedResponse->load('user')),
        ], 201);
    }

    public function show(CannedResponse $cannedResponse): JsonResponse
    {
        return response()->json([
            'data' => new CannedResponseResource($cannedResponse->load('user')),
        ]);
    }

    public function update(Request $request, CannedResponse $cannedResponse): JsonResponse
    {
        // Only owner or admin can update
        if ($cannedResponse->user_id !== $request->user()->id && $request->user()->role !== 'admin') {
            abort(403, 'No autorizado');
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'category' => 'nullable|string|max:100',
            'visibility' => 'sometimes|in:personal,team,global',
            'shortcut' => 'nullable|string|max:50',
        ]);

        // Only admins can set global visibility
        if (isset($validated['visibility']) && $validated['visibility'] === 'global' && $request->user()->role !== 'admin') {
            $validated['visibility'] = 'team';
        }

        $cannedResponse->update($validated);

        return response()->json([
            'data' => new CannedResponseResource($cannedResponse->load('user')),
        ]);
    }

    public function destroy(Request $request, CannedResponse $cannedResponse): JsonResponse
    {
        // Only owner or admin can delete
        if ($cannedResponse->user_id !== $request->user()->id && $request->user()->role !== 'admin') {
            abort(403, 'No autorizado');
        }

        $cannedResponse->delete();

        return response()->json(['message' => 'Respuesta predefinida eliminada']);
    }

    /**
     * Quick search endpoint for typeahead in ticket detail.
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->input('q', '');

        $query = CannedResponse::visibleTo($request->user())
            ->orderBy('usage_count', 'desc')
            ->orderBy('title')
            ->limit(15);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('shortcut', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $responses = $query->get()->map(fn ($r) => [
            'id' => $r->id,
            'title' => $r->title,
            'shortcut' => $r->shortcut,
            'content' => $r->content,
            'category' => $r->category,
            'visibility' => $r->visibility,
            'content_preview' => \Illuminate\Support\Str::limit(strip_tags($r->content), 100),
        ]);

        return response()->json(['data' => $responses]);
    }

    /**
     * Increment usage count when a canned response is used.
     */
    public function use(CannedResponse $cannedResponse): JsonResponse
    {
        $cannedResponse->increment('usage_count');

        return response()->json(['message' => 'ok']);
    }
}
