<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgentGroupResource;
use App\Models\AgentGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AgentGroupController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $groups = AgentGroup::withCount('members')->orderBy('name')->get();
        return AgentGroupResource::collection($groups);
    }

    public function show(AgentGroup $agentGroup): JsonResponse
    {
        return response()->json([
            'data' => new AgentGroupResource($agentGroup->load('members')),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $group = AgentGroup::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (!empty($validated['member_ids'])) {
            $group->members()->attach($validated['member_ids']);
        }

        return response()->json([
            'data' => new AgentGroupResource($group->load('members')),
        ], 201);
    }

    public function update(Request $request, AgentGroup $agentGroup): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $agentGroup->update(collect($validated)->except('member_ids')->toArray());

        if (array_key_exists('member_ids', $validated)) {
            $agentGroup->members()->sync($validated['member_ids'] ?? []);
        }

        return response()->json([
            'data' => new AgentGroupResource($agentGroup->load('members')),
        ]);
    }

    public function destroy(AgentGroup $agentGroup): JsonResponse
    {
        $agentGroup->delete();
        return response()->json(['message' => 'Grupo eliminado']);
    }
}
