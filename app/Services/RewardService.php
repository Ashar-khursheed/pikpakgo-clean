<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\RewardLedger;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class RewardService
{
    /**
     * Get user's current reward points balance.
     */
    public function getUserPointsBalance(int $userId): int
    {
        return (int) RewardLedger::where('user_id', $userId)->sum('points');
    }

    /**
     * Determine user's reward tier based on lifetime completed bookings or total spent.
     */
    public function determineUserTier(int $userId): string
    {
        $bookings = Booking::where('user_id', $userId)
            ->where('booking_status', 'completed')
            ->get();

        $bookingCount = $bookings->count();
        $totalSpent = $bookings->sum('total_price');

        if ($bookingCount >= 15 || $totalSpent >= 5000) {
            return 'platinum';
        }

        if ($bookingCount >= 5 || $totalSpent >= 1500) {
            return 'gold';
        }

        return 'silver';
    }

    /**
     * Calculate how many points a booking earns.
     */
    public function calculateEarnedPoints(Booking $booking): int
    {
        if (!$booking->user_id) {
            return 0;
        }

        $tier = $this->determineUserTier($booking->user_id);
        $basePrice = (float) $booking->total_price;

        $multiplier = match ($tier) {
            'platinum' => 1.5,
            'gold' => 1.2,
            'silver' => 1.0,
            default => 1.0,
        };

        return (int) round($basePrice * $multiplier);
    }

    /**
     * Earn reward points for a booking.
     */
    public function earnPointsForBooking(Booking $booking): ?RewardLedger
    {
        if (!$booking->user_id) {
            return null;
        }

        // Avoid duplicate earning for the same booking
        $exists = RewardLedger::where('user_id', $booking->user_id)
            ->where('booking_id', $booking->id)
            ->where('type', 'earn')
            ->exists();

        if ($exists) {
            return null;
        }

        $tier = $this->determineUserTier($booking->user_id);
        $points = $this->calculateEarnedPoints($booking);

        if ($points <= 0) {
            return null;
        }

        return RewardLedger::create([
            'user_id' => $booking->user_id,
            'booking_id' => $booking->id,
            'points' => $points,
            'type' => 'earn',
            'tier_applied' => $tier,
            'description' => "Earned {$points} points from booking {$booking->booking_reference}",
        ]);
    }

    /**
     * Redeem reward points on a booking to discount it.
     */
    public function redeemPointsForBooking(Booking $booking, int $pointsToRedeem): ?RewardLedger
    {
        if (!$booking->user_id || $pointsToRedeem <= 0) {
            return null;
        }

        $balance = $this->getUserPointsBalance($booking->user_id);

        if ($pointsToRedeem > $balance) {
            Log::warning("User #{$booking->user_id} tried to redeem {$pointsToRedeem} points but only has {$balance}.");
            return null;
        }

        // Create redemption record (negative points)
        return RewardLedger::create([
            'user_id' => $booking->user_id,
            'booking_id' => $booking->id,
            'points' => -$pointsToRedeem,
            'type' => 'redeem',
            'description' => "Redeemed {$pointsToRedeem} points on booking {$booking->booking_reference}",
        ]);
    }

    /**
     * Rollback earned reward points for a cancelled/refunded booking.
     */
    public function rollbackPointsForBooking(Booking $booking): ?RewardLedger
    {
        if (!$booking->user_id) {
            return null;
        }

        // Find how many points were earned for this booking
        $earnRecord = RewardLedger::where('booking_id', $booking->id)
            ->where('type', 'earn')
            ->first();

        if (!$earnRecord) {
            return null;
        }

        // Check if rollback already performed
        $rollbackExists = RewardLedger::where('booking_id', $booking->id)
            ->where('type', 'rollback')
            ->exists();

        if ($rollbackExists) {
            return null;
        }

        $pointsToRollback = $earnRecord->points;

        return RewardLedger::create([
            'user_id' => $booking->user_id,
            'booking_id' => $booking->id,
            'points' => -$pointsToRollback,
            'type' => 'rollback',
            'description' => "Rolled back {$pointsToRollback} points for cancelled/refunded booking {$booking->booking_reference}",
        ]);
    }
}
