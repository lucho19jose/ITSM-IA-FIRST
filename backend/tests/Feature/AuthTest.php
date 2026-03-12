<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class AuthTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    public function test_register_creates_tenant_and_user(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'email' => 'new@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'New Company SAC',
            'ruc' => '20123456789',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role', 'tenant'],
                'token',
            ]);

        $this->assertDatabaseHas('tenants', ['name' => 'New Company SAC', 'slug' => 'new-company-sac']);
        $this->assertDatabaseHas('users', ['email' => 'new@test.com', 'role' => 'admin']);
    }

    public function test_register_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'company_name']);
    }

    public function test_login_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.email', 'admin@test.com');
    }

    public function test_logout_works(): void
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/v1/auth/logout');

        $response->assertOk();
    }
}
