<?php

namespace App\Jobs;

use App\Mail\ReservationConfirmed;
use App\Models\Reservation;
use App\Observability\EventLog;
use App\Observability\Trace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendReservationConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Reservation $reservation) {}

    public function handle(Trace $trace): void
    {
        $trace->span('reservation.confirmation', function () {
            $this->reservation->loadMissing(['user', 'ticket.event']);

            Mail::to($this->reservation->user)->send(new ReservationConfirmed($this->reservation));

            EventLog::info('reservation.confirmation_sent', [
                'user_id' => $this->reservation->user_id,
                'reservation_id' => $this->reservation->id,
                'ticket_id' => $this->reservation->ticket_id,
                'quantity' => $this->reservation->quantity,
            ]);
        });
    }

    public function failed(): void
    {
        Reservation::query()
            ->whereKey($this->reservation->id)
            ->update(['confirmation_queued_at' => null]);
    }
}
