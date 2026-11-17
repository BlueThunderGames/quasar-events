<?php

namespace App\Http\Controllers;

use App\Actions\CreateReservation;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
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

    public function store(StoreReservationRequest $request, CreateReservation $create): JsonResponse
    {
        $reservation = $create->handle(
            $request->user(),
            $request->integer('ticket_id'),
            $request->integer('quantity'),
            $request->string('idempotency_key')->toString(),
        );

        $status = $reservation->wasRecentlyCreated ? 201 : 200;
        $reservation->load(['ticket.event', 'user']);

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode($status);
    }

    public function destroy(Reservation $reservation): Response
    {
        Gate::authorize('delete', $reservation);

        $reservation->delete();

        return response()->noContent();
    }
}
