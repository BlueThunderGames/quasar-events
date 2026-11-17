<?php

namespace App\Actions;

use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\User;
use App\Observability\EventLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateReservation
{
    public function handle(User $user, int $ticketId, int $quantity, string $idempotencyKey): Reservation
    {
        try {
            return DB::transaction(function () use ($user, $ticketId, $quantity, $idempotencyKey) {
                $existing = $this->findExisting($user, $idempotencyKey);

                if ($existing !== null) {
                    $this->assertSamePurchase($existing, $ticketId, $quantity);

                    return $existing;
                }

                $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticketId);
                $ticket->load('event');
                $closesAt = $ticket->event->ends_at ?? $ticket->event->starts_at;

                if ($closesAt->lte(now())) {
                    EventLog::warning('reservation.rejected', [
                        'reason' => 'event_closed',
                        'user_id' => $user->id,
                        'ticket_id' => $ticketId,
                        'quantity' => $quantity,
                        'idempotency_key' => $idempotencyKey,
                    ]);

                    throw ValidationException::withMessages([
                        'ticket_id' => ['This event is no longer open for reservations.'],
                    ]);
                }

                $reserved = (int) $ticket->reservations()->sum('quantity');
                $remaining = $ticket->quantity - $reserved;

                if ($quantity > $remaining) {
                    EventLog::warning('reservation.rejected', [
                        'reason' => 'insufficient_stock',
                        'user_id' => $user->id,
                        'ticket_id' => $ticketId,
                        'quantity' => $quantity,
                        'remaining' => $remaining,
                        'idempotency_key' => $idempotencyKey,
                    ]);

                    throw ValidationException::withMessages([
                        'quantity' => ["Only {$remaining} tickets remain."],
                    ]);
                }

                return $user->reservations()->create([
                    'ticket_id' => $ticket->id,
                    'quantity' => $quantity,
                    'idempotency_key' => $idempotencyKey,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $this->findExisting($user, $idempotencyKey);

            if ($existing !== null) {
                $this->assertSamePurchase($existing, $ticketId, $quantity);

                return $existing;
            }

            EventLog::warning('reservation.rejected', [
                'reason' => 'idempotency_mismatch',
                'user_id' => $user->id,
                'ticket_id' => $ticketId,
                'quantity' => $quantity,
                'idempotency_key' => $idempotencyKey,
            ]);

            throw ValidationException::withMessages([
                'idempotency_key' => ['This idempotency key was already used for a different reservation.'],
            ]);
        }
    }

    private function findExisting(User $user, string $idempotencyKey): ?Reservation
    {
        return Reservation::query()
            ->where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function assertSamePurchase(Reservation $existing, int $ticketId, int $quantity): void
    {
        if ($existing->ticket_id === $ticketId && $existing->quantity === $quantity) {
            return;
        }

        EventLog::warning('reservation.rejected', [
            'reason' => 'idempotency_mismatch',
            'user_id' => $existing->user_id,
            'ticket_id' => $ticketId,
            'quantity' => $quantity,
            'idempotency_key' => $existing->idempotency_key,
        ]);

        throw ValidationException::withMessages([
            'idempotency_key' => ['This idempotency key was already used for a different reservation.'],
        ]);
    }
}
