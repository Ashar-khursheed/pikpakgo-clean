<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoConfig extends Model
{
    protected $fillable = [
        'route_slug', 'route_path', 'route_label', 'route_group',
        'meta_title', 'meta_description',
        'og_title', 'og_description', 'og_image',
        'twitter_card', 'twitter_title', 'twitter_description', 'twitter_image',
        'canonical_url', 'no_index', 'no_follow',
        'schema_markup', 'is_active',
    ];

    protected $casts = [
        'schema_markup' => 'array',
        'no_index'      => 'boolean',
        'no_follow'     => 'boolean',
        'is_active'     => 'boolean',
    ];

    /** Full SEO meta block ready for the frontend */
    public function getSeoAttribute(): array
    {
        return [
            'title'               => $this->meta_title,
            'description'         => $this->meta_description,
            'og_title'            => $this->og_title    ?: $this->meta_title,
            'og_description'      => $this->og_description ?: $this->meta_description,
            'og_image'            => $this->og_image,
            'twitter_card'        => $this->twitter_card,
            'twitter_title'       => $this->twitter_title ?: ($this->og_title ?: $this->meta_title),
            'twitter_description' => $this->twitter_description ?: ($this->og_description ?: $this->meta_description),
            'twitter_image'       => $this->twitter_image ?: $this->og_image,
            'canonical'           => $this->canonical_url,
            'no_index'            => $this->no_index,
            'no_follow'           => $this->no_follow,
            'schema'              => $this->schema_markup,
        ];
    }
}
