# API Design Standards

## Overview

This guide establishes comprehensive standards for designing RESTful APIs in Laravel. These standards ensure consistency, maintainability, and excellent developer experience across all API endpoints.

## Table of Contents

- [RESTful Design Principles](#restful-design-principles)
- [URL Structure & Naming](#url-structure--naming)
- [HTTP Methods & Status Codes](#http-methods--status-codes)
- [Request Validation](#request-validation)
- [Response Formatting](#response-formatting)
- [Error Handling](#error-handling)
- [API Versioning](#api-versioning)
- [Rate Limiting & Throttling](#rate-limiting--throttling)
- [CORS Configuration](#cors-configuration)
- [API Documentation](#api-documentation)
- [Testing Standards](#testing-standards)

## RESTful Design Principles

### 1. Resource-Based URLs

Use nouns, not verbs, and follow RESTful conventions:

```php
// ✅ Good
GET    /api/users              // List users
GET    /api/users/123          // Get specific user
POST   /api/users              // Create user
PUT    /api/users/123          // Update user (full)
PATCH  /api/users/123          // Update user (partial)
DELETE /api/users/123          // Delete user

// ❌ Bad
GET    /api/getUsers
POST   /api/createUser
GET    /api/user_info/123
```

### 2. Nested Resources

For related resources, use nested URLs when appropriate:

```php
// User's posts
GET    /api/users/123/posts
POST   /api/users/123/posts
GET    /api/users/123/posts/456

// Project tasks
GET    /api/projects/123/tasks
POST   /api/projects/123/tasks

// Comments on posts
GET    /api/posts/123/comments
POST   /api/posts/123/comments
```

### 3. Collection Filtering & Pagination

Implement standardized filtering and pagination:

```php
// Filtering
GET /api/users?role=admin&status=active
GET /api/posts?author=john&category=tech

// Sorting
GET /api/users?sort=name&order=asc
GET /api/posts?sort=created_at&order=desc

// Pagination
GET /api/users?page=2&per_page=20
GET /api/posts?page=1&limit=10

// Search
GET /api/users?search=john
GET /api/products?q=laptop
```

## URL Structure & Naming

### 1. Base URL Structure

```php
// Format: {domain}/api/{version}/{resource}
https://api.company.com/api/v1/users
https://company.com/api/v1/users

// Internal/development
http://localhost:8000/api/v1/users
```

### 2. Naming Conventions

```php
// Resources: Use plural nouns
/api/users          ✅
/api/user           ❌

// Use kebab-case for multi-word resources
/api/user-profiles  ✅
/api/userProfiles   ❌
/api/user_profiles  ❌

// Actions: Use query parameters or separate endpoints
/api/users/123/activate     ✅
/api/users/123?action=activate  ✅
/api/activateUser/123       ❌
```

### 3. Route Organization

In `routes/api.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

// API Version 1
Route::prefix('v1')->group(function () {
    
    // Public routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        
        // Authentication routes
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/profile', [AuthController::class, 'profile']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
        });
        
        // User management (Admin only)
        Route::middleware('role:Admin')->group(function () {
            Route::apiResource('users', UserController::class);
            Route::post('users/{user}/activate', [UserController::class, 'activate']);
            Route::post('users/{user}/deactivate', [UserController::class, 'deactivate']);
        });
        
        // Projects (Role-based access)
        Route::apiResource('projects', ProjectController::class);
        Route::apiResource('projects.tasks', TaskController::class)->shallow();
        
        // Reports (Permission-based)
        Route::middleware('permission:view_reports')->group(function () {
            Route::get('reports/summary', [ReportController::class, 'summary']);
            Route::get('reports/detailed', [ReportController::class, 'detailed']);
        });
        
        Route::middleware('permission:export_reports')->group(function () {
            Route::get('reports/export', [ReportController::class, 'export']);
        });
    });
});
```

## HTTP Methods & Status Codes

### 1. HTTP Methods Usage

| Method | Purpose | Idempotent | Safe |
|--------|---------|------------|------|
| GET | Retrieve resource(s) | ✅ | ✅ |
| POST | Create new resource | ❌ | ❌ |
| PUT | Update entire resource | ✅ | ❌ |
| PATCH | Partial update | ✅ | ❌ |
| DELETE | Remove resource | ✅ | ❌ |

### 2. Standard Status Codes

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::paginate(20);
        
        return response()->json([
            'data' => $users
        ], 200); // OK
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());
        
        return response()->json([
            'message' => 'User created successfully',
            'data' => $user
        ], 201); // Created
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => $user
        ], 200); // OK
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());
        
        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user
        ], 200); // OK
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();
        
        return response()->json([
            'message' => 'User deleted successfully'
        ], 204); // No Content
    }
}
```

### 3. Complete Status Code Reference

```php
// Success Responses
200 OK                  // Standard successful response
201 Created            // Resource created successfully
202 Accepted          // Request accepted (async processing)
204 No Content        // Successful deletion or update with no response body

// Client Error Responses
400 Bad Request        // Invalid request format
401 Unauthorized      // Authentication required
403 Forbidden         // Authenticated but not authorized
404 Not Found         // Resource doesn't exist
405 Method Not Allowed // HTTP method not supported
409 Conflict          // Resource conflict (duplicate)
422 Unprocessable Entity // Validation errors
429 Too Many Requests  // Rate limit exceeded

// Server Error Responses
500 Internal Server Error // Unexpected server error
502 Bad Gateway          // Upstream server error
503 Service Unavailable  // Server temporarily unavailable
```

## Request Validation

### 1. Form Request Classes

Create dedicated request classes for validation:

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
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'username' => 'required|string|unique:users,username|max:50',
            'password' => [
                'required',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
            'role' => 'required|string|exists:roles,name',
            'department' => 'sometimes|string|max:100',
            'phone' => 'sometimes|string|regex:/^[0-9+\-\s()]+$/|max:20',
            'avatar' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already registered.',
            'username.unique' => 'This username is already taken.',
            'password.required' => 'A strong password is required.',
            'role.exists' => 'The selected role is invalid.',
            'avatar.image' => 'Avatar must be a valid image file.',
            'avatar.max' => 'Avatar file size cannot exceed 2MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'phone' => 'phone number',
        ];
    }
}
```

### 2. Update Request with Conditional Rules

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'password' => [
                'sometimes',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
            'role' => 'sometimes|string|exists:roles,name',
            'department' => 'sometimes|nullable|string|max:100',
            'phone' => 'sometimes|nullable|string|regex:/^[0-9+\-\s()]+$/|max:20',
            'status' => 'sometimes|in:active,inactive,suspended',
        ];
    }

    public function prepareForValidation(): void
    {
        // Clean phone number
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/[^0-9+\-\s()]/', '', $this->phone)
            ]);
        }

        // Convert email to lowercase
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->email)
            ]);
        }
    }
}
```

### 3. Custom Validation Rules

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strlen($value) < 8) {
            $fail('The password must be at least 8 characters long.');
        }

        if (!preg_match('/[A-Z]/', $value)) {
            $fail('The password must contain at least one uppercase letter.');
        }

        if (!preg_match('/[a-z]/', $value)) {
            $fail('The password must contain at least one lowercase letter.');
        }

        if (!preg_match('/[0-9]/', $value)) {
            $fail('The password must contain at least one number.');
        }

        if (!preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('The password must contain at least one special character.');
        }
    }
}

// Usage in request
'password' => ['required', new StrongPassword()],
```

