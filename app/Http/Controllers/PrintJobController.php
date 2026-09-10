<?php

namespace App\Http\Controllers;

use App\Enums\PrinterStatus;
use App\Enums\PrintJobStatus;
use App\Http\Requests\StorePrintJobRequest;
use App\Models\Printer;
use App\Models\PrintJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PrintJobController extends Controller
{
    public function index(Request $request): View
    {
        $printJobs = $request->user()->printJobs()
            ->with('printer:id,name')
            ->latest()
            ->paginate(15);

        return view('employee.print-jobs.index', compact('printJobs'));
    }

    public function create(): View
    {
        Gate::authorize('create', PrintJob::class);

        $printers = Printer::query()
            ->where('status', '!=', PrinterStatus::Disabled)
            ->orderBy('name')
            ->get(['id', 'name', 'location', 'status', 'last_seen_at']);

        return view('employee.print-jobs.create', compact('printers'));
    }

    public function store(StorePrintJobRequest $request): RedirectResponse
    {
        $file = $request->file('document');
        $extension = Str::lower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $safeOriginalName = Str::limit(
            preg_replace('/[^A-Za-z0-9._ -]/u', '_', basename($file->getClientOriginalName())) ?: 'document.'.$extension,
            200,
            '',
        );
        $filePath = $file->storeAs('print-jobs/'.now()->format('Y/m'), Str::uuid().'.'.$extension, 'local');

        abort_if($filePath === false, 500, 'The document could not be stored.');

        try {
            $printJob = DB::transaction(function () use ($request, $file, $filePath, $safeOriginalName, $mimeType, $extension): PrintJob {
                $printJob = $request->user()->printJobs()->create([
                    'printer_id' => $request->integer('printer_id'),
                    'original_name' => $safeOriginalName,
                    'mime_type' => $mimeType,
                    'file_extension' => $extension,
                    'file_path' => $filePath,
                    'file_size' => $file->getSize(),
                    'copies' => $request->integer('copies'),
                    'status' => PrintJobStatus::Pending,
                ]);
                $printJob->addLog('job_created', 'Document uploaded and queued for printing.');

                return $printJob;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($filePath);
            throw $exception;
        }

        return redirect()->route('print-jobs.show', $printJob)
            ->with('success', 'Print job submitted successfully.');
    }

    public function show(PrintJob $printJob): View
    {
        Gate::authorize('view', $printJob);

        $printJob->load(['printer:id,name,location', 'logs' => fn ($query) => $query->latest()]);

        return view('employee.print-jobs.show', compact('printJob'));
    }

    public function cancel(PrintJob $printJob): RedirectResponse
    {
        Gate::authorize('cancel', $printJob);

        DB::transaction(function () use ($printJob): void {
            $lockedJob = PrintJob::query()->whereKey($printJob->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedJob->status === PrintJobStatus::Pending, 409, 'Only pending jobs can be cancelled.');
            $lockedJob->update(['status' => PrintJobStatus::Cancelled]);
            $lockedJob->addLog('cancelled', 'Cancelled by the employee.');
        });

        return back()->with('success', 'Print job cancelled.');
    }
}
