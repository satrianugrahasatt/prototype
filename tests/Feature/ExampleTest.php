<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmployeesControllerTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_basic_test()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    use RefreshDatabase;

    /**
     * Test successful user registration with email verification.
     */
    public function test_user_can_register_successfully()
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/email/verify');
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
            'email_verified_at' => null,
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * Test registration fails with invalid data.
     */
    public function test_registration_fails_with_invalid_data()
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertDatabaseMissing('users', [
            'email' => 'invalid-email',
        ]);
    }

    /**
     * Test registration fails with duplicate email.
     */
    public function test_registration_fails_with_duplicate_email()
    {
        User::create([
            'name' => 'Existing User',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /**
     * Test registration fails with missing required fields.
     */
    public function test_registration_fails_with_missing_fields()
    {
        $response = $this->post('/register', []);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Test successful login with valid credentials and verified email.
     */
    public function test_user_can_login_with_valid_credentials_and_verified_email()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(RouteServiceProvider::HOME);
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Test login fails with unverified email.
     */
    public function test_login_fails_with_unverified_email()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $response = $this->post('/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/email/verify');
        $this->assertGuest();
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
            'email_verified_at' => now(),
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
            'email_verified_at' => now(),
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

    /**
     * Test successful email verification.
     */
    public function test_user_can_verify_email()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => null,
        ]);

        $this->actingAs($user);
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->get($verificationUrl);

        $response->assertRedirect(RouteServiceProvider::HOME);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    /**
     * Test email verification fails with invalid signed URL.
     */
    public function test_email_verification_fails_with_invalid_signature()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => null,
        ]);

        $this->actingAs($user);
        $invalidUrl = route('verification.verify', [
            'id' => $user->id,
            'hash' => 'invalid-hash',
        ]);

        $response = $this->get($invalidUrl);

        $response->assertStatus(403);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    /**
     * Test email verification fails for unauthenticated user.
     */
    public function test_email_verification_fails_for_unauthenticated_user()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->get($verificationUrl);

        $response->assertRedirect('/login');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    /**
     * Test resending verification email.
     */
    public function test_user_can_resend_verification_email()
    {
        Notification::fake();

        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => null,
        ]);

        $this->actingAs($user);
        $response = $this->post('/email/resend');

        $response->assertSessionHas('status', 'Verification link sent!');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * Test resend verification email fails for verified user.
     */
    public function test_resend_verification_fails_for_verified_user()
    {
        Notification::fake();

        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);
        $response = $this->post('/email/resend');

        $response->assertRedirect(RouteServiceProvider::HOME);
        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    /**
     * Test rate-limiting on resend verification email.
     */
    public function test_resend_verification_email_is_rate_limited()
    {
        Notification::fake();

        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => null,
        ]);

        $this->actingAs($user);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/email/resend');
        }

        $response = $this->post('/email/resend');
        $response->assertStatus(429); // Too Many Requests
    }

    /**
     * Test rate-limiting on email verification attempts.
     */
    public function test_email_verification_is_rate_limited()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => null,
        ]);

        $this->actingAs($user);

        for ($i = 0; $i < 6; $i++) {
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                ['id' => $user->id, 'hash' => sha1($user->email)]
            );
            $this->get($verificationUrl);
        }

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
        $response = $this->get($verificationUrl);

        $response->assertStatus(429); // Too Many Requests
    }
}
