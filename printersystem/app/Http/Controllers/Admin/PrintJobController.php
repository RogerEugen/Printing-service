<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrintJobStatus;
use App\Http\Controllers\Controller;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrintJobController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PrintJobStatus::class)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'printer_id' => ['nullable', 'integer', 'exists:printers,id'],
        ]);

        $printJobs = PrintJob::query()
            ->with(['user:id,username', 'printer:id,name'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['printer_id'] ?? null, fn ($query, $printerId) => $query->where('printer_id', $printerId))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.print-jobs.index', [
            'printJobs' => $printJobs,
            'users' => User::query()->orderBy('username')->get(['id', 'username']),
            'printers' => Printer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(PrintJob $printJob): View
    {
        $printJob->load(['user:id,username', 'printer:id,name,location', 'logs' => fn ($query) => $query->latest()]);

        return view('admin.print-jobs.show', compact('printJob'));
    }
}
