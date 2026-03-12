<?php
namespace Database\Factories;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(['incident', 'request', 'problem', 'change']),
            'status' => fake()->randomElement(['open', 'in_progress', 'pending', 'resolved', 'closed']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
        ];
    }
}
