<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Notifications",
 *     description="In-app user notifications with priority levels and badge count"
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
     *     @OA\Parameter(name="category",    in="query", @OA\Schema(type="string", enum={"booking","payment","system","promo","review"})),
     *     @OA\Parameter(name="priority",    in="query", @OA\Schema(type="string", enum={"low","normal","high","urgent"})),
     *     @OA\Parameter(name="per_page",    in="query", @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Notifications with unread count and badge")
     * )
     */
    public function index(Request $request)
    {
        $userId = auth()->id();

        $query = UserNotification::where('user_id', $userId)
            ->when($request->boolean('unread_only'),       fn($q) => $q->unread())
            ->when($request->category,                     fn($q) => $q->where('category', $request->category))
            ->when($request->priority,                     fn($q) => $q->byPriority($request->priority))
            ->orderByRaw("FIELD(priority, 'urgent','high','normal','low')")
            ->orderBy('created_at', 'desc');

        $perPage       = min((int) $request->get('per_page', 20), 50);
        $notifications = $query->paginate($perPage);
        $unreadCount   = UserNotification::unreadCount($userId);

        return response()->json([
            'success'      => true,
            'unread_count' => $unreadCount,
            'badge'        => $unreadCount > 99 ? '99+' : (string) $unreadCount,
            'data'         => $notifications,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/notifications/badge",
     *     summary="Get unread notification count (badge) — lightweight endpoint for navbar",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Badge count")
     * )
     */
    public function badge()
    {
        $count = UserNotification::unreadCount(auth()->id());
        return response()->json([
            'success'      => true,
            'unread_count' => $count,
            'badge'        => $count > 99 ? '99+' : (string) $count,
            'has_urgent'   => UserNotification::where('user_id', auth()->id())
                                ->whereNull('read_at')
                                ->where('priority', 'urgent')
                                ->exists(),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/notifications/{id}/read",
     *     summary="Mark a single notification as read",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Marked as read"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function markRead($id)
    {
        UserNotification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail()
            ->update(['read_at' => now()]);

        return response()->json([
            'success'      => true,
            'message'      => 'Marked as read.',
            'unread_count' => UserNotification::unreadCount(auth()->id()),
        ]);
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

        return response()->json(['success' => true, 'message' => 'All notifications marked as read.', 'unread_count' => 0]);
    }

    /**
     * @OA\Delete(
     *     path="/notifications/{id}",
     *     summary="Delete a single notification",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function destroy($id)
    {
        UserNotification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail()
            ->delete();

        return response()->json([
            'success'      => true,
            'message'      => 'Notification deleted.',
            'unread_count' => UserNotification::unreadCount(auth()->id()),
        ]);
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
        return response()->json(['success' => true, 'message' => 'All notifications deleted.', 'unread_count' => 0]);
    }
}
