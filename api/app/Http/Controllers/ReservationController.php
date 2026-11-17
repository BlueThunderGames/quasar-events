<?php

namespace App\Http\Controllers;

use App\Actions\CreateReservation;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Jobs\SendReservationConfirmation;
use App\Models\Reservation;
use App\Observability\EventLog;
use App\Observability\Trace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ReservationController extends Controller
{
    public function mine(Request $request): AnonymousResourceCollection
    {
        $reservations = $request->user()
            ->reservations()
            ->with(['ticket.event'])
            ->latest()
            ->paginate(20);

        return ReservationResource::collection($reservations);
    }

    public function store(StoreReservationRequest $request, CreateReservation $create, Trace $trace): JsonResponse
    {
        $created = false;

        $reservation = $trace->span('reservation.create', function () use ($request, $create, &$created) {
            $reservation = $create->handle(
                $request->user(),
                $request->integer('ticket_id'),
                $request->integer('quantity'),
                $request->string('idempotency_key')->toString(),
            );

            $created = $reservation->wasRecentlyCreated;
            $context = [
                'user_id' => $reservation->user_id,
                'reservation_id' => $reservation->id,
                'ticket_id' => $reservation->ticket_id,
                'quantity' => $reservation->quantity,
                'idempotency_key' => $reservation->idempotency_key,
            ];

            if ($created) {
                EventLog::info('reservation.created', $context);
            } else {
                EventLog::info('reservation.replayed', $context);
            }

            if ($reservation->confirmation_queued_at === null) {
                SendReservationConfirmation::dispatch($reservation)->afterCommit();
                $reservation->forceFill(['confirmation_queued_at' => now()])->save();
            }

            return $reservation;
        });

        $reservation->load(['ticket.event', 'user']);

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    public function destroy(Reservation $reservation, Trace $trace): Response
    {
        $trace->span('reservation.cancel', function () use ($reservation) {
            Gate::authorize('delete', $reservation);

            $context = [
                'user_id' => $reservation->user_id,
                'reservation_id' => $reservation->id,
                'ticket_id' => $reservation->ticket_id,
                'quantity' => $reservation->quantity,
            ];

            $reservation->delete();

            EventLog::info('reservation.cancelled', $context);
        });

        return response()->noContent();
    }
}
