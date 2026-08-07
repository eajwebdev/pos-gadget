<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_is_hidden_from_users_index_and_role_options(): void
    {
        $branch = $this->makeBranch();
        $superAdmin = $this->makeUser('superadmin', User::ROLE_SUPER_ADMIN, $branch->id);
        $admin = $this->makeUser('admin', User::ROLE_ADMINISTRATOR, $branch->id);
        $manager = $this->makeUser('manager', User::ROLE_MANAGER, $branch->id);

        $this->actingAs($superAdmin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->missing('roles.super_admin')
                ->has('users', 2)
                ->where('users.0.username', $admin->username)
                ->where('users.1.username', $manager->username)
            );
    }

    public function test_super_admin_role_cannot_be_created_from_users_store(): void
    {
        $branch = $this->makeBranch();
        $superAdmin = $this->makeUser('superadmin', User::ROLE_SUPER_ADMIN, $branch->id);

        $this->actingAs($superAdmin)
            ->from(route('users.index'))
            ->post(route('users.store'), [
                'fname' => 'Bad',
                'lname' => 'Actor',
                'username' => 'bad-superadmin',
                'password' => 'password',
                'role' => User::ROLE_SUPER_ADMIN,
                'branch_id' => $branch->id,
                'access' => [],
                'pos_layout' => 'grid',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', [
            'username' => 'bad-superadmin',
        ]);
    }

    public function test_super_admin_account_cannot_be_edited_or_deleted_from_users_routes(): void
    {
        $branch = $this->makeBranch();
        $owner = $this->makeUser('owner', User::ROLE_SUPER_ADMIN, $branch->id);
        $otherSuperAdmin = $this->makeUser('protected', User::ROLE_SUPER_ADMIN, $branch->id);

        $this->actingAs($owner)
            ->patch(route('users.update', $otherSuperAdmin), [
                'fname' => 'Edited',
                'lname' => 'Owner',
                'username' => 'protected',
                'password' => '',
                'role' => User::ROLE_ADMINISTRATOR,
                'branch_id' => $branch->id,
                'access' => [],
                'pos_layout' => 'grid',
            ])
            ->assertForbidden();

        $this->assertSame(User::ROLE_SUPER_ADMIN, $otherSuperAdmin->fresh()->role);

        $this->actingAs($owner)
            ->delete(route('users.destroy', $otherSuperAdmin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $otherSuperAdmin->id,
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    public function test_admin_and_super_admin_cannot_access_pos_or_see_pos_sidebar_access(): void
    {
        $branch = $this->makeBranch();
        $superAdmin = $this->makeUser('superadmin', User::ROLE_SUPER_ADMIN, $branch->id);
        $admin = $this->makeUser('admin', User::ROLE_ADMINISTRATOR, $branch->id, ['1', User::MENU_POS]);

        $this->actingAs($superAdmin)
            ->get(route('pos.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->get(route('pos.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.access', fn ($access) => ! collect($access)->contains(User::MENU_POS))
            );

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.access', fn ($access) => ! collect($access)->contains(User::MENU_POS))
            );
    }

    public function test_pos_permission_is_stripped_when_creating_administrator(): void
    {
        $branch = $this->makeBranch();
        $superAdmin = $this->makeUser('superadmin', User::ROLE_SUPER_ADMIN, $branch->id);

        $this->actingAs($superAdmin)
            ->post(route('users.store'), [
                'fname' => 'Demo',
                'lname' => 'Admin',
                'username' => 'demo-admin',
                'password' => 'password',
                'role' => User::ROLE_ADMINISTRATOR,
                'branch_id' => $branch->id,
                'access' => ['1', User::MENU_POS, '23'],
                'pos_layout' => 'grid',
            ])
            ->assertSessionHasNoErrors();

        $created = User::where('username', 'demo-admin')->firstOrFail();

        $this->assertSame(User::ROLE_ADMINISTRATOR, $created->role);
        $this->assertContains('1', $created->access);
        $this->assertContains('23', $created->access);
        $this->assertNotContains(User::MENU_POS, $created->access);
    }

    private function makeBranch(): Branch
    {
        $supplier = Supplier::create(['name' => 'NizPhone Supplier']);

        return Branch::create([
            'supplier_id' => $supplier->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'business_type' => Branch::TYPE_STORE,
        ]);
    }

    private function makeUser(string $username, string $role, ?int $branchId, array $access = []): User
    {
        return User::create([
            'fname' => ucfirst($username),
            'lname' => 'User',
            'username' => $username,
            'password' => Hash::make('password'),
            'role' => $role,
            'branch_id' => $branchId,
            'access' => $access,
            'pos_layout' => 'grid',
        ]);
    }
}
