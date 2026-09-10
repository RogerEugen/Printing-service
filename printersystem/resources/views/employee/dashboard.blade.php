<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-cyan-50 text-cyan-700"><x-icon name="dashboard" class="h-5 w-5" /></span>
                <div><h1 class="text-2xl font-bold">Employee Dashboard</h1><p class="mt-0.5 text-sm text-slate-500">Welcome, {{ auth()->user()->username }}.</p></div>
            </div>
            <a href="{{ route('print-jobs.create') }}" class="flex items-center gap-2 rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-700"><x-icon name="document" class="h-4 w-4" />Upload Document</a>
        </div>
    </x-slot>

    @php
        $cards = [
            ['label' => 'My Pending Jobs', 'value' => $pendingJobs, 'icon' => 'pending', 'iconClass' => 'bg-amber-50 text-amber-700', 'valueClass' => 'text-amber-600'],
            ['label' => 'My Printed Jobs', 'value' => $printedJobs, 'icon' => 'check', 'iconClass' => 'bg-emerald-50 text-emerald-700', 'valueClass' => 'text-emerald-600'],
            ['label' => 'My Failed Jobs', 'value' => $failedJobs, 'icon' => 'failed', 'iconClass' => 'bg-red-50 text-red-700', 'valueClass' => 'text-red-600'],
        ];
    @endphp

    <div class="grid gap-5 sm:grid-cols-3">
        @foreach ($cards as $card)
            <div class="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-200 hover:shadow-md">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-sm font-medium text-slate-500">{{ $card['label'] }}</p><p class="mt-3 text-3xl font-bold {{ $card['valueClass'] }}">{{ $card['value'] }}</p></div>
                    <span class="grid h-11 w-11 place-items-center rounded-xl {{ $card['iconClass'] }}"><x-icon :name="$card['icon']" class="h-5 w-5" /></span>
                </div>
            </div>
        @endforeach
    </div>
    <div class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4"><div class="flex items-center gap-2"><x-icon name="document" class="h-5 w-5 text-cyan-700" /><h2 class="font-semibold">Recent Print Jobs</h2></div><a class="text-sm font-semibold text-cyan-700 hover:text-cyan-800" href="{{ route('print-jobs.index') }}">View all</a></div>
        @include('employee.print-jobs._table', ['printJobs' => $recentJobs])
    </div>
</x-app-layout>
