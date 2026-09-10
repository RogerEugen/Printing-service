<?php

namespace App\Http\Middleware;

use App\Enums\PrinterStatus;
use App\Models\Printer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePrinter
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || mb_strlen($token) < 40) {
            return response()->json(['message' => 'Unauthenticated printer device.'], 401);
        }

        $printer = Printer::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->first();

        if (! $printer || $printer->status === PrinterStatus::Disabled) {
            return response()->json(['message' => 'Invalid or disabled printer device.'], 401);
        }

        $request->attributes->set('printer', $printer);

        return $next($request);
    }
}
