<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SlaPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlaPolicyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SlaPolicy::orderBy('priority')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'priority' => 'required|in:low,medium,high,urgent',
            'response_time' => 'required|integer|min:1',
            'resolution_time' => 'required|integer|min:1',
        ]);

        $policy = SlaPolicy::create($validated);

        return response()->json(['data' => $policy], 201);
    }

    public function show(SlaPolicy $slaPolicy): JsonResponse
    {
        return response()->json(['data' => $slaPolicy]);
    }

    public function update(Request $request, SlaPolicy $slaPolicy): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'response_time' => 'sometimes|integer|min:1',
            'resolution_time' => 'sometimes|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $slaPolicy->update($validated);

        return response()->json(['data' => $slaPolicy]);
    }

    public function destroy(SlaPolicy $slaPolicy): JsonResponse
    {
        $slaPolicy->delete();
        return response()->json(['message' => 'Política SLA eliminada']);
    }
}
