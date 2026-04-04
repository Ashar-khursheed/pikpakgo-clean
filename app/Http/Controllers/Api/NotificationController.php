<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Notifications",
 *     description="In-app user notifications"
 * )
 */
class NotificationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/notifications",
     *     summary="Get current user's notifications",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="unread_only", in="query", @OA\Schema(type="boolean", example=false)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Notifications list with unread count")
     * )
     */
    public function index(Request $request)
    {
        $query = UserNotification::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc');

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $perPage = min((int) $request->get('per_page', 20), 50);
        $notifications = $query->paginate($perPage);

        $unreadCount = UserNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success'      => true,
            'unread_count' => $unreadCount,
            'data'         => $notifications,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/notifications/{id}/read",
     *     summary="Mark a notification as read",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Marked as read"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function markRead($id)
    {
        $notification = UserNotification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Marked as read.']);
    }

    /**
     * @OA\Put(
     *     path="/notifications/read-all",
     *     summary="Mark all notifications as read",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="All marked as read")
     * )
     */
    public function markAllRead()
    {
        UserNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'All notifications marked as read.']);
    }

    /**
     * @OA\Delete(
     *     path="/notifications/{id}",
     *     summary="Delete a notification",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        UserNotification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail()
            ->delete();

        return response()->json(['success' => true, 'message' => 'Notification deleted.']);
    }

    /**
     * @OA\Delete(
     *     path="/notifications",
     *     summary="Delete all notifications for current user",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="All deleted")
     * )
     */
    public function destroyAll()
    {
        UserNotification::where('user_id', auth()->id())->delete();
        return response()->json(['success' => true, 'message' => 'All notifications deleted.']);
    }
}
