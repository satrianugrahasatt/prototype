<?php

namespace Tests\Feature\Auth;

use App\Models\Access;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test role
        $role = Role::create(['name' => 'Test Role']);

        // Create a menu for dashboard
        $menu = Menu::create([
            'name' => 'dashboard',
            'is_active' => true,
        ]);

        // Grant access to the role for the dashboard menu
        Access::create([
            'menu_id' => $menu->id,
            'role_id' => $role->id,
            'status' => 1, // Allow access
        ]);

        // Store role for tests
        $this->role = $role;
    }

    /**
     * Test successful login with valid credentials.
     */
    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'role_id' => $this->role->id,
        ]);

        $response = $this->post('/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(RouteServiceProvider::HOME);
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Test login fails with inactive user.
     */
    public function test_login_fails_with_inactive_user()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'is_active' => false,
            'role_id' => $this->role->id,
        ]);

        $response = $this->post('/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    /**
     * Test login fails with invalid credentials.
     */
    public function test_login_fails_with_invalid_credentials()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'role_id' => $this->role->id,
        ]);

        $response = $this->post('/login', [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    /**
     * Test login fails with missing credentials.
     */
    public function test_login_fails_with_missing_credentials()
    {
        $response = $this->post('/login', []);

        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }
}
