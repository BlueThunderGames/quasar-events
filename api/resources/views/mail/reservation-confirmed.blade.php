Your reservation is confirmed.

{{ $reservation->quantity }} × {{ $reservation->ticket->name }}
{{ $reservation->ticket->event->title }}
{{ $reservation->ticket->event->starts_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
{{ $reservation->ticket->event->location }}
