# API Documentation Overview

## 📚 Complete API Documentation

Welcome to the MP-Software Laravel Backend API documentation. This comprehensive guide covers all available endpoints, authentication, and usage examples.

---

## 🚀 Quick Start

### Base URL
```
http://localhost:8000/api/v1
```

### Authentication
All protected endpoints require a Bearer token:
```http
Authorization: Bearer {your-token}
Content-Type: application/json
Accept: application/json
```

### Get Your Token
1. Register: `POST /auth/register`
2. Login: `POST /auth/login` 
3. Use the returned token in Authorization header

---

## 📋 API Modules

### 🔐 [Authentication API](./authentication.md)
Complete authentication system with user management.

**Endpoints:**
- `POST /auth/register` - Register new user
- `POST /auth/login` - Login and get token
- `GET /auth/profile` - Get user profile
- `PUT /auth/profile` - Update profile
- `POST /auth/change-password` - Change password
- `POST /auth/logout` - Logout and revoke token

**Features:**
- Laravel Sanctum token authentication
- Rate limiting protection
- Secure password handling
- Profile management

---

### 👥 [RBAC API](./rbac.md)
Dynamic Role-Based Access Control system.

**Permission Management:**
- `POST /rbac/permissions` - Create permission
- `GET /rbac/permissions` - List all permissions
- `GET /rbac/permissions/{id}` - Get single permission
- `PUT /rbac/permissions/{id}` - Update permission
- `DELETE /rbac/permissions/{id}` - Delete permission

**Role Management:**
- `POST /rbac/roles` - Create role
- `GET /rbac/roles` - List all roles
- `GET /rbac/roles/{id}` - Get single role
- `PUT /rbac/roles/{id}` - Update role
- `DELETE /rbac/roles/{id}` - Delete role

**Assignment & Checking:**
- `POST /rbac/roles/{role_id}/permissions` - Assign permissions to role
- `DELETE /rbac/roles/{role_id}/permissions` - Remove permissions from role
- `POST /rbac/users/{user_id}/roles` - Assign roles to user
- `DELETE /rbac/users/{user_id}/roles/{role_id}` - Remove role from user
- `GET /rbac/user/can/{permission}` - Check if user has permission
- `GET /rbac/user/current-permissions` - Get user's all permissions

**Features:**
- Dynamic role and permission creation
- Flexible user-role assignment
- Real-time permission checking
- Middleware protection for routes

---

## 🛡️ Security Features

### Rate Limiting
- **Authentication**: 5 login attempts per minute per IP
- **Registration**: 3 attempts per minute per IP  
- **Password changes**: 3 attempts per minute per user
- **API calls**: 60 requests per minute per user

### Permission System
- **Granular permissions**: Create fine-grained access control
- **Role hierarchy**: Organize permissions into roles
- **Dynamic assignment**: Change user permissions without code changes
- **Middleware protection**: Automatic route protection

### Data Protection
- **Token expiration**: 24-hour token lifetime
- **Secure password hashing**: BCrypt with Laravel standards
- **Input validation**: Comprehensive request validation
- **SQL injection prevention**: Eloquent ORM protection

---

## 📊 Response Format

### Standard Success Response
```json
{
    "message": "Operation successful",
    "data": {
        // Response data here
    }
}
```

### Standard Error Response
```json
{
    "message": "Error description",
    "errors": {
        "field": ["Detailed error message"]
    }
}
```

### HTTP Status Codes
- **200 OK**: Successful GET, PUT operations
- **201 Created**: Successful POST operations
- **400 Bad Request**: Invalid request data
- **401 Unauthorized**: Authentication required
- **403 Forbidden**: Insufficient permissions
- **404 Not Found**: Resource not found
- **422 Unprocessable Entity**: Validation errors
- **429 Too Many Requests**: Rate limit exceeded
- **500 Internal Server Error**: Server error

---

## 🧪 Testing the API

### Using cURL

#### Get Authentication Token
```bash
# Register
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'

# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }'
```

