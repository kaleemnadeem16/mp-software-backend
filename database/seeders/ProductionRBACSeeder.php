<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProductionRBACSeeder extends Seeder
{
    /**
     * Run the database seeds for production environment.
     * Creates the complete role hierarchy with permissions.
     */
    public function run(): void
    {
        // Clear cache to avoid issues
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('🚀 Starting Production RBAC Seeding...');

        // Create all permissions first
        $this->createPermissions();
        
        // Create roles with permissions
        $this->createRoles();
        
        // Create initial users
        $this->createInitialUsers();

        $this->command->info('✅ Production RBAC Seeding completed successfully!');
    }

    /**
     * Create all permissions in the system
     */
    private function createPermissions(): void
    {
        $this->command->info('📋 Creating permissions...');

        $permissions = [
            // Authentication & User Management
            'view users' => 'View user list and details',
            'create users' => 'Create new users',
            'edit users' => 'Edit user information',
            'delete users' => 'Delete users from system',
            'view user profiles' => 'View user profile details',
            'edit user profiles' => 'Edit user profile information',

            // RBAC Management
            'view roles' => 'View roles list and details',
            'create roles' => 'Create new roles',
            'edit roles' => 'Edit existing roles',
            'delete roles' => 'Delete roles from system',
            
            'view permissions' => 'View permissions list and details',
            'create permissions' => 'Create new permissions',
            'edit permissions' => 'Edit existing permissions',
            'delete permissions' => 'Delete permissions from system',
            
            'assign role permissions' => 'Assign permissions to roles',
            'assign user roles' => 'Assign roles to users',
            'view user roles' => 'View user role assignments',
            'view role permissions' => 'View role permission assignments',

            // System Administration
            'view system settings' => 'View system configuration',
            'edit system settings' => 'Modify system configuration',
            'view system logs' => 'View system logs and audit trails',
            'manage system maintenance' => 'Perform system maintenance tasks',

            // Super Admin Only Permissions
            'manage super admins' => 'Create, edit, delete super admin users',
            'view all system data' => 'Access all system data regardless of ownership',
            'manage system security' => 'Configure security settings and policies',
            'manage database' => 'Direct database management access',

            // API Management
            'view api documentation' => 'Access API documentation',
            'manage api access' => 'Configure API access and rate limits',
            'view api analytics' => 'View API usage analytics',

            // Future Business Logic Permissions (placeholders)
            'view dashboard' => 'Access main dashboard',
            'view reports' => 'Access system reports',
            'create reports' => 'Create new reports',
            'edit reports' => 'Edit existing reports',
            'delete reports' => 'Delete reports',

            // Department Management (for future use)
            'view departments' => 'View department information',
            'create departments' => 'Create new departments',
            'edit departments' => 'Edit department information',
            'delete departments' => 'Delete departments',
            'assign department users' => 'Assign users to departments',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name],
                [
                    'display_name' => ucwords(str_replace(['_', '-'], ' ', $name)),
                    'description' => $description,
                    'guard_name' => 'web'
                ]
            );
        }

        $this->command->info("✅ Created " . count($permissions) . " permissions");
    }

    /**
     * Create roles with their respective permissions
     */
    private function createRoles(): void
    {
        $this->command->info('👥 Creating roles...');

        // 1. Super Admin Role (Developer/System Owner)
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super-admin'],
            [
                'display_name' => 'Super Administrator',
                'description' => 'Full system access - Developer/System Owner level',
                'guard_name' => 'web'
            ]
        );

        // Super Admin gets ALL permissions
        $allPermissions = Permission::all();
        $superAdmin->syncPermissions($allPermissions);
        $this->command->info("✅ Super Admin role created with " . $allPermissions->count() . " permissions");

        // 2. Admin Role (Software Admin)
        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrator',
                'description' => 'Administrative access - Cannot manage Super Admins',
                'guard_name' => 'web'
            ]
        );

        // Admin gets all permissions EXCEPT super admin management
        $adminPermissions = Permission::whereNotIn('name', [
            'manage super admins',
            'manage database',
            'manage system security'
        ])->get();
        $admin->syncPermissions($adminPermissions);
        $this->command->info("✅ Admin role created with " . $adminPermissions->count() . " permissions");

        // 3. User Role (Regular Users)
        $user = Role::firstOrCreate(
            ['name' => 'user'],
            [
                'display_name' => 'User',
                'description' => 'Regular user access - Basic functionality',
                'guard_name' => 'web'
            ]
        );

        // User gets basic permissions
        $userPermissions = Permission::whereIn('name', [
            'view user profiles',
            'edit user profiles',
            'view dashboard',
            'view reports',
            'view api documentation',
        ])->get();
        $user->syncPermissions($userPermissions);
        $this->command->info("✅ User role created with " . $userPermissions->count() . " permissions");
    }

    /**
     * Create initial users for the system
     */
    private function createInitialUsers(): void
    {
        $this->command->info('👤 Creating initial users...');

        // 1. Create Super Admin User (Developer)
        $superAdminUser = User::firstOrCreate(
            ['email' => 'kaleem.nadeem@gmail.com'],
            [
                'name' => 'Kaleem Nadeem',
                'email' => 'kaleem.nadeem@gmail.com',
                'password' => Hash::make('SuperAdmin123!'),
                'email_verified_at' => now(),
            ]
        );
        $superAdminUser->assignRole('super-admin');
        $this->command->info("✅ Super Admin user created: kaleem.nadeem@gmail.com");

        // 2. Create Default Admin User
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@mp-software.com'],
            [
                'name' => 'System Administrator',
                'email' => 'admin@mp-software.com',
                'password' => Hash::make('Admin123!'),
                'email_verified_at' => now(),
            ]
        );
        $adminUser->assignRole('admin');
        $this->command->info("✅ Admin user created: admin@mp-software.com");

        // 3. Create Test User
        $testUser = User::firstOrCreate(
            ['email' => 'test@mp-software.com'],
            [
                'name' => 'Test User',
                'email' => 'test@mp-software.com',
                'password' => Hash::make('TestUser123!'),
                'email_verified_at' => now(),
            ]
        );
        $testUser->assignRole('user');
        $this->command->info("✅ Test user created: test@mp-software.com");

        // Display login credentials
        $this->command->warn('🔐 IMPORTANT: Default Login Credentials');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Super Admin', 'kaleem.nadeem@gmail.com', 'SuperAdmin123!'],
                ['Admin', 'admin@mp-software.com', 'Admin123!'],
                ['User', 'test@mp-software.com', 'TestUser123!'],
            ]
        );
        $this->command->warn('🚨 SECURITY: Change these passwords immediately in production!');
    }
}