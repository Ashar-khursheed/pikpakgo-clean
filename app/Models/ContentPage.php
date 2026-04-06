<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentPage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        // Core
        'slug', 'parent_slug', 'title', 'type', 'template',
        'content', 'sections', 'featured_image',
        // SEO
        'meta_title', 'meta_description',
        'og_title', 'og_description', 'og_image',
        'canonical_url', 'no_index', 'schema_markup',
        // Nav
        'show_in_nav', 'nav_label', 'nav_icon', 'sort_order',
        // Status
        'is_active', 'published_at',
    ];

    protected $casts = [
        'content'       => 'array',
        'sections'      => 'array',
        'schema_markup' => 'array',
        'is_active'     => 'boolean',
        'no_index'      => 'boolean',
        'show_in_nav'   => 'boolean',
        'published_at'  => 'datetime',
    ];

    // ── Scopes ─────────��──────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublished($query)
    {
        return $query->where(fn($q) => $q
            ->whereNull('published_at')
            ->orWhere('published_at', '<=', now())
        );
    }

    public function scopeInNav($query)
    {
        return $query->where('show_in_nav', true)->orderBy('sort_order');
    }

    // ── Accessors ─────────────────────────────────────────────────

    /** Resolved SEO title: og_title > meta_title > title */
    public function getResolvedTitleAttribute(): string
    {
        return $this->og_title ?: ($this->meta_title ?: $this->title);
    }

    /** Resolved SEO description */
    public function getResolvedDescriptionAttribute(): ?string
    {
        return $this->og_description ?: $this->meta_description;
    }

    /** Full SEO meta block for frontend */
    public function getSeoAttribute(): array
    {
        return [
            'title'        => $this->meta_title ?: $this->title,
            'description'  => $this->meta_description,
            'og_title'     => $this->og_title ?: ($this->meta_title ?: $this->title),
            'og_description' => $this->og_description ?: $this->meta_description,
            'og_image'     => $this->og_image,
            'canonical'    => $this->canonical_url,
            'no_index'     => $this->no_index,
            'schema'       => $this->schema_markup,
        ];
    }
}
