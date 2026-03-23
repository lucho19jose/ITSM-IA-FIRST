<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssetTypeResource;
use App\Models\AssetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = AssetType::withCount('assets')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => AssetTypeResource::collection($types),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:100',
            'fields' => 'nullable|array',
            'fields.*.name' => 'required|string|max:100',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.type' => 'required|string|in:text,textarea,number,date,select,checkbox,url,email',
            'fields.*.options' => 'nullable|array',
            'fields.*.required' => 'nullable|boolean',
        ]);

        $type = AssetType::create($validated);

        return response()->json([
            'data' => new AssetTypeResource($type),
        ], 201);
    }

    public function show(AssetType $assetType): JsonResponse
    {
        $assetType->loadCount('assets');

        return response()->json([
            'data' => new AssetTypeResource($assetType),
        ]);
    }

    public function update(Request $request, AssetType $assetType): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'icon' => 'nullable|string|max:100',
            'fields' => 'nullable|array',
            'fields.*.name' => 'required|string|max:100',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.type' => 'required|string|in:text,textarea,number,date,select,checkbox,url,email',
            'fields.*.options' => 'nullable|array',
            'fields.*.required' => 'nullable|boolean',
        ]);

        $assetType->update($validated);

        return response()->json([
            'data' => new AssetTypeResource($assetType),
        ]);
    }

    public function destroy(AssetType $assetType): JsonResponse
    {
        if ($assetType->assets()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un tipo de activo que tiene activos asociados.',
            ], 422);
        }

        $assetType->delete();

        return response()->json(['message' => 'Tipo de activo eliminado']);
    }
}
