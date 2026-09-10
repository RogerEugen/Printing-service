<x-app-layout>
    <x-slot name="header"><div class="flex items-center justify-between gap-3"><div><h1 class="text-2xl font-bold">My Print Jobs</h1><p class="mt-1 text-sm text-slate-500">Only your own documents and images are shown.</p></div><a href="{{ route('print-jobs.create') }}" class="flex items-center gap-2 rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white"><x-icon name="document" class="h-4 w-4" />Upload File</a></div></x-slot>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">@include('employee.print-jobs._table', ['printJobs' => $printJobs])</div>
    <div class="mt-6">{{ $printJobs->links() }}</div>
</x-app-layout>
