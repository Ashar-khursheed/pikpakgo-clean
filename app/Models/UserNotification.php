<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserNotification extends Model
{
    use SoftDeletes;

    protected $table = 'user_notifications';

    protected $fillable = [
        'user_id', 'type', 'title', 'body', 'data',
        'priority', 'category', 'action_url', 'icon', 'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    // ── Notification type → icon/category map ──────────────────────────
    private const TYPE_MAP = [
        'booking_confirmed'  => ['icon' => '✅', 'category' => 'booking',  'priority' => 'high'],
        'booking_cancelled'  => ['icon' => '❌', 'category' => 'booking',  'priority' => 'high'],
        'payment_received'   => ['icon' => '💳', 'category' => 'payment',  'priority' => 'high'],
        'payment_failed'     => ['icon' => '⚠️',  'category' => 'payment',  'priority' => 'urgent'],
        'payout_failed'      => ['icon' => '🚨', 'category' => 'payment',  'priority' => 'urgent'],
        'review_approved'    => ['icon' => '⭐', 'category' => 'review',   'priority' => 'normal'],
        'review_rejected'    => ['icon' => '🚫', 'category' => 'review',   'priority' => 'normal'],
        'host_verified'      => ['icon' => '🏅', 'category' => 'system',   'priority' => 'high'],
        'welcome'            => ['icon' => '👋', 'category' => 'system',   'priority' => 'low'],
        'promo'              => ['icon' => '🎁', 'category' => 'promo',    'priority' => 'low'],
    ];

    // ── Scopes ─────────────────────────────────────────────────────────
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    // ── Accessors ─────────────────────────────────────────────────────
    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Static helpers ─────────────────────────────────────────────────

    /**
     * Create a notification with automatic icon/category/priority resolution.
     */
    public static function notify(
        int    $userId,
        string $type,
        string $title,
        string $body,
        array  $data       = [],
        string $priority   = 'normal',
        ?string $actionUrl = null
    ): self {
        $meta = self::TYPE_MAP[$type] ?? ['icon' => '🔔', 'category' => 'system', 'priority' => $priority];

        return static::create([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'data'       => $data,
            'priority'   => $meta['priority'],
            'category'   => $meta['category'],
            'icon'       => $meta['icon'],
            'action_url' => $actionUrl ?? ($data['action_url'] ?? null),
        ]);
    }

    /** Unread count for a user (cheap — uses index on read_at) */
    public static function unreadCount(int $userId): int
    {
        return static::where('user_id', $userId)->whereNull('read_at')->count();
    }
}
