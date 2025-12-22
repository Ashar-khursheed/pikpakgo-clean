<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Performance",
 *     description="Performance monitoring and system health"
 * )
 */
class PerformanceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/performance/database-stats",
     *     tags={"Performance"},
     *     summary="Get database statistics",
     *     description="Returns database connection and performance metrics",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Database statistics retrieved successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function databaseStats()
    {
        try {
            $connection = DB::connection()->getDatabaseName();
            
            $users = DB::table('users');
            $totalUsers = $users->count();
            $activeUsers = $users->where('status', 'active')->count();

            $stats = [
                'connection' => DB::connection()->getDriverName(),
                'database' => $connection,
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'tables' => [
                    'users' => DB::table('users')->count(),
                    'host_profiles' => DB::table('host_profiles')->count(),
                    'agency_profiles' => DB::table('agency_profiles')->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve database statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/performance/health",
     *     tags={"Performance"},
     *     summary="Health check",
     *     description="Check application health",
     *     @OA\Response(response=200, description="Application is healthy"),
     *     @OA\Response(response=503, description="Service unavailable")
     * )
     */
    public function health()
    {
        $health = [
            'database' => 'disconnected',
            'cache' => config('cache.default'),
            'timestamp' => now()->toIso8601String()
        ];

        $isHealthy = true;

        try {
            DB::connection()->getPdo();
            $health['database'] = 'connected';
        } catch (\Exception $e) {
            $isHealthy = false;
            $health['database_error'] = $e->getMessage();
        }

        return response()->json([
            'success' => $isHealthy,
            'message' => $isHealthy ? 'Application is healthy' : 'Database unavailable',
            'data' => $health
        ], $isHealthy ? 200 : 503);
    }

    /**
     * @OA\Post(
     *     path="/performance/clear-cache",
     *     tags={"Performance"},
     *     summary="Clear application cache",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Cache cleared"),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function clearCache()
    {
        $user = auth()->user();
        
        if ($user->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        try {
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');

            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