## Response Formatting

### 1. Standardized Response Structure

Create a response trait for consistency:

```php
<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200,
        array $headers = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status, $headers);
    }

    protected function error(
        string $message = 'Error',
        int $status = 400,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status, $headers);
    }

    protected function paginated(
        $data,
        string $message = 'Data retrieved successfully'
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'links' => [
                'first' => $data->url(1),
                'last' => $data->url($data->lastPage()),
                'prev' => $data->previousPageUrl(),
                'next' => $data->nextPageUrl(),
            ],
        ]);
    }
}
```

### 2. API Resource Classes

Use Laravel's API Resources for data transformation:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'department' => $this->department,
            'phone' => $this->phone,
            'status' => $this->status,
            'avatar' => $this->avatar ? asset("storage/{$this->avatar}") : null,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Conditional fields
            'email_verified' => $this->when(
                $request->user()?->hasRole('Admin'),
                $this->email_verified_at !== null
            ),
            
            // Computed fields
            'full_name' => $this->name,
            'initials' => $this->getInitials(),
            'role_names' => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),
        ];
    }

    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}
```

### 3. Collection Resources

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'users' => $this->collection,
            'summary' => [
                'total_users' => $this->collection->count(),
                'active_users' => $this->collection->where('status', 'active')->count(),
                'roles_distribution' => $this->getRolesDistribution(),
            ],
        ];
    }

    private function getRolesDistribution(): array
    {
        return $this->collection->flatMap(function ($user) {
            return $user->resource->roles->pluck('name');
        })->countBy()->toArray();
    }

    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'generated_at' => now()->toISOString(),
                'request_info' => [
                    'page' => $request->get('page', 1),
                    'per_page' => $request->get('per_page', 15),
                    'filters' => $request->only(['search', 'role', 'status']),
                ],
            ],
        ];
    }
}
```

