<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Campaign permissions
            'create campaigns',
            'edit own campaigns',
            'edit all campaigns',
            'delete own campaigns',
            'delete all campaigns',
            'publish campaigns',

            // AI permissions
            'generate ai frames',
            'unlimited ai generations',

            // Video permissions
            'generate videos',

            // Brand Kit permissions
            'create brand kits',
            'manage brand kits',

            // Statistics permissions
            'view own statistics',
            'view all statistics',
            'export statistics',

            // Admin permissions
            'manage users',
            'manage roles',
            'view admin dashboard',
            'moderate content',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions

        // User role
        $user = Role::create(['name' => 'User']);
        $user->givePermissionTo([
            'create campaigns',
            'edit own campaigns',
            'delete own campaigns',
            'generate ai frames',
            'create brand kits',
            'view own statistics',
        ]);

        // Organisation role
        $org = Role::create(['name' => 'Organisation']);
        $org->givePermissionTo([
            'create campaigns',
            'edit own campaigns',
            'delete own campaigns',
            'publish campaigns',
            'unlimited ai generations',
            'generate videos',
            'create brand kits',
            'manage brand kits',
            'view own statistics',
            'export statistics',
        ]);

        // Designer role
        $designer = Role::create(['name' => 'Designer']);
        $designer->givePermissionTo([
            'create campaigns',
            'edit own campaigns',
            'delete own campaigns',
            'publish campaigns',
            'unlimited ai generations',
            'generate videos',
            'create brand kits',
            'manage brand kits',
            'view own statistics',
        ]);

        // Admin role
        $admin = Role::create(['name' => 'Admin']);
        $admin->givePermissionTo(Permission::all());
    }
}
