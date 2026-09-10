<?php

namespace Database\Factories;

use App\Enums\PrinterStatus;
use App\Models\Printer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Printer>
 */
class PrinterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Printer',
            'device_name' => 'PRINTER-'.fake()->unique()->numberBetween(100, 999),
            'location' => fake()->randomElement(['Reception', 'Accounts', 'Main Office']),
            'api_token_hash' => hash('sha256', Str::random(80)),
            'status' => PrinterStatus::Offline,
            'last_seen_at' => null,
        ];
    }
}
