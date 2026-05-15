<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\RolePermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

/**
 * @OA\Tag(
 *     name="Admin Permissions",
 *     description="Granular permission management (Admin only)"
 * )
 */
class PermissionController extends Controller
{
    protected $service;

    public function __construct(RolePermissionService $service)
    {
        $this->service = $service;
    }

    /**
     * @OA\Get(
     *     path="/api/admin/permissions",
     *     summary="List all permissions",
     *     tags={"Admin Permissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->service->getAllPermissions()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/permissions",
     *     summary="Create a new permission",
     *     tags={"Admin Permissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="delete-posts")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Permission created")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|unique:permissions,name']);
        
        $permission = Permission::create(['name' => $request->name, 'guard_name' => 'api']);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Permission created successfully',
            'data' => $permission
        ], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/permissions/{id}",
     *     summary="Delete a permission",
     *     tags={"Admin Permissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Permission deleted")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Permission deleted successfully'
        ]);
    }
}
