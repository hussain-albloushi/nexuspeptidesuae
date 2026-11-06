<?php
namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    protected array $publicKeys = [
        'store_name','store_logo','store_email','store_phone','store_address',
        'currency','timezone','shipping_settings','tax_settings','social_media_links',
        'store_policies','seo_title','seo_description','email_settings','payment_methods',
    ];

    public function index(Request $r)
    {
        $items = Setting::whereIn('key', $this->publicKeys)->get();
        return response()->json($items->mapWithKeys(fn ($s) => [$s->key => $s->value]));
    }

    public function store(Request $r)
    {
        $data = $r->all();
        foreach ($data as $key => $value) {
            if (!in_array($key, $this->publicKeys)) continue;
            Setting::updateOrCreate(['key' => $key], ['value' => is_array($value) ? json_encode($value) : $value, 'type' => 'string', 'is_secret' => false]);
        }
        return response()->json(['message' => 'Saved']);
    }

    public function show($key)
    {
        if (!in_array($key, $this->publicKeys)) return response()->json(['message' => 'Not found or secret'], 404);
        return Setting::where('key', $key)->firstOrFail();
    }
}
