<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrintJobStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $jobCounts = PrintJob::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.dashboard', [
            'employeeCount' => User::query()->where('role', UserRole::Employee)->count(),
            'printerCount' => Printer::query()->count(),
            'pendingCount' => (int) $jobCounts->get(PrintJobStatus::Pending->value, 0),
            'printingCount' => (int) $jobCounts->get(PrintJobStatus::Printing->value, 0),
            'printedCount' => (int) $jobCounts->get(PrintJobStatus::Printed->value, 0),
            'failedCount' => (int) $jobCounts->get(PrintJobStatus::Failed->value, 0),
            'recentJobs' => PrintJob::query()->with(['user:id,username', 'printer:id,name'])->latest()->limit(8)->get(),
        ]);
    }
}
