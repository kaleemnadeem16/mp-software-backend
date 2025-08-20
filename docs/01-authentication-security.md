# Authentication & Security

## Overview

This guide covers the implementation of secure user authentication for a Laravel API-driven backend. We use Laravel Sanctum for stateless token authentication with admin-controlled user creation.

## Table of Contents

- [Authentication Strategy](#authentication-strategy)
- [Laravel Sanctum Setup](#laravel-sanctum-setup)
- [User Management](#user-management)
- [Login Implementation](#login-implementation)
- [Route Protection](#route-protection)
- [Security Considerations](#security-considerations)
- [Best Practices](#best-practices)

## Authentication Strategy

### Key Principles

1. **API-First**: Stateless token authentication using Laravel Sanctum
2. **Admin-Controlled**: No open registration - only admins can create users
3. **Secure by Default**: Strong password hashing and validation
4. **Flexible Login**: Support both email and username authentication

### Technology Choice

We use **Laravel Sanctum** because:
- ✅ Built-in to Laravel (no version conflicts)
- ✅ Simple token-based authentication
- ✅ Perfect for first-party applications
- ✅ Stateless and scalable
- ✅ No complex OAuth setup required

## Laravel Sanctum Setup

### 1. Installation

```bash
# Install Sanctum (included in Laravel 12+)
php artisan install:api

# Or manually install
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### 2. Model Configuration

Add the `HasApiTokens` trait to your User model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

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
}
```

### 3. Middleware Configuration

In `app/Http/Kernel.php` (or `bootstrap/app.php` in Laravel 12+):

```php
// Laravel 12+ (bootstrap/app.php)
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    ]);
})

// Laravel 10 and below (app/Http/Kernel.php)
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

## User Management

### 1. Disable Default Registration

Remove or comment out registration routes in `routes/web.php`:

```php
// Remove these lines if they exist
// Auth::routes(['register' => false]);

// Or in routes/auth.php, comment out:
// Route::post('/register', [RegisteredUserController::class, 'store']);
```

### 2. Admin-Only User Creation

Create a dedicated controller for user management:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:Admin']);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
        ]);

        // Assign role (covered in RBAC section)
        $user->assignRole($request->role);

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user->load('roles')
        ], 201);
    }

    public function index(): JsonResponse
    {
        $users = User::with('roles')->paginate(15);
        
        return response()->json([
            'data' => $users
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => $user->load('roles', 'permissions')
        ]);
    }

    public function update(StoreUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        if ($request->has('role')) {
            $user->syncRoles([$request->role]);
        }

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user->load('roles')
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ], 204);
    }
}
```

### 3. Request Validation

Create form requests for validation:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Admin');
    }

    public function rules(): array
    {
        $userId = $this->route('user') ? $this->route('user')->id : null;

        return [
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$userId}",
            'username' => "required|string|unique:users,username,{$userId}|max:255",
            'password' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
            'role' => 'required|string|exists:roles,name',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered.',
            'username.unique' => 'This username is already taken.',
            'password.required' => 'Password is required for new users.',
            'role.exists' => 'Invalid role specified.',
        ];
    }
}
```

## Login Implementation

### 1. Authentication Controller

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load('roles', 'permissions'),
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    public function profile(): JsonResponse
    {
        return response()->json([
            'data' => Auth::user()->load('roles', 'permissions')
        ]);
    }

    protected function findUser(string $identifier): ?User
    {
        // Support both email and username login
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

### 2. Login Request Validation

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
            'identifier' => 'required|string', // email or username
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

## Route Protection

### 1. API Routes Setup

In `routes/api.php`:

```php
<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\UserManagementController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Profile routes
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Admin routes
    Route::middleware('role:Admin')->prefix('admin')->group(function () {
        Route::apiResource('users', UserManagementController::class);
    });
    
    // Manager routes
    Route::middleware('role:Admin|Manager')->prefix('manager')->group(function () {
        // Manager-specific routes
    });
    
    // General user routes
    Route::prefix('user')->group(function () {
        // Routes available to all authenticated users
    });
});
```

### 2. Custom Middleware (Optional)

Create custom middleware for additional security:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Additional token validation logic
        $token = $user->currentAccessToken();
        
        if (!$token || $token->created_at->diffInDays() > 30) {
            return response()->json(['message' => 'Token expired'], 401);
        }

        return $next($request);
    }
}
```

## Security Considerations

### 1. Password Security

```php
// In config/auth.php or validation rules
use Illuminate\Validation\Rules\Password;

Password::min(8)
    ->letters()
    ->mixedCase()
    ->numbers()
    ->symbols()
    ->uncompromised(); // Check against data breaches
```

### 2. Rate Limiting

Configure in `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'api' => [
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];

// Custom rate limits in RouteServiceProvider
RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});
```

### 3. Token Management

Create commands for token cleanup:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class PruneExpiredTokens extends Command
{
    protected $signature = 'tokens:prune';
    protected $description = 'Prune expired API tokens';

    public function handle(): void
    {
        $count = PersonalAccessToken::where('created_at', '<', now()->subDays(30))->delete();
        
        $this->info("Pruned {$count} expired tokens.");
    }
}
```

## Best Practices

### 1. Environment Configuration

```env
# .env
APP_KEY=base64:your-app-key
APP_DEBUG=false
APP_ENV=production

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,yourdomain.com
SESSION_DOMAIN=.yourdomain.com
```

### 2. HTTPS Enforcement

In `AppServiceProvider`:

```php
public function boot(): void
{
    if ($this->app->environment('production')) {
        URL::forceScheme('https');
    }
}
```

### 3. Security Headers

Create middleware for security headers:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        
        return $response;
    }
}
```

### 4. Logging Security Events

```php
// In AuthController
use Illuminate\Support\Facades\Log;

// Log successful logins
Log::info('User logged in', [
    'user_id' => $user->id,
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
]);

// Log failed attempts
Log::warning('Failed login attempt', [
    'identifier' => $request->identifier,
    'ip' => $request->ip(),
]);
```

### 5. Testing Authentication

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'identifier' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => ['user', 'token', 'token_type']
                ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'identifier' => 'invalid@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['identifier']);
    }

    public function test_authenticated_user_can_access_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                        ->getJson('/api/profile');

        $response->assertStatus(200)
                ->assertJson(['data' => ['id' => $user->id]]);
    }
}
```

## Troubleshooting

### Common Issues

1. **Token not recognized**: Ensure `HasApiTokens` trait is added to User model
2. **CORS issues**: Configure CORS middleware properly
3. **Rate limiting errors**: Adjust rate limits in configuration
4. **Token expiration**: Implement token refresh mechanism if needed

### Debug Commands

```bash
# Clear all caches
php artisan optimize:clear

# Check Sanctum configuration
php artisan config:show sanctum

# List all routes
php artisan route:list --name=api

# Check current tokens
php artisan tinker
>>> Laravel\Sanctum\PersonalAccessToken::all()
```

---

**Next**: [Role-Based Access Control (RBAC)](./02-rbac.md)