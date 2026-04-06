<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'email', 'name', 'status', 'source',
        'unsubscribe_token', 'unsubscribed_at',
    ];

    protected $casts = ['unsubscribed_at' => 'datetime'];

    public static function subscribe(string $email, ?string $name = null, string $source = 'website'): self
    {
        return static::updateOrCreate(
            ['email' => $email],
            [
                'name'               => $name,
                'status'             => 'active',
                'source'             => $source,
                'unsubscribe_token'  => Str::random(32),
                'unsubscribed_at'    => null,
            ]
        );
    }
}
