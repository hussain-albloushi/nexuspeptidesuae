<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $fillable = ['key', 'value', 'type', 'is_secret'];

    protected $casts = ['is_secret' => 'boolean'];

    protected static function booted(): void
    {
        // Secrets are encrypted at rest. The saving hook runs after all
        // attributes are assigned, so is_secret is reliable here.
        static::saving(function (self $setting): void {
            if (! $setting->is_secret || ! is_string($setting->value) || $setting->value === '') {
                return;
            }

            // Avoid double-encrypting an already-encrypted value.
            try {
                Crypt::decryptString($setting->value);

                return;
            } catch (\Throwable) {
                // Plaintext legacy value — encrypt it now.
            }

            $setting->value = Crypt::encryptString($setting->value);
        });
    }
}
