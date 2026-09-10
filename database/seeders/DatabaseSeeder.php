<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $developmentPassword = config('printing.development_admin_password');

        if (is_string($developmentPassword) && $developmentPassword !== '') {
            User::query()->updateOrCreate(
                ['username' => 'admin'],
                ['password' => $developmentPassword, 'role' => UserRole::Admin, 'is_active' => true],
            );
        } else {
            $this->command?->warn('DEV_ADMIN_PASSWORD is empty; development admin was not created.');
        }

        $this->call(DevelopmentPrinterSeeder::class);
    }
}
