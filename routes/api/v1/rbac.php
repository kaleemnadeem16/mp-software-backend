<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\RBACController;

/*
|--------------------------------------------------------------------------
| RBAC (Role-Based Access Control) Routes
|--------------------------------------------------------------------------
|
| All role and permission management routes including role assignment,
| permission management, and user role/permission operations.
|
*/

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('rbac')->group(function () {
    // Role Management
    Route::prefix('roles')->group(function () {
        Route::get('/', [RBACController::class, 'getRoles'])
            ->middleware('check.permission:view roles')
            ->name('rbac.roles.index');
        
        Route::post('/', [RBACController::class, 'createRole'])
            ->middleware(['check.permission:create roles', 'throttle:rbac:sensitive'])
            ->name('rbac.roles.store');
        
        Route::get('/{role}', [RBACController::class, 'getRole'])
            ->middleware('check.permission:view roles')
            ->name('rbac.roles.show');
        
        Route::put('/{role}', [RBACController::class, 'updateRole'])
            ->middleware(['check.permission:edit roles', 'throttle:rbac:sensitive'])
            ->name('rbac.roles.update');
        
        Route::delete('/{role}', [RBACController::class, 'deleteRole'])
            ->middleware(['check.permission:delete roles', 'throttle:rbac:sensitive'])
            ->name('rbac.roles.destroy');
    });

    // Permission Management
    Route::prefix('permissions')->group(function () {
        Route::get('/', [RBACController::class, 'getPermissions'])
            ->middleware('check.permission:view permissions')
            ->name('rbac.permissions.index');
        
        Route::post('/', [RBACController::class, 'createPermission'])
            ->middleware(['check.permission:create permissions', 'throttle:rbac:sensitive'])
            ->name('rbac.permissions.store');
        
        Route::get('/{permission}', [RBACController::class, 'getPermission'])
            ->middleware('check.permission:view permissions')
            ->name('rbac.permissions.show');
        
        Route::put('/{permission}', [RBACController::class, 'updatePermission'])
            ->middleware(['check.permission:edit permissions', 'throttle:rbac:sensitive'])
            ->name('rbac.permissions.update');
        
        Route::delete('/{permission}', [RBACController::class, 'deletePermission'])
            ->middleware(['check.permission:delete permissions', 'throttle:rbac:sensitive'])
            ->name('rbac.permissions.destroy');
    });

    // User Role/Permission Assignment
    Route::prefix('users')->group(function () {
        Route::get('/{user}/roles', [RBACController::class, 'getUserRoles'])
            ->middleware('check.permission:view user roles')
            ->name('rbac.users.roles.index');
        
        Route::post('/{user}/roles', [RBACController::class, 'assignRoleToUser'])
            ->middleware(['check.permission:assign user roles', 'throttle:rbac:sensitive'])
            ->name('rbac.users.roles.assign');
        
        Route::delete('/{user}/roles/{role}', [RBACController::class, 'removeRoleFromUser'])
            ->middleware(['check.permission:remove user roles', 'throttle:rbac:sensitive'])
            ->name('rbac.users.roles.remove');
        
        Route::get('/{user}/permissions', [RBACController::class, 'getUserPermissions'])
            ->middleware('check.permission:view user permissions')
            ->name('rbac.users.permissions.index');
        
        Route::post('/{user}/permissions', [RBACController::class, 'assignPermissionToUser'])
            ->middleware(['check.permission:assign user permissions', 'throttle:rbac:sensitive'])
            ->name('rbac.users.permissions.assign');
        
        Route::delete('/{user}/permissions/{permission}', [RBACController::class, 'removePermissionFromUser'])
            ->middleware(['check.permission:remove user permissions', 'throttle:rbac:sensitive'])
            ->name('rbac.users.permissions.remove');
    });

    // Role Permission Assignment
    Route::prefix('roles/{role}/permissions')->group(function () {
        Route::get('/', [RBACController::class, 'getRolePermissions'])
            ->middleware('check.permission:view role permissions')
            ->name('rbac.roles.permissions.index');
        
        Route::post('/', [RBACController::class, 'assignPermissionToRole'])
            ->middleware(['check.permission:assign role permissions', 'throttle:rbac:sensitive'])
            ->name('rbac.roles.permissions.assign');
        
        Route::delete('/{permission}', [RBACController::class, 'removePermissionFromRole'])
            ->middleware(['check.permission:remove role permissions', 'throttle:rbac:sensitive'])
            ->name('rbac.roles.permissions.remove');
    });

    // Current User Utility Routes (no rate limit for performance)
    Route::get('user/current-permissions', [RBACController::class, 'getCurrentUserPermissions'])
        ->name('rbac.current-user-permissions');
    
    Route::get('user/can/{permission}', [RBACController::class, 'checkUserPermission'])
        ->name('rbac.check-permission');
});