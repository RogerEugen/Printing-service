<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrinterStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePrinterRequest;
use App\Http\Requests\Admin\UpdatePrinterRequest;
use App\Models\Printer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PrinterController extends Controller
{
    public function index(): View
    {
        $printers = Printer::query()->withCount('printJobs')->orderBy('name')->paginate(20);

        return view('admin.printers.index', compact('printers'));
    }

    public function create(): View
    {
        return view('admin.printers.create');
    }

    public function store(StorePrinterRequest $request): RedirectResponse
    {
        $plainToken = Str::random(80);
        $printer = Printer::create([
            ...$request->validated(),
            'api_token_hash' => hash('sha256', $plainToken),
            'status' => PrinterStatus::Offline,
        ]);

        return redirect()->route('admin.printers.show', $printer)
            ->with('device_token', Crypt::encryptString($plainToken))
            ->with('success', 'Printer created. Copy the device token now; it will not be shown again.');
    }

    public function show(Printer $printer): View
    {
        $printer->loadCount('printJobs');
        $recentJobs = $printer->printJobs()->with('user:id,username')->latest()->limit(10)->get();
        $encryptedToken = session('device_token');
        $deviceToken = is_string($encryptedToken) ? Crypt::decryptString($encryptedToken) : null;

        return view('admin.printers.show', compact('printer', 'recentJobs', 'deviceToken'));
    }

    public function update(UpdatePrinterRequest $request, Printer $printer): RedirectResponse
    {
        $printer->update($request->validated());

        return back()->with('success', 'Printer updated successfully.');
    }
}
