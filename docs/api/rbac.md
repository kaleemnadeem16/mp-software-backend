# RBAC (Role-Based Access Control) API Documentation

## Overview
Complete RBAC system with dynamic role and permission management using Spatie Laravel Permission package.

## Base URL
```
http://localhost:8000/api/v1/rbac
```

## Authentication Required
All RBAC endpoints require authentication via Bearer token:
```http
Authorization: Bearer {your-token}
Content-Type: application/json
Accept: application/json
```

---

## 🔐 Permission Management

### 1. Create Permission
**POST** `/permissions`

Create a new permission in the system.

**Required Permission**: `create permissions`

#### Request Body
```json
{
    "name": "edit-posts",
    "display_name": "Edit Posts",
    "description": "Can edit blog posts"
}
```

#### Response (201 Created)
```json
{
    "message": "Permission created successfully",
    "permission": {
        "id": 1,
        "name": "edit-posts",
        "display_name": "Edit Posts",
        "description": "Can edit blog posts",
        "guard_name": "web",
        "created_at": "2025-08-20T10:00:00.000000Z",
        "updated_at": "2025-08-20T10:00:00.000000Z"
    }
}
```

---

### 2. Get All Permissions
**GET** `/permissions`

Retrieve all permissions in the system.

**Required Permission**: `view permissions`

#### Response (200 OK)
```json
{
    "permissions": [
        {
            "id": 1,
            "name": "edit-posts",
            "display_name": "Edit Posts",
            "description": "Can edit blog posts",
            "guard_name": "web",
            "created_at": "2025-08-20T10:00:00.000000Z",
            "updated_at": "2025-08-20T10:00:00.000000Z"
        },
        {
            "id": 2,
            "name": "delete-posts",
            "display_name": "Delete Posts",
            "description": "Can delete blog posts",
            "guard_name": "web",
            "created_at": "2025-08-20T10:00:00.000000Z",
            "updated_at": "2025-08-20T10:00:00.000000Z"
        }
    ]
}
```

---

### 3. Get Single Permission
**GET** `/permissions/{id}`

Retrieve a specific permission by ID.

**Required Permission**: `view permissions`

#### Response (200 OK)
```json
{
    "permission": {
        "id": 1,
        "name": "edit-posts",
        "display_name": "Edit Posts",
        "description": "Can edit blog posts",
        "guard_name": "web",
        "created_at": "2025-08-20T10:00:00.000000Z",
        "updated_at": "2025-08-20T10:00:00.000000Z"
    }
}
```

---

### 4. Update Permission
**PUT** `/permissions/{id}`

Update an existing permission.

**Required Permission**: `edit permissions`

#### Request Body
```json
{
    "display_name": "Edit Blog Posts",
    "description": "Can create and edit blog posts"
}
```

#### Response (200 OK)
```json
{
    "message": "Permission updated successfully",
    "permission": {
        "id": 1,
        "name": "edit-posts",
        "display_name": "Edit Blog Posts",
        "description": "Can create and edit blog posts",
        "guard_name": "web",
        "created_at": "2025-08-20T10:00:00.000000Z",
        "updated_at": "2025-08-20T10:00:00.000000Z"
    }
}
```

---

### 5. Delete Permission
**DELETE** `/permissions/{id}`

Delete a permission from the system.

**Required Permission**: `delete permissions`

#### Response (200 OK)
```json
{
    "message": "Permission deleted successfully"
}
```

---

## 👥 Role Management

### 1. Create Role
**POST** `/roles`

Create a new role with optional permissions.

**Required Permission**: `create roles`

#### Request Body
```json
{
    "name": "editor",
    "display_name": "Content Editor",
    "description": "Can edit content but not delete",
    "permissions": ["edit-posts", "view-posts"]
}
```

#### Response (201 Created)
```json
{
    "message": "Role created successfully",
    "role": {
        "id": 1,
        "name": "editor",
        "display_name": "Content Editor",
        "description": "Can edit content but not delete",
        "guard_name": "web",
        "created_at": "2025-08-20T10:00:00.000000Z",
        "updated_at": "2025-08-20T10:00:00.000000Z",
        "permissions": [
            {
                "id": 1,
                "name": "edit-posts",
                "display_name": "Edit Posts"
            },
            {
                "id": 2,
                "name": "view-posts",
                "display_name": "View Posts"
            }
        ]
    }
}
```

---

### 2. Get All Roles
**GET** `/roles`

Retrieve all roles in the system.

**Required Permission**: `view roles`

