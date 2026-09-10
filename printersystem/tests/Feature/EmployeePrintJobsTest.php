<?php

use App\Enums\PrintJobStatus;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('renders the document and image upload form without requiring the intl extension', function () {
    $employee = User::factory()->create();
    Printer::factory()->create(['name' => 'Reception Printer']);

    $response = $this->actingAs($employee)->get(route('print-jobs.create'));

    $response->assertOk()
        ->assertSee('Document or Image')
        ->assertSee('Word, Excel, PowerPoint')
        ->assertSee('20 MB')
        ->assertSee('Reception Printer');
});

it('creates a private print job from a valid PDF', function () {
    Storage::fake('local');
    $employee = User::factory()->create();
    $printer = Printer::factory()->create();
    $pdf = UploadedFile::fake()->createWithContent('quarterly report.pdf', "%PDF-1.4\n%%EOF");

    $response = $this->actingAs($employee)->post('/print-jobs', [
        'document' => $pdf,
        'printer_id' => $printer->id,
        'copies' => 2,
    ]);

    $printJob = PrintJob::query()->sole();
    $response->assertRedirect(route('print-jobs.show', $printJob));
    expect($printJob->status)->toBe(PrintJobStatus::Pending)
        ->and($printJob->original_name)->toBe('quarterly report.pdf')
        ->and($printJob->copies)->toBe(2);
    Storage::disk('local')->assertExists($printJob->file_path);
    $this->assertDatabaseHas('print_job_logs', ['print_job_id' => $printJob->id, 'event' => 'job_created']);
});

it('creates a private print job from a valid PNG image', function () {
    Storage::fake('local');
    $employee = User::factory()->create();
    $printer = Printer::factory()->create();
    $png = UploadedFile::fake()->createWithContent(
        'team-photo.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
    );

    $response = $this->actingAs($employee)->post('/print-jobs', [
        'document' => $png,
        'printer_id' => $printer->id,
        'copies' => 1,
    ]);

    $printJob = PrintJob::query()->sole();
    $response->assertRedirect(route('print-jobs.show', $printJob));
    expect($printJob->original_name)->toBe('team-photo.png')
        ->and($printJob->mime_type)->toBe('image/png')
        ->and($printJob->file_extension)->toBe('png')
        ->and($printJob->file_path)->toEndWith('.png');
    Storage::disk('local')->assertExists($printJob->file_path);
});

it('creates a private print job from a valid DOCX document', function () {
    Storage::fake('local');
    $employee = User::factory()->create();
    $printer = Printer::factory()->create();
    $temporaryPath = tempnam(sys_get_temp_dir(), 'print-docx-');
    $archive = new ZipArchive;
    $archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $archive->addFromString('word/document.xml', '<document/>');
    $archive->close();
    $document = new UploadedFile($temporaryPath, 'company-letter.docx', null, null, true);

    $response = $this->actingAs($employee)->post('/print-jobs', [
        'document' => $document,
        'printer_id' => $printer->id,
        'copies' => 1,
    ]);

    $printJob = PrintJob::query()->sole();
    $response->assertRedirect(route('print-jobs.show', $printJob));
    expect($printJob->original_name)->toBe('company-letter.docx')
        ->and($printJob->mime_type)->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->and($printJob->file_extension)->toBe('docx')
        ->and($printJob->file_path)->toEndWith('.docx');
    Storage::disk('local')->assertExists($printJob->file_path);
});

it('rejects unsupported executable uploads', function () {
    Storage::fake('local');
    $employee = User::factory()->create();
    $printer = Printer::factory()->create();

    $response = $this->actingAs($employee)->post('/print-jobs', [
        'document' => UploadedFile::fake()->createWithContent('invoice.exe', 'MZ executable content'),
        'printer_id' => $printer->id,
        'copies' => 1,
    ]);

    $response->assertInvalid('document');
    expect(PrintJob::query()->count())->toBe(0);
});

it('returns 404 when an employee guesses another employees job URL', function () {
    $owner = User::factory()->create();
    $otherEmployee = User::factory()->create();
    $printJob = PrintJob::factory()->for($owner)->create();

    $this->actingAs($otherEmployee)->get(route('print-jobs.show', $printJob))->assertNotFound();
});

it('lists only print jobs owned by the authenticated employee', function () {
    $employee = User::factory()->create();
    $ownJob = PrintJob::factory()->for($employee)->create(['original_name' => 'mine.pdf']);
    $otherJob = PrintJob::factory()->create(['original_name' => 'secret.pdf']);

    $response = $this->actingAs($employee)->get('/print-jobs');

    $response->assertOk()->assertSee($ownJob->original_name)->assertDontSee($otherJob->original_name);
});

it('cancels only a pending job owned by the employee', function () {
    $employee = User::factory()->create();
    $printJob = PrintJob::factory()->for($employee)->create();

    $this->actingAs($employee)->post(route('print-jobs.cancel', $printJob))->assertRedirect();

    expect($printJob->fresh()->status)->toBe(PrintJobStatus::Cancelled);
    $this->assertDatabaseHas('print_job_logs', ['print_job_id' => $printJob->id, 'event' => 'cancelled']);
});
