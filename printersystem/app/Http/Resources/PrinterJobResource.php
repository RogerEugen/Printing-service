<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrinterJobResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'file_extension' => $this->file_extension,
            'file_size' => $this->file_size,
            'copies' => $this->copies,
            'status' => $this->status->value,
            'download_url' => route('api.printer.jobs.download', $this->resource),
            'claim_url' => route('api.printer.jobs.claim', $this->resource),
            'printing_url' => route('api.printer.jobs.printing', $this->resource),
            'printed_url' => route('api.printer.jobs.printed', $this->resource),
            'failed_url' => route('api.printer.jobs.failed', $this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