#### Use Token for Protected Endpoints
```bash
# Get profile
curl -X GET http://localhost:8000/api/v1/auth/profile \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"

# Create permission
curl -X POST http://localhost:8000/api/v1/rbac/permissions \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "test-permission",
    "display_name": "Test Permission",
    "description": "A test permission"
  }'
```

### Using Postman

1. **Import Collection**: Use our provided Postman collection
2. **Set Environment**: 
   - `base_url`: `http://localhost:8000/api/v1`
   - `token`: `{{auth_token}}` (auto-populated after login)
3. **Test Flow**:
   - Run authentication requests first
   - Token automatically saved to environment
   - Use token for subsequent requests

### Using PHP/Laravel Testing
```php
use Tests\TestCase;
use App\Models\User;

class ApiTest extends TestCase
{
    public function test_authentication_flow()
    {
        // Register
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);
        
        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'user', 'token']);
        
        $token = $response->json('token');
        
        // Use token for protected endpoint
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/auth/profile');
        
        $response->assertStatus(200)
            ->assertJsonStructure(['user']);
    }
}
```

---

## 🔧 Development Guidelines

### Adding New Endpoints

#### 1. Create Protected Route
```php
// routes/api/v1/feature.php
Route::middleware(['auth:sanctum', 'permission:manage-feature'])->group(function () {
    Route::apiResource('items', FeatureController::class);
});
```

#### 2. Add Permission Check in Controller
```php
public function store(Request $request)
{
    // Permission already checked by middleware
    // Additional checks if needed
    if (!auth()->user()->can('create-items')) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }
    
    // Your logic here
}
```

#### 3. Update Documentation
- Add endpoint to relevant documentation file
- Include request/response examples
- Document required permissions
- Add to API overview

### Route Organization
```
routes/api/v1/
├── auth.php         # Authentication routes
├── rbac.php         # RBAC management routes  
├── users.php        # User management routes
├── projects.php     # Project-specific routes
└── reports.php      # Reporting routes
```

---

## 🚀 Production Deployment

### Environment Variables
```env
# Authentication
SANCTUM_TOKEN_EXPIRES_IN=1440  # 24 hours in minutes

# Rate Limiting  
API_RATE_LIMIT=60              # Requests per minute
AUTH_RATE_LIMIT=5              # Login attempts per minute

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=mp_software
DB_USERNAME=your_user
DB_PASSWORD=your_password

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### Security Headers
```php
// Add to middleware
return response()->json($data)
    ->header('X-Content-Type-Options', 'nosniff')
    ->header('X-Frame-Options', 'DENY')
    ->header('X-XSS-Protection', '1; mode=block')
    ->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
```

### Performance Optimization
- **Route Caching**: `php artisan route:cache`
- **Config Caching**: `php artisan config:cache`
- **Database Indexing**: Index foreign keys and search columns
- **Query Optimization**: Use eager loading to prevent N+1 queries
- **Redis Caching**: Cache frequently accessed data

---

## 📞 Support & Resources

### Documentation Links
- [Authentication Guide](./authentication.md)
- [RBAC Implementation Guide](./rbac.md)
- [Response Format Guide](./responses.md)
- [Error Handling Guide](./errors.md)

### Development Tools
- **Laravel Sanctum**: [Official Documentation](https://laravel.com/docs/sanctum)
- **Spatie Permission**: [Package Documentation](https://spatie.be/docs/laravel-permission)
- **API Testing**: [Postman Collection](../assets/postman/mp-software-api.json)

### Testing Resources
- **Unit Tests**: `tests/Unit/`
- **Feature Tests**: `tests/Feature/`
- **Performance Tests**: `tests/Performance/`

---

## 📝 Changelog

### Version 1.0.0 (August 20, 2025)
- ✅ Complete authentication system
- ✅ Full RBAC implementation  
- ✅ API documentation
- ✅ Comprehensive testing suite
- ✅ Production-ready security features

### Upcoming Features
- API versioning improvements
- Advanced role hierarchy
- Audit trail system
- Enhanced rate limiting
- OpenAPI/Swagger integration

---

*Last Updated: August 20, 2025*
*Documentation Version: 1.0.0*
*API Version: v1*