#### Response (200 OK)
```json
{
    "roles": [
        {
            "id": 1,
            "name": "editor",
            "display_name": "Content Editor",
            "description": "Can edit content but not delete",
            "guard_name": "web",
            "created_at": "2025-08-20T10:00:00.000000Z",
            "updated_at": "2025-08-20T10:00:00.000000Z",
            "permissions_count": 2
        },
        {
            "id": 2,
            "name": "admin",
            "display_name": "Administrator",
            "description": "Full system access",
            "guard_name": "web",
            "created_at": "2025-08-20T10:00:00.000000Z",
            "updated_at": "2025-08-20T10:00:00.000000Z",
            "permissions_count": 10
        }
    ]
}
```

---

### 3. Get Single Role
**GET** `/roles/{id}`

Retrieve a specific role with its permissions.

**Required Permission**: `view roles`

#### Response (200 OK)
```json
{
    "role": {
        "id": 1,
        "name": "editor",
        "display_name": "Content Editor",
        "description": "Can edit content but not delete",
        "guard_name": "web",
        "created_at": "2025-08-20T10:00:00.000000Z",
        "updated_at": "2025-08-20T10:00:00.000000Z",
        "permissions": [
            {
                "id": 1,
                "name": "edit-posts",
                "display_name": "Edit Posts",
                "description": "Can edit blog posts"
            },
            {
                "id": 2,
                "name": "view-posts",
                "display_name": "View Posts",
                "description": "Can view blog posts"
            }
        ]
    }
}
```

---

### 4. Update Role
**PUT** `/roles/{id}`

Update an existing role.

**Required Permission**: `edit roles`

#### Request Body
```json
{
    "display_name": "Senior Content Editor",
    "description": "Can edit and publish content"
}
```

#### Response (200 OK)
```json
{
    "message": "Role updated successfully",
    "role": {
        "id": 1,
        "name": "editor",
        "display_name": "Senior Content Editor",
        "description": "Can edit and publish content",
        "guard_name": "web",
        "created_at": "2025-08-20T10:00:00.000000Z",
        "updated_at": "2025-08-20T10:00:00.000000Z"
    }
}
```

---

### 5. Delete Role
**DELETE** `/roles/{id}`

Delete a role from the system.

**Required Permission**: `delete roles`

#### Response (200 OK)
```json
{
    "message": "Role deleted successfully"
}
```

---

## 🔗 Role-Permission Management

### 1. Assign Permissions to Role
**POST** `/roles/{role_id}/permissions`

Assign multiple permissions to a role.

**Required Permission**: `assign role permissions`

#### Request Body
```json
{
    "permission_ids": [1, 2, 3]
}
```

#### Response (200 OK)
```json
{
    "message": "Permissions assigned to role successfully",
    "role": {
        "id": 1,
        "name": "editor",
        "display_name": "Content Editor",
        "permissions": [
            {
                "id": 1,
                "name": "edit-posts",
                "display_name": "Edit Posts"
            },
            {
                "id": 2,
                "name": "view-posts",
                "display_name": "View Posts"
            },
            {
                "id": 3,
                "name": "create-posts",
                "display_name": "Create Posts"
            }
        ]
    }
}
```

---

### 2. Remove Permissions from Role
**DELETE** `/roles/{role_id}/permissions`

Remove specific permissions from a role.

**Required Permission**: `assign role permissions`

#### Request Body
```json
{
    "permission_ids": [3]
}
```

#### Response (200 OK)
```json
{
    "message": "Permissions removed from role successfully"
}
```

---

## 👤 User-Role Management

### 1. Get User Roles
**GET** `/users/{user_id}/roles`

Get all roles assigned to a specific user.

**Required Permission**: `view user roles`

#### Response (200 OK)
```json
{
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
    },
    "roles": [
        {
            "id": 1,
            "name": "editor",
            "display_name": "Content Editor",
            "description": "Can edit content but not delete"
        },
        {
            "id": 2,
            "name": "reviewer",
            "display_name": "Content Reviewer",
            "description": "Can review and approve content"
        }
    ]
}
```

---

### 2. Assign Role to User
**POST** `/users/{user_id}/roles`

Assign roles to a user.

**Required Permission**: `assign user roles`

#### Request Body
```json
{
    "role_ids": [1, 2]
}
```

#### Response (200 OK)
```json
{
    "message": "Roles assigned to user successfully",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "roles": [
            {
                "id": 1,
                "name": "editor",
                "display_name": "Content Editor"
            },
            {
                "id": 2,
                "name": "reviewer",
                "display_name": "Content Reviewer"
            }
        ]
    }
}
```

---

### 3. Remove Role from User
**DELETE** `/users/{user_id}/roles/{role_id}`

Remove a specific role from a user.

**Required Permission**: `assign user roles`

#### Response (200 OK)
```json
{
    "message": "Role removed from user successfully"
}
```

---

## 🔍 Permission Checking

### 1. Check User Permission
**GET** `/user/can/{permission}`

Check if the current authenticated user has a specific permission.

**Required Permission**: None (checks own permissions)

#### Response (200 OK)
```json
{
    "can": true,
    "permission": "edit-posts",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
    }
}
```

