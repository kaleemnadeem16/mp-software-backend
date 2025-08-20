<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;

/**
 * Authentication Controller
 * 
 * Handles user authentication operations including registration,
 * login, logout, and token management with RBAC integration.
 */
class AuthController extends BaseApiController
{
    /**
     * Register a new user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'sometimes|string|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Assign role if provided, otherwise assign default role
        $roleName = $request->role ?? 'user';
        if (\Spatie\Permission\Models\Role::where('name', $roleName)->exists()) {
            $user->assignRole($roleName);
        } else {
            // Create default 'user' role if it doesn't exist
            $defaultRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user']);
            $user->assignRole($defaultRole);
        }

        // Create API token
        $token = $user->createApiToken('Registration Token');

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer'
        ], 'User registered successfully', Response::HTTP_CREATED);
    }

    /**
     * Login user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse(
                'Invalid credentials', 
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        // Create new API token
        $deviceName = $request->device_name ?? 'Unknown Device';
        $token = $user->createApiToken($deviceName);

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer'
        ], 'Login successful');
    }

    /**
     * Logout user (revoke current token)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the current user's token
        $request->user()->currentAccessToken()->delete();

                return $this->successResponse(null, 'Logout successful');
    }

    /**
     * Logout from all devices (revoke all tokens)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logoutAll(Request $request): JsonResponse
    {
        // Revoke all tokens for the user
        $request->user()->tokens()->delete();

        return $this->successResponse(null, 'Logged out from all devices');
    }

    /**
     * Get current authenticated user profile
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
            'role_permissions' => $user->getPermissionsViaRoles()->pluck('name'),
        ], 'Profile retrieved successfully');
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse('Current password is incorrect', [], 400);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        // Revoke all tokens except current one
        $currentToken = $user->currentAccessToken();
        $user->tokens()->where('id', '!=', $currentToken->id)->delete();

        return $this->successResponse(null, 'Password changed successfully');
    }

    /**
     * Refresh user token
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get current token name
        $currentToken = $request->user()->currentAccessToken();
        $tokenName = $currentToken->name ?? 'Refreshed Token';
        
        // Revoke current token
        $currentToken->delete();
        
        // Create new token
        $newToken = $user->createApiToken($tokenName);

        return $this->successResponse([
            'token' => $newToken->plainTextToken,
            'token_type' => 'Bearer'
        ], 'Token refreshed successfully');
    }
}