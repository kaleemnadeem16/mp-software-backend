# Quick Start Guide

## Overview

This guide provides step-by-step instructions to quickly set up a new Laravel backend project following our established standards. You'll have a fully functional API with authentication, role-based access control, and real-time capabilities.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Project Setup](#project-setup)
- [Core Configuration](#core-configuration)
- [Authentication Setup](#authentication-setup)
- [RBAC Implementation](#rbac-implementation)
- [API Structure](#api-structure)
- [Testing Setup](#testing-setup)
- [Development Workflow](#development-workflow)

## Prerequisites

### Required Software

```bash
# PHP 8.2 or higher
php --version

# Composer
composer --version

# Node.js 18+ (for asset compilation)
node --version
npm --version

# Database (choose one)
# MySQL 8.0+
mysql --version

# PostgreSQL 13+
psql --version

# Redis 6.0+ (for caching and queues)
redis-cli --version

# Git
git --version
```

### Development Tools (Recommended)

- **IDE**: VS Code, PhpStorm, or Sublime Text
- **API Testing**: Postman, Insomnia, or REST Client
- **Database GUI**: TablePlus, phpMyAdmin, or pgAdmin
- **Terminal**: Git Bash, PowerShell, or native terminal

## Project Setup

### 1. Create New Laravel Project

```bash
# Create project
composer create-project laravel/laravel mp-software-backend

# Navigate to project directory
cd mp-software-backend

# Install additional dependencies
composer require laravel/sanctum spatie/laravel-permission maatwebsite/excel

# Optional: For WebSockets
php artisan install:broadcasting

# Optional: For high performance
composer require laravel/octane
```

### 2. Environment Configuration

Copy and configure environment file:

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

Edit `.env` file:

```env
APP_NAME="MP Software API"
APP_ENV=local
APP_KEY=base64:your-generated-key
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mp_software
DB_USERNAME=root
DB_PASSWORD=

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@mp-software.com"
MAIL_FROM_NAME="${APP_NAME}"

# Broadcasting (for WebSockets)
BROADCAST_DRIVER=log
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,localhost:3000
SESSION_DOMAIN=localhost
```

### 3. Database Setup

```bash
# Create database (MySQL example)
mysql -u root -p
CREATE DATABASE mp_software;
exit

# Run migrations
php artisan migrate

# Clear config cache
php artisan config:clear
```

## Core Configuration

### 1. CORS Configuration

Update `config/cors.php`:

```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:8080',
        'http://127.0.0.1:3000',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

### 2. API Rate Limiting

Update `app/Providers/RouteServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/dashboard';

    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
```

## Authentication Setup

### 1. Install Sanctum

```bash
# Install Sanctum
php artisan install:api

# Or manually:
# php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
# php artisan migrate
```

### 2. Update User Model

Edit `app/Models/User.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

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

    // Specify guard for permissions
    protected $guard_name = 'sanctum';

    public function getInitials(): string
    {
        $names = explode(' ', $this->name);
        $initials = '';
        
        foreach ($names as $name) {
            $initials .= strtoupper(substr($name, 0, 1));
        }
        
        return $initials;
    }
}
```

### 3. Create Migration for Username

```bash
# Create migration to add username to users table
php artisan make:migration add_username_to_users_table
```

Edit the migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
```

Run the migration:

```bash
php artisan migrate
```

## RBAC Implementation

### 1. Install Spatie Laravel Permission

```bash
# Install package
composer require spatie/laravel-permission

# Publish migration
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Run migration
php artisan migrate

# Publish config (optional)
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="config"
```

### 2. Create Roles and Permissions Seeder

```bash
php artisan make:seeder RolePermissionSeeder
```

Edit `database/seeders/RolePermissionSeeder.php`:

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

        // Create super admin user
        $adminUser = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@mp-software.com',
            'username' => 'admin',
            'password' => Hash::make('Admin@123!'),
            'email_verified_at' => now(),
        ]);

        $adminUser->assignRole('Admin');
    }
}
```

### 3. Update DatabaseSeeder

Edit `database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
        ]);
    }
}
```

Run the seeder:

```bash
php artisan db:seed
```

## API Structure

### 1. Create Response Trait

Create `app/Traits/ApiResponse.php`:

```php
<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    protected function error(
        string $message = 'Error',
        int $status = 400,
        mixed $errors = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}
