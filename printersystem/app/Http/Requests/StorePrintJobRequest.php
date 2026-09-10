<?php

namespace App\Http\Requests;

use App\Enums\PrinterStatus;
use App\Models\PrintJob;
use App\Rules\SupportedPrintDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrintJobRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', PrintJob::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document' => [
                'required',
                'file',
                new SupportedPrintDocument,
                'max:'.config('printing.max_upload_kilobytes'),
            ],
            'printer_id' => [
                'required',
                'integer',
                Rule::exists('printers', 'id')->where(
                    fn ($query) => $query->where('status', '!=', PrinterStatus::Disabled->value),
                ),
            ],
            'copies' => ['required', 'integer', 'min:1', 'max:'.config('printing.max_copies')],
        ];
    }
}
