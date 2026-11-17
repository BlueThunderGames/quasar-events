<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_list_events(): void
    {
        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
            ]);
    }

    public function test_event_details_report_remaining_tickets(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['quantity' => 5]);

        $this->withToken($user->createToken('api')->plainTextToken)
            ->postJson('/api/reservations', [
                'ticket_id' => $ticket->id,
                'quantity' => 2,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ])
            ->assertCreated();

        $this->getJson("/api/events/{$ticket->event_id}")
            ->assertOk()
            ->assertJsonPath('data.tickets.0.quantity', 5)
            ->assertJsonPath('data.tickets.0.reserved', 2)
            ->assertJsonPath('data.tickets.0.remaining', 3);
    }
}
