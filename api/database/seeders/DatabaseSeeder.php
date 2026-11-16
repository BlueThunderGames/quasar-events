<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = User::factory()->count(100)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $ticketNames = [
            'General admission',
            'VIP',
            'Early bird',
            'Student',
            'Balcony',
        ];

        $events = Event::factory()
            ->count(fake()->numberBetween(100, 200))
            ->create();

        $soonest = $events->sortBy('starts_at')->first();
        $startsAt = now()->addHours(2);
        $soonest->update([
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(3),
        ]);

        $events->each(function (Event $event) use ($users, $ticketNames) {
                $names = fake()->randomElements($ticketNames, fake()->numberBetween(1, 3));

                $tickets = collect($names)->map(fn (string $name) => Ticket::factory()->create([
                    'event_id' => $event->id,
                    'name' => $name,
                ]));

                $tickets->each(function (Ticket $ticket) use ($users) {
                    $remaining = $ticket->quantity;
                    $reservationCount = fake()->numberBetween(0, min(12, $remaining));

                    for ($made = 0; $made < $reservationCount && $remaining > 0; $made++) {
                        $quantity = fake()->numberBetween(1, min(3, $remaining));

                        Reservation::factory()->create([
                            'user_id' => $users->random()->id,
                            'ticket_id' => $ticket->id,
                            'quantity' => $quantity,
                        ]);

                        $remaining -= $quantity;
                    }
                });
            });
    }
}
