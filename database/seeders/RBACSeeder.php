<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RBACSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create basic permissions
        $permissions = [
            // User management
            'view users',
            'create users',
            'edit users',
            'delete users',
            
            // Role management
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            
            // Permission management
            'view permissions',
            'create permissions',
            'edit permissions',
            'delete permissions',
            
            // User role assignment
            'view user roles',
            'assign user roles',
            'remove user roles',
            
            // User permission assignment
            'view user permissions',
            'assign user permissions',
            'remove user permissions',
            
            // Role permission assignment
            'view role permissions',
            'assign role permissions',
            'remove role permissions',
            
            // System management
            'view system logs',
            'manage system settings',
            'backup system',
            'restore system',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create basic roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Assign permissions to roles
        $superAdminRole->givePermissionTo(Permission::all());
        
        $adminRole->givePermissionTo([
            'view users', 'create users', 'edit users', 'delete users',
            'view roles', 'create roles', 'edit roles',
            'view permissions',
            'view user roles', 'assign user roles', 'remove user roles',
            'view user permissions', 'assign user permissions', 'remove user permissions',
            'view role permissions', 'assign role permissions', 'remove role permissions',
        ]);
        
        $managerRole->givePermissionTo([
            'view users', 'create users', 'edit users',
            'view roles',
            'view permissions',
            'view user roles', 'assign user roles', 'remove user roles',
            'view user permissions',
        ]);
        
        $userRole->givePermissionTo([
            'view users',
            'view roles',
            'view permissions',
        ]);

        // Create super admin user
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@mpsoftware.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('SuperAdmin@123'),
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->assignRole('super-admin');

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@mpsoftware.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('Admin@123'),
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('admin');

        // Create manager user
        $manager = User::firstOrCreate(
            ['email' => 'manager@mpsoftware.com'],
            [
                'name' => 'Manager',
                'password' => Hash::make('Manager@123'),
                'email_verified_at' => now(),
            ]
        );
        $manager->assignRole('manager');

        // Create regular user
        $user = User::firstOrCreate(
            ['email' => 'user@mpsoftware.com'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('User@123'),
                'email_verified_at' => now(),
            ]
        );
        $user->assignRole('user');

        $this->command->info('RBAC seeder completed successfully!');
        $this->command->info('Default users created:');
        $this->command->info('- superadmin@mpsoftware.com / SuperAdmin@123 (Super Admin)');
        $this->command->info('- admin@mpsoftware.com / Admin@123 (Admin)');
        $this->command->info('- manager@mpsoftware.com / Manager@123 (Manager)');
        $this->command->info('- user@mpsoftware.com / User@123 (User)');
    }
}