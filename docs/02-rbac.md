# Role-Based Access Control (RBAC)

## Overview

This guide covers implementing Role-Based Access Control using Spatie's Laravel Permission package. This provides a flexible, database-driven approach to managing user roles and permissions in your Laravel application.

## Table of Contents

- [Package Selection](#package-selection)
- [Installation & Setup](#installation--setup)
- [Basic Usage](#basic-usage)
- [Project Structure](#project-structure)
- [Middleware Integration](#middleware-integration)
- [Permission Management](#permission-management)
- [Security Best Practices](#security-best-practices)
- [Testing RBAC](#testing-rbac)

## Package Selection

### Why Spatie Laravel Permission?

- ✅ **Battle-tested**: Used by thousands of Laravel applications
- ✅ **Laravel 12 Compatible**: Actively maintained with latest Laravel support
- ✅ **Feature-rich**: Supports roles, permissions, and role hierarchy
- ✅ **Performance optimized**: Built-in caching mechanisms
- ✅ **Well documented**: Extensive documentation and community support
- ✅ **No conflicts**: Works seamlessly with Sanctum and other packages

### Alternative Approaches

```php
// Laravel's built-in Gates (for simple scenarios)
Gate::define('edit-post', function ($user, $post) {
    return $user->id === $post->user_id;
});

// Laravel Policies (for model-specific permissions)
php artisan make:policy PostPolicy --model=Post
```

*Note: For enterprise applications with complex permission requirements, Spatie's package is recommended.*

## Installation & Setup

### 1. Install Package

```bash
composer require spatie/laravel-permission

# Publish and run migrations
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate

# Optional: Publish config
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="config"
```

### 2. Update User Model

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Optional: Define guard for permissions
    protected $guard_name = 'sanctum';
}
```

### 3. Register Middleware

In `bootstrap/app.php` (Laravel 12+):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
    ]);
})
```

Or in `app/Http/Kernel.php` (Laravel 10 and below):

```php
protected $middlewareAliases = [
    'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
    'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
    'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
];
```

### 4. Create Seeder for Initial Setup

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User management
            'create_users',
            'view_users',
            'edit_users',
            'delete_users',
            
            // Role management
            'manage_roles',
            'assign_roles',
            
            // Content management
            'create_content',
            'edit_content',
            'delete_content',
            'publish_content',
            
            // Reports
            'view_reports',
            'export_reports',
            
            // System settings
            'manage_settings',
            'view_logs',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $admin = Role::create(['name' => 'Admin']);
        $admin->givePermissionTo(Permission::all());

        $manager = Role::create(['name' => 'Manager']);
        $manager->givePermissionTo([
            'view_users',
            'edit_users',
            'create_content',
            'edit_content',
            'publish_content',
            'view_reports',
            'export_reports',
        ]);

        $employee = Role::create(['name' => 'Employee']);
        $employee->givePermissionTo([
            'create_content',
            'edit_content',
            'view_reports',
        ]);

        $user = Role::create(['name' => 'User']);
        $user->givePermissionTo([
            'create_content',
        ]);

        // Create admin user
        $adminUser = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@company.com',
            'username' => 'admin',
            'password' => Hash::make('Admin@123!'),
            'email_verified_at' => now(),
        ]);

        $adminUser->assignRole('Admin');
    }
}
```

Run the seeder:

```bash
php artisan db:seed --class=RolePermissionSeeder
```

## Basic Usage

### 1. Assigning Roles and Permissions

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:Admin']);
    }

    // Assign role to user
    public function assignRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|exists:roles,name'
        ]);

        $user->assignRole($request->role);

        return response()->json([
            'message' => 'Role assigned successfully',
            'data' => $user->load('roles', 'permissions')
        ]);
    }

    // Sync user roles
    public function syncRoles(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name'
        ]);

        $user->syncRoles($request->roles);

        return response()->json([
            'message' => 'Roles synchronized successfully',
            'data' => $user->load('roles', 'permissions')
        ]);
    }

    // Give permission directly to user
    public function givePermission(Request $request, User $user)
    {
        $request->validate([
            'permission' => 'required|exists:permissions,name'
        ]);

        $user->givePermissionTo($request->permission);

        return response()->json([
            'message' => 'Permission granted successfully',
            'data' => $user->load('roles', 'permissions')
        ]);
    }

    // List all roles with permissions
    public function index()
    {
        $roles = Role::with('permissions')->get();

        return response()->json([
            'data' => $roles
        ]);
    }

    // Create new role
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $role = Role::create(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->givePermissionTo($request->permissions);
        }

        return response()->json([
            'message' => 'Role created successfully',
            'data' => $role->load('permissions')
        ], 201);
    }
}
```

### 2. Checking Roles and Permissions

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Check role
        if ($user->hasRole('Admin')) {
            // Admin-specific logic
            $data = $this->getAdminData();
        } elseif ($user->hasRole(['Manager', 'Employee'])) {
            // Manager or Employee logic
            $data = $this->getStaffData();
        } else {
            // Regular user logic
            $data = $this->getUserData();
        }

        return response()->json(['data' => $data]);
    }

    public function sensitiveAction(Request $request)
    {
        $user = $request->user();

        // Check specific permission
        if (!$user->can('delete_users')) {
            return response()->json([
                'message' => 'You do not have permission to perform this action'
            ], 403);
        }

        // Proceed with action
        return response()->json(['message' => 'Action completed']);
    }

    public function conditionalContent(Request $request)
    {
        $user = $request->user();

        $response = [
            'user' => $user->only(['name', 'email']),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->getRoleNames(),
        ];

        // Conditional data based on permissions
        if ($user->can('view_reports')) {
            $response['reports'] = $this->getReportsData();
        }

        if ($user->can('manage_settings')) {
            $response['settings'] = $this->getSettingsData();
        }

        return response()->json(['data' => $response]);
    }

    // Helper methods
    private function getAdminData() { /* ... */ }
    private function getStaffData() { /* ... */ }
    private function getUserData() { /* ... */ }
    private function getReportsData() { /* ... */ }
    private function getSettingsData() { /* ... */ }
}
```

## Project Structure

### 1. Organize Controllers by Role

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/           # Admin-only controllers
│   │   │   ├── UserManagementController.php
│   │   │   ├── RoleController.php
│   │   │   ├── SystemSettingsController.php
│   │   │   └── ReportsController.php
│   │   ├── Manager/         # Manager-level controllers
│   │   │   ├── TeamController.php
│   │   │   └── ProjectController.php
│   │   ├── Employee/        # Employee-specific controllers
│   │   │   └── TaskController.php
│   │   └── User/           # General user controllers
│   │       └── ProfileController.php
```

### 2. Route Organization

In `routes/api.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Routes for all authenticated users
    Route::prefix('user')->group(function () {
        Route::get('/profile', [User\ProfileController::class, 'show']);
        Route::put('/profile', [User\ProfileController::class, 'update']);
    });

    // Admin-only routes
    Route::middleware('role:Admin')->prefix('admin')->group(function () {
        Route::apiResource('users', Admin\UserManagementController::class);
        Route::apiResource('roles', Admin\RoleController::class);
        Route::get('settings', [Admin\SystemSettingsController::class, 'index']);
        Route::get('reports', [Admin\ReportsController::class, 'index']);
    });

    // Manager routes (Admin or Manager)
    Route::middleware('role:Admin|Manager')->prefix('manager')->group(function () {
        Route::apiResource('teams', Manager\TeamController::class);
        Route::apiResource('projects', Manager\ProjectController::class);
    });

    // Employee routes (Admin, Manager, or Employee)
    Route::middleware('role:Admin|Manager|Employee')->prefix('employee')->group(function () {
        Route::apiResource('tasks', Employee\TaskController::class);
    });

    // Permission-based routes
    Route::middleware('permission:view_reports')->group(function () {
        Route::get('/reports/summary', [ReportController::class, 'summary']);
    });

    Route::middleware('permission:export_reports')->group(function () {
        Route::get('/reports/export', [ReportController::class, 'export']);
    });
});
```

### 3. Custom Route Model Binding

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Models\User;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Custom route model binding with permission check
        Route::bind('manageable_user', function ($value) {
            $user = User::findOrFail($value);
            
            // Only allow managing users with lower or equal role hierarchy
            if (!auth()->user()->canManageUser($user)) {
                abort(403, 'Cannot manage this user');
            }
            
            return $user;
        });
    }
}

