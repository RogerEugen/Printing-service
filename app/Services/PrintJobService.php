<?php

namespace App\Services;

use App\Enums\PrintJobStatus;
use App\Models\Printer;
use App\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrintJobService
{
    public function claimNext(Printer $printer): ?PrintJob
    {
        return DB::transaction(function () use ($printer): ?PrintJob {
            $printJob = PrintJob::query()
                ->whereBelongsTo($printer)
                ->where('status', PrintJobStatus::Pending)
                ->oldest('created_at')
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if (! $printJob) {
                return null;
            }

            return $this->markClaimed($printJob, $printer);
        });
    }

    public function claim(Printer $printer, PrintJob $printJob): PrintJob
    {
        return DB::transaction(function () use ($printer, $printJob): PrintJob {
            $lockedJob = PrintJob::query()
                ->whereKey($printJob->id)
                ->whereBelongsTo($printer)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedJob->status === PrintJobStatus::Claimed) {
                return $lockedJob;
            }

            if ($lockedJob->status !== PrintJobStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'This print job can no longer be claimed.',
                ]);
            }

            return $this->markClaimed($lockedJob, $printer);
        });
    }

    /** @param list<PrintJobStatus> $fromStatuses */
    public function transition(
        Printer $printer,
        PrintJob $printJob,
        array $fromStatuses,
        PrintJobStatus $toStatus,
        string $timestampField,
        string $event,
        string $message,
        ?string $errorMessage = null,
    ): PrintJob {
        return DB::transaction(function () use ($printer, $printJob, $fromStatuses, $toStatus, $timestampField, $event, $message, $errorMessage): PrintJob {
            $lockedJob = PrintJob::query()
                ->whereKey($printJob->id)
                ->whereBelongsTo($printer)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedJob->status === $toStatus) {
                return $lockedJob;
            }

            abort_unless(in_array($lockedJob->status, $fromStatuses, true), 409, 'Invalid status transition.');

            $updates = [
                'status' => $toStatus,
                $timestampField => now(),
            ];

            if ($toStatus === PrintJobStatus::Failed) {
                $updates['error_message'] = $errorMessage;
            } elseif ($toStatus === PrintJobStatus::Printed) {
                $updates['error_message'] = null;
            }

            $lockedJob->update($updates);
            $lockedJob->addLog($event, $message);

            return $lockedJob->fresh();
        });
    }

    private function markClaimed(PrintJob $printJob, Printer $printer): PrintJob
    {
        $printJob->update([
            'status' => PrintJobStatus::Claimed,
            'claimed_at' => now(),
            'error_message' => null,
        ]);
        $printJob->addLog('job_claimed', "Claimed by printer {$printer->name}.");

        return $printJob->fresh(['user', 'printer']);
    }
}
