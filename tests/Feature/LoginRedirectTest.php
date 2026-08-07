<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_login_roles_redirect_to_valid_pages(): void
    {
        $this->makeUser('superadmin', 'superadmin', User::ROLE_SUPER_ADMIN, []);
        $this->makeUser('manager', 'manager', User::ROLE_MANAGER, ['1', '2', '6', '18']);
        $this->makeUser('cashier', 'cashier', User::ROLE_CASHIER, ['2', '3', '14', '15']);
        $this->makeUser('staff', 'staff', User::ROLE_CASHIER, ['2', '3']);

        $this->post(route('login.post'), ['username' => 'superadmin', 'password' => 'superadmin'])
            ->assertRedirect(route('dashboard'));
        $this->post(route('logout.post'));

        $this->post(route('login.post'), ['username' => 'manager', 'password' => 'manager'])
            ->assertRedirect(route('dashboard'));
        $this->post(route('logout.post'));

        $this->post(route('login.post'), ['username' => 'cashier', 'password' => 'cashier'])
            ->assertRedirect(route('pos.index'));
        $this->post(route('logout.post'));

        $this->post(route('login.post'), ['username' => 'staff', 'password' => 'staff'])
            ->assertRedirect(route('pos.index'));
    }

    public function test_user_without_valid_access_is_logged_out_instead_of_redirect_looping(): void
    {
        $this->makeUser('baduser', 'password', User::ROLE_MANAGER, []);

        $this->post(route('login.post'), ['username' => 'baduser', 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_access_middleware_redirects_to_allowed_page_or_returns_403(): void
    {
        $cashier = $this->makeUser('posonly', 'password', User::ROLE_CASHIER, ['2']);
        $locked = $this->makeUser('locked', 'password', User::ROLE_MANAGER, []);

        $this->actingAs($cashier)
            ->get(route('dashboard'))
            ->assertRedirect(route('pos.index'));

        $this->actingAs($locked)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    private function makeUser(string $username, string $password, string $role, array $access): User
    {
        return User::create([
            'fname' => ucfirst($username),
            'lname' => 'User',
            'username' => $username,
            'password' => Hash::make($password),
            'role' => $role,
            'branch_id' => null,
            'access' => $access,
            'pos_layout' => 'grid',
        ]);
    }
}
