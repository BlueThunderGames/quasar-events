<?php

namespace App\Actions;

use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\User;
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
                $reserved = (int) $ticket->reservations()->sum('quantity');
                $remaining = $ticket->quantity - $reserved;

                if ($quantity > $remaining) {
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

        throw ValidationException::withMessages([
            'idempotency_key' => ['This idempotency key was already used for a different reservation.'],
        ]);
    }
}