#### Response (200 OK) - No Permission
```json
{
    "can": false,
    "permission": "delete-posts",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
    }
}
```

---

### 2. Get Current User Permissions
**GET** `/user/current-permissions`

Get all permissions for the current authenticated user.

**Required Permission**: None (gets own permissions)

#### Response (200 OK)
```json
{
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
    },
    "permissions": [
        {
            "id": 1,
            "name": "edit-posts",
            "display_name": "Edit Posts",
            "description": "Can edit blog posts"
        },
        {
            "id": 2,
            "name": "view-posts",
            "display_name": "View Posts",
            "description": "Can view blog posts"
        },
        {
            "id": 3,
            "name": "create-posts",
            "display_name": "Create Posts",
            "description": "Can create new blog posts"
        }
    ]
}
```

---

## 📝 Complete RBAC Usage Examples

### Setting Up a Complete Role System

#### 1. Create Permissions
```bash
# Create content permissions
curl -X POST http://localhost:8000/api/v1/rbac/permissions \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "view-posts",
    "display_name": "View Posts",
    "description": "Can view blog posts"
  }'

curl -X POST http://localhost:8000/api/v1/rbac/permissions \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "create-posts",
    "display_name": "Create Posts", 
    "description": "Can create new blog posts"
  }'

curl -X POST http://localhost:8000/api/v1/rbac/permissions \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "edit-posts",
    "display_name": "Edit Posts",
    "description": "Can edit existing blog posts"
  }'
```

#### 2. Create Role with Permissions
```bash
curl -X POST http://localhost:8000/api/v1/rbac/roles \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "editor",
    "display_name": "Content Editor",
    "description": "Can create and edit content",
    "permissions": ["view-posts", "create-posts", "edit-posts"]
  }'
```

#### 3. Assign Role to User
```bash
curl -X POST http://localhost:8000/api/v1/rbac/users/1/roles \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "role_ids": [1]
  }'
```

#### 4. Check User Permissions
```bash
curl -X GET http://localhost:8000/api/v1/rbac/user/can/edit-posts \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

---

## ⚠️ Common Error Responses

### 403 Forbidden (Insufficient Permissions)
```json
{
    "message": "This action is unauthorized.",
    "required_permission": "create roles"
}
```

### 404 Not Found
```json
{
    "message": "Role not found"
}
```

### 422 Validation Error
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "name": ["The name field is required."],
        "permissions": ["The selected permissions.0 is invalid."]
    }
}
```

### 409 Conflict (Duplicate)
```json
{
    "message": "Role with this name already exists"
}
```

---

## 🛡️ Middleware Usage in Routes

### Protecting Routes with Permissions
```php
// In your route files
Route::middleware(['auth:sanctum', 'permission:edit-posts'])->group(function () {
    Route::put('/posts/{post}', [PostController::class, 'update']);
});

// Multiple permissions (user needs ALL)
Route::middleware(['auth:sanctum', 'permission:edit-posts,publish-posts'])->group(function () {
    Route::post('/posts/{post}/publish', [PostController::class, 'publish']);
});

// Alternative permissions (user needs ANY)
Route::middleware(['auth:sanctum', 'permission:edit-posts|delete-posts'])->group(function () {
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);
});
```

### Protecting Routes with Roles
```php
// Require specific role
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
});

// Multiple roles (user needs ANY)
Route::middleware(['auth:sanctum', 'role:admin|manager'])->group(function () {
    Route::get('/management/reports', [ReportController::class, 'index']);
});
```

---

## 🧪 Testing RBAC

### Testing Permission Checks
```php
// Test user has permission
$user = User::factory()->create();
$permission = Permission::create(['name' => 'edit-posts']);
$user->givePermissionTo($permission);

$response = $this->actingAs($user)
    ->getJson('/api/v1/rbac/user/can/edit-posts');

$response->assertStatus(200)
    ->assertJson(['can' => true]);
```

### Testing Role Assignment
```php
// Test role assignment
$user = User::factory()->create();
$role = Role::create(['name' => 'editor']);

$response = $this->actingAs($admin)
    ->postJson("/api/v1/rbac/users/{$user->id}/roles", [
        'role_ids' => [$role->id]
    ]);

$response->assertStatus(200);
$this->assertTrue($user->fresh()->hasRole('editor'));
```

---

## 📊 Performance Considerations

### Caching Recommendations
- **Role/Permission lookups**: Cache for 1 hour
- **User permissions**: Cache for 30 minutes
- **Permission checks**: Use database-level caching

### Database Optimization
- Index on `model_has_permissions.model_id`
- Index on `model_has_roles.model_id`
- Index on permission and role names for faster lookups

---

*Last Updated: August 20, 2025*
*Version: 1.0.0*
*All endpoints tested and verified working*