<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use SoftDeletes;

    protected $appends = ['seo_slug', 'seo'];

    protected $fillable = [
        'blog_category_id', 'author_id',
        'title', 'slug', 'excerpt', 'content',
        'featured_image', 'gallery', 'tags',
        'status', 'published_at',
        'read_time', 'is_featured', 'allow_comments',
        'meta_title', 'meta_description',
        'og_title', 'og_description', 'og_image',
        'twitter_title', 'twitter_description', 'twitter_image',
        'canonical_url', 'no_index', 'schema_markup',
    ];

    protected $casts = [
        'gallery'      => 'array',
        'tags'         => 'array',
        'schema_markup'=> 'array',
        'is_featured'  => 'boolean',
        'allow_comments'=> 'boolean',
        'no_index'     => 'boolean',
        'published_at' => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────
    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // ── Scopes ───────────────────────────────────────────────────
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // ── Accessors ─────────────────────────────────────────────────

    /** Auto-calculate read time when content is set */
    public function setContentAttribute($value)
    {
        $this->attributes['content'] = $value;
        $wordCount = str_word_count(strip_tags($value ?? ''));
        $this->attributes['read_time'] = max(1, (int) ceil($wordCount / 200));
    }

    /** Full SEO meta block — checks for manual overrides in seo_configs first */
    public function getSeoAttribute(): array
    {
        return app(\App\Services\SeoService::class)->getBlogPostSeo($this);
    }

    /** Full SEO meta block — auto-filled from post fields if not explicitly set */
    public function getGeneratedSeoAttribute(): array
    {
        $defaultTitle = $this->meta_title ?: $this->title;
        $defaultDesc  = $this->meta_description
            ?: ($this->excerpt ?: Str::limit(strip_tags($this->content ?? ''), 155));
        $defaultImage = $this->og_image ?: $this->featured_image;

        return [
            'title'               => $defaultTitle,
            'description'         => $defaultDesc,
            'og_title'            => $this->og_title    ?: $defaultTitle,
            'og_description'      => $this->og_description ?: $defaultDesc,
            'og_image'            => $defaultImage,
            'twitter_title'       => $this->twitter_title ?: $defaultTitle,
            'twitter_description' => $this->twitter_description ?: $defaultDesc,
            'twitter_image'       => $this->twitter_image ?: $defaultImage,
            'canonical'           => $this->canonical_url,
            'no_index'            => $this->no_index,
            'schema'              => $this->schema_markup ?? $this->buildArticleSchema(),
        ];
    }

    /** Auto-generate Article JSON-LD schema */
    private function buildArticleSchema(): array
    {
        return [
            '@context'         => 'https://schema.org',
            '@type'            => 'BlogPosting',
            'headline'         => $this->title,
            'description'      => $this->excerpt ?: Str::limit(strip_tags($this->content ?? ''), 155),
            'image'            => $this->featured_image,
            'datePublished'    => $this->published_at?->toIso8601String(),
            'dateModified'     => $this->updated_at?->toIso8601String(),
            'author'           => $this->author ? [
                '@type' => 'Person',
                'name'  => $this->author->first_name . ' ' . $this->author->last_name,
            ] : null,
            'publisher' => [
                '@type' => 'Organization',
                'name'  => config('app.name', 'PikPakGo'),
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => $this->canonical_url,
            ],
            'keywords' => $this->tags ? implode(', ', $this->tags) : null,
            'articleSection' => $this->category?->name,
        ];
    }

    public function getSeoSlugAttribute(): string
    {
        return $this->seo['slug'];
    }
}
