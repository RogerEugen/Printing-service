<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-xl bg-cyan-50 text-cyan-700"><x-icon name="dashboard" class="h-5 w-5" /></span>
            <div><h1 class="text-2xl font-bold">Admin Dashboard</h1><p class="mt-0.5 text-sm text-slate-500">Company printing overview.</p></div>
        </div>
    </x-slot>

    @php
        $cards = [
            ['label' => 'Total Employees', 'value' => $employeeCount, 'icon' => 'users', 'iconClass' => 'bg-cyan-50 text-cyan-700', 'valueClass' => 'text-cyan-700'],
            ['label' => 'Total Printers', 'value' => $printerCount, 'icon' => 'printer', 'iconClass' => 'bg-sky-50 text-sky-700', 'valueClass' => 'text-slate-900'],
            ['label' => 'Pending Print Jobs', 'value' => $pendingCount, 'icon' => 'pending', 'iconClass' => 'bg-amber-50 text-amber-700', 'valueClass' => 'text-amber-600'],
            ['label' => 'Printing Jobs', 'value' => $printingCount, 'icon' => 'printing', 'iconClass' => 'bg-indigo-50 text-indigo-700', 'valueClass' => 'text-indigo-600'],
            ['label' => 'Printed Jobs', 'value' => $printedCount, 'icon' => 'check', 'iconClass' => 'bg-emerald-50 text-emerald-700', 'valueClass' => 'text-emerald-600'],
            ['label' => 'Failed Jobs', 'value' => $failedCount, 'icon' => 'failed', 'iconClass' => 'bg-red-50 text-red-700', 'valueClass' => 'text-red-600'],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4"><div class="flex items-center gap-2"><x-icon name="document" class="h-5 w-5 text-cyan-700" /><h2 class="font-semibold">Recent Print Jobs</h2></div><a class="text-sm font-semibold text-cyan-700 hover:text-cyan-800" href="{{ route('admin.print-jobs.index') }}">View all</a></div>
        @include('admin.print-jobs._table', ['printJobs' => $recentJobs])
    </div>
</x-app-layout>
