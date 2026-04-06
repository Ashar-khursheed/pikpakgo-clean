<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ConfirmBookingWithProvider;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Admin - Financial",
 *     description="Revenue, profit, and provider payout management"
 * )
 */
class FinancialController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/financial/overview",
     *     summary="Platform financial overview — revenue, costs, profit",
     *     tags={"Admin - Financial"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="from", in="query", description="Start date (Y-m-d)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to",   in="query", description="End date (Y-m-d)",   @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="group_by", in="query", description="day|month|year", @OA\Schema(type="string", default="month")),
     *     @OA\Response(response=200, description="Financial overview")
     * )
     */
    public function overview(Request $request)
    {
        $from    = $request->get('from', now()->startOfMonth()->toDateString());
        $to      = $request->get('to',   now()->endOfMonth()->toDateString());
        $groupBy = $request->get('group_by', 'month');

        $dateFormat = match ($groupBy) {
            'day'   => '%Y-%m-%d',
            'year'  => '%Y',
            default => '%Y-%m',
        };

        // ── All-time totals ────────────────────────────────────────────────
        $allTime = Booking::where('payment_status', 'paid')
            ->selectRaw('
                COUNT(*)                     AS total_bookings,
                SUM(paid_amount)             AS total_collected,
                SUM(base_price)              AS total_provider_cost,
                SUM(markup_amount)           AS total_platform_fee,
                SUM(net_profit)              AS total_net_profit,
                AVG(markup_percentage)       AS avg_markup_pct
            ')
            ->first();

        // ── Period totals ──────────────────────────────────────────────────
        $period = Booking::where('payment_status', 'paid')
            ->whereBetween('paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('
                COUNT(*)                     AS total_bookings,
                SUM(paid_amount)             AS total_collected,
                SUM(base_price)              AS total_provider_cost,
                SUM(markup_amount)           AS total_platform_fee,
                SUM(net_profit)              AS total_net_profit
            ')
            ->first();

        // ── Chart data (grouped) ───────────────────────────────────────────
        $chart = Booking::where('payment_status', 'paid')
            ->whereBetween('paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw("
                DATE_FORMAT(paid_at, '{$dateFormat}') AS period,
                COUNT(*)                              AS bookings,
                SUM(paid_amount)                      AS collected,
                SUM(base_price)                       AS provider_cost,
                SUM(markup_amount)                    AS platform_fee,
                SUM(net_profit)                       AS net_profit
            ")
            ->groupByRaw("DATE_FORMAT(paid_at, '{$dateFormat}')")
            ->orderByRaw("DATE_FORMAT(paid_at, '{$dateFormat}')")
            ->get();

        // ── Payout status breakdown ────────────────────────────────────────
        $payoutStatus = Booking::where('payment_status', 'paid')
            ->selectRaw('provider_payout_status, COUNT(*) AS count, SUM(base_price) AS provider_cost')
            ->groupBy('provider_payout_status')
            ->get();

        // ── By provider ───────────────────────────────────────────────────
        $byProvider = Booking::where('payment_status', 'paid')
            ->selectRaw('provider, COUNT(*) AS bookings, SUM(paid_amount) AS collected, SUM(net_profit) AS profit')
            ->groupBy('provider')
            ->get();

        return response()->json([
            'success'       => true,
            'period'        => ['from' => $from, 'to' => $to],
            'all_time'      => $allTime,
            'period_totals' => $period,
            'chart'         => $chart,
            'payout_status' => $payoutStatus,
            'by_provider'   => $byProvider,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/financial/payouts",
     *     summary="List bookings by provider payout status",
     *     tags={"Admin - Financial"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", description="pending|sent|failed|not_required", @OA\Schema(type="string", default="pending")),
     *     @OA\Parameter(name="provider", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Payout list")
     * )
     */
    public function payouts(Request $request)
    {
        $status   = $request->get('status', 'pending');
        $provider = $request->get('provider');

        $query = Booking::where('payment_status', 'paid')
            ->where('provider_payout_status', $status)
            ->when($provider, fn ($q) => $q->where('provider', $provider))
            ->select([
                'id', 'booking_reference', 'provider', 'provider_booking_reference',
                'property_name', 'property_city',
                'check_in_date', 'check_out_date',
                'base_price', 'markup_amount', 'total_price', 'net_profit', 'currency',
                'provider_payout_status', 'provider_payout_at', 'provider_payout_error',
                'paid_at', 'holder_first_name', 'holder_last_name', 'holder_email',
            ])
            ->orderBy('paid_at', 'desc')
            ->paginate(25);

        // Summary for this status
        $summary = Booking::where('payment_status', 'paid')
            ->where('provider_payout_status', $status)
            ->when($provider, fn ($q) => $q->where('provider', $provider))
            ->selectRaw('COUNT(*) AS count, SUM(base_price) AS total_provider_cost, SUM(net_profit) AS total_profit')
            ->first();

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data'    => $query,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/financial/payouts/{id}/retry",
     *     summary="Retry a failed provider payout for a specific booking",
     *     tags={"Admin - Financial"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payout job re-queued"),
     *     @OA\Response(response=404, description="Booking not found"),
     *     @OA\Response(response=422, description="Booking not eligible for retry")
     * )
     */
    public function retryPayout($id)
    {
        $booking = Booking::findOrFail($id);

        if ($booking->payment_status !== 'paid') {
            return response()->json(['success' => false, 'message' => 'Booking has not been paid.'], 422);
        }

        if (in_array($booking->provider_payout_status, ['sent', 'not_required'])) {
            return response()->json(['success' => false, 'message' => 'Payout already completed.'], 422);
        }

        // Reset error so the job can try fresh
        $booking->update([
            'provider_payout_status' => 'pending',
            'provider_payout_error'  => null,
        ]);

        ConfirmBookingWithProvider::dispatch($booking->id);

        return response()->json([
            'success' => true,
            'message' => "Payout job re-queued for booking {$booking->booking_reference}.",
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/financial/payouts/retry-all-failed",
     *     summary="Retry all bookings with provider_payout_status = failed",
     *     tags={"Admin - Financial"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Jobs queued")
     * )
     */
    public function retryAllFailed()
    {
        $failed = Booking::where('payment_status', 'paid')
            ->where('provider_payout_status', 'failed')
            ->get();

        $count = 0;
        foreach ($failed as $booking) {
            $booking->update([
                'provider_payout_status' => 'pending',
                'provider_payout_error'  => null,
            ]);
            ConfirmBookingWithProvider::dispatch($booking->id);
            $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} payout job(s) re-queued.",
            'count'   => $count,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/financial/profit-by-property",
     *     summary="Net profit ranked by property",
     *     tags={"Admin - Financial"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Response(response=200, description="Profit by property")
     * )
     */
    public function profitByProperty(Request $request)
    {
        $limit = (int) $request->get('limit', 10);

        $data = Booking::where('payment_status', 'paid')
            ->selectRaw('
                property_code,
                property_name,
                COUNT(*)           AS bookings,
                SUM(paid_amount)   AS total_collected,
                SUM(base_price)    AS total_provider_cost,
                SUM(net_profit)    AS total_profit,
                AVG(markup_percentage) AS avg_markup_pct
            ')
            ->groupBy('property_code', 'property_name')
            ->orderByRaw('SUM(net_profit) DESC')
            ->limit($limit)
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }
}
