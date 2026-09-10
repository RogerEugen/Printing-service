<?php

namespace Database\Factories;

use App\Models\PrintJob;
use App\Models\PrintJobLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintJobLog>
 */
class PrintJobLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'print_job_id' => PrintJob::factory(),
            'event' => 'job_created',
            'message' => fake()->sentence(),
        ];
    }
}
