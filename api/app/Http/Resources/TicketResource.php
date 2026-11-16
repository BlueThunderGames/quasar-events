<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reserved = (int) ($this->reserved ?? 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'reserved' => $reserved,
            'remaining' => max($this->quantity - $reserved, 0),
            'reservations' => ReservationResource::collection($this->whenLoaded('reservations')),
        ];
    }
}
