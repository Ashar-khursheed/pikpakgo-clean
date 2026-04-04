<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\PaymentTransaction;
use App\Models\PropertyListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(
 *     name="Admin - Dashboard",
 *     description="Admin dashboard statistics and analytics"
 * )
 */
class DashboardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/dashboard/stats",
     *     summary="Get dashboard overview stats",
     *     tags={"Admin - Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Overview stats")
     * )
     */
    public function getStats()
    {
        $stats = Cache::remember('admin_dashboard_stats', 300, function () {
            $today     = now()->startOfDay();
            $thisMonth = now()->startOfMonth();
            $lastMonth = now()->subMonth()->startOfMonth();

            return [
                'bookings' => [
                    'total'        => Booking::count(),
                    'today'        => Booking::where('created_at', '>=', $today)->count(),
                    'this_month'   => Booking::where('created_at', '>=', $thisMonth)->count(),
                    'last_month'   => Booking::whereBetween('created_at', [$lastMonth, $thisMonth])->count(),
                    'pending'      => Booking::where('booking_status', 'pending')->count(),
                    'confirmed'    => Booking::where('booking_status', 'confirmed')->count(),
                    'cancelled'    => Booking::where('booking_status', 'cancelled')->count(),
                    'completed'    => Booking::where('booking_status', 'completed')->count(),
                ],
                'revenue' => [
                    'total'           => Booking::where('payment_status', 'paid')->sum('total_price'),
                    'today'           => Booking::where('payment_status', 'paid')->where('created_at', '>=', $today)->sum('total_price'),
                    'this_month'      => Booking::where('payment_status', 'paid')->where('created_at', '>=', $thisMonth)->sum('total_price'),
                    'last_month'      => Booking::where('payment_status', 'paid')->whereBetween('created_at', [$lastMonth, $thisMonth])->sum('total_price'),
                    'total_markup'    => Booking::sum('markup_amount'),
                    'currency'        => 'USD',
                ],
                'users' => [
                    'total'           => User::count(),
                    'customers'       => User::where('user_type', 'customer')->count(),
                    'hosts'           => User::where('user_type', 'host')->count(),
                    'agencies'        => User::where('user_type', 'agency')->count(),
                    'new_this_month'  => User::where('created_at', '>=', $thisMonth)->count(),
                    'active'          => User::where('status', 'active')->count(),
                ],
                'properties' => [
                    'total'           => PropertyListing::count(),
                    'active'          => PropertyListing::where('is_active', true)->count(),
                    'featured'        => PropertyListing::where('is_featured', true)->count(),
                ],
                'payments' => [
                    'total_processed'  => PaymentTransaction::where('status', 'success')->sum('amount'),
                    'total_refunded'   => PaymentTransaction::where('transaction_type', 'refund')->sum('amount'),
                    'failed_today'     => PaymentTransaction::where('status', 'failed')->where('created_at', '>=', $today)->count(),
                ],
            ];
        });

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * @OA\Get(
     *     path="/admin/dashboard/revenue",
     *     summary="Get revenue statistics over time",
     *     tags={"Admin - Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="period", in="query", description="daily|weekly|monthly", @OA\Schema(type="string", example="monthly")),
     *     @OA\Parameter(name="months", in="query", description="Number of months (default 6)", @OA\Schema(type="integer", example=6)),
     *     @OA\Response(response=200, description="Revenue chart data")
     * )
     */
    public function getRevenueStats(Request $request)
    {
        $months = min((int)$request->get('months', 6), 12);
        $period = $request->get('period', 'monthly');

        $data = [];

        if ($period === 'daily') {
            for ($i = 29; $i >= 0; $i--) {
                $date    = now()->subDays($i)->toDateString();
                $revenue = Booking::where('payment_status', 'paid')
                    ->whereDate('created_at', $date)
                    ->sum('total_price');
                $count   = Booking::whereDate('created_at', $date)->count();
                $data[]  = ['date' => $date, 'revenue' => (float)$revenue, 'bookings' => $count];
            }
        } else {
            for ($i = $months - 1; $i >= 0; $i--) {
                $month   = now()->subMonths($i);
                $revenue = Booking::where('payment_status', 'paid')
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->sum('total_price');
                $count   = Booking::whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count();
                $data[]  = [
                    'month'    => $month->format('Y-m'),
                    'label'    => $month->format('M Y'),
                    'revenue'  => (float)$revenue,
                    'bookings' => $count,
                ];
            }
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * @OA\Get(
     *     path="/admin/dashboard/bookings-chart",
     *     summary="Get booking status breakdown for chart",
     *     tags={"Admin - Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Booking chart data")
     * )
     */
    public function getBookingsChart()
    {
        $data = Booking::select('booking_status', DB::raw('count(*) as count'))
            ->groupBy('booking_status')
            ->pluck('count', 'booking_status');

        $providerData = Booking::select('provider', DB::raw('count(*) as count'))
            ->groupBy('provider')
            ->pluck('count', 'provider');

        return response()->json([
            'success' => true,
            'data'    => [
                'by_status'   => $data,
                'by_provider' => $providerData,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/dashboard/recent-bookings",
     *     summary="Get recent bookings for dashboard feed",
     *     tags={"Admin - Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", example=10)),
     *     @OA\Response(response=200, description="Recent bookings")
     * )
     */
    public function getRecentBookings(Request $request)
    {
        $limit = min((int)$request->get('limit', 10), 50);

        $bookings = Booking::with('user:id,first_name,last_name,email')
            ->select([
                'id', 'booking_reference', 'provider', 'holder_first_name', 'holder_last_name',
                'holder_email', 'property_name', 'property_city', 'check_in_date', 'check_out_date',
                'total_price', 'currency', 'booking_status', 'payment_status', 'user_id', 'created_at',
            ])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json(['success' => true, 'data' => $bookings]);
    }

    /**
     * @OA\Get(
     *     path="/admin/dashboard/top-properties",
     *     summary="Get top properties by bookings and revenue",
     *     tags={"Admin - Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", example=10)),
     *     @OA\Response(response=200, description="Top properties")
     * )
     */
    public function getTopProperties(Request $request)
    {
        $limit = min((int)$request->get('limit', 10), 50);

        $top = Booking::select(
                'property_code',
                'property_name',
                'property_city',
                'property_country',
                'provider',
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw('SUM(total_price) as total_revenue'),
                DB::raw('AVG(total_price) as avg_booking_value')
            )
            ->where('booking_status', '!=', 'cancelled')
            ->groupBy('property_code', 'property_name', 'property_city', 'property_country', 'provider')
            ->orderByDesc('total_bookings')
            ->limit($limit)
            ->get();

        return response()->json(['success' => true, 'data' => $top]);
    }

    /**
     * @OA\Get(
     *     path="/admin/dashboard/user-growth",
     *     summary="Get user registration growth over time",
     *     tags={"Admin - Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="months", in="query", @OA\Schema(type="integer", example=6)),
     *     @OA\Response(response=200, description="User growth data")
     * )
     */
    public function getUserGrowth(Request $request)
    {
        $months = min((int)$request->get('months', 6), 12);
        $data   = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month  = now()->subMonths($i);
            $counts = User::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->select('user_type', DB::raw('count(*) as count'))
                ->groupBy('user_type')
                ->pluck('count', 'user_type');

            $data[] = [
                'month'     => $month->format('Y-m'),
                'label'     => $month->format('M Y'),
                'total'     => $counts->sum(),
                'customers' => $counts->get('customer', 0),
                'hosts'     => $counts->get('host', 0),
                'agencies'  => $counts->get('agency', 0),
            ];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}
