<?php

namespace App\Models;

use App\Enums\PrintJobStatus;
use Database\Factories\PrintJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'printer_id', 'original_name', 'mime_type', 'file_extension', 'file_path', 'file_size', 'copies', 'status',
    'claimed_at', 'printing_at', 'printed_at', 'failed_at', 'error_message',
])]
class PrintJob extends Model
{
    /** @use HasFactory<PrintJobFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => PrintJobStatus::Pending,
        'copies' => 1,
    ];

    protected function casts(): array
    {
        return [
            'status' => PrintJobStatus::class,
            'claimed_at' => 'datetime',
            'printing_at' => 'datetime',
            'printed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PrintJobLog::class);
    }

    public function addLog(string $event, ?string $message = null): PrintJobLog
    {
        return $this->logs()->create(['event' => $event, 'message' => $message]);
    }
}
