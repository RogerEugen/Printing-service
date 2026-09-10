<?php

namespace Database\Seeders;

use App\Enums\PrinterStatus;
use App\Models\Printer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DevelopmentPrinterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Printer::query()->where('device_name', 'OFFICE-PRINTER-01')->exists()) {
            return;
        }

        $plainToken = Str::random(80);
        Printer::create([
            'name' => 'Main Office Printer',
            'device_name' => 'OFFICE-PRINTER-01',
            'location' => 'Main Office',
            'api_token_hash' => hash('sha256', $plainToken),
            'status' => PrinterStatus::Offline,
        ]);

        $this->command?->warn('Development printer token (shown once): '.$plainToken);
    }
}
