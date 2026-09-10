<?php

namespace Database\Factories;

use App\Enums\PrintJobStatus;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintJob>
 */
class PrintJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'printer_id' => Printer::factory(),
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_path' => 'print-jobs/test/'.fake()->uuid().'.pdf',
            'file_size' => fake()->numberBetween(1000, 1000000),
            'copies' => 1,
            'status' => PrintJobStatus::Pending,
        ];
    }
}
