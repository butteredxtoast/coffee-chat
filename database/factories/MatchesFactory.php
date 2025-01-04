<?php

namespace Database\Factories;

use App\Models\Matches;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatchesFactory extends Factory
{
    protected $model = Matches::class;

    public function definition(): array
    {
        return [
            'member1_id' => Member::factory(),
            'member2_id' => Member::factory(),
            'member3_id' => null,
            'matched_at' => now(),
            'met' => false,
            'is_current' => true,
            'met_confirmed_at' => null
        ];
    }
}
