<?php

namespace Tests\Feature;

use App\Jobs\SendReservationConfirmation;
use App\Mail\ReservationConfirmed;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\User;
use ArrayObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
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

    public function test_a_new_reservation_queues_one_confirmation_email(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['quantity' => 5]);
        $key = (string) Str::uuid();

        $this->reserve($user, $ticket, 1, $key)->assertCreated();
        $this->reserve($user, $ticket, 1, $key)->assertOk();

        Queue::assertPushed(SendReservationConfirmation::class, 1);
    }

    public function test_the_confirmation_email_goes_to_the_user(): void
    {
        Mail::fake();
        Queue::fake();

        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['quantity' => 5]);

        $reservationId = $this->reserve($user, $ticket, 1)
            ->assertCreated()
            ->json('data.id');

        $reservation = Reservation::query()->findOrFail($reservationId);
        $this->app->call([new SendReservationConfirmation($reservation), 'handle']);

        Mail::assertSent(ReservationConfirmed::class, function (ReservationConfirmed $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
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

    public function test_reservation_logs_share_one_trace(): void
    {
        $events = $this->captureLogs();

        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['quantity' => 5]);
        $incoming = '00-'.str_repeat('ab', 16).'-'.str_repeat('cd', 8).'-01';

        $created = $this->withToken($user->createToken('api')->plainTextToken)
            ->postJson('/api/reservations', [
                'ticket_id' => $ticket->id,
                'quantity' => 2,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
                'traceparent' => $incoming,
            ])
            ->assertCreated();

        $created->assertHeader('traceparent');
        $this->assertStringStartsWith('00-'.str_repeat('ab', 16).'-', (string) $created->headers->get('traceparent'));

        $traceId = str_repeat('ab', 16);
        $createdEvent = $this->logged($events, 'reservation.created');
        $confirmation = $this->logged($events, 'reservation.confirmation_sent');
        $createSpan = $this->span($events, 'reservation.create');
        $requestSpan = $this->span($events, 'http.request');

        $this->assertSame($traceId, $createdEvent['trace_id']);
        $this->assertSame($traceId, $confirmation['trace_id']);
        $this->assertSame($createSpan['span_id'], $createdEvent['span_id']);
        $this->assertSame($createSpan['span_id'], $confirmation['parent_span_id']);
        $this->assertSame($requestSpan['span_id'], $createSpan['parent_span_id']);
        $this->assertSame(str_repeat('cd', 8), $requestSpan['parent_span_id']);
        $this->assertSame('ok', $createSpan['status']);
        $this->assertArrayNotHasKey('email', $createdEvent);

        $events = $this->captureLogs();
        $this->withToken($user->createToken('api')->plainTextToken)
            ->postJson('/api/reservations', [
                'ticket_id' => $ticket->id,
                'quantity' => 4,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ])
            ->assertUnprocessable();

        $rejected = $this->logged($events, 'reservation.rejected');
        $this->assertSame('warning', $rejected['level']);
        $this->assertSame('insufficient_stock', $rejected['context']['reason']);
        $this->assertSame('error', $this->span($events, 'reservation.create')['status']);
    }

    public function test_a_closed_event_cannot_be_reserved(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'quantity' => 5,
            'event_id' => Event::factory()->create([
                'starts_at' => now()->subDays(2),
                'ends_at' => now()->subDay(),
            ])->id,
        ]);

        $this->reserve($user, $ticket, 1)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ticket_id');

        $this->assertSame(0, (int) $ticket->reservations()->sum('quantity'));
    }

    public function test_an_event_in_progress_can_still_be_reserved(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'quantity' => 5,
            'event_id' => Event::factory()->create([
                'starts_at' => now()->subHour(),
                'ends_at' => now()->addHour(),
            ])->id,
        ]);

        $this->reserve($user, $ticket, 1)->assertCreated();
    }

    public function test_a_replay_queues_the_confirmation_when_it_never_queued(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['quantity' => 5]);
        $key = (string) Str::uuid();

        $reservationId = $this->reserve($user, $ticket, 1, $key)
            ->assertCreated()
            ->json('data.id');

        Reservation::query()->whereKey($reservationId)->update(['confirmation_queued_at' => null]);
        Queue::fake();

        $this->reserve($user, $ticket, 1, $key)->assertOk();

        Queue::assertPushed(SendReservationConfirmation::class, 1);
    }

    public function test_the_confirmation_worker_keeps_the_request_trace(): void
    {
        config(['queue.default' => 'database']);

        $events = $this->captureLogs();
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['quantity' => 5]);

        $response = $this->reserve($user, $ticket, 1)->assertCreated();
        $traceId = explode('-', (string) $response->headers->get('traceparent'))[1];
        $createSpanId = $this->span($events, 'reservation.create')['span_id'];

        Context::flush();

        $this->artisan('queue:work', ['--once' => true])->assertSuccessful();

        $confirmation = $this->logged($events, 'reservation.confirmation_sent');

        $this->assertSame($traceId, $confirmation['trace_id']);
        $this->assertSame($createSpanId, $confirmation['parent_span_id']);
    }

    private function captureLogs(): ArrayObject
    {
        $events = new ArrayObject;
        Log::listen(function (MessageLogged $event) use ($events) {
            $events->append($event);
        });

        return $events;
    }

    /**
     * @return array<string, mixed>
     */
    private function logged(ArrayObject $events, string $message): array
    {
        $matches = array_values(array_filter(
            $events->getArrayCopy(),
            fn (MessageLogged $event) => $event->message === $message,
        ));

        $this->assertCount(1, $matches);

        return ['level' => $matches[0]->level, 'context' => $matches[0]->context, ...$matches[0]->context];
    }

    /**
     * @return array<string, mixed>
     */
    private function span(ArrayObject $events, string $name): array
    {
        $matches = array_values(array_filter(
            $events->getArrayCopy(),
            fn (MessageLogged $event) => $event->message === 'span' && ($event->context['name'] ?? null) === $name,
        ));

        $this->assertCount(1, $matches);

        return $matches[0]->context;
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
