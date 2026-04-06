<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Centralized cache TTL constants + helpers.
 *
 * Switch to Redis by changing .env:
 *   CACHE_DRIVER=redis
 *   REDIS_CLIENT=predis
 *   QUEUE_CONNECTION=redis   (for queued jobs)
 *
 * All TTLs are in SECONDS.
 */
class CacheService
{
    // ── TTL Constants ──────────────────────────────────────────────────
    const TTL_REALTIME  = 30;       // 30s  — live prices, availability
    const TTL_SHORT     = 300;      // 5min — search results, property lists
    const TTL_MEDIUM    = 600;      // 10min — CMS pages, nav, settings
    const TTL_LONG      = 1800;     // 30min — calendar, top properties
    const TTL_HOUR      = 3600;     // 1hr  — site-info, currencies
    const TTL_DAY       = 86400;    // 1day — static content, FAQs

    // ── Key Generators ─────────────────────────────────────────────────
    public static function propertyKey(int|string $id): string
    {
        return "property_{$id}";
    }

    public static function calendarKey(string $propertyCode, int $year, int $month): string
    {
        return "property_calendar_{$propertyCode}_{$year}_{$month}";
    }

    public static function searchKey(array $params): string
    {
        return 'property_search_' . md5(json_encode($params));
    }

    public static function contentPageKey(string $slug): string
    {
        return "content_page_{$slug}";
    }

    public static function notificationBadgeKey(int $userId): string
    {
        return "notif_badge_{$userId}";
    }

    // ── Flush helpers ─────────────────────────────────────────────────
    public static function flushProperty(int $id, ?string $code = null): void
    {
        Cache::forget(self::propertyKey($id));
        if ($code) Cache::forget(self::propertyKey($code));
        // Bust search result caches (pattern-based — only works with Redis)
        if (config('cache.default') === 'redis') {
            // Redis supports tag-based cache via Cache::tags()
            // Usage: Cache::tags(['properties'])->flush();
        }
    }

    public static function flushContent(string $slug): void
    {
        Cache::forget(self::contentPageKey($slug));
        Cache::forget('content_header');
        Cache::forget('content_footer');
        Cache::forget('nav_menu');
    }

    public static function flushNotificationBadge(int $userId): void
    {
        Cache::forget(self::notificationBadgeKey($userId));
    }
}