### 4. Controller Implementation

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserCollection;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = User::with(['roles', 'permissions']);

        // Apply filters
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('username', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Apply sorting
        $sortField = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        // Paginate results
        $perPage = min($request->get('per_page', 15), 100); // Max 100 per page
        $users = $query->paginate($perPage);

        return (new UserCollection($users))->additional([
            'message' => 'Users retrieved successfully'
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $user = User::create($request->validated());
        $user->assignRole($request->role);

        return $this->success(
            new UserResource($user->load(['roles', 'permissions'])),
            'User created successfully',
            201
        );
    }

    public function show(User $user)
    {
        return $this->success(
            new UserResource($user->load(['roles', 'permissions'])),
            'User retrieved successfully'
        );
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user->update($request->validated());

        if ($request->has('role')) {
            $user->syncRoles([$request->role]);
        }

        return $this->success(
            new UserResource($user->load(['roles', 'permissions'])),
            'User updated successfully'
        );
    }

    public function destroy(User $user)
    {
        $user->delete();

        return $this->success(
            null,
            'User deleted successfully',
            204
        );
    }
}
```

## Error Handling

### 1. Global Exception Handler

In `app/Exceptions/Handler.php`:

```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Custom logging logic
        });

        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return $this->handleApiException($e, $request);
            }
        });
    }

    private function handleApiException(Throwable $exception, Request $request)
    {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $exception->errors(),
            ], 422);
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
            ], 404);
        }

        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found',
            ], 404);
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Method not allowed',
                'allowed_methods' => $exception->getHeaders()['Allow'] ?? [],
            ], 405);
        }

        // Handle other exceptions
        $statusCode = method_exists($exception, 'getStatusCode') 
            ? $exception->getStatusCode() 
            : 500;

        $message = config('app.debug') 
            ? $exception->getMessage() 
            : 'Internal server error';

        return response()->json([
            'success' => false,
            'message' => $message,
            'trace' => config('app.debug') ? $exception->getTrace() : null,
        ], $statusCode);
    }
}
```

### 2. Custom Exception Classes

```php
<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class BusinessLogicException extends Exception
{
    public function __construct(
        string $message = 'Business logic error',
        int $code = 422,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ], $this->getCode());
    }
}

class InsufficientPermissionException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to perform this action',
        ], 403);
    }
}

class ResourceConflictException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage() ?: 'Resource conflict',
        ], 409);
    }
}
```

### 3. Error Response Examples

```json
// Validation Error (422)
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password must be at least 8 characters."]
    }
}

// Authentication Error (401)
{
    "success": false,
    "message": "Unauthenticated"
}

// Authorization Error (403)
{
    "success": false,
    "message": "You do not have permission to perform this action"
}

// Not Found (404)
{
    "success": false,
    "message": "Resource not found"
}

// Server Error (500)
{
    "success": false,
    "message": "Internal server error",
    "trace": null  // Only in debug mode
}
```

## API Versioning

### 1. URL-Based Versioning

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::apiResource('users', 'Api\V1\UserController');
});

Route::prefix('v2')->group(function () {
    Route::apiResource('users', 'Api\V2\UserController');
});
```

### 2. Header-Based Versioning

Create middleware for header-based versioning:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiVersion
{
    public function handle(Request $request, Closure $next, $version = 'v1')
    {
        $apiVersion = $request->header('Accept-Version', $version);
        
        // Store version for use in controllers
        $request->merge(['api_version' => $apiVersion]);
        
        return $next($request);
    }
}

// Usage in routes
Route::middleware(['api_version:v1'])->group(function () {
    Route::apiResource('users', UserController::class);
});
```

### 3. Controller Versioning Strategy

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;

class UserController extends Controller
{
    public function index()
    {
        $users = User::paginate();
        
        return UserResource::collection($users);
    }
}

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\UserResource;

class UserController extends Controller
{
    public function index()
    {
        // V2 might include additional fields or logic
        $users = User::with(['profile', 'preferences'])->paginate();
        
        return UserResource::collection($users);
    }
}
```

## Rate Limiting & Throttling

### 1. Basic Rate Limiting

Configure in `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'api' => [
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];
```

### 2. Custom Rate Limiters

