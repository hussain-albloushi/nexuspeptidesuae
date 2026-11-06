<?php

namespace App\Http\Controllers;

use App\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ContentEditorController extends Controller
{
    public function index()
    {
        $groups = config('storefront-content.groups', []);
        $records = Content::whereIn('key', array_keys($groups))->get()->keyBy('key');

        return response()->json([
            'groups' => collect($groups)->map(function (array $group, string $key) use ($records) {
                $record = $records->get($key);

                return [
                    'key' => $key,
                    'label' => $group['label'],
                    'icon' => $group['icon'] ?? 'bi-file-text',
                    'description' => $group['description'] ?? '',
                    'live_url' => $group['live_url'] ?? '/',
                    'sections' => $group['sections'] ?? [],
                    'is_active' => $record?->is_active ?? true,
                    'data' => $record?->data ?? [],
                    'updated_at' => $record?->updated_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    public function update(Request $request, string $key)
    {
        $groups = config('storefront-content.groups', []);
        abort_unless(isset($groups[$key]), 404);

        $validated = $request->validate([
            'data' => ['required', 'array'],
            'is_active' => ['required', 'boolean'],
        ]);

        $group = $groups[$key];

        $content = Content::updateOrCreate(
            ['key' => $key],
            [
                'label' => $group['label'],
                'type' => 'group',
                'data' => $validated['data'],
                'is_active' => $validated['is_active'],
                'sort_order' => array_search($key, array_keys($groups), true) ?: 0,
            ]
        );

        Cache::forget(config('storefront-content.cache_key', 'storefront:content'));

        return response()->json([
            'key' => $content->key,
            'is_active' => $content->is_active,
            'data' => $content->data,
            'updated_at' => $content->updated_at?->toIso8601String(),
        ]);
    }

    public function upload(Request $request)
    {
        $config = config('storefront-content.upload', []);

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.($config['max_kb'] ?? 10240),
                'mimes:'.implode(',', $config['mimes'] ?? ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf']),
            ],
        ]);

        $disk = $config['disk'] ?? 'public';
        $path = $request->file('file')->store($config['path'] ?? 'content', $disk);
        $url = Storage::disk($disk)->url($path);

        return response()->json([
            'path' => $path,
            // Keep URLs host-relative so uploads work on every domain the site is served from.
            'url' => parse_url($url, PHP_URL_PATH) ?: $url,
        ]);
    }
}
