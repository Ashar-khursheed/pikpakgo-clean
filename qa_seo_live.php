<?php
use App\Models\PropertyListing;
use App\Models\BlogPost;
use App\Models\BlogCategory;
use App\Models\SeoConfig;

echo "\n--- PROFESSIONAL SEO QA LIVE CHECK ---\n";

// 1. PROPERTY SYNC CHECK
echo "[1/4] Checking Property Sync Logic...\n";
$property = PropertyListing::first();
if ($property) {
    $testTitle = "QA Property Title " . rand(100, 999);
    
    // Simulate sync
    SeoConfig::updateOrCreate(
        ['model_type' => PropertyListing::class, 'model_id' => $property->id],
        ['route_slug' => 'qa-test-slug', 'meta_title' => $testTitle, 'is_active' => true]
    );

    $seo = $property->seo;
    if ($seo['source'] === 'seo_config' && $seo['data']['title'] === $testTitle) {
        echo "✅ SUCCESS: Property SEO polymorphic link is WORKING.\n";
    } else {
        echo "❌ FAILED: Property SEO link failed.\n";
    }
}

// 2. BLOG SYNC CHECK
echo "[2/4] Checking Blog Sync Logic...\n";
$post = BlogPost::first();
if ($post) {
    $testTitle = "QA Blog Title " . rand(100, 999);
    
    SeoConfig::updateOrCreate(
        ['model_type' => BlogPost::class, 'model_id' => $post->id],
        ['route_slug' => 'blog-qa-slug', 'meta_title' => $testTitle, 'is_active' => true]
    );

    $seo = $post->seo;
    if ($seo['source'] === 'seo_config' && $seo['data']['title'] === $testTitle) {
        echo "✅ SUCCESS: Blog SEO polymorphic link is WORKING.\n";
    } else {
        echo "❌ FAILED: Blog SEO link failed.\n";
    }
}

// 3. FALLBACK CHECK
echo "[3/4] Checking Auto-Generation Fallback...\n";
$tempProp = new PropertyListing(['name' => 'Luxury Tent', 'city' => 'Desert', 'country' => 'UAE', 'property_type' => 'Villa']);
$fallback = app(\App\Services\SeoService::class)->generatePropertySeo($tempProp);
if (str_contains($fallback['title'], 'Luxury Tent')) {
    echo "✅ SUCCESS: Fallback SEO is generating professional metadata.\n";
} else {
    echo "❌ FAILED: Fallback generation error.\n";
}

// 4. CLEANUP (Removing test data)
echo "[4/4] Cleaning up test records...\n";
SeoConfig::where('route_slug', 'qa-test-slug')->delete();
SeoConfig::where('route_slug', 'blog-qa-slug')->delete();
echo "✅ SUCCESS: Cleanup complete.\n";

echo "--- QA CHECK FINISHED ---\n";
