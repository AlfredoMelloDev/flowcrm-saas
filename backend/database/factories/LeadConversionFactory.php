<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadConversion;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadConversion>
 */
class LeadConversionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'client_id' => Client::factory(),
            'opportunity_id' => Opportunity::factory(),
            'converted_by' => User::factory(),
            'converted_at' => now(),
        ];
    }
}
