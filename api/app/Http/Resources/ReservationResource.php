<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'ticket' => $this->whenLoaded('ticket', function () {
                $ticket = [
                    'id' => $this->ticket->id,
                    'name' => $this->ticket->name,
                ];

                if ($this->ticket->relationLoaded('event')) {
                    $ticket['event'] = [
                        'id' => $this->ticket->event->id,
                        'title' => $this->ticket->event->title,
                    ];
                }

                return $ticket;
            }),
        ];
    }
}
