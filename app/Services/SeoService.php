<?php

namespace App\Services;

use App\Models\PropertyListing;
use App\Models\SeoConfig;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

class SeoService
{
    /**
     * Get SEO metadata for a property, checking for manual overrides first.
     */
    public function getPropertySeo(PropertyListing $property): array
    {
        $propertyCode = $property->provider_code ?: $property->provider_property_id;
        $routeSlug = 'property-' . Str::slug($propertyCode);
        $routePath = '/properties/' . $propertyCode;
        return $this->resolveSeo($routeSlug, fn() => $this->generatePropertySeo($property), $routePath, $property);
    }

    /**
     * Get SEO metadata for a blog post.
     */
    public function getBlogPostSeo(\App\Models\BlogPost $post): array
    {
        $routeSlug = 'blog-' . $post->slug;
        $routePath = '/blog/' . $post->slug;
        return $this->resolveSeo($routeSlug, fn() => $post->getGeneratedSeoAttribute(), $routePath, $post);
    }

    /**
     * Get SEO metadata for a blog category.
     */
    public function getBlogCategorySeo(\App\Models\BlogCategory $category): array
    {
        $routeSlug = 'blog-category-' . $category->slug;
        return $this->resolveSeo($routeSlug, fn() => $category->getGeneratedSeoAttribute(), null, $category);
    }

    /**
     * Generic resolver that checks for overrides in seo_configs.
     */
    private function resolveSeo(string $routeSlug, callable $fallbackGenerator, ?string $routePath = null, ?Model $model = null): array
    {
        $override = null;

        // 1. Try to find by direct model relation if provided
        if ($model) {
            $override = SeoConfig::where('model_type', get_class($model))
                ->where('model_id', $model->id)
                ->where('is_active', true)
                ->first();
        }

        // 2. Try to find by route_slug (legacy/fallback lookup)
        if (!$override) {
            $override = SeoConfig::where('route_slug', $routeSlug)
                ->where('is_active', true)
                ->first();
        }

        // 3. Try to find by route_path
        if (!$override && $routePath) {
            $override = SeoConfig::where('route_path', $routePath)
                ->where('is_active', true)
                ->first();
        }

        if ($override) {
            return [
                'source' => 'seo_config',
                'slug' => $override->route_slug,
                'data' => $override->seo
            ];
        }

        // Auto-generate using the fallback
        return [
            'source' => 'auto-generated',
            'slug' => $routeSlug,
            'data' => $fallbackGenerator()
        ];
    }

    /**
     * Auto-generate SEO block from a PropertyListing
     */
    public function generatePropertySeo(PropertyListing $property): array
    {
        $title = $property->name . ' — ' . $property->city . ', ' . $property->country;
        $desc  = $property->description
            ? Str::limit(strip_tags($property->description), 155)
            : "Book {$property->name} in {$property->city}, {$property->country}. "
              . ucfirst($property->property_type) . ' available from $' . ($property->price_from ?? 'N/A') . '/night.';

        return [
            'title'          => $title,
            'description'    => $desc,
            'og_title'       => $title,
            'og_description' => $desc,
            'og_image'       => $property->featured_image,
            'canonical'      => null,
            'no_index'       => false,
            'schema'         => [
                '@context'       => 'https://schema.org',
                '@type'          => 'LodgingBusiness',
                'name'           => $property->name,
                'description'    => $desc,
                'image'          => $property->images ?? [],
                'address'        => [
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => $property->address,
                    'addressLocality' => $property->city,
                    'addressRegion'   => $property->state,
                    'postalCode'      => $property->postal_code,
                    'addressCountry'  => $property->country_code ?? $property->country,
                ],
                'geo' => $property->latitude ? [
                    '@type'     => 'GeoCoordinates',
                    'latitude'  => $property->latitude,
                    'longitude' => $property->longitude,
                ] : null,
                'starRating' => $property->star_rating ? [
                    '@type'       => 'Rating',
                    'ratingValue' => $property->star_rating,
                ] : null,
                'aggregateRating' => $property->rating_average ? [
                    '@type'       => 'AggregateRating',
                    'ratingValue' => $property->rating_average,
                    'reviewCount' => $property->rating_count,
                ] : null,
                'priceRange' => $property->price_from ? ('$' . number_format($property->price_from, 0)) : null,
                'telephone'  => $property->phone,
                'email'      => $property->email,
                'url'        => $property->website,
            ],
        ];
    }
}
