<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'team_id'          => null,
            'title'            => fake()->sentence(4),
            'description'      => fake()->sentence(),
            'audio_path'       => 'meetings/' . fake()->uuid() . '.mp3',
            'status'           => 'pending',
            'processing_stage' => null,
            'share_token'      => null,
            'duration_seconds' => null,
        ];
    }
}
