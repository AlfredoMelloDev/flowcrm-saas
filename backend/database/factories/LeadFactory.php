<?php

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('###########'),
            'source' => fake()->randomElement(LeadSource::cases()),
            'status' => LeadStatus::New,
            'estimated_value' => fake()->randomFloat(2, 100, 50000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
