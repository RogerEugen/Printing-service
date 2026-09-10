<?php

namespace App\Http\Controllers\Api;

use App\Enums\PrintJobStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PrinterJobResource;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Services\PrintJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PrinterJobController extends Controller
{
    public function __construct(private PrintJobService $printJobService) {}

    public function next(Request $request): JsonResponse|PrinterJobResource
    {
        $printJob = $this->printJobService->claimNext($this->printer($request));

        if (! $printJob) {
            return response()->json(null, 204);
        }

        return new PrinterJobResource($printJob);
    }

    public function claim(Request $request, PrintJob $printJob): PrinterJobResource
    {
        return new PrinterJobResource(
            $this->printJobService->claim($this->printer($request), $printJob),
        );
    }

    public function download(Request $request, PrintJob $printJob): BinaryFileResponse
    {
        $this->ensurePrinterOwnsJob($request, $printJob);
        abort_unless(
            in_array($printJob->status, [PrintJobStatus::Claimed, PrintJobStatus::Printing], true),
            409,
            'Print job is not available for download.',
        );
        abort_unless(Storage::disk('local')->exists($printJob->file_path), 404, 'Print file is missing.');

        $printJob->addLog('download_started', 'Document download started by the printer agent.');

        return response()->download(
            Storage::disk('local')->path($printJob->file_path),
            $printJob->original_name,
            ['Content-Type' => $printJob->mime_type],
        );
    }

    public function printing(Request $request, PrintJob $printJob): PrinterJobResource
    {
        return new PrinterJobResource($this->printJobService->transition(
            $this->printer($request),
            $printJob,
            [PrintJobStatus::Claimed],
            PrintJobStatus::Printing,
            'printing_at',
            'printing_started',
            'Printer agent started physical printing.',
        ));
    }

    public function printed(Request $request, PrintJob $printJob): PrinterJobResource
    {
        return new PrinterJobResource($this->printJobService->transition(
            $this->printer($request),
            $printJob,
            [PrintJobStatus::Printing],
            PrintJobStatus::Printed,
            'printed_at',
            'printed',
            'Printer agent reported successful printing.',
        ));
    }

    public function failed(Request $request, PrintJob $printJob): PrinterJobResource
    {
        $this->ensurePrinterOwnsJob($request, $printJob);
        $validated = $request->validate([
            'error_message' => ['required', 'string', 'max:2000'],
        ]);

        return new PrinterJobResource($this->printJobService->transition(
            $this->printer($request),
            $printJob,
            [PrintJobStatus::Claimed, PrintJobStatus::Printing],
            PrintJobStatus::Failed,
            'failed_at',
            'failed',
            $validated['error_message'],
            $validated['error_message'],
        ));
    }

    private function ensurePrinterOwnsJob(Request $request, PrintJob $printJob): void
    {
        abort_unless($printJob->printer_id === $this->printer($request)->id, 404);
    }

    private function printer(Request $request): Printer
    {
        return $request->attributes->get('printer');
    }
}
