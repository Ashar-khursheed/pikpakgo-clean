<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions (skipped due to resolution issue)
        // app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            'manage-users',
            'manage-roles',
            'manage-properties',
            'approve-properties',
            'view-financials',
            'manage-blog',
            'manage-seo',
            'manage-bookings',
            'host-access',
            'agency-access',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // Create Roles and Assign Permissions
        
        // Super Admin
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'api']);
        $superAdmin->syncPermissions(Permission::all());

        // Admin
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $admin->syncPermissions([
            'manage-users',
            'manage-properties',
            'approve-properties',
            'manage-bookings',
            'manage-blog',
            'manage-seo',
        ]);

        // Host
        $host = Role::firstOrCreate(['name' => 'host', 'guard_name' => 'api']);
        $host->syncPermissions(['host-access', 'manage-properties']);

        // Agency
        $agency = Role::firstOrCreate(['name' => 'agency', 'guard_name' => 'api']);
        $agency->syncPermissions(['agency-access', 'manage-properties']);

        // Customer
        $customer = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'api']);
    }
}
