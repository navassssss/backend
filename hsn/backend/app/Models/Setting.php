<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key, cached for 1 hour.
     */
    public static function getValue($key, $default = null)
    {
        return Cache::remember("setting:{$key}", now()->addHour(), function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value and bust the cache.
     */
    public static function setValue($key, $value)
    {
        Cache::forget("setting:{$key}");
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
