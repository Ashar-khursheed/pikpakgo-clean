<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\RolePermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Admin Roles",
 *     description="User role management (Admin only)"
 * )
 */
class RoleController extends Controller
{
    protected $service;

    public function __construct(RolePermissionService $service)
    {
        $this->service = $service;
    }

    /**
     * @OA\Get(
     *     path="/admin/roles",
     *     summary="List all roles",
     *     tags={"Admin Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->service->getAllRoles()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/roles",
     *     summary="Create a new role",
     *     tags={"Admin Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="editor")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Role created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|unique:roles,name']);
        
        $role = $this->service->createRole($request->name);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Role created successfully',
            'data' => $role
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/roles/{id}",
     *     summary="Update a role",
     *     tags={"Admin Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="updated-role")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Role updated")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate(['name' => 'required|string|unique:roles,name,' . $id]);
        
        $role = $this->service->updateRole($id, $request->name);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Role updated successfully',
            'data' => $role
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/roles/{id}",
     *     summary="Delete a role",
     *     tags={"Admin Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Role deleted")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->deleteRole($id);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/roles/{id}/permissions",
     *     summary="Sync permissions to a role",
     *     tags={"Admin Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string", example="manage-users"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Permissions synced")
     * )
     */
    public function syncPermissions(Request $request, int $id): JsonResponse
    {
        $request->validate(['permissions' => 'required|array']);
        
        $role = $this->service->syncRolePermissions($id, $request->permissions);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Permissions synced successfully',
            'data' => $role->load('permissions')
        ]);
    }
}
