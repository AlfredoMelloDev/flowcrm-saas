<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // client_id is required (not nullable) — defaulted the same way
            // UserFactory defaults company_id, since Opportunity has no
            // automatic assignment for it the way BelongsToCompany handles
            // company_id. Tests needing a specific client override this.
            'client_id' => Client::factory(),
            'title' => fake()->catchPhrase(),
            'value' => fake()->randomFloat(2, 100, 50000),
            'expected_close_date' => fake()->optional()->dateTimeBetween('now', '+3 months'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
