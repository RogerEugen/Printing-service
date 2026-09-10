<?php

namespace App\Models;

use Database\Factories\PrintJobLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['print_job_id', 'event', 'message'])]
class PrintJobLog extends Model
{
    /** @use HasFactory<PrintJobLogFactory> */
    use HasFactory;

    public function printJob(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class);
    }
}
