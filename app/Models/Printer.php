<?php

namespace App\Models;

use App\Enums\PrinterStatus;
use Database\Factories\PrinterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'device_name', 'location', 'api_token_hash', 'status', 'last_seen_at'])]
#[Hidden(['api_token_hash'])]
class Printer extends Model
{
    /** @use HasFactory<PrinterFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PrinterStatus::class,
            'last_seen_at' => 'datetime',
        ];
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function displayedStatus(): PrinterStatus
    {
        if ($this->status === PrinterStatus::Disabled) {
            return PrinterStatus::Disabled;
        }

        return $this->last_seen_at?->gte(now()->subSeconds((int) config('printing.offline_after_seconds')))
            ? PrinterStatus::Online
            : PrinterStatus::Offline;
    }
}