// In User model
public function canManageUser(User $targetUser): bool
{
    if ($this->hasRole('Admin')) {
        return true;
    }
    
    if ($this->hasRole('Manager')) {
        return !$targetUser->hasRole(['Admin', 'Manager']);
    }
    
    return false;
}
```

## Middleware Integration

### 1. Custom Permission Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermissionWithLogging
{
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        foreach ($permissions as $permission) {
            if (!$user->can($permission)) {
                // Log unauthorized access attempt
                \Log::warning('Unauthorized access attempt', [
                    'user_id' => $user->id,
                    'permission' => $permission,
                    'route' => $request->route()->getName(),
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'You do not have permission to perform this action'
                ], 403);
            }
        }

        return $next($request);
    }
}
```

### 2. Role Hierarchy Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRoleHierarchy
{
    protected $roleHierarchy = [
        'Admin' => 4,
        'Manager' => 3,
        'Employee' => 2,
        'User' => 1,
    ];

    public function handle(Request $request, Closure $next, string $minimumRole): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $userLevel = $this->getUserLevel($user);
        $requiredLevel = $this->roleHierarchy[$minimumRole] ?? 0;

        if ($userLevel < $requiredLevel) {
            return response()->json([
                'message' => 'Insufficient role level'
            ], 403);
        }

        return $next($request);
    }

    protected function getUserLevel($user): int
    {
        $maxLevel = 0;
        
        foreach ($user->getRoleNames() as $roleName) {
            $level = $this->roleHierarchy[$roleName] ?? 0;
            $maxLevel = max($maxLevel, $level);
        }

        return $maxLevel;
    }
}
```

## Permission Management

### 1. Dynamic Permission Creation

```php
<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionService
{
    public function createModulePermissions(string $module): array
    {
        $actions = ['create', 'view', 'edit', 'delete'];
        $permissions = [];

        foreach ($actions as $action) {
            $permissionName = "{$action}_{$module}";
            $permission = Permission::firstOrCreate(['name' => $permissionName]);
            $permissions[] = $permission;
        }

        return $permissions;
    }

    public function assignPermissionsToRole(string $roleName, array $permissions): void
    {
        $role = Role::findByName($roleName);
        $role->givePermissionTo($permissions);
        
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function getPermissionMatrix(): array
    {
        $roles = Role::with('permissions')->get();
        $allPermissions = Permission::all();
        
        $matrix = [];
        
        foreach ($roles as $role) {
            $matrix[$role->name] = [];
            foreach ($allPermissions as $permission) {
                $matrix[$role->name][$permission->name] = $role->hasPermissionTo($permission);
            }
        }

        return $matrix;
    }
}
```

### 2. Permission API Endpoints

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct(protected PermissionService $permissionService)
    {
        $this->middleware(['auth:sanctum', 'role:Admin']);
    }

    public function index()
    {
        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('_', $permission->name)[1] ?? 'general';
        });

        return response()->json(['data' => $permissions]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name',
            'guard_name' => 'sometimes|string',
        ]);

        $permission = Permission::create($request->only(['name', 'guard_name']));

        return response()->json([
            'message' => 'Permission created successfully',
            'data' => $permission
        ], 201);
    }

    public function createModulePermissions(Request $request)
    {
        $request->validate([
            'module' => 'required|string|unique:permissions,name'
        ]);

        $permissions = $this->permissionService->createModulePermissions($request->module);

        return response()->json([
            'message' => 'Module permissions created successfully',
            'data' => $permissions
        ], 201);
    }

    public function matrix()
    {
        $matrix = $this->permissionService->getPermissionMatrix();

        return response()->json(['data' => $matrix]);
    }
}
```

## Security Best Practices

### 1. Principle of Least Privilege

```php
<?php

namespace App\Http\Controllers\Admin;

class UserController extends Controller
{
    public function store(Request $request)
    {
        // Validate that the requesting user can assign the requested role
        $requestedRole = $request->input('role');
        
        if (!$this->canAssignRole($request->user(), $requestedRole)) {
            return response()->json([
                'message' => 'You cannot assign this role'
            ], 403);
        }

        // Create user with validated role
        $user = User::create($request->validated());
        $user->assignRole($requestedRole);

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user->load('roles')
        ], 201);
    }

    protected function canAssignRole($assigningUser, $roleToAssign): bool
    {
        // Admins can assign any role
        if ($assigningUser->hasRole('Admin')) {
            return true;
        }

        // Managers can only assign Employee or User roles
        if ($assigningUser->hasRole('Manager')) {
            return in_array($roleToAssign, ['Employee', 'User']);
        }

        return false;
    }
}
```

### 2. Audit Trail for Role Changes

```php
<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    public function updated(User $user): void
    {
        if ($user->isDirty('roles')) {
            Log::info('User roles changed', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'old_roles' => $user->getOriginal('roles'),
                'new_roles' => $user->roles,
                'changed_by' => auth()->id(),
                'timestamp' => now(),
            ]);
        }
    }
}

// Register in AppServiceProvider
public function boot(): void
{
    User::observe(UserObserver::class);
}
```

### 3. Super Admin Protection

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProtectSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $targetUser = $request->route('user');
        
        // Protect super admin from being modified by other admins
        if ($targetUser && $targetUser->hasRole('Super Admin')) {
            $currentUser = $request->user();
            
            if (!$currentUser->hasRole('Super Admin')) {
                return response()->json([
                    'message' => 'Cannot modify super administrator account'
                ], 403);
            }
        }

        return $next($request);
    }
}
```

## Testing RBAC

### 1. Feature Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles and permissions
        $admin = Role::create(['name' => 'Admin']);
        $user = Role::create(['name' => 'User']);
        
        $manageUsers = Permission::create(['name' => 'manage_users']);
        $viewReports = Permission::create(['name' => 'view_reports']);
        
        $admin->givePermissionTo([$manageUsers, $viewReports]);
        $user->givePermissionTo($viewReports);
    }

    public function test_admin_can_access_user_management(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                        ->getJson('/api/admin/users');

        $response->assertStatus(200);
    }

    public function test_regular_user_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                        ->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function test_user_with_permission_can_access_protected_resource(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                        ->getJson('/api/reports');

        $response->assertStatus(200);
    }

    public function test_role_assignment_and_permission_check(): void
    {
        $user = User::factory()->create();
        
        // Initially no roles
        $this->assertFalse($user->hasRole('Admin'));
        $this->assertFalse($user->can('manage_users'));
        
        // Assign role
        $user->assignRole('Admin');
        
        // Check role and permissions
        $this->assertTrue($user->hasRole('Admin'));
        $this->assertTrue($user->can('manage_users'));
        $this->assertTrue($user->can('view_reports'));
    }
}
```

