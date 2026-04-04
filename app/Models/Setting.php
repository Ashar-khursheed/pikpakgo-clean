<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'label', 'description', 'is_public'];

    protected $casts = ['is_public' => 'boolean'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) return $default;
        return static::castValue($setting->value, $setting->type);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget('settings_all');
    }

    public static function allCached(): array
    {
        return Cache::remember('settings_all', 600, fn () =>
            static::all()->keyBy('key')->map(fn ($s) => static::castValue($s->value, $s->type))->toArray()
        );
    }

    private static function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => (bool)(int)$value,
            'integer' => (int)$value,
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }
}
