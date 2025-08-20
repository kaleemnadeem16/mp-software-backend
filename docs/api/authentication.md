# Authentication API Documentation

## Overview
Complete authentication system using Laravel Sanctum with secure token-based authentication.

## Base URL
```
http://localhost:8000/api/v1/auth
```

## Authentication Headers
```http
Authorization: Bearer {your-token}
Content-Type: application/json
Accept: application/json
```

---

## 🔐 Authentication Endpoints

### 1. Register User
**POST** `/register`

Create a new user account.

#### Request Body
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

#### Response (201 Created)
```json
{
    "message": "User registered successfully",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": null,
        "created_at": "2025-08-20T10:00:00.000000Z",
        "updated_at": "2025-08-20T10:00:00.000000Z"
    },
    "token": "1|abc123def456ghi789..."
}
```

#### Validation Errors (422)
```json
{
    "message": "The email has already been taken.",
    "errors": {
        "email": ["The email has already been taken."],
        "password": ["The password confirmation does not match."]
    }
}
```

---

### 2. Login User
**POST** `/login`

Authenticate user and receive access token.

#### Request Body
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

#### Response (200 OK)
```json
{
    "message": "Login successful",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": null,
        "created_at": "2025-08-20T10:00:00.000000Z",
        "updated_at": "2025-08-20T10:00:00.000000Z"
    },
    "token": "1|abc123def456ghi789..."
}
```

#### Authentication Failed (401)
```json
{
    "message": "Invalid credentials"
}
```

---

### 3. Get User Profile
**GET** `/profile`

Get authenticated user's profile information.

#### Headers Required
```http
Authorization: Bearer {your-token}
```

#### Response (200 OK)
```json
{
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": null,
        "created_at": "2025-08-20T10:00:00.000000Z",
        "updated_at": "2025-08-20T10:00:00.000000Z",
        "roles": [
            {
                "id": 1,
                "name": "user",
                "display_name": "Regular User",
                "description": "Standard user access"
            }
        ],
        "permissions": [
            {
                "id": 1,
                "name": "view-dashboard",
                "display_name": "View Dashboard",
                "description": "Can view main dashboard"
            }
        ]
    }
}
```

---

### 4. Update Profile
**PUT** `/profile`

Update authenticated user's profile information.

#### Headers Required
```http
Authorization: Bearer {your-token}
```

#### Request Body
```json
{
    "name": "John Smith",
    "email": "johnsmith@example.com"
}
```

#### Response (200 OK)
```json
{
    "message": "Profile updated successfully",
    "user": {
        "id": 1,
        "name": "John Smith",
        "email": "johnsmith@example.com",
        "email_verified_at": null,
        "created_at": "2025-08-20T10:00:00.000000Z",
        "updated_at": "2025-08-20T10:00:00.000000Z"
    }
}
```

---

### 5. Change Password
**POST** `/change-password`

Change authenticated user's password.

#### Headers Required
```http
Authorization: Bearer {your-token}
```

#### Request Body
```json
{
    "current_password": "oldpassword123",
    "password": "newpassword456",
    "password_confirmation": "newpassword456"
}
```

#### Response (200 OK)
```json
{
    "message": "Password changed successfully"
}
```

#### Current Password Invalid (400)
```json
{
    "message": "Current password is incorrect"
}
```

---

### 6. Logout
**POST** `/logout`

Logout user and revoke current access token.

#### Headers Required
```http
Authorization: Bearer {your-token}
```

#### Response (200 OK)
```json
{
    "message": "Logout successful"
}
```

---

## 🔒 Security Features

### Rate Limiting
- **Login attempts**: 5 attempts per minute per IP
- **Registration**: 3 attempts per minute per IP
- **Password changes**: 3 attempts per minute per user

### Token Management
- **Token expiration**: 24 hours (configurable)
- **Token revocation**: On logout or password change
- **Multiple sessions**: Supported (each device gets unique token)

### Validation Rules
- **Email**: Valid email format, unique in database
- **Password**: Minimum 8 characters
- **Name**: Required, maximum 255 characters

---

## 📝 Usage Examples

### Complete Authentication Flow

#### 1. Register a new user
```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "securepass123",
    "password_confirmation": "securepass123"
  }'
```

#### 2. Login and get token
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "jane@example.com",
    "password": "securepass123"
  }'
```

#### 3. Use token to access protected resources
```bash
curl -X GET http://localhost:8000/api/v1/auth/profile \
  -H "Authorization: Bearer 1|abc123def456ghi789..." \
  -H "Accept: application/json"
```

#### 4. Logout when done
```bash
curl -X POST http://localhost:8000/api/v1/auth/logout \
  -H "Authorization: Bearer 1|abc123def456ghi789..." \
  -H "Accept: application/json"
```

---

## ⚠️ Common Error Responses

### 401 Unauthorized
```json
{
    "message": "Unauthenticated."
}
```

### 422 Validation Error
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "field_name": ["Error message here"]
    }
}
```

### 429 Too Many Requests
```json
{
    "message": "Too Many Attempts."
}
```

### 500 Server Error
```json
{
    "message": "Server Error",
    "error": "Internal server error occurred"
}
```

---

## 🧪 Testing the API

### Using Postman
1. Import the provided Postman collection
2. Set base URL to `http://localhost:8000/api/v1/auth`
3. Use environment variables for tokens

### Using PHP/Laravel Testing
```php
// Register user test
$response = $this->postJson('/api/v1/auth/register', [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => 'password123',
    'password_confirmation' => 'password123'
]);

$response->assertStatus(201)
    ->assertJsonStructure([
        'message',
        'user' => ['id', 'name', 'email'],
        'token'
    ]);
```

---

*Last Updated: August 20, 2025*
*Version: 1.0.0*