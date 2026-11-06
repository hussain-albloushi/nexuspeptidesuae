<?php
namespace App\Http\Controllers;

use App\Models\Content;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function index(Request $r)
    {
        return Content::orderBy('key')->get();
    }

    public function show($key)
    {
        return Content::where('key', $key)->firstOrFail();
    }

    public function store(Request $r)
    {
        $r->validate(['key' => 'required|string|unique:contents,key', 'label' => 'required|string']);
        return Content::create([
            'key' => $r->key,
            'label' => $r->label,
            'body' => $r->body ?? '',
            'image_path' => $r->image_path ?? '',
            'is_active' => $r->boolean('is_active', true),
        ]);
    }

    public function update(Request $r, $key)
    {
        $c = Content::where('key', $key)->firstOrFail();
        $c->update([
            'label' => $r->label ?? $c->label,
            'body' => $r->body ?? $c->body,
            'image_path' => $r->image_path ?? $c->image_path,
            'is_active' => $r->boolean('is_active', $c->is_active),
        ]);
        return $c;
    }
}
