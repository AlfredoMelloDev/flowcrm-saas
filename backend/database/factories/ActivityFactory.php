<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // user_id is required (not nullable) — defaulted the same way
            // OpportunityFactory defaults client_id, since Activity has no
            // automatic assignment mechanism the way BelongsToCompany
            // handles company_id.
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'type' => fake()->randomElement(ActivityType::cases()),
            'description' => fake()->optional()->sentence(),
            'scheduled_at' => fake()->dateTimeBetween('-1 week', '+2 weeks'),
        ];
    }
}