### 2. Unit Tests for Custom Logic

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_lower_role_users(): void
    {
        $admin = Role::create(['name' => 'Admin']);
        $manager = Role::create(['name' => 'Manager']);
        $employee = Role::create(['name' => 'Employee']);

        $adminUser = User::factory()->create();
        $adminUser->assignRole('Admin');

        $managerUser = User::factory()->create();
        $managerUser->assignRole('Manager');

        $employeeUser = User::factory()->create();
        $employeeUser->assignRole('Employee');

        // Admin can manage everyone
        $this->assertTrue($adminUser->canManageUser($managerUser));
        $this->assertTrue($adminUser->canManageUser($employeeUser));

        // Manager can manage employees but not admins
        $this->assertFalse($managerUser->canManageUser($adminUser));
        $this->assertTrue($managerUser->canManageUser($employeeUser));

        // Employee cannot manage anyone
        $this->assertFalse($employeeUser->canManageUser($adminUser));
        $this->assertFalse($employeeUser->canManageUser($managerUser));
    }
}
```

### 3. API Testing Helper Traits

```php
<?php

namespace Tests\Traits;

use App\Models\User;
use Spatie\Permission\Models\Role;

trait WithRoles
{
    protected function createUserWithRole(string $roleName, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user->assignRole($role);
        
        return $user;
    }

