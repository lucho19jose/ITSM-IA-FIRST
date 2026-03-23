<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\KnownErrorResource;
use App\Models\KnownError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class KnownErrorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = KnownError::with(['problem', 'category']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('workaround', 'like', "%{$search}%")
                  ->orWhere('root_cause', 'like', "%{$search}%");
            });
        }

        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDir);

        return KnownErrorResource::collection($query->paginate($request->get('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'workaround' => 'nullable|string',
            'root_cause' => 'nullable|string',
            'status' => 'sometimes|in:open,in_progress,resolved',
            'category_id' => 'nullable|exists:categories,id',
            'problem_id' => 'nullable|exists:problems,id',
        ]);

        $knownError = KnownError::create($validated);

        return response()->json([
            'data' => new KnownErrorResource($knownError->load(['problem', 'category'])),
        ], 201);
    }

    public function show(KnownError $knownError): JsonResponse
    {
        $knownError->load(['problem', 'category']);

        return response()->json([
            'data' => new KnownErrorResource($knownError),
        ]);
    }

    public function update(Request $request, KnownError $knownError): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'workaround' => 'nullable|string',
            'root_cause' => 'nullable|string',
            'status' => 'sometimes|in:open,in_progress,resolved',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $knownError->update($validated);

        return response()->json([
            'data' => new KnownErrorResource($knownError->load(['problem', 'category'])),
        ]);
    }

    public function destroy(KnownError $knownError): JsonResponse
    {
        $knownError->delete();
        return response()->json(['message' => 'Error conocido eliminado'], 200);
    }

    public function search(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $search = $request->q;

        $results = KnownError::with(['problem', 'category'])
            ->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('workaround', 'like', "%{$search}%")
                  ->orWhere('root_cause', 'like', "%{$search}%");
            })
            ->orderBy('updated_at', 'desc')
            ->paginate($request->get('per_page', 10));

        return KnownErrorResource::collection($results);
    }
}
