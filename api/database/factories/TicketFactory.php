<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement([
                'General admission',
                'VIP',
                'Early bird',
                'Student',
                'Balcony',
            ]),
            'description' => fake()->sentence(),
            'quantity' => fake()->numberBetween(10, 40),
        ];
    }
}
