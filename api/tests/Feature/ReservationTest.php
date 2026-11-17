<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_reserve_tickets(): void
    {
        $ticket = Ticket::factory()->create(['quantity' => 5]);

        $this->postJson('/api/reservations', [
            'ticket_id' => $ticket->id,
            'quantity' => 1,
        ], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertUnauthorized();
    }

    public function test_a_reservation_requires_an_idempotency_key(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['quantity' => 5]);

        $this->withToken($user->createToken('api')->plainTextToken)
            ->postJson('/api/reservations', [
                'ticket_id' => $ticket->id,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');
    }

    public function test_a_user_can_reserve_tickets(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['quantity' => 5]);

        $response = $this->reserve($user, $ticket, 2);

        $response->assertCreated()
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.ticket.id', $ticket->id);

        $this->assertSame(2, (int) $ticket->reservations()->sum('quantity'));
        $this->assertSame(1, $user->reservations()->count());
    }

    public function test_the_same_idempotency_key_does_not_reserve_twice(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['quantity' => 5]);
        $key = (string) Str::uuid();

        $first = $this->reserve($user, $ticket, 2, $key)->assertCreated();
        $second = $this->reserve($user, $ticket, 2, $key)->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, $user->reservations()->count());
        $this->assertSame(2, (int) $ticket->reservations()->sum('quantity'));
    }

    public function test_a_new_key_can_reserve_the_same_ticket_again(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['quantity' => 5]);

        $this->reserve($user, $ticket, 1)->assertCreated();
        $this->reserve($user, $ticket, 1)->assertCreated();

        $this->assertSame(2, $user->reservations()->count());
        $this->assertSame(2, (int) $ticket->reservations()->sum('quantity'));
    }

    public function test_reusing_a_key_for_a_different_quantity_is_rejected(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['quantity' => 5]);
        $key = (string) Str::uuid();

        $this->reserve($user, $ticket, 1, $key)->assertCreated();

        $this->reserve($user, $ticket, 2, $key)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');

        $this->assertSame(1, (int) $ticket->reservations()->sum('quantity'));
    }

    public function test_a_reservation_cannot_oversell_a_ticket(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['quantity' => 5]);

        $this->reserve($user, $ticket, 4)->assertCreated();

        $this->reserve($user, $ticket, 2)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertSame(4, (int) $ticket->reservations()->sum('quantity'));
    }

    public function test_the_owner_can_cancel_and_another_user_cannot(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ticket = Ticket::factory()->create(['quantity' => 5]);

        $reservationId = $this->reserve($owner, $ticket, 2)
            ->assertCreated()
            ->json('data.id');

        $this->app['auth']->forgetGuards();

        $this->withToken($other->createToken('api')->plainTextToken)
            ->deleteJson("/api/reservations/{$reservationId}")
            ->assertForbidden();

        $this->assertNotNull($ticket->reservations()->find($reservationId));

        $this->app['auth']->forgetGuards();

        $this->withToken($owner->createToken('api')->plainTextToken)
            ->deleteJson("/api/reservations/{$reservationId}")
            ->assertNoContent();

        $this->assertSame(0, (int) $ticket->reservations()->sum('quantity'));

        $this->reserve($owner, $ticket, 5)->assertCreated();
    }

    private function reserve(User $user, Ticket $ticket, int $quantity, ?string $key = null): TestResponse
    {
        return $this->withToken($user->createToken('api')->plainTextToken)
            ->postJson('/api/reservations', [
                'ticket_id' => $ticket->id,
                'quantity' => $quantity,
            ], [
                'Idempotency-Key' => $key ?? (string) Str::uuid(),
            ]);
    }
}
