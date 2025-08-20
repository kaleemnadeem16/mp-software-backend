<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * User Model with Authentication and RBAC
 * 
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles, HasPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user has any of the given permissions
     *
     * @param array|string $permissions
     * @return bool
     */
    public function hasAnyPermission(array|string $permissions): bool
    {
        if (is_string($permissions)) {
            return $this->hasPermissionTo($permissions);
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermissionTo($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get user's direct permissions (not through roles)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDirectPermissions(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->permissions;
    }

        /**
     * Get all permissions for the user (via roles and direct permissions)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllPermissions(): \Illuminate\Database\Eloquent\Collection
    {
        // Get direct permissions
        $directPermissions = $this->getDirectPermissions();
        
        // Get permissions via roles
        $rolePermissions = $this->getPermissionsViaRoles();
        
        // Merge and return unique permissions
        return $directPermissions->merge($rolePermissions)->unique('id');
    }

    /**
     * Create API token for user
     *
     * @param string $name
     * @param array $abilities
     * @return \Laravel\Sanctum\NewAccessToken
     */
    public function createApiToken(string $name = 'API Token', array $abilities = ['*']): \Laravel\Sanctum\NewAccessToken
    {
        return $this->createToken($name, $abilities);
    }
}
