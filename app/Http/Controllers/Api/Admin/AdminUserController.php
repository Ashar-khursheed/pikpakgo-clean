<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Admin - Users",
 *     description="Admin user management endpoints"
 * )
 */
class AdminUserController extends Controller
{
    /**
     * @OA\Post(
     *     path="/admin/users",
     *     summary="Create a new user (Admin)",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"first_name","last_name","email","user_type"},
     *         @OA\Property(property="first_name",         type="string",  example="John"),
     *         @OA\Property(property="last_name",          type="string",  example="Doe"),
     *         @OA\Property(property="email",              type="string",  format="email", example="john@example.com"),
     *         @OA\Property(property="phone",              type="string",  example="+1234567890"),
     *         @OA\Property(property="user_type",          type="string",  enum={"customer","host","agency","admin"}, example="customer"),
     *         @OA\Property(property="status",             type="string",  enum={"active","inactive","suspended","pending"}, example="active"),
     *         @OA\Property(property="country",            type="string",  example="US"),
     *         @OA\Property(property="city",               type="string",  example="Miami"),
     *         @OA\Property(property="password",           type="string",  example="SecurePass123!", description="Leave blank to auto-generate"),
     *         @OA\Property(property="email_verified",     type="boolean", example=true, description="Mark email as verified immediately")
     *     )),
     *     @OA\Response(response=201, description="User created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'email'          => 'required|email|unique:users,email|max:200',
            'phone'          => 'nullable|string|max:20',
            'user_type'      => 'required|string|max:50',
            'status'         => 'nullable|in:active,inactive,suspended,pending',
            'country'        => 'nullable|string|max:100',
            'city'           => 'nullable|string|max:100',
            'password'       => 'nullable|string|min:8',
            'email_verified' => 'boolean',
        ]);

        $password = $validated['password'] ?? Str::random(12);

        $user = User::create([
            'first_name'        => $validated['first_name'],
            'last_name'         => $validated['last_name'],
            'email'             => $validated['email'],
            'phone'             => $validated['phone'] ?? null,
            'user_type'         => $validated['user_type'],
            'status'            => $validated['status'] ?? 'active',
            'country'           => $validated['country'] ?? null,
            'city'              => $validated['city'] ?? null,
            'password'          => Hash::make($password),
            'email_verified_at' => ($validated['email_verified'] ?? false) ? now() : null,
        ]);

        return response()->json([
            'success'           => true,
            'message'           => 'User created successfully.',
            'data'              => $user,
            'generated_password'=> isset($validated['password']) ? null : $password,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/admin/users",
     *     summary="List all users",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="user_type", in="query", @OA\Schema(type="string", enum={"customer","host","agency","admin"})),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"active","inactive","suspended","pending"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Paginated user list"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        $users = $query->with('roles')
            ->select([
                'id', 'first_name', 'last_name', 'email', 'phone',
                'user_type', 'status', 'country', 'email_verified_at',
                'last_login_at', 'created_at',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json(['success' => true, 'data' => $users]);
    }

    /**
     * @OA\Get(
     *     path="/admin/users/{id}",
     *     summary="Get user details",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User details with booking stats"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $user = User::with(['hostProfile', 'agencyProfile'])->findOrFail($id);

        $stats = [
            'total_bookings'     => $user->bookings()->count() ?? 0,
            'confirmed_bookings' => $user->bookings()->where('booking_status', 'confirmed')->count() ?? 0,
            'total_spent'        => $user->bookings()->where('payment_status', 'paid')->sum('total_price') ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data'    => array_merge($user->toArray(), ['booking_stats' => $stats]),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/admin/users/{id}/status",
     *     summary="Update user status",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"status"},
     *         @OA\Property(property="status", type="string", enum={"active","inactive","suspended","pending"}, example="active")
     *     )),
     *     @OA\Response(response=200, description="Status updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function updateStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'status' => 'required|in:active,inactive,suspended,pending',
        ]);

        // Prevent admin from suspending themselves
        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Cannot change your own status'], 422);
        }

        $user->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => "User status updated to {$request->status}",
            'data'    => $user,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/admin/users/{id}/role",
     *     summary="Update user role/type",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"user_type"},
     *         @OA\Property(property="user_type", type="string", enum={"customer","host","agency","admin"}, example="admin")
     *     )),
     *     @OA\Response(response=200, description="Role updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function updateRole(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'user_type' => 'required|string|max:50',
        ]);

        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Cannot change your own role'], 422);
        }

        $user->update(['user_type' => $request->user_type]);

        return response()->json([
            'success' => true,
            'message' => "User role updated to {$request->user_type}",
            'data'    => $user,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/admin/users/{id}",
     *     summary="Update user details",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="first_name", type="string"),
     *         @OA\Property(property="last_name", type="string"),
     *         @OA\Property(property="phone", type="string"),
     *         @OA\Property(property="country", type="string"),
     *         @OA\Property(property="city", type="string")
     *     )),
     *     @OA\Response(response=200, description="User updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'first_name'         => 'sometimes|string|max:255',
            'last_name'          => 'sometimes|string|max:255',
            'phone'              => 'sometimes|string|max:20',
            'country'            => 'sometimes|string|max:100',
            'city'               => 'sometimes|string|max:100',
            'state'              => 'sometimes|string|max:100',
            'preferred_currency' => 'sometimes|string|max:3',
            'preferred_language' => 'sometimes|string|max:5',
        ]);

        $user->update($validated);

        return response()->json(['success' => true, 'message' => 'User updated', 'data' => $user]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/users/{id}",
     *     summary="Soft delete a user",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete your own account'], 422);
        }

        $user->delete();

        return response()->json(['success' => true, 'message' => 'User deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/admin/users/{id}/bookings",
     *     summary="Get bookings for a specific user",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User bookings"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function getUserBookings($id)
    {
        $user = User::findOrFail($id);

        $bookings = $user->bookings()
            ->select([
                'id', 'booking_reference', 'provider', 'property_name',
                'property_city', 'check_in_date', 'check_out_date',
                'total_price', 'currency', 'booking_status', 'payment_status', 'created_at',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $bookings]);
    }

    /**
     * @OA\Post(
     *     path="/admin/users/{id}/reset-password",
     *     summary="Admin reset a user's password",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"password"},
     *         @OA\Property(property="password", type="string", example="NewPass123!")
     *     )),
     *     @OA\Response(response=200, description="Password reset"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['success' => true, 'message' => 'Password reset successfully']);
    }
}
