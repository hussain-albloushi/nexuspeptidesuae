<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\Product;
use App\Services\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class StorefrontController extends Controller
{
    public function index()
    {
        $content = $this->publishedContent();
        $content['store'] = array_replace(config('storefront-content.shipping_defaults'), $content['store'] ?? []);

        $site = $content['site'] ?? [];

        $seo = [
            'title' => $site['seoTitle'] ?? 'Nexus Peptide - UAE',
            'description' => $site['seoDescription'] ?? 'Research-grade peptides with verified purity and full traceability.',
        ];

        return view('storefront', [
            'content' => $content,
            'seo' => $seo,
            'products' => $this->publishedProducts(),
        ]);
    }

    /**
     * Load every published content group, falling back to the storefront's
     * static defaults if the database or cache is temporarily unavailable.
     */
    public function storeSettings(): JsonResponse
    {
        $content = $this->publishedContent();

        return response()->json(array_replace(
            config('storefront-content.shipping_defaults'),
            $content['store'] ?? [],
            ['timezone' => app(SiteSettings::class)->timezone()],
        ))->header('Cache-Control', 'no-store');
    }

    /**
     * Admin-managed catalogue snapshot for the storefront. Products that are
     * inactive, hidden, or deleted in the admin panel are left out, so the
     * storefront catalogue mirrors the database exactly.
     */
    protected function publishedProducts(): array
    {
        try {
            return Product::query()
                ->where('is_active', true)
                ->where('visibility', 'public')
                ->orderBy('id')
                ->get(['slug', 'name', 'price', 'compare_at_price', 'featured_image_url'])
                ->map(fn (Product $product): array => [
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'compare_at_price' => $product->compare_at_price !== null ? (float) $product->compare_at_price : null,
                    'image' => $product->featured_image_url,
                ])
                ->all();
        } catch (Throwable $exception) {
            Log::warning('Could not load storefront products.', [
                'exception' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    protected function publishedContent(): array
    {
        try {
            return Cache::remember(
                config('storefront-content.cache_key', 'storefront:content'),
                config('storefront-content.cache_ttl', 3600),
                function () {
                    return Content::query()
                        ->where('type', 'group')
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn (Content $content) => [$content->key => $content->data ?? []])
                        ->all();
                }
            );
        } catch (Throwable $exception) {
            Log::warning('Could not load storefront content.', [
                'exception' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}
