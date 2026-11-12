<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Product;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $r): Collection|LengthAwarePaginator
    {
        $data = $r->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', Rule::when($r->filled('min_price'), 'gte:min_price')],
            'stock_status' => ['nullable', Rule::in(['in_stock', 'low_stock', 'out_of_stock'])],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $term = trim($data['q'] ?? '');
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';

        $products = Product::with('category')
            ->when($term !== '', function (Builder $query) use ($pattern): void {
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereHas('category', fn (Builder $category) => $category->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern]));
                });
            })
            ->when(isset($data['category_id']), fn (Builder $query) => $query->where('category_id', $data['category_id']))
            ->when(isset($data['min_price']), fn (Builder $query) => $query->where('price', '>=', $data['min_price']))
            ->when(isset($data['max_price']), fn (Builder $query) => $query->where('price', '<=', $data['max_price']))
            ->when(isset($data['is_active']), fn (Builder $query) => $query->where('is_active', $data['is_active']))
            ->when(isset($data['stock_status']), function (Builder $query) use ($data): void {
                match ($data['stock_status']) {
                    'out_of_stock' => $query->where('stock_quantity', '<=', 0),
                    'low_stock' => $query->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'low_stock_threshold'),
                    'in_stock' => $query->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '>', 'low_stock_threshold'),
                };
            })
            ->latest()->orderByDesc('id');

        return isset($data['per_page']) ? $products->paginate((int) $data['per_page'])->withQueryString() : $products->get();
    }

    public function show(int $id): Product
    {
        return Product::with(['category', 'variants', 'images'])->findOrFail($id);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product)],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($product)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'compare_at_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99', 'gte:price'],
            'cost_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'expected_stock_quantity' => ['required', 'integer'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
            'featured' => ['required', 'boolean'],
            'requires_subscription' => ['required', 'boolean'],
            'visibility' => ['required', Rule::in(['public', 'hidden', 'private'])],
            'featured_image_url' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                $isLocal = str_starts_with($value, '/') && ! str_starts_with($value, '//')
                    && ! str_contains($value, '\\') && ! preg_match('/\s/', $value);
                $isRemote = filter_var($value, FILTER_VALIDATE_URL)
                    && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);
                if (! $isLocal && ! $isRemote) {
                    $fail('Use an http(s) image URL or a local path starting with /.');
                }
            }],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:10000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $uploadedPath = null;
        try {
            $updated = DB::transaction(function () use ($id, $data, $request, &$uploadedPath): Product {
                $product = Product::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($product->stock_quantity !== (int) $data['expected_stock_quantity']) {
                    throw ValidationException::withMessages([
                        'stock_quantity' => 'Stock changed while you were editing. Cancel and reopen the product to use the latest quantity.',
                    ]);
                }

                $before = $product->stock_quantity;
                unset($data['expected_stock_quantity'], $data['image']);
                if ($request->hasFile('image')) {
                    $uploadedPath = $request->file('image')->store('products/'.$id, 'public');
                    if (! $uploadedPath) {
                        throw ValidationException::withMessages(['image' => 'The image could not be stored. Please try again.']);
                    }
                    $url = Storage::disk('public')->url($uploadedPath);
                    $data['featured_image_url'] = parse_url($url, PHP_URL_PATH) ?: $url;
                }
                $data['stock_status'] = $data['stock_quantity'] <= 0 ? 'out_of_stock'
                    : ($data['stock_quantity'] <= $data['low_stock_threshold'] ? 'low_stock' : 'in_stock');
                $product->update($data);
                if ($before !== $product->stock_quantity) {
                    InventoryTransaction::create([
                        'product_id' => $product->id, 'quantity_before' => $before,
                        'quantity_after' => $product->stock_quantity, 'type' => 'adjustment',
                        'reason' => 'Stock updated from the product editor.',
                    ]);
                }

                return $product->load(['category', 'variants', 'images']);
            });
        } catch (Throwable $exception) {
            if ($uploadedPath) {
                Storage::disk('public')->delete($uploadedPath);
            }
            throw $exception;
        }

        return response()->json(['message' => 'Product updated successfully.', 'product' => $updated]);
    }

    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully.']);
    }
}
