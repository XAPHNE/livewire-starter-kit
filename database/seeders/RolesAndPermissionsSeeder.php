<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            'Create Users',
            'Edit Users',
            'Delete Users',
            'View Users',
            'Create Roles',
            'Edit Roles',
            'Delete Roles',
            'View Roles',
            'Create Permissions',
            'Edit Permissions',
            'Delete Permissions',
            'View Permissions',
            'Edit Settings',
            'View Settings',
            'Create Tiers',
            'Edit Tiers',
            'Delete Tiers',
            'View Tiers',
            'View Audit Trail',
            'View Authentication Log',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create Admin Role and assign permissions
        $adminRole = Role::create(['name' => 'Admin']);
        $adminRole->givePermissionTo($permissions);

        // Create Super Admin Role and assign all permissions
        $superAdminRole = Role::create(['name' => 'Super Admin']);
        $superAdminRole->givePermissionTo(Permission::all());
    }
}
