<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $events = Event::query()
            ->orderBy('starts_at')
            ->paginate(40);

        return EventResource::collection($events);
    }

    public function show(Event $event): EventResource
    {
        $event->load([
            'tickets' => fn (HasMany $tickets) => $tickets->withSum('reservations as reserved', 'quantity'),
            'reservations.user',
            'reservations.ticket',
        ]);

        return new EventResource($event);
    }
}
