<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => CategoryResource::collection($categories),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'icon' => 'nullable|string',
            'sort_order' => 'integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category = Category::create($validated);

        return response()->json([
            'data' => new CategoryResource($category),
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'data' => new CategoryResource($category->load('children')),
        ]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'icon' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return response()->json([
            'data' => new CategoryResource($category),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();
        return response()->json(['message' => 'Categoría eliminada']);
    }
}
