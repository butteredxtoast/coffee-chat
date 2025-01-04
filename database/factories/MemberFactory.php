<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'slack_id' => $this->faker->unique()->regexify('U[A-Z0-9]{8}'),
            'slack_handle' => $this->faker->unique()->userName,
            'is_active' => true,
            'preferred_contact_method' => $this->faker->randomElement(['slack', 'email']),
            'notes' => $this->faker->optional()->sentence
        ];
    }
}
