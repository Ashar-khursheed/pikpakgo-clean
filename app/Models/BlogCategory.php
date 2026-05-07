<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogCategory extends Model
{
    protected $appends = ['seo'];

    protected $fillable = [
        'name', 'slug', 'description', 'featured_image',
        'color', 'sort_order', 'is_active',
        'meta_title', 'meta_description', 'og_image',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function posts()
    {
        return $this->hasMany(BlogPost::class);
    }

    public function publishedPosts()
    {
        return $this->hasMany(BlogPost::class)->published();
    }

    /** Full SEO meta block — checks for overrides first */
    public function getSeoAttribute(): array
    {
        return app(\App\Services\SeoService::class)->getBlogCategorySeo($this);
    }

    /** Generated SEO block — fallback */
    public function getGeneratedSeoAttribute(): array
    {
        return [
            'title'       => $this->meta_title ?: ($this->name . ' — Blog'),
            'description' => $this->meta_description ?: $this->description,
            'og_title'    => $this->meta_title ?: $this->name,
            'og_description' => $this->meta_description ?: $this->description,
            'og_image'    => $this->og_image ?? $this->featured_image,
            'no_index'    => false,
        ];
    }
}
