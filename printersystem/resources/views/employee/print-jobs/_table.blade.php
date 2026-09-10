<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-6 py-3">Document</th><th class="px-6 py-3">Printer</th><th class="px-6 py-3">Copies</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Created</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($printJobs as $job)
                <tr class="hover:bg-slate-50"><td class="px-6 py-4 font-medium"><a class="text-cyan-700 hover:underline" href="{{ route('print-jobs.show', $job) }}">{{ $job->original_name }}</a></td><td class="px-6 py-4 text-slate-600">{{ $job->printer->name }}</td><td class="px-6 py-4">{{ $job->copies }}</td><td class="px-6 py-4"><x-status-badge :status="$job->status" /></td><td class="whitespace-nowrap px-6 py-4 text-slate-500">{{ $job->created_at->format('d M Y, H:i') }}</td></tr>
            @empty
                <tr><td colspan="5" class="px-6 py-10 text-center text-slate-500">No print jobs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
