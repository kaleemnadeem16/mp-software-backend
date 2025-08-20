<?php

declare(strict_types=1);

namespace Tests\Feature\RBAC;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RBACTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test permissions
        Permission::create(['name' => 'view roles']);
        Permission::create(['name' => 'create roles']);
        Permission::create(['name' => 'edit roles']);
        Permission::create(['name' => 'delete roles']);
        Permission::create(['name' => 'view permissions']);
        Permission::create(['name' => 'create permissions']);
        Permission::create(['name' => 'edit permissions']);
        Permission::create(['name' => 'delete permissions']);
        Permission::create(['name' => 'assign user roles']);
        Permission::create(['name' => 'remove user roles']);
        Permission::create(['name' => 'view user roles']);
        Permission::create(['name' => 'view user permissions']);
        Permission::create(['name' => 'assign user permissions']);
        Permission::create(['name' => 'remove user permissions']);
        
        // Create test roles
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);
        
        $adminRole->givePermissionTo([
            'view roles', 'create roles', 'edit roles', 'delete roles',
            'view permissions', 'create permissions', 'edit permissions', 'delete permissions',
            'assign user roles', 'remove user roles', 'view user roles',
            'view user permissions', 'assign user permissions', 'remove user permissions'
        ]);
        $userRole->givePermissionTo(['view roles']);
    }

    public function test_admin_can_view_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/rbac/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'roles' => [
                        '*' => ['id', 'name', 'permissions']
                    ]
                ],
                'timestamp'
            ]);
    }

    public function test_user_cannot_create_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/rbac/roles', [
            'name' => 'new-role',
            'description' => 'A new test role'
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient permissions. Required permission(s): create roles'
            ]);
    }

    public function test_admin_can_create_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/rbac/roles', [
            'name' => 'new-role',
            'description' => 'A new test role'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'role' => ['id', 'name']
                ],
                'timestamp'
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'new-role'
        ]);
    }

    public function test_admin_can_assign_role_to_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $adminToken = $admin->createToken('test-token')->plainTextToken;

        $targetUser = User::factory()->create();
        $role = Role::findByName('user');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $adminToken,
        ])->postJson("/api/v1/rbac/users/{$targetUser->id}/roles", [
            'role_name' => 'user'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Role assigned successfully'
            ]);

        $this->assertTrue($targetUser->hasRole('user'));
    }

    public function test_user_can_check_own_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/rbac/user/current-permissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'roles',
                    'permissions'
                ],
                'timestamp'
            ]);
    }

    public function test_user_can_check_specific_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/rbac/user/can/view roles');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'permission' => 'view roles',
                    'has_permission' => true
                ]
            ]);
    }

    public function test_unauthenticated_user_cannot_access_rbac_routes(): void
    {
        $response = $this->getJson('/api/v1/rbac/roles');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated'
            ]);
    }

    public function test_admin_can_view_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/rbac/permissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'permissions' => [
                        '*' => ['id', 'name']
                    ]
                ],
                'timestamp'
            ]);
    }
}