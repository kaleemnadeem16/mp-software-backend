<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * RBAC Controller
 * 
 * Handles dynamic role and permission management including
 * creating, assigning, and managing user permissions and roles.
 */
class RBACController extends BaseApiController
{
    /**
     * Get all roles with their permissions
     *
     * @return JsonResponse
     */
    public function getRoles(): JsonResponse
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                    ];
                }),
            ];
        });

        return $this->successResponse([
            'roles' => $roles,
        ], 'Roles retrieved successfully');
    }

    /**
     * Create a new role
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createRole(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $role = Role::create(['name' => $request->name]);

        // Assign permissions if provided
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return $this->successResponse([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
            ],
        ], 'Role created successfully', Response::HTTP_CREATED);
    }

    /**
     * Get all permissions
     *
     * @return JsonResponse
     */
    public function getPermissions(): JsonResponse
    {
        $permissions = Permission::all()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
            ];
        });

        return $this->successResponse([
            'permissions' => $permissions,
        ], 'Permissions retrieved successfully');
    }

    /**
     * Create a new permission
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createPermission(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:permissions,name'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $permission = Permission::create(['name' => $request->name]);

        return $this->successResponse([
            'id' => $permission->id,
            'name' => $permission->name,
        ], 'Permission created successfully', Response::HTTP_CREATED);
    }

    /**
     * Assign role to user (legacy method - kept for compatibility)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function assignRole(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'required|string|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $user = User::findOrFail($request->user_id);
        $user->assignRole($request->role);

        return $this->successResponse([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'role_assigned' => $request->role,
        ], 'Role assigned successfully');
    }

    /**
     * Remove role from user (legacy method - kept for compatibility)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function removeRole(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'required|string|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $user = User::findOrFail($request->user_id);
        $user->removeRole($request->role);

        return $this->successResponse([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'role_removed' => $request->role,
        ], 'Role removed successfully');
    }

    /**
     * Assign permission to user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function assignPermission(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'permission' => 'required|string|exists:permissions,name'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $user = User::findOrFail($request->user_id);
        $user->givePermissionTo($request->permission);

        return $this->successResponse([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'permission_assigned' => $request->permission,
        ], 'Permission assigned successfully');
    }

    /**
     * Remove permission from user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function removePermission(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'permission' => 'required|string|exists:permissions,name'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $user = User::findOrFail($request->user_id);
        $user->revokePermissionTo($request->permission);

        return $this->successResponse([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'permission_removed' => $request->permission,
        ], 'Permission removed successfully');
    }

    /**
     * Bulk sync user roles
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function syncUserRoles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $user = User::findOrFail($request->user_id);
        $user->syncRoles($request->roles);

        return $this->successResponse([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ], 'User roles synchronized successfully');
    }

    /**
     * Get user's RBAC information
     *
     * @param int $userId
     * @return JsonResponse
     */
    public function getUserRBAC(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        return $this->successResponse([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'roles' => $user->getRoleNames(),
            'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
            'role_permissions' => $user->getPermissionsViaRoles()->pluck('name'),
            'all_permissions' => $user->getAllPermissions()->pluck('name'),
        ], 'User RBAC information retrieved successfully');
    }

    /**
     * Get current user's permissions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getCurrentUserPermissions(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'roles' => $user->getRoleNames(),
            'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
            'role_permissions' => $user->getPermissionsViaRoles()->pluck('name'),
            'all_permissions' => $user->getAllPermissions()->pluck('name'),
        ], 'Current user permissions retrieved successfully');
    }

    /**
     * Check if current user has specific permission
     *
     * @param Request $request
     * @param string $permission
     * @return JsonResponse
     */
    public function checkUserPermission(Request $request, string $permission): JsonResponse
    {
        $user = $request->user();
        $hasPermission = $user->hasPermissionTo($permission);

        return $this->successResponse([
            'has_permission' => $hasPermission,
            'permission' => $permission,
            'via_role' => $hasPermission ? $user->getPermissionsViaRoles()->pluck('name')->contains($permission) : false,
            'direct' => $hasPermission ? $user->getDirectPermissions()->pluck('name')->contains($permission) : false,
        ], 'Permission check completed');
    }

    /**
     * Get user roles
     *
     * @param User $user
     * @return JsonResponse
     */
    public function getUserRoles(User $user): JsonResponse
    {
        $roles = $user->roles()->with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                    ];
                }),
            ];
        });

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'roles' => $roles,
        ], 'User roles retrieved successfully');
    }

    /**
     * Assign role to user
     *
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function assignRoleToUser(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role_name' => 'required|string|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $user->assignRole($request->role_name);

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'role_assigned' => $request->role_name,
        ], 'Role assigned successfully');
    }

    /**
     * Remove role from user
     *
     * @param User $user
     * @param Role $role
     * @return JsonResponse
     */
    public function removeRoleFromUser(User $user, Role $role): JsonResponse
    {
        $user->removeRole($role);

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'role_removed' => $role->name,
        ], 'Role removed successfully');
    }
}