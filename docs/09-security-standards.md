# Security Standards

## Overview

This comprehensive security guide covers all aspects of securing your Laravel backend application, from basic security measures to advanced protection techniques. These standards ensure your application is resistant to common vulnerabilities and follows industry best practices.

## Table of Contents

- [Environment & Configuration Security](#environment--configuration-security)
- [Input Validation & Sanitization](#input-validation--sanitization)
- [Authentication Security](#authentication-security)
- [Authorization & Access Control](#authorization--access-control)
- [Data Protection](#data-protection)
- [File Upload Security](#file-upload-security)
- [API Security](#api-security)
- [Database Security](#database-security)
- [Infrastructure Security](#infrastructure-security)
- [Monitoring & Logging](#monitoring--logging)
- [Security Headers](#security-headers)
- [Vulnerability Prevention](#vulnerability-prevention)

## Environment & Configuration Security

### 1. Environment File Protection

```bash
# .env file permissions (Linux/Mac)
chmod 600 .env

# Never commit .env to version control
# Ensure .env is in .gitignore
echo ".env" >> .gitignore
```

### 2. Secure Environment Configuration

```env
# Production Environment Settings
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-strong-32-character-key

# HTTPS Enforcement
APP_URL=https://yourdomain.com
ASSET_URL=https://yourdomain.com

# Database Security
DB_HOST=127.0.0.1  # Don't use public IPs
DB_PASSWORD=strong-random-password-here

# Session Security
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict
SESSION_LIFETIME=120

# Sanctum Security
SANCTUM_STATEFUL_DOMAINS=yourdomain.com,app.yourdomain.com
SESSION_DOMAIN=.yourdomain.com

# Disable Debug Mode in Production
LOG_LEVEL=error
```

### 3. Configuration Security Service

```php
<?php

namespace App\Services;

class SecurityConfigService
{
    public static function enforceProductionSecurity(): void
    {
        if (app()->environment('production')) {
            // Force HTTPS
            if (!request()->secure() && !app()->runningInConsole()) {
                abort(403, 'HTTPS required');
            }

            // Ensure debug is disabled
            if (config('app.debug')) {
                throw new \Exception('Debug mode must be disabled in production');
            }

            // Validate APP_KEY is set
            if (empty(config('app.key'))) {
                throw new \Exception('Application key must be set');
            }
        }
    }
}

// Call in AppServiceProvider boot method
public function boot(): void
{
    SecurityConfigService::enforceProductionSecurity();
}
```

## Input Validation & Sanitization

### 1. Comprehensive Validation Rules

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SecureUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z\s]+$/', // Only letters and spaces
            ],
            'email' => [
                'required',
                'email:rfc,dns', // Strict email validation
                'max:255',
                'unique:users,email,' . $this->route('user')?->id,
            ],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-zA-Z0-9_]+$/', // Alphanumeric and underscore only
                'unique:users,username,' . $this->route('user')?->id,
            ],
            'password' => [
                'required',
                Password::min(12)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(3), // Check against data breaches
            ],
            'phone' => [
                'sometimes',
                'string',
                'regex:/^\+?[1-9]\d{1,14}$/', // International phone format
            ],
            'website' => [
                'sometimes',
                'url',
                'regex:/^https:\/\//', // Only HTTPS URLs
            ],
            'bio' => [
                'sometimes',
                'string',
                'max:1000',
                new NoScriptTags(), // Custom rule to prevent script injection
            ],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim($this->email)),
            'username' => strtolower(trim($this->username)),
            'name' => trim($this->name),
            'phone' => preg_replace('/[^+0-9]/', '', $this->phone ?? ''),
        ]);
    }

    protected function passedValidation(): void
    {
        // Additional sanitization after validation
        $this->merge([
            'name' => strip_tags($this->name),
            'bio' => strip_tags($this->bio, '<p><br><strong><em>'), // Allow basic formatting
        ]);
    }
}
```

### 2. Custom Validation Rules

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoScriptTags implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (preg_match('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', $value)) {
            $fail('The :attribute field contains forbidden script tags.');
        }

        // Check for other dangerous patterns
        $dangerousPatterns = [
            '/javascript:/i',
            '/data:text\/html/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/onclick\s*=/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $fail('The :attribute field contains potentially dangerous content.');
            }
        }
    }
}

class SafeFilename implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check for directory traversal
        if (strpos($value, '..') !== false || strpos($value, '/') !== false) {
            $fail('The :attribute contains invalid path characters.');
        }

        // Check for dangerous extensions
        $dangerousExtensions = ['php', 'exe', 'bat', 'sh', 'cmd', 'scr', 'vbs', 'js'];
        $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
        
        if (in_array($extension, $dangerousExtensions)) {
            $fail('The :attribute has a forbidden file extension.');
        }
    }
}
```

### 3. Input Sanitization Service

```php
<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class InputSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function cleanHtml(string $html): string
    {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', 'p,br,strong,em,u,ol,ul,li,h3,h4,h5,h6');
            $config->set('HTML.ForbiddenElements', 'script,object,embed,iframe,form,input');
            self::$purifier = new HTMLPurifier($config);
        }

        return self::$purifier->purify($html);
    }

    public static function sanitizeForDatabase(string $input): string
    {
        // Remove null bytes
        $input = str_replace(chr(0), '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Remove control characters except tab, newline, and carriage return
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        return $input;
    }

    public static function sanitizeFilename(string $filename): string
    {
        // Remove path components
        $filename = basename($filename);
        
        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Prevent hidden files
        $filename = ltrim($filename, '.');
        
        // Ensure it's not empty
        if (empty($filename)) {
            $filename = 'file_' . uniqid();
        }
        
        return $filename;
    }
}
```

## Authentication Security

### 1. Enhanced Login Controller

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SecurityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class SecureAuthController extends Controller
{
    public function login(Request $request)
    {
        $this->validateLogin($request);
        $this->checkRateLimit($request);

        $user = $this->findUser($request->identifier);
        
        if (!$user || !Hash::check($request->password, $user->password)) {
            $this->handleFailedLogin($request);
            return $this->failedLoginResponse();
        }

        // Additional security checks
        if (!$this->additionalSecurityChecks($user, $request)) {
            SecurityLogger::logSuspiciousActivity($user, 'Failed additional security checks', $request);
            return $this->failedLoginResponse();
        }

        $this->handleSuccessfulLogin($user, $request);
        
        return $this->successfulLoginResponse($user);
    }

    protected function additionalSecurityChecks(User $user, Request $request): bool
    {
        // Check if account is active
        if ($user->status !== 'active') {
            return false;
        }

        // Check if account is locked
        if ($user->locked_until && $user->locked_until->isFuture()) {
            return false;
        }

        // Check for suspicious login patterns
        if ($this->isSuspiciousLogin($user, $request)) {
            return false;
        }

        return true;
    }

    protected function isSuspiciousLogin(User $user, Request $request): bool
    {
        $lastLogin = $user->last_login_at;
        $lastIp = $user->last_login_ip;
        $currentIp = $request->ip();

        // Different country login within short time
        if ($lastLogin && $lastLogin->diffInHours(now()) < 1) {
            if ($lastIp && $this->isDifferentCountry($lastIp, $currentIp)) {
                return true;
            }
        }

        // Too many recent failed attempts from this IP
        $failedAttempts = RateLimiter::attempts("failed_login_ip:{$currentIp}");
        if ($failedAttempts > 10) {
            return true;
        }

        return false;
    }

    protected function handleFailedLogin(Request $request): void
    {
        $key = "failed_login_ip:{$request->ip()}";
        RateLimiter::hit($key, 3600); // 1 hour decay

        $attempts = RateLimiter::attempts($key);
        
        SecurityLogger::logFailedLogin($request->identifier, $request->ip(), $attempts);

        // Lock IP after too many attempts
        if ($attempts > 20) {
            // Implement IP blocking logic here
        }
    }

    protected function handleSuccessfulLogin(User $user, Request $request): void
    {
        // Update login information
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        // Clear rate limiting
        RateLimiter::clear("failed_login_ip:{$request->ip()}");
        
        SecurityLogger::logSuccessfulLogin($user, $request);
    }
}
```

### 2. Password Security Enhancements

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PasswordSecurityService
{
    public static function validatePasswordStrength(string $password): array
    {
        $errors = [];

        if (strlen($password) < 12) {
            $errors[] = 'Password must be at least 12 characters long';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        if (!preg_match('/[^a-zA-Z\d]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        // Check against common passwords
        if (self::isCommonPassword($password)) {
            $errors[] = 'Password is too common, please choose a stronger one';
        }

        return $errors;
    }

    public static function checkPasswordHistory(User $user, string $newPassword): bool
    {
        // Check against last 5 passwords
        $lastPasswords = $user->passwordHistory()->latest()->take(5)->get();
        
        foreach ($lastPasswords as $oldPassword) {
            if (Hash::check($newPassword, $oldPassword->password)) {
                return false; // Password was used before
            }
        }

        return true;
    }

    public static function isCommonPassword(string $password): bool
    {
        $commonPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123',
            'password123', 'admin', 'letmein', 'welcome', 'monkey',
            // Add more common passwords
        ];

        return in_array(strtolower($password), $commonPasswords);
    }

    public static function generateSecurePassword(int $length = 16): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $password;
    }
}
```

### 3. Two-Factor Authentication

```php
<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function generateSecretKey(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQRCodeUrl(User $user, string $secret): string
    {
        $companyName = config('app.name');
        $companyEmail = $user->email;
        
        return $this->google2fa->getQRCodeUrl(
            $companyName,
            $companyEmail,
            $secret
        );
    }

    public function generateQRCode(User $user, string $secret): string
    {
        $qrCodeUrl = $this->getQRCodeUrl($user, $secret);
        
        $renderer = new ImageRenderer(
            new RendererStyle(400),
            new SvgImageBackEnd()
        );
        
        $writer = new Writer($renderer);
        
        return base64_encode($writer->writeString($qrCodeUrl));
    }

    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }

    public function enable2FA(User $user, string $code): bool
    {
        if (!$user->two_factor_secret) {
            return false;
        }

        if (!$this->verifyCode($user->two_factor_secret, $code)) {
            return false;
        }

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);

        return true;
    }

    public function disable2FA(User $user): void
    {
        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);
    }

    public function generateRecoveryCodes(): array
    {
        $codes = [];
        
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtolower(bin2hex(random_bytes(5)));
        }
        
        return $codes;
    }
}
```

## Authorization & Access Control

### 1. Enhanced Permission Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SecurityLogger;

class EnhancedPermissionMiddleware
{
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Check if user account is active
        if ($user->status !== 'active') {
            SecurityLogger::logUnauthorizedAccess($user, 'Inactive account', $request);
            return response()->json(['message' => 'Account is not active'], 403);
        }

        // Check session validity
        if (!$this->isValidSession($user, $request)) {
            SecurityLogger::logUnauthorizedAccess($user, 'Invalid session', $request);
            return response()->json(['message' => 'Session expired'], 401);
        }

        // Check permissions
        foreach ($permissions as $permission) {
            if (!$user->can($permission)) {
                SecurityLogger::logUnauthorizedAccess($user, "Missing permission: {$permission}", $request);
                return response()->json([
                    'message' => 'You do not have permission to perform this action'
                ], 403);
            }
        }

        // Log successful access for sensitive operations
        if ($this->isSensitiveOperation($request)) {
            SecurityLogger::logSensitiveAccess($user, $request);
        }

        return $next($request);
    }

    private function isValidSession(User $user, Request $request): bool
    {
        // Check if login is from same IP (optional, might be too strict)
        $currentIp = $request->ip();
        $loginIp = $user->last_login_ip;

        // Allow IP changes but log them
        if ($loginIp && $currentIp !== $loginIp) {
            SecurityLogger::logIpChange($user, $loginIp, $currentIp);
        }

        // Check token age
        $token = $user->currentAccessToken();
        if ($token && $token->created_at->diffInDays() > 30) {
            return false;
        }

        return true;
    }

    private function isSensitiveOperation(Request $request): bool
    {
        $sensitiveRoutes = [
            '/admin/users',
            '/admin/roles',
            '/admin/settings',
            '/admin/logs',
            '/api/*/export',
        ];

        $path = $request->path();
        
        foreach ($sensitiveRoutes as $sensitiveRoute) {
            if (fnmatch($sensitiveRoute, $path)) {
                return true;
            }
        }

        return false;
    }
}
```

### 2. Resource-Based Authorization

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Project;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager', 'Employee']);
    }

    public function view(User $user, Project $project): bool
    {
        // Admin can view all projects
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Users can only view projects they're assigned to
        return $project->members()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']);
    }

    public function update(User $user, Project $project): bool
    {
        // Admin can update all projects
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Project owner can update
        if ($project->owner_id === $user->id) {
            return true;
        }

        // Manager with proper department can update
        if ($user->hasRole('Manager') && $user->department === $project->department) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Project $project): bool
    {
        // Only admin or project owner can delete
        return $user->hasRole('Admin') || $project->owner_id === $user->id;
    }

    public function addMember(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function removeMember(User $user, Project $project, User $member): bool
    {
        // Can't remove project owner
        if ($member->id === $project->owner_id) {
            return false;
        }

        return $this->update($user, $project);
    }
}
```

## Data Protection

### 1. Data Encryption Service

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class DataEncryptionService
{
    public static function encryptSensitiveData(array $data): array
    {
        $sensitiveFields = ['ssn', 'tax_id', 'bank_account', 'credit_card'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = Crypt::encryptString($data[$field]);
            }
        }
        
        return $data;
    }

    public static function decryptSensitiveData(array $data): array
    {
        $sensitiveFields = ['ssn', 'tax_id', 'bank_account', 'credit_card'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                try {
                    $data[$field] = Crypt::decryptString($data[$field]);
                } catch (\Exception $e) {
                    // Handle decryption failure
                    $data[$field] = null;
                }
            }
        }
        
        return $data;
    }

    public static function hashPII(string $data): string
    {
        // One-way hash for PII that doesn't need to be decrypted
        return hash('sha256', $data . config('app.key'));
    }
}
```

### 2. Database Model with Encryption

```php
<?php

