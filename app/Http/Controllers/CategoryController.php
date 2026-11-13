<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $r)
    {
        return Category::withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function show($id)
    {
        return Category::withCount('products')->findOrFail($id);
    }

    public function store(Request $request): JsonResponse
    {
        $category = Category::create($this->validated($request));

        if (empty($category->slug)) {
            $category->update(['slug' => Category::generateSlug($category->name)]);
        }

        return response()->json($category->fresh()->loadCount('products'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->update($this->validated($request, $category));

        return response()->json($category->fresh()->loadCount('products'));
    }

    public function destroy(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        if ($category->products()->exists() || $category->children()->exists()) {
            return response()->json([
                'message' => 'This category has products or subcategories and cannot be deleted.',
            ], 409);
        }

        $category->delete();

        return response()->json(['deleted' => (int) $id]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Category $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($existing?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'image_url' => ['nullable', 'string', 'max:500'],
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = Category::generateSlug($data['name']);
        }

        $data['is_active'] = $data['is_active'] ?? true;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
