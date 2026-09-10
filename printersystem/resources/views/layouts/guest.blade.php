<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Elegansky Print') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white font-sans text-slate-900 antialiased">
        <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-white px-4 py-10">
            <div class="pointer-events-none absolute -left-24 top-0 h-72 w-72 rounded-full bg-cyan-50 blur-3xl"></div>
            <div class="pointer-events-none absolute -right-24 bottom-0 h-72 w-72 rounded-full bg-sky-50 blur-3xl"></div>
            <div class="relative w-full max-w-md rounded-3xl border border-slate-200 bg-white p-7 shadow-[0_24px_70px_-28px_rgba(15,23,42,0.3)] sm:p-10">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
