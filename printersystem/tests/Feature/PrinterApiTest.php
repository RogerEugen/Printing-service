<?php

use App\Enums\PrintJobStatus;
use App\Models\Printer;
use App\Models\PrintJob;
use Illuminate\Support\Facades\Storage;

function printerHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

it('returns 401 when the printer token is invalid', function () {
    $this->postJson('/api/printer/heartbeat', [], printerHeaders(str_repeat('x', 80)))->assertUnauthorized();
});

it('updates the authenticated printer heartbeat', function () {
    $token = str_repeat('a', 80);
    $printer = Printer::factory()->create(['api_token_hash' => hash('sha256', $token)]);

    $this->postJson('/api/printer/heartbeat', [], printerHeaders($token))->assertOk()->assertJsonPath('printer_id', $printer->id);

    expect($printer->fresh()->last_seen_at)->not->toBeNull();
});

it('atomically claims only a job assigned to the authenticated printer', function () {
    $token = str_repeat('b', 80);
    $printer = Printer::factory()->create(['api_token_hash' => hash('sha256', $token)]);
    $otherJob = PrintJob::factory()->create();
    $assignedJob = PrintJob::factory()->for($printer)->create();

    $this->getJson('/api/printer/jobs/next', printerHeaders($token))
        ->assertOk()
        ->assertJsonPath('data.id', $assignedJob->id)
        ->assertJsonPath('data.mime_type', 'application/pdf')
        ->assertJsonPath('data.file_extension', 'pdf')
        ->assertJsonPath('data.status', 'claimed');

    expect($assignedJob->fresh()->status)->toBe(PrintJobStatus::Claimed)
        ->and($otherJob->fresh()->status)->toBe(PrintJobStatus::Pending);
});

it('downloads an assigned image with its original content type', function () {
    Storage::fake('local');
    $token = str_repeat('f', 80);
    $printer = Printer::factory()->create(['api_token_hash' => hash('sha256', $token)]);
    $printJob = PrintJob::factory()->for($printer)->create([
        'original_name' => 'notice.png',
        'mime_type' => 'image/png',
        'file_extension' => 'png',
        'file_path' => 'print-jobs/test/notice.png',
        'status' => PrintJobStatus::Claimed,
    ]);
    Storage::disk('local')->put($printJob->file_path, "\x89PNG\r\n\x1a\nimage");

    $response = $this->get("/api/printer/jobs/{$printJob->id}/download", printerHeaders($token));

    $response->assertOk()
        ->assertHeader('content-type', 'image/png')
        ->assertDownload('notice.png');
});

it('prevents a different printer from claiming an already claimed job', function () {
    $firstToken = str_repeat('c', 80);
    $secondToken = str_repeat('d', 80);
    $firstPrinter = Printer::factory()->create(['api_token_hash' => hash('sha256', $firstToken)]);
    Printer::factory()->create(['api_token_hash' => hash('sha256', $secondToken)]);
    $printJob = PrintJob::factory()->for($firstPrinter)->create();

    $this->postJson("/api/printer/jobs/{$printJob->id}/claim", [], printerHeaders($firstToken))->assertOk();
    $this->postJson("/api/printer/jobs/{$printJob->id}/claim", [], printerHeaders($secondToken))->assertNotFound();

    expect($printJob->fresh()->status)->toBe(PrintJobStatus::Claimed);
});

it('enforces printing status transitions and allows idempotent printed reports', function () {
    Storage::fake('local');
    $token = str_repeat('e', 80);
    $printer = Printer::factory()->create(['api_token_hash' => hash('sha256', $token)]);
    $printJob = PrintJob::factory()->for($printer)->create(['status' => PrintJobStatus::Claimed]);

    $this->postJson("/api/printer/jobs/{$printJob->id}/printed", [], printerHeaders($token))->assertStatus(409);
    $this->postJson("/api/printer/jobs/{$printJob->id}/printing", [], printerHeaders($token))->assertOk();
    $this->postJson("/api/printer/jobs/{$printJob->id}/printed", [], printerHeaders($token))->assertOk();
    $this->postJson("/api/printer/jobs/{$printJob->id}/printed", [], printerHeaders($token))->assertOk();

    expect($printJob->fresh()->status)->toBe(PrintJobStatus::Printed);
});
