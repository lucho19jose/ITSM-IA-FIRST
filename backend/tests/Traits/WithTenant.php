<?php
namespace Tests\Traits;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

trait WithTenant
{
    protected Tenant $tenant;
    protected User $adminUser;
    protected User $agentUser;
    protected User $endUser;

    protected function setUpTenant(): void
    {
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'plan' => 'professional',
            'is_active' => true,
        ]);

        app()->instance('tenant_id', $this->tenant->id);

        // Create Passport personal access client for testing (needed for login/register token creation)
        $clientRepository = app(ClientRepository::class);
        $clientRepository->createPersonalAccessGrantClient('Test Personal Access Client');

        $this->adminUser = User::withoutGlobalScopes()->create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->agentUser = User::withoutGlobalScopes()->create([
            'name' => 'Agent Test',
            'email' => 'agent@test.com',
            'password' => Hash::make('password'),
            'role' => 'agent',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->endUser = User::withoutGlobalScopes()->create([
            'name' => 'End User Test',
            'email' => 'user@test.com',
            'password' => Hash::make('password'),
            'role' => 'end_user',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function actingAsAdmin()
    {
        Passport::actingAs($this->adminUser);
        return $this;
    }

    protected function actingAsAgent()
    {
        Passport::actingAs($this->agentUser);
        return $this;
    }

    protected function actingAsEndUser()
    {
        Passport::actingAs($this->endUser);
        return $this;
    }
}
