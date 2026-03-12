<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class TicketCrudTest extends TestCase
{
    use RefreshDatabase, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    public function test_admin_can_list_all_tickets(): void
    {
        Ticket::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'requester_id' => $this->endUser->id,
        ]);

        $response = $this->actingAsAdmin()
            ->getJson('/api/v1/tickets');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_end_user_only_sees_own_tickets(): void
    {
        Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'requester_id' => $this->endUser->id,
        ]);
        Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'requester_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAsEndUser()
            ->getJson('/api/v1/tickets');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_create_ticket(): void
    {
        $category = Category::create([
            'name' => 'Test Cat',
            'slug' => 'test-cat',
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAsEndUser()
            ->postJson('/api/v1/tickets', [
                'title' => 'Mi computadora no prende',
                'description' => 'Al presionar el botón de encendido no pasa nada.',
                'type' => 'incident',
                'priority' => 'high',
                'category_id' => $category->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Mi computadora no prende')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'high');

        $this->assertDatabaseHas('tickets', [
            'title' => 'Mi computadora no prende',
            'requester_id' => $this->endUser->id,
        ]);
    }

    public function test_create_ticket_auto_assigns_sla(): void
    {
        SlaPolicy::create([
            'name' => 'High SLA',
            'priority' => 'high',
            'response_time' => 60,
            'resolution_time' => 480,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAsEndUser()
            ->postJson('/api/v1/tickets', [
                'title' => 'Urgent issue',
                'description' => 'Need help ASAP',
                'type' => 'incident',
                'priority' => 'high',
            ]);

        $response->assertStatus(201);

        $ticket = Ticket::first();
        $this->assertNotNull($ticket->sla_policy_id);
        $this->assertNotNull($ticket->response_due_at);
        $this->assertNotNull($ticket->resolution_due_at);
    }

    public function test_show_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'requester_id' => $this->endUser->id,
        ]);

        $response = $this->actingAsEndUser()
            ->getJson("/api/v1/tickets/{$ticket->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $ticket->id);
    }

    public function test_end_user_cannot_view_others_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'requester_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAsEndUser()
            ->getJson("/api/v1/tickets/{$ticket->id}");

        $response->assertStatus(403);
    }

    public function test_update_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'requester_id' => $this->endUser->id,
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/v1/tickets/{$ticket->id}", [
                'status' => 'in_progress',
                'priority' => 'urgent',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.priority', 'urgent');
    }

    public function test_assign_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'requester_id' => $this->endUser->id,
        ]);

        $response = $this->actingAsAdmin()
            ->postJson("/api/v1/tickets/{$ticket->id}/assign", [
                'assigned_to' => $this->agentUser->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.assigned_to', $this->agentUser->id);
    }

    public function test_close_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'requester_id' => $this->endUser->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAsAdmin()
            ->postJson("/api/v1/tickets/{$ticket->id}/close");

        $response->assertOk()
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_add_comment(): void
    {
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'requester_id' => $this->endUser->id,
        ]);

        $response = $this->actingAsEndUser()
            ->postJson("/api/v1/tickets/{$ticket->id}/comments", [
                'body' => 'Necesito ayuda urgente',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.body', 'Necesito ayuda urgente');
    }

    public function test_end_user_cannot_create_internal_notes(): void
    {
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'requester_id' => $this->endUser->id,
        ]);

        $response = $this->actingAsEndUser()
            ->postJson("/api/v1/tickets/{$ticket->id}/comments", [
                'body' => 'Should not be internal',
                'is_internal' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_internal', false);
    }

    public function test_delete_ticket_only_admin(): void
    {
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'requester_id' => $this->endUser->id,
        ]);

        $this->actingAsEndUser()
            ->deleteJson("/api/v1/tickets/{$ticket->id}")
            ->assertStatus(403);

        $this->actingAsAdmin()
            ->deleteJson("/api/v1/tickets/{$ticket->id}")
            ->assertOk();
    }

    public function test_filter_tickets_by_status(): void
    {
        Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'requester_id' => $this->endUser->id,
            'status' => 'open',
        ]);
        Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'requester_id' => $this->endUser->id,
            'status' => 'closed',
        ]);

        $response = $this->actingAsAdmin()
            ->getJson('/api/v1/tickets?status=open');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
