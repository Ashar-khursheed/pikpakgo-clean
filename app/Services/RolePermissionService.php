<?php

namespace App\Services;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;

class RolePermissionService
{
    /**
     * Get all roles with their permissions.
     */
    public function getAllRoles(): Collection
    {
        return Role::with('permissions')->get();
    }

    /**
     * Create a new role.
     */
    public function createRole(string $name): Role
    {
        return Role::create(['name' => $name, 'guard_name' => 'api']);
    }

    /**
     * Update a role.
     */
    public function updateRole(int $id, string $name): Role
    {
        $role = Role::findOrFail($id);
        $role->update(['name' => $name]);
        return $role;
    }

    /**
     * Delete a role.
     */
    public function deleteRole(int $id): bool
    {
        $role = Role::findOrFail($id);
        return $role->delete();
    }

    /**
     * Get all permissions.
     */
    public function getAllPermissions(): Collection
    {
        return Permission::all();
    }

    /**
     * Sync permissions to a role.
     */
    public function syncRolePermissions(int $roleId, array $permissions): Role
    {
        $role = Role::findOrFail($roleId);
        $role->syncPermissions($permissions);
        return $role;
    }

    /**
     * Assign a role to a user.
     */
    public function assignUserRole(User $user, string $roleName): void
    {
        $user->assignRole($roleName);
    }

    /**
     * Sync roles for a user.
     */
    public function syncUserRoles(User $user, array $roles): void
    {
        $user->syncRoles($roles);
    }
}
