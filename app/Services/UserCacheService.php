<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class UserCacheService
{
    // Cache TTL in seconds
    const USER_CACHE_TTL = 3600; // 1 hour
    const USER_TOKEN_TTL = 3600; // 1 hour
    const USER_SESSION_TTL = 7200; // 2 hours
    const VERIFICATION_TOKEN_TTL = 86400; // 24 hours
    const RESET_TOKEN_TTL = 7200; // 2 hours

    /**
     * Generate cache key for user by ID
     */
    private function getUserCacheKey(int $userId): string
    {
        return "user:{$userId}";
    }

    /**
     * Generate cache key for user by email
     */
    private function getUserEmailCacheKey(string $email): string
    {
        return "user:email:{$email}";
    }

    /**
     * Generate cache key for user session
     */
    private function getUserSessionKey(int $userId): string
    {
        return "user:session:{$userId}";
    }

    /**
     * Generate cache key for JWT token blacklist
     */
    private function getTokenBlacklistKey(string $token): string
    {
        return "jwt:blacklist:{$token}";
    }

    /**
     * Generate cache key for user verification token
     */
    private function getVerificationTokenKey(string $token): string
    {
        return "verification:token:{$token}";
    }

    /**
     * Generate cache key for reset token
     */
    private function getResetTokenKey(string $token): string
    {
        return "reset:token:{$token}";
    }

    /**
     * Cache user data
     */
    public function cacheUser(User $user): bool
    {
        try {
            $userData = $user->toArray();
            
            // Load relationships if they exist
            if ($user->hostProfile) {
                $userData['host_profile'] = $user->hostProfile->toArray();
            }
            if ($user->agencyProfile) {
                $userData['agency_profile'] = $user->agencyProfile->toArray();
            }

            // Cache by ID
            Cache::put(
                $this->getUserCacheKey($user->id),
                $userData,
                self::USER_CACHE_TTL
            );

            // Cache by email for login lookups
            Cache::put(
                $this->getUserEmailCacheKey($user->email),
                $user->id,
                self::USER_CACHE_TTL
            );

            return true;
        } catch (\Exception $e) {
            \Log::error('User cache error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user from cache by ID
     */
    public function getUserById(int $userId): ?array
    {
        return Cache::get($this->getUserCacheKey($userId));
    }

    /**
     * Get user ID from cache by email
     */
    public function getUserIdByEmail(string $email): ?int
    {
        return Cache::get($this->getUserEmailCacheKey($email));
    }

    /**
     * Get user from cache or database
     */
    public function getUser(int $userId): ?User
    {
        $cacheKey = $this->getUserCacheKey($userId);
        
        return Cache::remember($cacheKey, self::USER_CACHE_TTL, function () use ($userId) {
            return User::with(['hostProfile', 'agencyProfile'])->find($userId);
        });
    }

    /**
     * Invalidate user cache
     */
    public function invalidateUser(int $userId, ?string $email = null): bool
    {
        try {
            Cache::forget($this->getUserCacheKey($userId));
            
            if ($email) {
                Cache::forget($this->getUserEmailCacheKey($email));
            }

            Cache::forget($this->getUserSessionKey($userId));

            return true;
        } catch (\Exception $e) {
            \Log::error('Cache invalidation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Cache user session data
     */
    public function cacheUserSession(int $userId, array $sessionData): bool
    {
        return Cache::put(
            $this->getUserSessionKey($userId),
            $sessionData,
            self::USER_SESSION_TTL
        );
    }

    /**
     * Get user session from cache
     */
    public function getUserSession(int $userId): ?array
    {
        return Cache::get($this->getUserSessionKey($userId));
    }

    /**
     * Add JWT token to blacklist
     */
    public function blacklistToken(string $token, int $ttl = self::USER_TOKEN_TTL): bool
    {
        return Cache::put(
            $this->getTokenBlacklistKey($token),
            true,
            $ttl
        );
    }

    /**
     * Check if token is blacklisted
     */
    public function isTokenBlacklisted(string $token): bool
    {
        return Cache::has($this->getTokenBlacklistKey($token));
    }

    /**
     * Cache verification token
     */
    public function cacheVerificationToken(string $token, int $userId): bool
    {
        return Cache::put(
            $this->getVerificationTokenKey($token),
            $userId,
            self::VERIFICATION_TOKEN_TTL
        );
    }

    /**
     * Get user ID from verification token
     */
    public function getUserByVerificationToken(string $token): ?int
    {
        return Cache::get($this->getVerificationTokenKey($token));
    }

    /**
     * Invalidate verification token
     */
    public function invalidateVerificationToken(string $token): bool
    {
        return Cache::forget($this->getVerificationTokenKey($token));
    }

    /**
     * Cache reset token
     */
    public function cacheResetToken(string $token, int $userId): bool
    {
        return Cache::put(
            $this->getResetTokenKey($token),
            $userId,
            self::RESET_TOKEN_TTL
        );
    }

    /**
     * Get user ID from reset token
     */
    public function getUserByResetToken(string $token): ?int
    {
        return Cache::get($this->getResetTokenKey($token));
    }

    /**
     * Invalidate reset token
     */
    public function invalidateResetToken(string $token): bool
    {
        return Cache::forget($this->getResetTokenKey($token));
    }

    /**
     * Clear all user-related caches
     */
    public function clearAllUserCaches(int $userId, string $email): bool
    {
        try {
            $this->invalidateUser($userId, $email);
            
            // Clear all related keys using Redis pattern matching
            $pattern = "laravel_database_user:{$userId}*";
            $keys = Redis::keys($pattern);
            
            if (!empty($keys)) {
                Redis::del($keys);
            }

            return true;
        } catch (\Exception $e) {
            \Log::error('Clear all caches error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        try {
            $info = Redis::info();
            
            return [
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? 'N/A',
                'total_keys' => Redis::dbSize(),
                'hit_rate' => $this->calculateHitRate($info),
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Unable to fetch Redis stats',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate cache hit rate
     */
    private function calculateHitRate(array $info): string
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        if ($total === 0) {
            return '0%';
        }

        return number_format(($hits / $total) * 100, 2) . '%';
    }

    /**
     * Warm up cache for active users
     */
    public function warmUpCache(array $userIds): int
    {
        $cached = 0;

        foreach ($userIds as $userId) {
            $user = User::with(['hostProfile', 'agencyProfile'])->find($userId);
            
            if ($user) {
                if ($this->cacheUser($user)) {
                    $cached++;
                }
            }
        }

        return $cached;
    }

    /**
     * Increment login attempt counter
     */
    public function incrementLoginAttempts(string $email): int
    {
        $key = "login:attempts:{$email}";
        $attempts = Cache::get($key, 0);
        $attempts++;
        
        // Cache for 15 minutes
        Cache::put($key, $attempts, 900);
        
        return $attempts;
    }

    /**
     * Get login attempts
     */
    public function getLoginAttempts(string $email): int
    {
        return Cache::get("login:attempts:{$email}", 0);
    }

    /**
     * Clear login attempts
     */
    public function clearLoginAttempts(string $email): bool
    {
        return Cache::forget("login:attempts:{$email}");
    }

    /**
     * Check if email is rate limited
     */
    public function isRateLimited(string $email, int $maxAttempts = 5): bool
    {
        return $this->getLoginAttempts($email) >= $maxAttempts;
    }
}
