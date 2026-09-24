<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
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
            'document' => fake()->unique()->numerify('###########'),
            'type' => ClientType::Individual,
            'status' => ClientStatus::Active,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