    protected function actingAsAdmin(array $attributes = []): User
    {
        $admin = $this->createUserWithRole('Admin', $attributes);
        $this->actingAs($admin, 'sanctum');
        
        return $admin;
    }

    protected function actingAsManager(array $attributes = []): User
    {
        $manager = $this->createUserWithRole('Manager', $attributes);
        $this->actingAs($manager, 'sanctum');
        
        return $manager;
    }

    protected function actingAsEmployee(array $attributes = []): User
    {
        $employee = $this->createUserWithRole('Employee', $attributes);
        $this->actingAs($employee, 'sanctum');
        
        return $employee;
    }
}

// Usage in tests
class ExampleTest extends TestCase
{
    use WithRoles;

    public function test_admin_specific_functionality(): void
    {
        $admin = $this->actingAsAdmin();
        
        $response = $this->getJson('/api/admin/dashboard');
        
        $response->assertStatus(200);
    }
}
```

## Performance Optimization

### 1. Cache Configuration

```php
// config/permission.php
return [
    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'model_key' => 'name',
        'store' => 'default',
    ],
];
```

### 2. Eager Loading

```php
// Always eager load roles and permissions
public function index()
{
    $users = User::with(['roles.permissions', 'permissions'])->paginate(20);
    
    return response()->json(['data' => $users]);
}

// Use specific loading for better performance
public function getUsersWithRoles()
{
    $users = User::with('roles:id,name')->get();
    
    return response()->json(['data' => $users]);
}
```

### 3. Clear Cache When Needed

```php
// After role/permission changes
use Spatie\Permission\PermissionRegistrar;

public function updateRole(Request $request, Role $role)
{
    $role->update($request->validated());
    
    // Clear permission cache
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    
    return response()->json(['message' => 'Role updated successfully']);
}
```

## Troubleshooting

### Common Issues

1. **Permission cache not clearing**: Use `php artisan permission:cache-reset`
2. **Guard mismatch**: Ensure guard_name matches your authentication guard
3. **Role not found**: Check if role exists before assignment
4. **Performance issues**: Implement eager loading for roles/permissions

### Debug Commands

```bash
# Clear permission cache
php artisan permission:cache-reset

# Show all permissions
php artisan permission:show

# Create permission
php artisan permission:create-permission "permission name"

# Create role
php artisan permission:create-role "role name"
```

---

**Next**: [API Design Standards](./03-api-standards.md)