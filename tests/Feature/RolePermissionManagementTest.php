<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_role_with_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'administrateur', 'guard_name' => 'web']));
        $permission = Permission::create(['name' => 'voir demandes', 'guard_name' => 'web']);

        $response = $this->actingAs($user)->post(route('roles-permissions.store'), [
            'name' => 'coordinateur',
            'permissions' => [$permission->id],
        ]);

        $response->assertRedirect(route('roles-permissions.index'));
        $this->assertDatabaseHas('roles', ['name' => 'coordinateur']);
        $this->assertTrue(Role::findByName('coordinateur')->hasPermissionTo('voir demandes'));
    }
}