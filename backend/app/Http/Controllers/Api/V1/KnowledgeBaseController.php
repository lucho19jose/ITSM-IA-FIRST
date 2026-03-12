<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\KbArticleResource;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KnowledgeBaseController extends Controller
{
    // --- Categories ---
    public function categories(): JsonResponse
    {
        $categories = KnowledgeBaseCategory::withCount('articles')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $categories]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'sort_order' => 'integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category = KnowledgeBaseCategory::create($validated);

        return response()->json(['data' => $category], 201);
    }

    public function updateCategory(Request $request, KnowledgeBaseCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return response()->json(['data' => $category]);
    }

    public function destroyCategory(KnowledgeBaseCategory $category): JsonResponse
    {
        $category->delete();
        return response()->json(['message' => 'Categoría eliminada']);
    }

    // --- Articles ---
    public function articles(Request $request): JsonResponse
    {
        $query = KnowledgeBaseArticle::with(['category', 'author']);

        // Public users only see published public articles
        if (!$request->user() || $request->user()->isEndUser()) {
            $query->where('status', 'published')->where('is_public', true);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        return response()->json(
            KbArticleResource::collection($query->latest()->paginate($request->get('per_page', 15)))->response()->getData(true)
        );
    }

    public function showArticle(KnowledgeBaseArticle $article): JsonResponse
    {
        $article->increment('views_count');
        $article->load(['category', 'author']);

        return response()->json(['data' => new KbArticleResource($article)]);
    }

    public function storeArticle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'category_id' => 'required|exists:knowledge_base_categories,id',
            'status' => 'sometimes|in:draft,published,archived',
            'is_public' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $validated['author_id'] = $request->user()->id;

        if (($validated['status'] ?? 'draft') === 'published') {
            $validated['published_at'] = now();
        }

        $article = KnowledgeBaseArticle::create($validated);

        return response()->json(['data' => new KbArticleResource($article->load(['category', 'author']))], 201);
    }

    public function updateArticle(Request $request, KnowledgeBaseArticle $article): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'excerpt' => 'nullable|string',
            'category_id' => 'sometimes|exists:knowledge_base_categories,id',
            'status' => 'sometimes|in:draft,published,archived',
            'is_public' => 'boolean',
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        if (isset($validated['status']) && $validated['status'] === 'published' && !$article->published_at) {
            $validated['published_at'] = now();
        }

        $article->update($validated);

        return response()->json(['data' => new KbArticleResource($article->load(['category', 'author']))]);
    }

    public function destroyArticle(KnowledgeBaseArticle $article): JsonResponse
    {
        $article->delete();
        return response()->json(['message' => 'Artículo eliminado']);
    }

    public function helpful(Request $request, KnowledgeBaseArticle $article): JsonResponse
    {
        $request->validate(['helpful' => 'required|boolean']);

        if ($request->helpful) {
            $article->increment('helpful_count');
        } else {
            $article->increment('not_helpful_count');
        }

        return response()->json(['message' => 'Gracias por tu feedback']);
    }
}
