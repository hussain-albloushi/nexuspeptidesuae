<?php

namespace App\Http\Controllers;

use App\Models\Content;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class StorefrontController extends Controller
{
    public function index()
    {
        $content = $this->publishedContent();

        $site = $content['site'] ?? [];

        $seo = [
            'title' => $site['seoTitle'] ?? 'Zenvy Peptides — Research-Grade Peptides',
            'description' => $site['seoDescription'] ?? 'Research-grade peptides with verified purity and full traceability.',
        ];

        return view('storefront', [
            'content' => $content,
            'seo' => $seo,
        ]);
    }

    /**
     * Load every published content group, falling back to the storefront's
     * static defaults if the database or cache is temporarily unavailable.
     */
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
