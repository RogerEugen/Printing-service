<?php

namespace App\Http\Controllers\Api;

use App\Enums\PrinterStatus;
use App\Http\Controllers\Controller;
use App\Models\Printer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrinterHeartbeatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Printer $printer */
        $printer = $request->attributes->get('printer');
        $printer->update([
            'last_seen_at' => now(),
            'status' => PrinterStatus::Online,
        ]);

        return response()->json([
            'status' => 'ok',
            'printer_id' => $printer->id,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