namespace App\Models;

use App\Services\DataEncryptionService;
use Illuminate\Database\Eloquent\Model;

class EmployeeRecord extends Model
{
    protected $fillable = [
        'name',
        'email',
        'ssn',
        'bank_account',
        'salary',
    ];

    protected $hidden = [
        'ssn',
        'bank_account',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
    ];

    // Automatically encrypt sensitive data before saving
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->attributes = DataEncryptionService::encryptSensitiveData($model->attributes);
        });

        static::updating(function ($model) {
            $model->attributes = DataEncryptionService::encryptSensitiveData($model->attributes);
        });
    }

    // Accessor to decrypt data when accessed
    public function getSsnAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getBankAccountAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    // Method to get masked SSN for display
    public function getMaskedSsn(): string
    {
        $ssn = $this->ssn;
        if (!$ssn) {
            return 'XXX-XX-XXXX';
        }

        return 'XXX-XX-' . substr($ssn, -4);
    }
}
```

## File Upload Security

### 1. Secure File Upload Handler

```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SecureFileUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    public function uploadFile(UploadedFile $file, string $directory = 'uploads'): array
    {
        // Validate file
        $this->validateFile($file);

        // Generate secure filename
        $filename = $this->generateSecureFilename($file);

        // Scan for malware (if antivirus is available)
        $this->scanForMalware($file);

        // Store file in secure location
        $path = $file->storeAs($directory, $filename, 'local');

        // Create file record
        $fileRecord = $this->createFileRecord($file, $path, $filename);

        return [
            'id' => $fileRecord->id,
            'filename' => $fileRecord->original_name,
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ];
    }

    private function validateFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('File size exceeds maximum allowed size');
        }

        // Check MIME type
        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            throw new \InvalidArgumentException('File type not allowed');
        }

        // Check file extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (!in_array($extension, $allowedExtensions)) {
            throw new \InvalidArgumentException('File extension not allowed');
        }

        // Verify MIME type matches extension
        if (!$this->mimeTypeMatchesExtension($file->getMimeType(), $extension)) {
            throw new \InvalidArgumentException('File type does not match extension');
        }

        // Check for executable files disguised as images
        if ($this->isExecutableFile($file)) {
            throw new \InvalidArgumentException('Executable files are not allowed');
        }
    }

    private function generateSecureFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $hash = hash('sha256', $file->getContent());
        $timestamp = now()->format('Y-m-d_H-i-s');
        
        return "{$timestamp}_{$hash}.{$extension}";
    }

    private function scanForMalware(UploadedFile $file): void
    {
        // Implement malware scanning using ClamAV or similar
        // This is a placeholder - implement based on your antivirus solution
        
        $filePath = $file->getPathname();
        
        // Example ClamAV integration
        if (function_exists('shell_exec') && !app()->environment('testing')) {
            $result = shell_exec("clamscan --no-summary {$filePath}");
            
            if (strpos($result, 'FOUND') !== false) {
                throw new \InvalidArgumentException('File contains malware');
            }
        }
    }

    private function mimeTypeMatchesExtension(string $mimeType, string $extension): bool
    {
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        return isset($mimeMap[$extension]) && $mimeMap[$extension] === $mimeType;
    }

    private function isExecutableFile(UploadedFile $file): bool
    {
        $content = $file->getContent();
        
        // Check for common executable signatures
        $executableSignatures = [
            "\x4D\x5A", // PE executable
            "\x7F\x45\x4C\x46", // ELF executable
            "\xCA\xFE\xBA\xBE", // Mach-O executable
            "#!/bin/sh",
            "#!/bin/bash",
            "<?php",
            "<script",
        ];

        foreach ($executableSignatures as $signature) {
            if (strpos($content, $signature) === 0 || strpos($content, $signature) !== false) {
                return true;
            }
        }

        return false;
    }
}
```

## API Security

### 1. API Rate Limiting

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimiting
{
    public function handle(Request $request, Closure $next, string $tier = 'default'): Response
    {
        $key = $this->resolveRequestSignature($request, $tier);
        $maxAttempts = $this->getMaxAttempts($tier, $request->user());
        $decayMinutes = $this->getDecayMinutes($tier);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);
            
            return response()->json([
                'message' => 'Too many requests',
                'retry_after' => $retryAfter,
            ], 429, [
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        $remaining = $maxAttempts - RateLimiter::attempts($key);
        
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remaining),
            'X-RateLimit-Reset' => now()->addMinutes($decayMinutes)->timestamp,
        ]);
    }

    protected function resolveRequestSignature(Request $request, string $tier): string
    {
        $user = $request->user();
        
        if ($user) {
            return "api_rate_limit:{$tier}:user:{$user->id}";
        }

        return "api_rate_limit:{$tier}:ip:{$request->ip()}";
    }

    protected function getMaxAttempts(string $tier, $user = null): int
    {
        $limits = [
            'auth' => 5,
            'api' => 60,
            'admin' => 100,
            'upload' => 10,
            'export' => 5,
        ];

        $baseLimit = $limits[$tier] ?? 60;

        // Increase limits for authenticated users with higher roles
        if ($user && $user->hasRole('Admin')) {
            return $baseLimit * 3;
        }

        if ($user && $user->hasRole('Manager')) {
            return $baseLimit * 2;
        }

        return $baseLimit;
    }

    protected function getDecayMinutes(string $tier): int
    {
        $decayTimes = [
            'auth' => 15, // Longer decay for auth attempts
            'api' => 1,
            'admin' => 1,
            'upload' => 5,
            'export' => 10,
        ];

        return $decayTimes[$tier] ?? 1;
    }
}
```