```

### 2. Create Authentication Controller

```bash
php artisan make:controller Auth/AuthController
```

Edit `app/Http/Controllers/Auth/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $this->ensureIsNotRateLimited($request);

        $credentials = $request->only('identifier', 'password');
        $user = $this->findUser($credentials['identifier']);

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'identifier' => ['Invalid credentials.'],
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $token = $user->createToken('API Token')->plainTextToken;

        return $this->success([
            'user' => $user->load('roles', 'permissions'),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful');
    }

    public function logout(): JsonResponse
    {
        Auth::user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully');
    }

    public function profile(): JsonResponse
    {
        return $this->success(
            Auth::user()->load('roles', 'permissions'),
            'Profile retrieved successfully'
        );
    }

    protected function findUser(string $identifier): ?User
    {
        return User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();
    }

    protected function ensureIsNotRateLimited(LoginRequest $request): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            $seconds = RateLimiter::availableIn($this->throttleKey($request));

            throw ValidationException::withMessages([
                'identifier' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ]);
        }
    }

    protected function throttleKey(LoginRequest $request): string
    {
        return strtolower($request->input('identifier')) . '|' . $request->ip();
    }
}
```

### 3. Create Login Request

```bash
php artisan make:request LoginRequest
```

Edit `app/Http/Requests/LoginRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => 'required|string',
            'password' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => 'Email or username is required.',
            'password.required' => 'Password is required.',
        ];
    }
}
```

### 4. Setup API Routes

Edit `routes/api.php`:

```php
<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// API Version 1
Route::prefix('v1')->group(function () {
    
    // Public routes
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login']);
    });
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        
        // Authentication routes
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/profile', [AuthController::class, 'profile']);
        });
        
        // Admin routes
        Route::middleware('role:Admin')->prefix('admin')->group(function () {
            Route::get('/dashboard', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'Admin dashboard data',
                    'data' => [
                        'stats' => [
                            'total_users' => \App\Models\User::count(),
                            'total_roles' => \Spatie\Permission\Models\Role::count(),
                        ]
                    ]
                ]);
            });
        });
        
        // General authenticated routes
        Route::get('/dashboard', function () {
            return response()->json([
                'success' => true,
                'message' => 'User dashboard data',
                'data' => [
                    'user' => auth()->user()->load('roles'),
                ]
            ]);
        });
    });
});
```

## Testing Setup

### 1. Update PHPUnit Configuration

Edit `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
    </php>
</phpunit>
```

### 2. Create Test Trait

Create `tests/Traits/WithApiAuthentication.php`:

```php
<?php

namespace Tests\Traits;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait WithApiAuthentication
{
    protected function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        
        Sanctum::actingAs($admin);
        
        return $admin;
    }

    protected function actingAsUser(string $role = 'User'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        
        Sanctum::actingAs($user);
        
        return $user;
    }

    protected function assertApiResponse(
        $response,
        int $status = 200,
        bool $success = true
    ): void {
        $response->assertStatus($status)
                ->assertJson(['success' => $success]);
    }
}
```

### 3. Create Basic API Test

Create `tests/Feature/AuthTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\WithApiAuthentication;

class AuthTest extends TestCase
{
    use RefreshDatabase, WithApiAuthentication;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed();
    }

    public function test_user_can_login_with_email(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => ['user', 'token', 'token_type']
                ]);
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/admin/dashboard');

        $this->assertApiResponse($response);
    }

    public function test_regular_user_cannot_access_admin_dashboard(): void
    {
        $user = $this->actingAsUser();

        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(403);
    }
}
```

Run tests:

```bash
php artisan test
```

## Development Workflow

### 1. Start Development Server

```bash
# Start Laravel server
php artisan serve

# In another terminal, start queue worker (if using queues)
php artisan queue:work

# If using WebSockets with Reverb
php artisan reverb:start
```

### 2. Development Commands

```bash
# Clear all caches
php artisan optimize:clear

# Generate IDE helper files (optional)
composer require --dev barryvdh/laravel-ide-helper
php artisan ide-helper:generate
php artisan ide-helper:models
php artisan ide-helper:meta

# Run code style fixes
composer require --dev laravel/pint
./vendor/bin/pint

# Run static analysis (optional)
composer require --dev larastan/larastan
./vendor/bin/phpstan analyse
```

### 3. Testing Your Setup

Use these curl commands or import into Postman:

```bash
# Test login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "identifier": "admin@mp-software.com",
    "password": "Admin@123!"
  }'

# Test authenticated endpoint (replace TOKEN with actual token)
curl -X GET http://localhost:8000/api/v1/auth/profile \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept: application/json"

# Test admin endpoint
curl -X GET http://localhost:8000/api/v1/admin/dashboard \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept: application/json"
```

### 4. Git Setup

```bash
# Initialize git repository
git init

# Add gitignore for Laravel
# (Laravel projects come with .gitignore by default)

# Initial commit
git add .
git commit -m "Initial Laravel project setup with auth and RBAC"

# Add remote repository
git remote add origin https://github.com/yourusername/mp-software-backend.git
git push -u origin main
```

## Next Steps

Now that you have a basic setup running, you can:

1. **Add more features**: Follow the detailed guides for [WebSockets](./05-websockets.md), [Excel Operations](./06-excel-operations.md), etc.
2. **Customize roles and permissions**: Modify the seeder to match your specific needs
3. **Add more API endpoints**: Create controllers for your business logic
4. **Set up frontend**: Connect your frontend application using the API
5. **Deploy to production**: Follow the [Deployment Guide](./10-deployment.md)

## Troubleshooting

### Common Issues

1. **Database connection errors**: Check your `.env` database configuration
2. **Permission denied errors**: Ensure proper file permissions on storage and cache directories
3. **CORS issues**: Verify CORS configuration matches your frontend URL
4. **Token authentication fails**: Ensure Sanctum is properly configured

### Debug Commands

```bash
# Check configuration
php artisan config:show database
php artisan config:show sanctum

# Check routes
php artisan route:list

# Check permissions
php artisan permission:show

# Clear everything and rebuild
php artisan optimize:clear
composer dump-autoload
php artisan migrate:fresh --seed
```

---

**Next**: Continue with the detailed implementation guides for specific features you need.