In `RouteServiceProvider`:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // General API rate limiting
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    // Strict rate limiting for auth endpoints
    RateLimiter::for('auth', function (Request $request) {
        return Limit::perMinute(5)->by($request->ip());
    });

    // Admin users get higher limits
    RateLimiter::for('admin', function (Request $request) {
        if ($request->user()?->hasRole('Admin')) {
            return Limit::perMinute(200)->by($request->user()->id);
        }
        
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    // File upload limits
    RateLimiter::for('uploads', function (Request $request) {
        return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
    });
}
```

### 3. Apply Rate Limiting to Routes

```php
// In routes/api.php
Route::middleware(['throttle:auth'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
});

Route::middleware(['auth:sanctum', 'throttle:admin'])->group(function () {
    Route::apiResource('users', UserController::class);
});

Route::middleware(['auth:sanctum', 'throttle:uploads'])->group(function () {
    Route::post('/upload', [FileController::class, 'upload']);
});
```

## CORS Configuration

### 1. Laravel 12+ CORS Configuration

In `config/cors.php`:

```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    
    'allowed_methods' => ['*'],
    
    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:8080',
        'https://yourdomain.com',
        'https://app.yourdomain.com',
    ],
    
    'allowed_origins_patterns' => [
        '/^https:\/\/.*\.yourdomain\.com$/',
    ],
    
    'allowed_headers' => ['*'],
    
    'exposed_headers' => [
        'X-RateLimit-Remaining',
        'X-RateLimit-Limit',
    ],
    
    'max_age' => 0,
    
    'supports_credentials' => true,
];
```

### 2. Environment-Based CORS

```php
// config/cors.php
return [
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
    
    'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', true),
];

// .env
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://app.example.com
CORS_SUPPORTS_CREDENTIALS=true
```

## API Documentation

### 1. OpenAPI/Swagger Integration

Install Swagger PHP:

```bash
composer require zircote/swagger-php
```

### 2. Controller Documentation

```php
<?php

namespace App\Http\Controllers\Api\V1;

use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', title: 'Company API')]
#[OA\Server(url: 'http://localhost:8000/api/v1')]
class UserController extends Controller
{
    #[OA\Get(
        path: '/users',
        summary: 'Get list of users',
        security: [['bearerAuth' => []]],
        tags: ['Users']
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', minimum: 1)
    )]
    #[OA\Parameter(
        name: 'per_page',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)
    )]
    #[OA\Response(
        response: 200,
        description: 'Successful response',
        content: new OA\JsonContent(
            properties: [
                'success' => new OA\Property(property: 'success', type: 'boolean'),
                'message' => new OA\Property(property: 'message', type: 'string'),
                'data' => new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/User')
                ),
            ]
        )
    )]
    public function index(Request $request)
    {
        // Implementation
    }

    #[OA\Post(
        path: '/users',
        summary: 'Create a new user',
        security: [['bearerAuth' => []]],
        tags: ['Users']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/CreateUserRequest')
    )]
    #[OA\Response(
        response: 201,
        description: 'User created successfully',
        content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')
    )]
    public function store(StoreUserRequest $request)
    {
        // Implementation
    }
}

#[OA\Schema(
    schema: 'User',
    properties: [
        'id' => new OA\Property(property: 'id', type: 'integer'),
        'name' => new OA\Property(property: 'name', type: 'string'),
        'email' => new OA\Property(property: 'email', type: 'string', format: 'email'),
        'username' => new OA\Property(property: 'username', type: 'string'),
        'created_at' => new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class UserSchema {}
```

### 3. Generate Documentation

Create an Artisan command to generate documentation:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenApi\Generator;

class GenerateApiDocs extends Command
{
    protected $signature = 'api:docs';
    protected $description = 'Generate API documentation';

    public function handle(): void
    {
        $openapi = Generator::scan([app_path('Http/Controllers/Api')]);
        
        file_put_contents(
            public_path('api-docs.json'),
            $openapi->toJson()
        );

        $this->info('API documentation generated successfully!');
    }
}
```

## Testing Standards

### 1. API Test Structure

```php
<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(); // Seed roles and permissions
    }

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        
        User::factory()->count(5)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => ['id', 'name', 'email', 'username']
                    ]
                ]);
    }

    public function test_regular_user_cannot_list_users(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(403);
    }

    public function test_user_creation_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/users', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'weak',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_successful_user_creation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        Sanctum::actingAs($admin);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'username' => 'johndoe',
            'password' => 'SecurePass123!',
            'role' => 'Employee',
        ];

        $response = $this->postJson('/api/v1/users', $userData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'User created successfully',
                ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'username' => 'johndoe',
        ]);
    }
}
```

### 2. Test Traits for Common Operations

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

    protected function assertValidationErrors($response, array $fields): void
    {
        $response->assertStatus(422)
                ->assertJsonValidationErrors($fields);
    }
}
```

---

**Next**: [Database Standards](./04-database-standards.md)