### 2. Request Signing for Critical Operations

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateRequestSignature
{
    public function handle(Request $request, Closure $next)
    {
        if (!$this->hasValidSignature($request)) {
            return response()->json([
                'message' => 'Invalid request signature'
            ], 400);
        }

        return $next($request);
    }

    protected function hasValidSignature(Request $request): bool
    {
        $signature = $request->header('X-Signature');
        
        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');

        // Check timestamp (prevent replay attacks)
        if (!$timestamp || abs(time() - $timestamp) > 300) { // 5 minutes
            return false;
        }

        // Check nonce (prevent duplicate requests)
        if (!$nonce || $this->isNonceUsed($nonce)) {
            return false;
        }

        // Verify signature
        $expectedSignature = hash_hmac(
            'sha256',
            $timestamp . $nonce . $payload,
            config('app.webhook_secret')
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        // Store nonce to prevent reuse
        $this->storeNonce($nonce, $timestamp);

        return true;
    }

    protected function isNonceUsed(string $nonce): bool
    {
        return \Cache::has("nonce:{$nonce}");
    }

    protected function storeNonce(string $nonce, int $timestamp): void
    {
        \Cache::put("nonce:{$nonce}", $timestamp, 600); // 10 minutes
    }
}
```

## Security Headers

### 1. Security Headers Middleware

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

        // Content Security Policy
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "img-src 'self' data: https:; " .
            "connect-src 'self' wss: ws:; " .
            "frame-ancestors 'none';"
        );

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // XSS Protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // HSTS (HTTP Strict Transport Security)
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Feature Policy / Permissions Policy
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );

        // Remove identifying headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
```

## Monitoring & Logging

### 1. Security Event Logger

```php
<?php

namespace App\Services;

use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityLogger
{
    public static function logFailedLogin(string $identifier, string $ip, int $attempts): void
    {
        $data = [
            'event' => 'failed_login',
            'identifier' => $identifier,
            'ip' => $ip,
            'attempts' => $attempts,
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
        ];

        Log::warning('Failed login attempt', $data);
        
        SecurityLog::create([
            'event_type' => 'failed_login',
            'severity' => 'medium',
            'data' => $data,
            'ip_address' => $ip,
        ]);
    }

    public static function logSuccessfulLogin(User $user, Request $request): void
    {
        $data = [
            'event' => 'successful_login',
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now(),
        ];

        Log::info('Successful login', $data);
        
        SecurityLog::create([
            'event_type' => 'successful_login',
            'severity' => 'low',
            'user_id' => $user->id,
            'data' => $data,
            'ip_address' => $request->ip(),
        ]);
    }

    public static function logUnauthorizedAccess(User $user, string $reason, Request $request): void
    {
        $data = [
            'event' => 'unauthorized_access',
            'user_id' => $user->id,
            'reason' => $reason,
            'route' => $request->route()?->getName(),
            'url' => $request->url(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now(),
        ];

        Log::warning('Unauthorized access attempt', $data);
        
        SecurityLog::create([
            'event_type' => 'unauthorized_access',
            'severity' => 'high',
            'user_id' => $user->id,
            'data' => $data,
            'ip_address' => $request->ip(),
        ]);
    }

    public static function logSensitiveAccess(User $user, Request $request): void
    {
        $data = [
            'event' => 'sensitive_access',
            'user_id' => $user->id,
            'route' => $request->route()?->getName(),
            'url' => $request->url(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now(),
        ];

        Log::info('Sensitive operation accessed', $data);
        
        SecurityLog::create([
            'event_type' => 'sensitive_access',
            'severity' => 'medium',
            'user_id' => $user->id,
            'data' => $data,
            'ip_address' => $request->ip(),
        ]);
    }

    public static function logSuspiciousActivity(User $user, string $activity, Request $request): void
    {
        $data = [
            'event' => 'suspicious_activity',
            'user_id' => $user->id,
            'activity' => $activity,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now(),
        ];

        Log::warning('Suspicious activity detected', $data);
        
        SecurityLog::create([
            'event_type' => 'suspicious_activity',
            'severity' => 'high',
            'user_id' => $user->id,
            'data' => $data,
            'ip_address' => $request->ip(),
        ]);

        // Trigger additional security measures
        self::handleSuspiciousActivity($user, $activity);
    }

    private static function handleSuspiciousActivity(User $user, string $activity): void
    {
        // Implement automatic responses to suspicious activity
        // For example:
        // - Lock account temporarily
        // - Require additional verification
        // - Send alert to security team
        // - Invalidate all sessions
    }
}
```

### 2. Security Monitoring Dashboard

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityLog;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SecurityMonitoringController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:Admin']);
    }

    public function dashboard()
    {
        $stats = [
            'total_events_today' => SecurityLog::whereDate('created_at', today())->count(),
            'failed_logins_today' => SecurityLog::where('event_type', 'failed_login')
                ->whereDate('created_at', today())->count(),
            'unauthorized_attempts_today' => SecurityLog::where('event_type', 'unauthorized_access')
                ->whereDate('created_at', today())->count(),
            'suspicious_activities_week' => SecurityLog::where('event_type', 'suspicious_activity')
                ->where('created_at', '>=', now()->subWeek())->count(),
        ];

        $recentEvents = SecurityLog::with('user')
            ->where('severity', 'high')
            ->latest()
            ->take(10)
            ->get();

        $topFailedIPs = SecurityLog::where('event_type', 'failed_login')
            ->whereDate('created_at', today())
            ->selectRaw('ip_address, COUNT(*) as attempts')
            ->groupBy('ip_address')
            ->orderByDesc('attempts')
            ->take(10)
            ->get();

        return $this->success([
            'stats' => $stats,
            'recent_events' => $recentEvents,
            'top_failed_ips' => $topFailedIPs,
        ]);
    }

    public function events(Request $request)
    {
        $query = SecurityLog::with('user')->latest();

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', $request->ip_address);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $events = $query->paginate(20);

        return $this->success($events);
    }
}
```

---

**Next**: Continue with additional security documentation sections as needed for your specific requirements.