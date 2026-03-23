<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApprovalWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalWorkflowController extends Controller
{
    public function index(): JsonResponse
    {
        $workflows = ApprovalWorkflow::with('steps')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $workflows]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'steps' => 'required|array|min:1',
            'steps.*.step_order' => 'required|integer|min:1',
            'steps.*.approver_type' => 'required|in:user,role,department_head',
            'steps.*.approver_id' => 'nullable|integer|exists:users,id',
            'steps.*.approver_role' => 'nullable|string|in:admin,agent',
            'steps.*.auto_approve_after_hours' => 'nullable|integer|min:1',
        ]);

        $workflow = ApprovalWorkflow::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        foreach ($validated['steps'] as $stepData) {
            $workflow->steps()->create($stepData);
        }

        return response()->json(['data' => $workflow->load('steps')], 201);
    }

    public function show(ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        return response()->json(['data' => $approvalWorkflow->load('steps')]);
    }

    public function update(Request $request, ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'steps' => 'sometimes|array|min:1',
            'steps.*.step_order' => 'required|integer|min:1',
            'steps.*.approver_type' => 'required|in:user,role,department_head',
            'steps.*.approver_id' => 'nullable|integer|exists:users,id',
            'steps.*.approver_role' => 'nullable|string|in:admin,agent',
            'steps.*.auto_approve_after_hours' => 'nullable|integer|min:1',
        ]);

        $approvalWorkflow->update([
            'name' => $validated['name'] ?? $approvalWorkflow->name,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $approvalWorkflow->description,
            'is_active' => $validated['is_active'] ?? $approvalWorkflow->is_active,
        ]);

        // Replace steps if provided
        if (isset($validated['steps'])) {
            $approvalWorkflow->steps()->delete();
            foreach ($validated['steps'] as $stepData) {
                $approvalWorkflow->steps()->create($stepData);
            }
        }

        return response()->json(['data' => $approvalWorkflow->load('steps')]);
    }

    public function destroy(ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        $approvalWorkflow->delete();
        return response()->json(['message' => 'Flujo de aprobación eliminado']);
    }
}
