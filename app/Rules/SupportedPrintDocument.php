<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;
use ZipArchive;

class SupportedPrintDocument implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if (! in_array($extension, config('printing.allowed_extensions'), true)) {
            $fail('The :attribute must be a supported document or image.');

            return;
        }

        $mimeType = strtolower($value->getMimeType() ?: '');

        if (! $this->matchesExpectedContent($value, $extension, $mimeType)) {
            $fail('The :attribute content does not match its file type.');
        }
    }

    private function matchesExpectedContent(UploadedFile $file, string $extension, string $mimeType): bool
    {
        return match ($extension) {
            'pdf' => in_array($mimeType, ['application/pdf', 'application/x-pdf'], true),
            'jpg', 'jpeg' => $mimeType === 'image/jpeg',
            'png' => $mimeType === 'image/png',
            'bmp' => in_array($mimeType, ['image/bmp', 'image/x-ms-bmp'], true),
            'gif' => $mimeType === 'image/gif',
            'tif', 'tiff' => $mimeType === 'image/tiff',
            'webp' => $mimeType === 'image/webp',
            'txt' => $mimeType === 'text/plain',
            'rtf' => in_array($mimeType, ['application/rtf', 'text/rtf', 'text/plain'], true)
                && $this->startsWith($file, '{\\rtf'),
            'doc', 'xls', 'ppt' => $this->isLegacyOfficeDocument($file, $mimeType),
            'docx', 'xlsx', 'pptx' => $this->isOpenXmlDocument($file, $extension, $mimeType),
            default => false,
        };
    }

    private function isLegacyOfficeDocument(UploadedFile $file, string $mimeType): bool
    {
        $allowedMimeTypes = [
            'application/msword',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
            'application/vnd.ms-office',
            'application/x-cfb',
            'application/x-ole-storage',
            'application/octet-stream',
        ];

        return in_array($mimeType, $allowedMimeTypes, true)
            && $this->startsWith($file, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
    }

    private function isOpenXmlDocument(UploadedFile $file, string $extension, string $mimeType): bool
    {
        $allowedMimeTypes = [
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        if (! in_array($mimeType, $allowedMimeTypes, true)) {
            return false;
        }

        $expectedDirectory = match ($extension) {
            'docx' => 'word/',
            'xlsx' => 'xl/',
            'pptx' => 'ppt/',
        };
        $archive = new ZipArchive;

        if ($archive->open($file->getPathname()) !== true) {
            return false;
        }

        try {
            if ($archive->locateName('[Content_Types].xml') === false) {
                return false;
            }

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entryName = $archive->getNameIndex($index);

                if (is_string($entryName) && str_starts_with($entryName, $expectedDirectory)) {
                    return true;
                }
            }

            return false;
        } finally {
            $archive->close();
        }
    }

    private function startsWith(UploadedFile $file, string $signature): bool
    {
        $handle = fopen($file->getPathname(), 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            return fread($handle, strlen($signature)) === $signature;
        } finally {
            fclose($handle);
        }
    }
}
