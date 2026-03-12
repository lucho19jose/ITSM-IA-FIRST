<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        $departments = Department::orderBy('name')->get();

        return response()->json(['data' => $departments]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'head_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        $department = Department::create($validated);

        return response()->json(['data' => $department], 201);
    }

    public function show(Department $department): JsonResponse
    {
        $department->load('head');
        return response()->json(['data' => $department]);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:255',
            'head_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        $department->update($validated);

        return response()->json(['data' => $department]);
    }

    public function destroy(Department $department): JsonResponse
    {
        $department->delete();
        return response()->json(['message' => 'Departamento eliminado']);
    }
}
