<?php

namespace Database\Factories;

use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;

class TodoItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'meeting_id'  => Meeting::factory(),
            'assigned_to' => null,
            'title'       => fake()->sentence(5),
            'description' => fake()->sentence(),
            'status'      => 'pending',
            'due_date'    => null,
        ];
    }
}
