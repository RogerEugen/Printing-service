<?php

namespace App\Http\Controllers;

use App\Enums\PrintJobStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $counts = $request->user()->printJobs()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentJobs = $request->user()->printJobs()
            ->with('printer:id,name')
            ->latest()
            ->limit(8)
            ->get();

        return view('employee.dashboard', [
            'pendingJobs' => (int) $counts->get(PrintJobStatus::Pending->value, 0),
            'printedJobs' => (int) $counts->get(PrintJobStatus::Printed->value, 0),
            'failedJobs' => (int) $counts->get(PrintJobStatus::Failed->value, 0),
            'recentJobs' => $recentJobs,
        ]);
    }
}
